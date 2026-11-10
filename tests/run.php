<?php
/**
 * Test-Runner ohne Abhaengigkeiten: php tests/run.php
 * Teil 1: Unit-Tests (Hilfsfunktionen, NUT-Client gegen Fake-Server, MQTT-Client gegen Fake-Broker)
 * Teil 2: Ende-zu-Ende: der Dienst (nutd.php im Entwicklungsmodus) gegen Fake-NUT und Fake-Broker.
 * Nicht getestet werden koennen hier: LoxBerry-Bibliotheken, Weboberflaeche, nut-apply.sh als root (siehe README).
 */
$root = dirname(__DIR__);
// Gemeinsamer Code direkt aus ../common (im ZIP liegt er in bin/lib)
foreach (['Util', 'Logger', 'MqttClient'] as $c) {
    require_once $root . '/../common/php/' . $c . '.php';
}
foreach (['NutClient', 'NutConfig', 'AppVersion'] as $c) {
    require_once $root . '/nut2lox/bin/lib/' . $c . '.php';
}

$fails = 0;
$count = 0;
function check(string $name, $actual, $expected): void
{
    global $fails, $count;
    $count++;
    if ($actual !== $expected) {
        $fails++;
        echo "FAIL $name: erwartet " . var_export($expected, true) . ", erhalten " . var_export($actual, true) . "\n";
    }
}
function waitFor(callable $f, float $timeout = 8.0): bool
{
    $end = microtime(true) + $timeout;
    while (microtime(true) < $end) {
        if ($f()) {
            return true;
        }
        usleep(100000);
    }
    return false;
}
function startProc(array $cmd, array $env = []): array
{
    $p = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/nut_test_out.txt', 'a'], 2 => ['file', sys_get_temp_dir() . '/nut_test_err.txt', 'a']], $pipes, null, $env ? array_merge(getenv(), $env) : null);
    return [$p, $pipes];
}
function stopProc(array $pp): void
{
    @proc_terminate($pp[0]);
    foreach ($pp[1] as $h) {
        @fclose($h);
    }
    @proc_close($pp[0]);
}

// ---------------------------------------------------------------- Unit: Hilfsfunktionen
check('name leer', Util::name('  '), '');
check('name umlaut', Util::name('Büro USV 1'), 'buero_usv_1');
check('name sonderzeichen', Util::name('a/b#c'), 'a_b_c');
check('varName', NutClient::varName('battery.charge.low'), 'battery_charge_low');
check('value', NutClient::value("a \n  b"), 'a b');
check('value zahl', NutClient::value('231.0'), '231.0');
$f = NutClient::flags('OB LB');
check('flags OB', $f['batterie'], 1);
check('flags LB', $f['batterie_schwach'], 1);
check('flags online', $f['online'], 0);
check('flags CHRG', NutClient::flags('OL CHRG')['laedt'], 1);
check('flags leer', array_sum(NutClient::flags('')), 0);
check('cmd beeper', preg_match(NutClient::CMD_ALLOWED, 'beeper.toggle'), 1);
check('cmd test', preg_match(NutClient::CMD_ALLOWED, 'test.battery.start.quick'), 1);
check('cmd shutdown', preg_match(NutClient::CMD_ALLOWED, 'shutdown.return'), 0);
check('cmd load.off', preg_match(NutClient::CMD_ALLOWED, 'load.off'), 0);
check('cmd injection', preg_match(NutClient::CMD_ALLOWED, "beeper.toggle\nLOGOUT"), 0);

