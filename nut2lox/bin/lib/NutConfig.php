<?php
/** Konfiguration des Plugins (nut.json, Rechte 0600) und lokales Root-Konfigfile (lokal.conf). */
final class NutConfig
{
    public const array TREIBER = ['usbhid-ups', 'blazer_usb', 'nutdrv_qx', 'richcomm_usb', 'tripplite_usb', 'riello_usb', 'bcmxcp_usb'];

    public static function defaultServer(string $alias = ''): array
    {
        return ['aktiv' => false, 'alias' => $alias, 'host' => '', 'port' => 3493, 'ups' => '', 'benutzer' => '', 'passwort' => ''];
    }

    public static function defaults(): array
    {
        return [
            'intervall' => 10,
            'befehle_frei' => false,
            'mqtt' => ['eigener_broker' => false, 'host' => '', 'port' => 1883, 'benutzer' => '', 'passwort' => ''],
            'server' => [self::defaultServer('usv'), self::defaultServer(), self::defaultServer(), self::defaultServer()],
            'lokal' => [
                'aktiv' => false, 'name' => 'usv', 'treiber' => 'usbhid-ups', 'vendorid' => '', 'productid' => '',
                'netz' => false, 'synology' => false, 'benutzer' => 'nutplugin', 'passwort' => '',
            ],
        ];
    }

    public static function load(string $file): array
    {
        $def = self::defaults();
        $c = Util::jsonLoad($file);
        $out = array_merge($def, $c);
        $out['lokal'] = array_merge($def['lokal'], is_array($c['lokal'] ?? null) ? $c['lokal'] : []);
        $srv = [];
        foreach (is_array($c['server'] ?? null) ? $c['server'] : $def['server'] as $s) {
            $srv[] = array_merge(self::defaultServer(), is_array($s) ? $s : []);
        }
        while (count($srv) < 4) {
            $srv[] = self::defaultServer();
        }
        $out['server'] = array_slice($srv, 0, 4);
        $out['intervall'] = max(2, min(300, (int)$out['intervall']));
        $out['befehle_frei'] = (bool)$out['befehle_frei'];
        $out['mqtt'] = array_merge($def['mqtt'], is_array($c['mqtt'] ?? null) ? $c['mqtt'] : []);
        return $out;
    }

    /** Liste der abzufragenden Server (inkl. lokalem NUT-Server, falls aktiviert). */
    public static function servers(array $cfg): array
    {
        $out = [];
        foreach ($cfg['server'] as $s) {
            if (empty($s['aktiv']) || trim((string)$s['host']) === '') {
                continue;
            }
            $alias = Util::name((string)($s['alias'] !== '' ? $s['alias'] : $s['ups']));
            $port = (int)$s['port'];
            $out[] = ['alias' => $alias !== '' ? $alias : 'usv', 'host' => trim((string)$s['host']), 'port' => $port > 0 ? $port : 3493,
                'ups' => trim((string)$s['ups']), 'benutzer' => (string)$s['benutzer'], 'passwort' => (string)$s['passwort']];
        }
        $l = $cfg['lokal'];
        if (!empty($l['aktiv'])) {
            $alias = Util::name((string)$l['name']);
            $out[] = ['alias' => $alias !== '' ? $alias : 'usv', 'host' => '127.0.0.1', 'port' => 3493,
                'ups' => (string)$l['name'], 'benutzer' => (string)$l['benutzer'], 'passwort' => (string)$l['passwort']];
        }
        return $out;
    }

