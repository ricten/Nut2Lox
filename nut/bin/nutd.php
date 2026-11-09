<?php
/** NUT-Dienst: fragt NUT-Server ab, veroeffentlicht per MQTT und nimmt Befehle entgegen. */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
if (!getenv('NUT_DEV_DIR')) {
    require_once 'loxberry_system.php';
    require_once 'loxberry_io.php';
    require_once 'loxberry_log.php';
}
require_once __DIR__ . '/lib/bootstrap.php';

const T = 'nut';    // Topic-Wurzel

$paths = Paths::detect();

// Nur eine Instanz
$lock = fopen($paths->lockFile(), 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
file_put_contents($paths->pidFile(), (string)getmypid());

$log = new Logger($paths->logfile, 'Dienst', 'NUT-Dienst');
if (getenv('NUT_DEV_DIR')) {
    $log->setLevel(getenv('NUT_DEV_LOGLEVEL') ?: 'debug');
} elseif (class_exists('LBSystem') && method_exists('LBSystem', 'pluginloglevel')) {
    $log->setLoxberryLevel((int)LBSystem::pluginloglevel());
}
register_shutdown_function(function () use ($log) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $log->error('Dienst abgestuerzt: ' . $e['message'] . ' (' . basename($e['file']) . ':' . $e['line'] . ')');
    }
});
$running = true;
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    $stop = function () use (&$running) {
        $running = false;
    };
    pcntl_signal(SIGTERM, $stop);
    pcntl_signal(SIGINT, $stop);
}

/** Broker-Daten: LoxBerry-Konfiguration (keine eigenen Zugangsdaten), im Test NUT_DEV_MQTT=host:port. */
function brokerDetails(Logger $log): ?array
{
    $dev = getenv('NUT_DEV_MQTT');
    if ($dev) {
        [$h, $p] = explode(':', $dev, 2) + [1 => '1883'];
        return [$h, (int)$p, '', ''];
    }
    if (!function_exists('mqtt_connectiondetails')) {
        $log->error('LoxBerry-Funktion mqtt_connectiondetails() nicht verfuegbar', 'mqtt-details');
        return null;
    }
    $d = mqtt_connectiondetails();     // ueberschreibt die globale Variable $cfg - die Dienstkonfiguration heisst deshalb $nut
    $host = is_array($d) ? (string)($d['brokerhost'] ?? '') : '';
    $port = is_array($d) ? (int)($d['brokerport'] ?? 0) : 0;
    if (($host === '' || $port === 0) && is_array($d) && !empty($d['brokeraddress']) && str_contains((string)$d['brokeraddress'], ':')) {
        [$h2, $p2] = explode(':', (string)$d['brokeraddress'], 2);
        $host = $host !== '' ? $host : $h2;
        $port = $port ?: (int)$p2;
    }
    if ($host === '') {
        $log->error('Keine Broker-Daten in der LoxBerry-Konfiguration (MQTT eingerichtet?)', 'mqtt-details');
        return null;
    }
    return [$host, $port ?: 1883, (string)($d['brokeruser'] ?? ''), (string)($d['brokerpass'] ?? '')];
}

