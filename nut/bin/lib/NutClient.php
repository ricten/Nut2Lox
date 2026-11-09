<?php
/** NUT-Client (Textprotokoll, Port 3493) und Hilfen fuer Topics/Zustaende. */
final class NutClient
{
    /** Erlaubte Befehle (Name im Topic mit "_" statt "."). Alles andere (shutdown.*, load.off ...) wird abgelehnt. */
    public const string CMD_ALLOWED = '/^(beeper\.(enable|disable|mute|toggle)|test\.(battery\.(start(\.quick|\.deep)?|stop)|panel\.(start|stop)))$/';

    /** @var resource|null */
    private $fp = null;
    public string $error = '';

    public function __construct(private readonly string $host, private readonly int $port = 3493, private readonly int $timeout = 4)
    {
    }

    public function connect(): bool
    {
        $en = 0;
        $es = '';
        $this->fp = @stream_socket_client('tcp://' . $this->host . ':' . $this->port, $en, $es, $this->timeout) ?: null;
        if (!$this->fp) {
            $this->error = "NUT-Server {$this->host}:{$this->port} nicht erreichbar ($es)";
            return false;
        }
        stream_set_timeout($this->fp, $this->timeout);
        return true;
    }

    public function close(): void
    {
        if ($this->fp) {
            @fwrite($this->fp, "LOGOUT\n");
            @fclose($this->fp);
        }
        $this->fp = null;
    }

    private function send(string $line): void
    {
        @fwrite($this->fp, $line . "\n");
    }

    private function readLine(): string|false
    {
        $l = @fgets($this->fp, 4096);
        return $l === false ? false : rtrim($l, "\r\n");
    }

    private static function unquote(string $s): string
    {
        return (string)preg_replace('/\\\\(["\\\\])/', '$1', $s);
    }

    /** @return array<string,string>|null USV-Name => Beschreibung */
    public function listUps(): ?array
    {
        $this->send('LIST UPS');
        $res = [];
        while (($l = $this->readLine()) !== false) {
            if (str_starts_with($l, 'ERR ')) {
                $this->error = 'NUT: ' . substr($l, 4);
                return null;
            }
            if (str_starts_with($l, 'END LIST')) {
                return $res;
            }
            if (preg_match('/^UPS (\S+) "(.*)"$/', $l, $m)) {
                $res[$m[1]] = self::unquote($m[2]);
            }
        }
        $this->error = 'NUT: Zeitueberschreitung beim Lesen der USV-Liste';
        return null;
    }