// Konfiguration
$tmp = __DIR__ . '/tmp';
@mkdir($tmp, 0777, true);
$dir = $tmp . '/t' . getmypid();
@mkdir($dir, 0777, true);
$cfgFile = $dir . '/nut.json';
$c = NutConfig::load($cfgFile);
check('default server', count($c['server']), 4);
check('default befehle gesperrt', $c['befehle_frei'], false);
check('default keine Server', NutConfig::servers($c), []);
$post = ['lokal' => ['name' => 'usv'], 'intervall' => '1', 'srv' => [0 => ['aktiv' => '1', 'alias' => 'Mein USV', 'host' => ' 10.0.0.5 ', 'port' => '99999', 'ups' => 'ups 1;x', 'benutzer' => 'u', 'passwort' => 'geheim12']]];
$n = NutConfig::fromPost($c, $post);
$onlyMqtt = NutConfig::fromPost($n, ['mqtt' => ['eigener_broker' => '1', 'host' => 'b.lan']]);
check('nur MQTT gepostet: Server bleiben', $onlyMqtt['server'][0]['host'], '10.0.0.5');
check('nur MQTT gepostet: Intervall bleibt', $onlyMqtt['intervall'], 2);
check('nur MQTT gepostet: Broker gesetzt', $onlyMqtt['mqtt']['host'], 'b.lan');
$onlyGen = NutConfig::fromPost($onlyMqtt, ['intervall' => '30', 'srv' => [0 => ['aktiv' => '1', 'host' => '10.0.0.5']]]);
check('ohne MQTT gepostet: Broker bleibt', $onlyGen['mqtt']['host'], 'b.lan');
check('ohne MQTT gepostet: Intervall neu', $onlyGen['intervall'], 30);
check('post intervall min', $n['intervall'], 2);
check('post alias', $n['server'][0]['alias'], 'mein_usv');
check('post port max', $n['server'][0]['port'], 65535);
check('post ups bereinigt', $n['server'][0]['ups'], 'ups1x');
$ns = NutConfig::fromPost($c, ['lokal' => ['aktiv' => '1', 'synology' => '1', 'name' => 'meineusv']]);
check('Synology: Name ups', $ns['lokal']['name'], 'ups');
check('Synology: Netz erzwungen', $ns['lokal']['netz'], true);
check('ohne Synology Name bleibt', NutConfig::fromPost($c, ['lokal' => ['name' => 'meineusv']])['lokal']['name'], 'meineusv');
check('post lokal passwort erzeugt', strlen($n['lokal']['passwort']), 24);
$n2 = NutConfig::fromPost($n, ['srv' => [0 => ['aktiv' => '1', 'host' => '10.0.0.5', 'passwort' => '']]]);
check('leeres Passwortfeld behaelt Passwort', $n2['server'][0]['passwort'], 'geheim12');
check('lokal passwort stabil', $n2['lokal']['passwort'], $n['lokal']['passwort']);
check('speichern', Util::jsonSave($cfgFile, $n), true);
if (DIRECTORY_SEPARATOR === '/') {
    check('Rechte 0600', substr(sprintf('%o', fileperms($cfgFile)), -4), '0600');
}
check('laden', NutConfig::load($cfgFile)['server'][0]['host'], '10.0.0.5');
check('ownBroker standardmaessig aus', NutConfig::ownBroker($c), null);
$nb = NutConfig::fromPost($c, ['mqtt' => ['eigener_broker' => '1', 'host' => ' broker.lan ', 'port' => '8883', 'benutzer' => 'u1', 'passwort' => 'brokerpw']]);
check('ownBroker aus Einstellungen', NutConfig::ownBroker($nb), ['broker.lan', 8883, 'u1', 'brokerpw']);
$nb2 = NutConfig::fromPost($nb, ['mqtt' => ['eigener_broker' => '1', 'host' => 'broker.lan', 'passwort' => '']]);
check('Broker-Passwort bleibt bei leerem Feld', $nb2['mqtt']['passwort'], 'brokerpw');
check('Broker-Port Standard', $nb2['mqtt']['port'], 1883);
check('ownBroker ohne Host = LoxBerry-Broker', NutConfig::ownBroker(NutConfig::fromPost($c, ['mqtt' => ['eigener_broker' => '1', 'host' => '']])), null);
check('ownBroker ohne Haken = LoxBerry-Broker', NutConfig::ownBroker(NutConfig::fromPost($c, ['mqtt' => ['host' => 'x.lan']])), null);
check('servers', count(NutConfig::servers(NutConfig::load($cfgFile))), 1);
$lokalFile = $dir . '/lokal.conf';
NutConfig::writeLokal($lokalFile, array_merge($n['lokal'], ['aktiv' => true]));
check('lokal.conf aktiv', str_contains((string)file_get_contents($lokalFile), "aktiv=1\n"), true);

