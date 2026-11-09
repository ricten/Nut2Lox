<?php
/**
 * Gemeinsamer Start fuer Dienst und Weboberflaeche: Klassen und Pfade.
 * Ausserhalb von LoxBerry (Entwicklung/Tests) greift NUT_DEV_DIR als Basisverzeichnis.
 * Util, Logger und MqttClient kommen aus ../common/php (build.sh kopiert sie nach bin/lib).
 */
foreach (['Util', 'Logger', 'MqttClient', 'NutClient', 'NutConfig', 'AppVersion'] as $cls) {
    // im ZIP liegt alles in bin/lib; im Quellbaum kommen die gemeinsamen Klassen aus ../common/php
    $f = __DIR__ . '/' . $cls . '.php';
    require_once is_file($f) ? $f : __DIR__ . '/../../../../common/php/' . $cls . '.php';
}

final class Paths
{
    public string $folder;
    public string $config;     // nut.json
    public string $lokal;      // lokal.conf (fuer das Root-Skript)
    public string $logfile;
    public string $run;        // /dev/shm/<folder>
    public string $bin;

    public static function detect(): Paths
    {
        $p = new Paths();
        $dev = getenv('NUT_DEV_DIR');
        if ($dev) {
            $p->folder = getenv('NUT_DEV_FOLDER') ?: 'nut2lox';
            $p->config = $dev . '/nut.json';
            $p->lokal = $dev . '/lokal.conf';
            $p->logfile = $dev . '/nut.log';
            $p->run = getenv('NUT_DEV_RUN') ?: $dev . '/run';
            $p->bin = dirname(__DIR__);
        } else {
            require_once 'loxberry_system.php';
            $p->folder = LBPPLUGINDIR;
            $p->config = LBPCONFIGDIR . '/nut.json';
            $p->lokal = LBPCONFIGDIR . '/lokal.conf';
            $p->logfile = LBPLOGDIR . '/nut.log';
            $p->run = '/dev/shm/' . LBPPLUGINDIR;
            $p->bin = LBPBINDIR;
        }
        if (!is_dir($p->run)) {
            @mkdir($p->run, 0775, true);
        }
        return $p;
    }

    public function statusFile(): string
    {
        return $this->run . '/status.json';
    }

    public function pidFile(): string
    {
        return $this->run . '/nut.pid';
    }

    public function lockFile(): string
    {
        return $this->run . '/run.lock';
    }
}