/** Befehl aus Loxone: nut/befehl/<alias>/<name> = 1. Quittung unter .../quittung, nie retained. */
function handleCommand(string $topic, string $msg, array $nut, MqttClient $mqtt, Logger $log): void
{
    if (!preg_match('#^' . T . '/befehl/([a-z0-9_-]+)/([a-z0-9_]+)$#', $topic, $m)) {
        return;     // auch alle .../quittung
    }
    [, $alias, $name] = $m;
    $quit = $topic . '/quittung';
    $cmd = str_replace('_', '.', $name);
    if (!$nut['befehle_frei']) {
        $log->warn("Befehl $cmd fuer $alias abgelehnt: Befehle sind gesperrt");
        $mqtt->publish($quit, 'abgelehnt: Befehle sind gesperrt');
        return;
    }
    if (!preg_match(NutClient::CMD_ALLOWED, $cmd)) {
        $log->warn("Befehl $cmd fuer $alias abgelehnt: nicht erlaubt");
        $mqtt->publish($quit, 'abgelehnt: Befehl nicht erlaubt');
        return;
    }
    if (trim($msg) !== '1') {
        $mqtt->publish($quit, 'abgelehnt: zum Ausloesen 1 senden');
        return;
    }
    $srv = null;
    foreach (NutConfig::servers($nut) as $s) {
        if ($s['alias'] === $alias) {
            $srv = $s;
        }
    }
    if ($srv === null) {
        $mqtt->publish($quit, 'abgelehnt: unbekanntes Geraet');
        return;
    }
    $mqtt->publish($quit, 'angenommen');
    $err = '';
    $o = NutClient::open($srv, $err);
    if ($o === null) {
        $log->error("Befehl $cmd fuer $alias fehlgeschlagen: $err");
        $mqtt->publish($quit, 'abgelehnt: ' . $err);
        return;
    }
    [$c, $ups] = $o;
    $ok = $c->instcmd($ups, $cmd, $srv['benutzer'], $srv['passwort']);
    $cerr = $c->error;
    $c->close();
    if ($ok) {
        $log->info("Befehl $cmd fuer $alias ausgefuehrt");
        $mqtt->publish($quit, 'ausgefuehrt');
    } else {
        $log->error("Befehl $cmd fuer $alias fehlgeschlagen: $cerr");
        $mqtt->publish($quit, 'abgelehnt: ' . $cerr);
    }
}

/** Ein Abrufdurchgang. @return array<string,array{ok:bool,error:string}> */
function pollAll(array $servers, MqttClient $mqtt, Logger $log): array
{
    $res = [];
    foreach ($servers as $srv) {
        $alias = $srv['alias'];
        $err = '';
        $vars = null;
        $o = NutClient::open($srv, $err);
        if ($o !== null) {
            [$c, $ups] = $o;
            $vars = $c->listVars($ups);
            if ($vars === null) {
                $err = $c->error;
            }
            $c->close();
        }
        if (!$vars) {
            $err = $err !== '' ? $err : 'keine Werte erhalten';
            $log->error("[$alias] Abruf fehlgeschlagen: $err", "poll-$alias");
            $mqtt->publish(T . "/$alias/verbunden", '0');
            $res[$alias] = ['ok' => false, 'error' => $err];
            continue;
        }
        $log->info("[$alias] erreichbar, " . count($vars) . ' Werte', "up-$alias");
        foreach ($vars as $name => $val) {
            $val = NutClient::value($val);
            if ($val !== '') {          // ohne gueltigen Wert nichts senden
                $mqtt->publish(T . "/$alias/" . NutClient::varName($name), $val);
            }
        }
        if (isset($vars['ups.status'])) {
            foreach (NutClient::flags($vars['ups.status']) as $n => $v) {
                $mqtt->publish(T . "/$alias/zustand/$n", (string)$v);
            }
        }
        $mqtt->publish(T . "/$alias/verbunden", '1');
        $res[$alias] = ['ok' => true, 'error' => ''];
    }
    return $res;
}

// ------------------------------------------------------------------ Hauptschleife
$nut = NutConfig::load($paths->config);
$cfgMtime = -1;
$mqtt = null;
$mqttErr = '';
$mqttBackoff = 2;
$mqttNext = 0;
$mqttSince = 0;
$zaehler = 0;
$nextPoll = 0;
$lastPoll = 0;
$lastStatus = 0;
$ok = 0;
$results = [];
$started = time();
$log->info('NUT-Dienst ' . AppVersion::CURRENT . ' gestartet (PID ' . getmypid() . ')');