// Logger maskiert Secrets
$lf = $dir . '/test.log';
$lg = new Logger($lf, 'Test', 'Test');
$lg->setLevel('debug');
$lg->addSecret('geheim12');
$lg->info('Passwort geheim12 benutzt');
check('Logger maskiert', str_contains((string)file_get_contents($lf), 'geheim12'), false);
check('Logger schreibt', str_contains((string)file_get_contents($lf), '***'), true);

// ---------------------------------------------------------------- Unit: NUT-Client gegen Fake-Server
$php = PHP_BINARY;
$nutPort = 13493;
$mqPort = 18830;
$nutProc = startProc([$php, __DIR__ . '/fake_nut.php', (string)$nutPort]);
$mqProc = startProc([$php, __DIR__ . '/fake_mqtt.php', (string)$mqPort, $dir]);
$up = waitFor(function () use ($nutPort) {
    $s = @stream_socket_client("tcp://127.0.0.1:$nutPort", $e, $es, 1);
    if ($s) {
        fclose($s);
        return true;
    }
    return false;
}, 5);
check('Fake-NUT gestartet', $up, true);
waitFor(fn() => @stream_socket_client("tcp://127.0.0.1:$mqPort", $e, $es, 1) !== false, 5);

$err = '';
$o = NutClient::open(['host' => '127.0.0.1', 'port' => $nutPort, 'ups' => ''], $err);
check('open', $o !== null, true);
if ($o !== null) {
    [$cl, $ups] = $o;
    check('USV automatisch', $ups, 'usv');
    $v = $cl->listVars($ups);
    check('Variablen', count((array)$v), 11);
    check('Wert', $v['battery.charge'] ?? null, '100');
    check('Seriennummer bleibt Text', $v['ups.serial'] ?? null, '000123');
    check('unbekannte USV', $cl->listVars('gibtsnicht'), null);
    check('Fehlertext', $cl->error, 'NUT: UNKNOWN-UPS');
    $cl->close();
}
$cl = new NutClient('127.0.0.1', $nutPort);
$cl->connect();
$info = $cl->info('usv');
check('info Beschreibung', $info['beschreibung'] ?? null, 'Fake USV');
check('info Serverversion', $info['server_version'] ?? null, 'Network UPS Tools upsd 2.8.0 - https://www.networkupstools.org/');
check('info Protokoll', $info['protokoll'] ?? null, '1.3');
check('info Anmeldungen', $info['anmeldungen'] ?? null, '2');
check('info Clients', $info['clients'] ?? null, '2');
check('info Befehle', $info['befehle'] ?? null, 'beeper.toggle,test.battery.start.quick');
check('info beschreibbar', $info['beschreibbar'] ?? null, 'ups.delay.shutdown,input.transfer.low');
check('info enum', $info['enum/ups_delay_shutdown'] ?? null, '20,30');
check('info range', $info['range/input_transfer_low'] ?? null, '160..190');
check('info ohne enum leer', isset($info['enum/input_transfer_low']), false);
$cl->close();
$cl = new NutClient('127.0.0.1', $nutPort);
$cl->connect();
check('instcmd ok', $cl->instcmd('usv', 'beeper.toggle', 'test', 'testpw1234'), true);
$cl->close();
$cl = new NutClient('127.0.0.1', $nutPort);
$cl->connect();
check('instcmd falsches Passwort', $cl->instcmd('usv', 'beeper.toggle', 'test', 'falsch'), false);
check('Fehlertext ohne Passwort', str_contains($cl->error, 'falsch'), false);
$cl->close();
$cl = new NutClient('127.0.0.1', $nutPort);
$cl->connect();
check('instcmd nicht unterstuetzt', $cl->instcmd('usv', 'beeper.mute', 'test', 'testpw1234'), false);
$cl->close();
$cl = new NutClient('127.0.0.1', 1);
check('nicht erreichbar', $cl->connect(), false);
check('check erreichbar', NutClient::check('127.0.0.1', $nutPort), ['ok', 'erreichbar, USV: usv']);
check('check nicht erreichbar', NutClient::check('127.0.0.1', 1), ['fehler', 'nicht erreichbar']);

