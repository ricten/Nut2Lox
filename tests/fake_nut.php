<?php
/** Minimaler NUT-Server fuer Tests: php tests/fake_nut.php [port]
 *  USV "usv", Benutzer test / Passwort testpw1234 darf INSTCMD beeper.toggle.
 *  Mit Datei <port>.fail im Temp-Ordner antwortet er auf LIST VAR mit ERR (Fehlerfall). */
$port = (int)($argv[1] ?? 3493);
$srv = stream_socket_server("tcp://127.0.0.1:$port", $e, $es);
if (!$srv) {
    fwrite(STDERR, "listen: $es\n");
    exit(1);
}
$vars = [
    'battery.charge' => '100', 'battery.runtime' => '1800', 'battery.voltage' => '27.4',
    'input.voltage' => '231.0', 'output.voltage' => '230.0', 'ups.load' => '23',
    'ups.status' => 'OL CHRG', 'ups.mfr' => 'Fake Corp', 'ups.model' => 'Test 1500',
    'ups.serial' => '000123', 'device.type' => 'ups',
];
$clients = [];
$st = [];
echo "listening $port\n";
while (true) {
    $r = array_merge([$srv], $clients);
    $w = $x = null;
    if (!stream_select($r, $w, $x, 1)) {
        continue;
    }
    foreach ($r as $s) {
        if ($s === $srv) {
            $c = stream_socket_accept($srv);
            $clients[(int)$c] = $c;
            $st[(int)$c] = ['buf' => '', 'user' => null, 'pw' => null];
            continue;
        }
        $d = fread($s, 8192);
        if ($d === '' || $d === false) {
            fclose($s);
            unset($clients[(int)$s], $st[(int)$s]);
            continue;
        }
        $id = (int)$s;
        $st[$id]['buf'] .= $d;
        while (($nl = strpos($st[$id]['buf'], "\n")) !== false) {
            $line = trim(substr($st[$id]['buf'], 0, $nl));
            $st[$id]['buf'] = substr($st[$id]['buf'], $nl + 1);
            $p = explode(' ', $line, 3);
            $cmd = strtoupper($p[0]);
            $out = '';
            if ($cmd === 'LIST' && ($p[1] ?? '') === 'UPS') {
                $out = "BEGIN LIST UPS\nUPS usv \"Fake USV\"\nEND LIST UPS\n";
            } elseif ($cmd === 'LIST' && ($p[1] ?? '') === 'VAR') {
                if (is_file(sys_get_temp_dir() . "/fake_nut_$port.fail")) {
                    $out = "ERR DATA-STALE\n";
                } elseif (($p[2] ?? '') !== 'usv') {
                    $out = "ERR UNKNOWN-UPS\n";
                } else {
                    $out = "BEGIN LIST VAR usv\n";
                    foreach ($vars as $k => $v) {
                        $out .= "VAR usv $k \"$v\"\n";
                    }
                    $out .= "END LIST VAR usv\n";
                }
            } elseif ($cmd === 'USERNAME') {
                $st[$id]['user'] = $p[1] ?? null;
                $out = "OK\n";
            } elseif ($cmd === 'PASSWORD') {
                $st[$id]['pw'] = $p[1] ?? null;
                $out = ($st[$id]['user'] === 'test' && $st[$id]['pw'] === 'testpw1234') ? "OK\n" : "ERR ACCESS-DENIED\n";
            } elseif ($cmd === 'INSTCMD') {
                $out = (($p[2] ?? '') === 'beeper.toggle') ? "OK\n" : "ERR CMD-NOT-SUPPORTED\n";
            } elseif ($cmd === 'LOGOUT') {
                fwrite($s, "OK Goodbye\n");
                fclose($s);
                unset($clients[$id], $st[$id]);
                continue 2;
            } else {
                $out = "ERR UNKNOWN-COMMAND\n";
            }
            fwrite($s, $out);
        }
    }
}