while ($running) {
    try {
        $now = time();

        // Konfiguration neu laden, wenn sich die Datei geaendert hat
        clearstatcache(true, $paths->config);
        $mt = is_file($paths->config) ? (int)filemtime($paths->config) : 0;
        if ($mt !== $cfgMtime) {
            $cfgMtime = $mt;
            $nut = NutConfig::load($paths->config);
            foreach ($nut['server'] as $s) {
                $log->addSecret((string)$s['passwort']);
            }
            $log->addSecret((string)$nut['lokal']['passwort']);
            $nextPoll = 0;
            $log->info('Konfiguration geladen: ' . count(NutConfig::servers($nut)) . ' NUT-Server, Intervall ' . $nut['intervall'] . ' s');
        }

        // MQTT verbinden (mit wachsendem Abstand bei Fehlern)
        if (($mqtt === null || !$mqtt->connected()) && $now >= $mqttNext) {
            $bd = brokerDetails($log);
            if ($bd === null) {
                $mqttErr = 'Keine Broker-Daten';
                $mqttNext = $now + 30;
            } else {
                $folder = $paths->folder;
                $mqtt = new MqttClient($bd[0], $bd[1], $bd[2], $bd[3], 'nut_' . $folder, T . '/status/ok');
                $log->addSecret($bd[3]);
                $mqtt->subscribe(T . '/befehl/#');
                if ($mqtt->connect()) {
                    $log->info("MQTT verbunden ({$bd[0]}:{$bd[1]})");
                    $mqttErr = '';
                    $mqttSince = $now;
                    $mqttBackoff = 2;
                } else {
                    $mqttErr = $mqtt->lastError();
                    $log->error('MQTT: ' . $mqttErr, 'mqtt-connect');
                    $mqttNext = $now + $mqttBackoff;
                    $mqttBackoff = min(60, $mqttBackoff * 2);
                }
            }
        }
        if ($mqtt !== null && $mqtt->connected()) {
            if (!$mqtt->loop()) {
                $mqttErr = $mqtt->lastError();
                $log->warn('MQTT getrennt: ' . $mqttErr, 'mqtt-lost');
            }
            foreach ($mqtt->takeMessages() as [$t, $p]) {
                $log->debug("Befehl empfangen: $t");
                handleCommand($t, $p, $nut, $mqtt, $log);
            }
        }

        // Abrufdurchgang
        if ($now >= $nextPoll) {
            $nextPoll = $now + $nut['intervall'];
            $lastPoll = $now;
            $servers = NutConfig::servers($nut);
            $out = ($mqtt !== null && $mqtt->connected()) ? $mqtt : new MqttClient('', 0, '', '', '', '');   // ohne Broker: Abruf trotzdem pruefen
            $results = pollAll($servers, $out, $log);
            $ok = $results && !array_filter($results, fn($r) => !$r['ok']) ? 1 : 0;
            if (!$servers) {
                $log->warn('Kein NUT-Server konfiguriert', 'no-server');
            }
            $zaehler = ($zaehler + 1) % 1000;
            if ($mqtt !== null && $mqtt->connected()) {
                $mqtt->publish(T . '/status/ok', (string)$ok);
                $mqtt->publish(T . '/status/zaehler', (string)$zaehler);
                $mqtt->publish(T . '/status/ts', (string)time());
            }
            $log->rotateCheck();
        }

        if ($now !== $lastStatus) {      // Status hoechstens einmal pro Sekunde (liegt im RAM, /dev/shm)
        $lastStatus = $now;
        Util::jsonSave($paths->statusFile(), [
            'service' => 'running', 'time' => $now, 'started' => $started,
            'ok' => $ok, 'zaehler' => $zaehler,
            'ts' => $lastPoll,
            'mqtt' => ($mqtt !== null && $mqtt->connected()) ? 1 : 0, 'mqtt_error' => $mqttErr, 'mqtt_seit' => $mqttSince,
            'server' => $results, 'version' => AppVersion::CURRENT,
        ]);
        }
    } catch (Throwable $e) {
        $log->error('Fehler in der Hauptschleife: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')', 'loop');
    }
    usleep(200000);
}

if ($mqtt !== null && $mqtt->connected()) {
    $mqtt->publish(T . '/status/ok', '0');
    $mqtt->close();
}
$log->info('NUT-Dienst beendet');
$log->end();
@unlink($paths->pidFile());