// ---------------------------------------------------------------- Unit: MQTT-Client gegen Fake-Broker
$mq = new MqttClient('127.0.0.1', $mqPort, '', '', 'unit_test', 'unit/will');
$mq->subscribe('unit/cmd/#');
check('mqtt connect', $mq->connect(), true);
check('mqtt publish', $mq->publish('unit/a', '1'), true);
file_put_contents($dir . '/inject.txt', "unit/cmd/x\thallo\n");
$got = [];
waitFor(function () use ($mq, &$got) {
    $mq->loop();
    $got = array_merge($got, $mq->takeMessages());
    return count($got) > 0;
}, 5);
check('mqtt empfangen', $got, [['unit/cmd/x', 'hallo']]);
$mq->close();
$log = (string)@file_get_contents($dir . '/mqtt.log');
check('Broker sah CONNECT mit Will', str_contains($log, "CONNECT\tunit_test\tunit/will"), true);
check('Broker sah SUBSCRIBE', str_contains($log, "SUB\tunit/cmd/#"), true);
check('Broker sah PUBLISH', str_contains($log, "unit/a\t1"), true);

// ---------------------------------------------------------------- Ende-zu-Ende: Dienst
$e2e = $dir . '/e2e';
@mkdir($e2e, 0777, true);
$conf = NutConfig::load($e2e . '/nut.json');
$conf['intervall'] = 2;
$conf['befehle_frei'] = true;
$conf['mqtt'] = ['eigener_broker' => true, 'host' => '127.0.0.1', 'port' => $mqPort, 'benutzer' => '', 'passwort' => ''];   // Ende-zu-Ende ueber die Option "eigener Broker"
$conf['server'][0] = ['aktiv' => true, 'alias' => 'usv', 'host' => '127.0.0.1', 'port' => $nutPort, 'ups' => '', 'benutzer' => 'test', 'passwort' => 'testpw1234'];
$conf['server'][1] = ['aktiv' => true, 'alias' => 'weg', 'host' => '127.0.0.1', 'port' => 1, 'ups' => 'x', 'benutzer' => '', 'passwort' => ''];
Util::jsonSave($e2e . '/nut.json', $conf);
@unlink($dir . '/mqtt.log');
$svc = startProc([$php, dirname(__DIR__) . '/nut2lox/bin/nutd.php'], ['NUT_DEV_DIR' => $e2e, 'NUT_DEV_LOGLEVEL' => 'debug']);
$mlog = fn() => (string)@file_get_contents($dir . '/mqtt.log');

check('Dienst veroeffentlicht Wert', waitFor(fn() => str_contains($mlog(), "nut/usv/battery_charge\t100")), true);
$l = $mlog();
check('Client-ID', str_contains($l, "CONNECT\tnut_nut2lox\tnut/status/ok"), true);
check('Abo Befehle', str_contains($l, "SUB\tnut/befehl/#"), true);
check('Zustand online', str_contains($l, "nut/usv/zustand/online\t1"), true);
check('Zustand laedt', str_contains($l, "nut/usv/zustand/laedt\t1"), true);
check('Text', str_contains($l, "nut/usv/ups_mfr\tFake Corp"), true);
check('Seriennummer', str_contains($l, "nut/usv/ups_serial\t000123"), true);
check('verbunden 1', str_contains($l, "nut/usv/verbunden\t1"), true);
check('Ausfall: verbunden 0', waitFor(fn() => str_contains($mlog(), "nut/weg/verbunden\t0")), true);
check('Ausfall: status/ok 0', waitFor(fn() => str_contains($mlog(), "nut/status/ok\t0")), true);
check('Lebenszeichen', str_contains($mlog(), "nut/status/zaehler\t1"), true);
check('Zeitstempel', (bool)preg_match('/nut\/status\/ts\t\d{10}/', $mlog()), true);
check('status.json', Util::jsonLoad($e2e . '/run/status.json')['service'] ?? '', 'running');