    /** Eingaben der Weboberflaeche pruefen und mit der bisherigen Konfiguration zusammenfuehren. */
    public static function fromPost(array $old, array $post): array
    {
        $new = $old;
        // Jeder Reiter der Oberflaeche sendet nur seine Abschnitte; fehlende bleiben unveraendert.
        if (isset($post['intervall'])) {
            $new['intervall'] = max(2, min(300, (int)$post['intervall']));
            $new['befehle_frei'] = !empty($post['befehle_frei']);
        }
        if (is_array($post['mqtt'] ?? null)) {
            $m = is_array($post['mqtt'] ?? null) ? $post['mqtt'] : [];
            $mpw = (string)($m['passwort'] ?? '');
            $new['mqtt'] = [
                'eigener_broker' => !empty($m['eigener_broker']),
                'host' => (string)preg_replace('/[^A-Za-z0-9_.:-]/', '', trim((string)($m['host'] ?? ''))),
                'port' => max(1, min(65535, (int)($m['port'] ?? 1883))),
                'benutzer' => trim((string)($m['benutzer'] ?? '')),
                'passwort' => $mpw !== '' ? $mpw : (string)$old['mqtt']['passwort'],
            ];
        }
        if (is_array($post['srv'] ?? null)) {
            for ($i = 0; $i < 4; $i++) {
                $p = is_array($post['srv'][$i] ?? null) ? $post['srv'][$i] : [];
                $pw = (string)($p['passwort'] ?? '');
                $new['server'][$i] = [
                    'aktiv' => !empty($p['aktiv']),
                    'alias' => Util::name((string)($p['alias'] ?? '')),
                    'host' => trim((string)($p['host'] ?? '')),
                    'port' => max(1, min(65535, (int)($p['port'] ?? 3493))),
                    'ups' => (string)preg_replace('/[^A-Za-z0-9_.@-]/', '', (string)($p['ups'] ?? '')),
                    'benutzer' => (string)preg_replace('/[^A-Za-z0-9_.@-]/', '', (string)($p['benutzer'] ?? '')),
                    'passwort' => $pw !== '' ? $pw : $old['server'][$i]['passwort'],
                ];
            }
        }
        if (is_array($post['lokal'] ?? null)) {
            $l = is_array($post['lokal'] ?? null) ? $post['lokal'] : [];
            $o = $old['lokal'];
            $name = Util::name((string)($l['name'] ?? ''));
            $vid = strtolower(trim((string)($l['vendorid'] ?? '')));
            $pid = strtolower(trim((string)($l['productid'] ?? '')));
            // Synology als NUT-Client: erwartet fest die USV "ups" und Netzfreigabe
            $syno = !empty($l['synology']);
            if ($syno) {
                $name = 'ups';
            }
            $new['lokal'] = [
                'aktiv' => !empty($l['aktiv']),
                'synology' => $syno,
                'name' => $name !== '' ? $name : 'usv',
                'treiber' => in_array($l['treiber'] ?? '', self::TREIBER, true) ? $l['treiber'] : 'usbhid-ups',
                'vendorid' => preg_match('/^[0-9a-f]{4}$/', $vid) ? $vid : '',
                'productid' => preg_match('/^[0-9a-f]{4}$/', $pid) ? $pid : '',
                'netz' => $syno || !empty($l['netz']),
                'benutzer' => $o['benutzer'] !== '' ? $o['benutzer'] : 'nutplugin',
                'passwort' => $o['passwort'] !== '' ? $o['passwort'] : bin2hex(random_bytes(12)),
            ];
        }
        return $new;
    }

    /**
     * Eigener MQTT-Broker aus der Konfiguration (statt dem LoxBerry-Broker).
     * @return array{0:string,1:int,2:string,3:string}|null  [host, port, user, pass]; null = LoxBerry-Broker verwenden
     */
    public static function ownBroker(array $cfg): ?array
    {
        $m = $cfg['mqtt'];
        if (empty($m['eigener_broker']) || trim((string)$m['host']) === '') {
            return null;
        }
        return [trim((string)$m['host']), (int)$m['port'] > 0 ? (int)$m['port'] : 1883, (string)$m['benutzer'], (string)$m['passwort']];
    }

    /** key=value-Datei fuer das Root-Skript nut-apply.sh (Rechte 0600). */
    public static function writeLokal(string $file, array $l): bool
    {
        $lines = [
            'aktiv=' . (empty($l['aktiv']) ? '0' : '1'),
            'name=' . $l['name'],
            'treiber=' . $l['treiber'],
            'vendorid=' . $l['vendorid'],
            'productid=' . $l['productid'],
            'netz=' . (empty($l['netz']) ? '0' : '1'),
            'synology=' . (empty($l['synology']) ? '0' : '1'),
            'benutzer=' . $l['benutzer'],
            'passwort=' . $l['passwort'],
        ];
        $tmp = $file . '.tmp' . getmypid();
        $old = umask(0077);
        $ok = @file_put_contents($tmp, implode("\n", $lines) . "\n") !== false;
        umask($old);
        if (!$ok) {
            return false;
        }
        @chmod($tmp, 0600);
        return @rename($tmp, $file);
    }
}