    /** @return array<string,string>|null Variable => Wert */
    public function listVars(string $ups): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_.@-]+$/', $ups)) {
            $this->error = 'Ungueltiger USV-Name';
            return null;
        }
        $this->send('LIST VAR ' . $ups);
        $vars = [];
        while (($l = $this->readLine()) !== false) {
            if (str_starts_with($l, 'ERR ')) {
                $this->error = 'NUT: ' . substr($l, 4);
                return null;
            }
            if (str_starts_with($l, 'END LIST')) {
                return $vars;
            }
            if (preg_match('/^VAR \S+ (\S+) "(.*)"$/', $l, $m)) {
                $vars[$m[1]] = self::unquote($m[2]);
            }
        }
        $this->error = 'NUT: Zeitueberschreitung beim Lesen der Werte';
        return null;
    }

    /** Allgemeine LIST-Abfrage. @return string[]|null Zeilen ohne BEGIN/END; null bei Fehler (z. B. ERR FEATURE-NOT-SUPPORTED) */
    public function rawList(string $cmd): ?array
    {
        $this->send($cmd);
        $lines = [];
        while (($l = $this->readLine()) !== false) {
            if (str_starts_with($l, 'ERR ')) {
                $this->error = 'NUT: ' . substr($l, 4);
                return null;
            }
            if (str_starts_with($l, 'BEGIN LIST')) {
                continue;
            }
            if (str_starts_with($l, 'END LIST')) {
                return $lines;
            }
            $lines[] = $l;
        }
        $this->error = 'NUT: Zeitueberschreitung';
        return null;
    }

    /** Einzeilige Antwort (VER, NETVER, GET ...). null bei ERR. */
    public function rawLine(string $cmd): ?string
    {
        $this->send($cmd);
        $l = $this->readLine();
        if ($l === false || str_starts_with($l, 'ERR ')) {
            return null;
        }
        return $l;
    }

    /**
     * Zusatzinformationen des Servers zu einer USV (alles, was NUT ausser den Variablen liefert):
     * Version, Protokoll, Anmeldungen, Clients, Sofortbefehle, schreibbare Variablen mit erlaubten Werten/Bereichen.
     * @return array<string,string> Topic-Teil (unter info/) => Wert. Nicht unterstuetzte Abfragen fehlen einfach.
     */
    public function info(string $ups, string $desc = ''): array
    {
        $o = [];
        if ($desc === '' && ($v = $this->rawLine("GET UPSDESC $ups")) !== null && preg_match('/^UPSDESC \S+ "(.*)"$/', $v, $m)) {
            $desc = self::unquote($m[1]);
        }
        if ($desc !== '') {
            $o['beschreibung'] = $desc;
        }
        if (($v = $this->rawLine('VER')) !== null) {
            $o['server_version'] = self::value($v);
        }
        if (($v = $this->rawLine('NETVER')) !== null) {
            $o['protokoll'] = self::value($v);
        }
        if (($v = $this->rawLine("GET NUMLOGINS $ups")) !== null && preg_match('/^NUMLOGINS \S+ (\d+)/', $v, $m)) {
            $o['anmeldungen'] = $m[1];
        }
        if (($rows = $this->rawList("LIST CLIENT $ups")) !== null) {
            $o['clients'] = (string)count($rows);
        }
        if (($rows = $this->rawList("LIST CMD $ups")) !== null) {
            $names = [];
            foreach ($rows as $r) {
                if (preg_match('/^CMD \S+ (\S+)/', $r, $m)) {
                    $names[] = $m[1];
                }
            }
            $o['befehle'] = implode(',', $names);
        }
        if (($rows = $this->rawList("LIST RW $ups")) !== null) {
            $names = [];
            foreach ($rows as $r) {
                if (preg_match('/^RW \S+ (\S+) /', $r, $m)) {
                    $names[] = $m[1];
                }
            }
            $o['beschreibbar'] = implode(',', $names);
            foreach (array_slice($names, 0, 60) as $n) {
                if (!preg_match('/^[A-Za-z0-9_.@-]+$/', $n)) {
                    continue;
                }
                $vn = self::varName($n);
                if (($e = $this->rawList("LIST ENUM $ups $n")) !== null && $e) {
                    $vals = [];
                    foreach ($e as $r) {
                        if (preg_match('/^ENUM \S+ \S+ "(.*)"$/', $r, $m)) {
                            $vals[] = self::unquote($m[1]);
                        }
                    }
                    $o["enum/$vn"] = implode(',', $vals);
                }
                if (($e = $this->rawList("LIST RANGE $ups $n")) !== null && $e) {
                    $vals = [];
                    foreach ($e as $r) {
                        if (preg_match('/^RANGE \S+ \S+ "(.*)" "(.*)"$/', $r, $m)) {
                            $vals[] = $m[1] . '..' . $m[2];
                        }
                    }
                    $o["range/$vn"] = implode(';', $vals);
                }
            }
        }
        return $o;
    }

    private static function credentialsValid(string $user, string $pass): bool
    {
        return $user !== '' && !preg_match('/[\x00-\x20"\\\\]/', $user . $pass);
    }

    /** Nur die Anmeldung pruefen (Selbstpruefung). */
    public function login(string $user, string $pass): bool
    {
        if (!self::credentialsValid($user, $pass)) {
            $this->error = 'Benutzer/Passwort fehlen oder enthalten ungueltige Zeichen';
            return false;
        }
        foreach (['USERNAME ' . $user, 'PASSWORD ' . $pass] as $line) {
            $this->send($line);
            $r = $this->readLine();
            if ($r === false || !str_starts_with($r, 'OK')) {
                $this->error = 'Anmeldung am NUT-Server fehlgeschlagen';
                return false;
            }
        }
        return true;
    }

    /** Sofortbefehl (INSTCMD) mit Anmeldung. Fehlertext (nie mit Passwort) in $error. */
    public function instcmd(string $ups, string $cmd, string $user, string $pass): bool
    {
        if (!preg_match('/^[A-Za-z0-9_.@-]+$/', $ups) || !preg_match('/^[A-Za-z0-9_.-]+$/', $cmd)) {
            $this->error = 'Ungueltiger Name';
            return false;
        }
        if (!$this->login($user, $pass)) {
            return false;
        }
        $this->send('INSTCMD ' . $ups . ' ' . $cmd);
        $r = $this->readLine();
        if ($r === false) {
            $this->error = 'NUT: keine Antwort';
            return false;
        }
        if (!str_starts_with($r, 'OK')) {
            $this->error = 'NUT: ' . (str_starts_with($r, 'ERR ') ? substr($r, 4) : $r);
            return false;
        }
        return true;
    }

    /**
     * Verbindung herstellen und USV-Namen bestimmen.
     * @return array{0:NutClient,1:string}|null  Fehlertext in $err
     */
    public static function open(array $srv, ?string &$err): ?array
    {
        $c = new self($srv['host'], (int)$srv['port']);
        if (!$c->connect()) {
            $err = $c->error;
            return null;
        }
        $ups = (string)$srv['ups'];
        if ($ups === '') {
            $list = $c->listUps();
            if (!$list) {
                $err = $c->error !== '' ? $c->error : 'Keine USV auf dem Server gefunden';
                $c->close();
                return null;
            }
            $ups = (string)array_key_first($list);
        }
        return [$c, $ups];
    }

    /** Wert fuer MQTT: ohne Zeilenumbrueche. Zahlen bleiben unveraendert (Punkt). */
    public static function value(string $v): string
    {
        return trim((string)preg_replace('/\s+/', ' ', $v));
    }

    /** battery.charge -> battery_charge */
    public static function varName(string $name): string
    {
        return (string)preg_replace('/[^a-z0-9_-]+/', '_', strtolower(str_replace('.', '_', $name)));
    }

    /** Zustandsflags aus ups.status. @return array<string,int> */
    public static function flags(string $status): array
    {
        $map = ['OL' => 'online', 'OB' => 'batterie', 'LB' => 'batterie_schwach', 'RB' => 'batterie_tauschen',
            'CHRG' => 'laedt', 'DISCHRG' => 'entlaedt', 'OVER' => 'ueberlast', 'BYPASS' => 'bypass', 'ALARM' => 'alarm'];
        $tokens = preg_split('/\s+/', trim($status)) ?: [];
        $res = [];
        foreach ($map as $flag => $name) {
            $res[$name] = in_array($flag, $tokens, true) ? 1 : 0;
        }
        return $res;
    }
}