check('Info Befehle', waitFor(fn() => str_contains($mlog(), "nut/usv/info/befehle\tbeeper.toggle,test.battery.start.quick")), true);
check('Info schreibbar', str_contains($mlog(), "nut/usv/info/beschreibbar\tups.delay.shutdown,input.transfer.low"), true);
check('Info enum', str_contains($mlog(), "nut/usv/info/enum/ups_delay_shutdown\t20,30"), true);
check('Info Bereich', str_contains($mlog(), "nut/usv/info/range/input_transfer_low\t160..190"), true);
check('Info Version', str_contains($mlog(), "nut/usv/info/server_version\tNetwork UPS Tools upsd 2.8.0"), true);
check('Info Clients', str_contains($mlog(), "nut/usv/info/clients\t2"), true);
check('Info Beschreibung', str_contains($mlog(), "nut/usv/info/beschreibung\tFake USV"), true);

// Befehle
file_put_contents($dir . '/inject.txt', "nut/befehl/usv/beeper_toggle\t1\n");
check('Befehl angenommen', waitFor(fn() => str_contains($mlog(), "nut/befehl/usv/beeper_toggle/quittung\tangenommen")), true);
check('Befehl ausgefuehrt', waitFor(fn() => str_contains($mlog(), "nut/befehl/usv/beeper_toggle/quittung\tausgefuehrt")), true);
file_put_contents($dir . '/inject.txt', "nut/befehl/usv/shutdown_return\t1\n");
check('Abschalten abgelehnt', waitFor(fn() => str_contains($mlog(), "nut/befehl/usv/shutdown_return/quittung\tabgelehnt: Befehl nicht erlaubt")), true);
file_put_contents($dir . '/inject.txt', "nut/befehl/usv/beeper_toggle\t0\n");
check('Befehl ohne 1 abgelehnt', waitFor(fn() => str_contains($mlog(), "abgelehnt: zum Ausloesen 1 senden")), true);
file_put_contents($dir . '/inject.txt', "nut/befehl/gibtsnicht/beeper_toggle\t1\n");
check('unbekanntes Geraet', waitFor(fn() => str_contains($mlog(), "nut/befehl/gibtsnicht/beeper_toggle/quittung\tabgelehnt: unbekanntes Geraet")), true);
file_put_contents($dir . '/inject.txt', "nut/befehl/usv/beeper_mute\t1\n");
check('nicht unterstuetzt gemeldet', waitFor(fn() => str_contains($mlog(), "nut/befehl/usv/beeper_mute/quittung\tabgelehnt: NUT: CMD-NOT-SUPPORTED")), true);

// Sperre per Konfiguration (wird im laufenden Betrieb neu geladen)
sleep(1);
$conf['befehle_frei'] = false;
Util::jsonSave($e2e . '/nut.json', $conf);
sleep(1);
file_put_contents($dir . '/inject.txt', "nut/befehl/usv/beeper_toggle\t1\n");
check('gesperrt', waitFor(fn() => str_contains($mlog(), "quittung\tabgelehnt: Befehle sind gesperrt")), true);

// Server mit mehreren USVs (kein fester USV-Name): Geraetename alias_<usv>, Befehl an die zweite USV
@touch(sys_get_temp_dir() . "/fake_nut_$nutPort.multi");
$conf['befehle_frei'] = true;
$conf['server'][1]['aktiv'] = false;
Util::jsonSave($e2e . '/nut.json', $conf);
check('mehrere USVs: Geraet usv_usv2', waitFor(fn() => str_contains($mlog(), "nut/usv_usv2/battery_charge\t100"), 12), true);
check('mehrere USVs: erste als usv_usv', str_contains($mlog(), "nut/usv_usv/battery_charge\t100"), true);
file_put_contents($dir . '/inject.txt', "nut/befehl/usv_usv2/beeper_toggle\t1\n");
check('Befehl an zweite USV', waitFor(fn() => str_contains($mlog(), "nut/befehl/usv_usv2/beeper_toggle/quittung\tausgefuehrt")), true);
@unlink(sys_get_temp_dir() . "/fake_nut_$nutPort.multi");

// Quelle faellt aus -> status/ok 0, Zaehler laeuft weiter
@touch(sys_get_temp_dir() . "/fake_nut_$nutPort.fail");
@unlink($dir . '/mqtt.log');
check('Quelle fehlerhaft: verbunden 0', waitFor(fn() => str_contains($mlog(), "nut/usv/verbunden\t0")), true);
check('Zaehler laeuft weiter', waitFor(fn() => (bool)preg_match('/nut\/status\/zaehler\t\d+/', $mlog())), true);
@unlink(sys_get_temp_dir() . "/fake_nut_$nutPort.fail");

stopProc($svc);
$logtext = (string)@file_get_contents($e2e . '/nut.log');
check('Dienstlog vorhanden', str_contains($logtext, 'gestartet'), true);
check('Passwort nicht im Log', str_contains($logtext, 'testpw1234'), false);
check('Passwort nicht per MQTT', str_contains($mlog(), 'testpw1234'), false);

stopProc($nutProc);
stopProc($mqProc);
// ---------------------------------------------------------------- Healthcheck (LoxBerry-Healthcheck-Seite)
$hcDir = $dir . '/hc';
@mkdir($hcDir . '/run', 0777, true);
$hc = function (string $arg, ?array $status) use ($php, $root, $hcDir): array {
    $f = $hcDir . '/run/status.json';
    @unlink($f);
    if ($status !== null) {
        file_put_contents($f, json_encode($status));
    }
    $p = proc_open([$php, $root . '/nut2lox/bin/healthcheck', $arg], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge(getenv(), ['NUT_DEV_DIR' => $hcDir]));
    $out = (string)stream_get_contents($pipes[1]);
    $err = (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $rc = proc_close($p);
    return [$out, $err, $rc];
};
$basis = ['service' => 'running', 'time' => time(), 'mqtt' => 1, 'mqtt_error' => '', 'version' => AppVersion::CURRENT,
    'server' => ['usv' => ['ok' => true, 'error' => ''], 'garage' => ['ok' => true, 'error' => '']]];
[$o, $e, $rc] = $hc('title', null);
check('Healthcheck title', trim($o), 'Nut2Lox: Dienst, MQTT-Broker und USV-Abfrage');
check('Healthcheck title: Exitcode 0, keine Fehlerausgabe', [$rc, $e], [0, '']);
check('Healthcheck ohne Argument: keine Ausgabe', $hc('', null)[0], '');
$res = function (?array $st) use ($hc): array {
    [$o, $e, $rc] = $hc('check', $st);
    $j = json_decode($o, true);
    return [is_array($j) ? $j : ['status' => -1, 'result' => $o], $rc, $e];
};
[$j, $rc, $e] = $res(null);
check('Healthcheck: ohne Status = Fehler 3', $j['status'], 3);
check('Healthcheck: Dienst laeuft nicht im Text', str_contains($j['result'], 'Der Dienst läuft nicht'), true);
check('Healthcheck: Exitcode 0, keine Fehlerausgabe', [$rc, $e], [0, '']);
check('Healthcheck: veralteter Status = Fehler 3', $res(['time' => time() - 120] + $basis)[0]['status'], 3);
$j = $res(['mqtt' => 0, 'mqtt_error' => 'Verbindung abgelehnt'] + $basis)[0];
check('Healthcheck: Broker weg = Warnung 4', $j['status'], 4);
check('Healthcheck: Broker-Grund im Text', str_contains($j['result'], 'Verbindung abgelehnt'), true);
$j = $res(['server' => []] + $basis)[0];
check('Healthcheck: nichts eingerichtet = Info 6', $j['status'], 6);
$j = $res(['server' => ['usv' => ['ok' => true, 'error' => ''], 'weg' => ['ok' => false, 'error' => 'nicht erreichbar']]] + $basis)[0];
check('Healthcheck: Server ausgefallen = Warnung 4', $j['status'], 4);
check('Healthcheck: Server im Text', str_contains($j['result'], 'weg (nicht erreichbar)'), true);
$j = $res($basis)[0];
check('Healthcheck: alles gut = OK 5', $j['status'], 5);
check('Healthcheck: Text zaehlt Server und nennt Version', $j['result'], '2 NUT-Server erreichbar, Version ' . AppVersion::CURRENT);
check('Healthcheck: ohne Logdatei kein logfile', isset($j['logfile']), false);
file_put_contents($hcDir . '/nut.log', "x\n");
check('Healthcheck: mit Logdatei der Pfad', $res($basis)[0]['logfile'] ?? null, $hcDir . '/nut.log');
check('Healthcheck: Ausgabe ist genau eine JSON-Zeile', substr_count(rtrim($hc('check', $basis)[0], "\n"), "\n"), 0);

// ---------------------------------------------------------------- Weboberflaeche mit Attrappen der LoxBerry-Bibliotheken
// Rendert jeden Reiter (ohne echtes LoxBerry): findet undefinierte Variablen, Syntax- und Laufzeitfehler und prueft Menueleiste,
// Gateway-Abo und UDP-Port. Nicht pruefbar: das echte Aussehen in LoxBerry.
$uiDir = $dir . '/ui';
foreach (['config', 'log', 'data', 'sys', 'dev'] as $sub) {
    @mkdir("$uiDir/$sub", 0777, true);
}
@mkdir("$uiDir/sess", 0777, true);
$uiPage = function (string $tab, array $env = []) use ($php, $root, $uiDir): array {
    $e = array_merge(getenv(), [
        'STUB_INDEX' => $root . '/nut2lox/webfrontend/htmlauth/index.php', 'STUB_BIN' => $root . '/nut2lox/bin', 'STUB_CONFIG' => "$uiDir/config",
        'STUB_LOG' => "$uiDir/log", 'STUB_DATA' => "$uiDir/data", 'STUB_SYSCONFIG' => "$uiDir/sys", 'NUT_DEV_DIR' => "$uiDir/dev",
    ], $env);
    $cmd = [$php, '-d', 'display_errors=1', '-d', 'error_reporting=-1', '-d', 'include_path=' . $root . '/tests/stubs', '-d', 'session.save_path=' . "$uiDir/sess",
        $root . '/tests/ui_page.php', $tab];
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $e);
    $out = (string)stream_get_contents($pipes[1]);
    $err = (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($p);
    return [$out, $err];
};
$bad = fn(string $o, string $e) => (bool)preg_match('/(Warning|Notice|Fatal error|Deprecated|Parse error|Uncaught)/', $o . $e);
file_put_contents("$uiDir/sys/general.json", json_encode(['Mqtt' => ['Gatewayversion' => 2, 'Udpinport' => '12345']]));
file_put_contents("$uiDir/sys/subscriptions.json", json_encode(['Subscriptions' => [['Id' => 'shellies/#']]]));
file_put_contents("$uiDir/log/test.log", "2026-01-01 <INFO> Testzeile\n");
foreach (['einstellungen', 'mqtt', 'loxone', 'test', 'logs'] as $tab) {
    [$o, $e] = $uiPage($tab, ['STUB_UDP' => '12345']);
    check("UI $tab: ohne PHP-Warnungen und -Fehler", [$bad($o, $e), $e], [false, '']);
    check("UI $tab: Kopf und Fuss", str_contains($o, '<!--HEADER:') && str_contains($o, '<!--FOOTER-->'), true);
    preg_match('/<!--NAVBAR:(.*?)-->/', $o, $m);
    $nav = json_decode($m[1] ?? '[]', true) ?: [];
    check("UI $tab: Menueleiste mit 5 Eintraegen", count($nav), 5);
    check("UI $tab: genau ein aktiver Eintrag, und zwar dieser", array_values(array_filter($nav, fn($n) => !empty($n['active'])))[0]['URL'] ?? null, '?tab=' . $tab);
    check("UI $tab: nur ein aktiver Eintrag", count(array_filter($nav, fn($n) => !empty($n['active']))), 1);
    check("UI $tab: kein eigener Tab-Block mehr", str_contains($o, '-tabs"'), false);
}
[$o] = $uiPage('mqtt', ['STUB_UDP' => '12345']);
check('UI mqtt: Gateway V2, Abo fehlt, mit Anleitung', str_contains($o, 'Gateway V2: Das Abo „nut/#“ fehlt'), true);
check('UI mqtt: nichts abonniert = rotes Feld', str_contains($o, 'gw-abo-fehler') && !str_contains($o, 'gw-abo-ok'), true);
check('UI mqtt: rotes Feld ohne Topic-Liste', str_contains($o, 'gw-abo-fehler') && !str_contains($o, '<ul style="margin:4px 0 0 18px'), true);
file_put_contents("$uiDir/sys/subscriptions.json", json_encode(['Subscriptions' => [['Id' => 'nut/#']]]));
[$o] = $uiPage('mqtt', ['STUB_UDP' => '12345']);
check('UI mqtt: Gateway V2, Abo eingetragen', str_contains($o, 'Gateway V2: eingetragen (nut/#)'), true);
check('UI mqtt: vollstaendig abonniert = gruenes Feld mit Topic-Liste', str_contains($o, 'gw-abo-ok') && str_contains($o, '<li><code>nut/#</code></li>') && !str_contains($o, 'gw-abo-fehler'), true);
file_put_contents("$uiDir/sys/subscriptions.json", json_encode(['Subscriptions' => [['Id' => 'nut/status/#']]]));
[$o] = $uiPage('mqtt');
check('UI mqtt: nur ein Teil abonniert = gruen (nicht rot)', str_contains($o, 'gw-abo-ok') && !str_contains($o, 'gw-abo-fehler'), true);
check('UI mqtt: Teil-Abo: Hinweis und Topic aufgelistet', str_contains($o, 'teilweise abonniert (nut/status/#)') && str_contains($o, '<li><code>nut/status/#</code></li>'), true);
file_put_contents("$uiDir/sys/general.json", json_encode(['Mqtt' => ['Gatewayversion' => 1]]));
file_put_contents("$uiDir/config/mqtt_subscriptions.cfg", "nut/#\n");
[$o] = $uiPage('mqtt');
check('UI mqtt: Gateway V1, Abo in der Datei = gruen', str_contains($o, 'Gateway V1: eingetragen (nut/#)') && str_contains($o, 'gw-abo-ok'), true);
@unlink("$uiDir/config/mqtt_subscriptions.cfg");
[$o] = $uiPage('mqtt');
check('UI mqtt: Gateway V1, Abo-Datei fehlt', str_contains($o, 'Gateway V1: Das Abo'), true);
[$o] = $uiPage('loxone', ['STUB_UDP' => '12345']);
preg_match('#/dev/udp/[^/]+/(\d+)#', $o, $um);
check('UI loxone: UDP-Port aus der Konfiguration statt fest 11884', $um[1] ?? null, '12345');

[$o] = $uiPage('logs');
check('UI logs: zeigt die Logdatei', str_contains($o, 'Testzeile'), true);

echo "\n$count Pruefungen, $fails Fehler\n";
exit($fails ? 1 : 0);
