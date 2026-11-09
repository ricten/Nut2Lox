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
            $w = explode(' ', $line);
            $multi = is_file(sys_get_temp_dir() . "/fake_nut_$port.multi");
            $upsList = $multi ? ['usv' => 'Fake USV', 'usv2' => 'Zweite USV'] : ['usv' => 'Fake USV'];
            if ($cmd === 'LIST' && ($p[1] ?? '') === 'UPS') {
                $out = "BEGIN LIST UPS\n";
                foreach ($upsList as $u => $dsc) {
                    $out .= "UPS $u \"$dsc\"\n";
                }
                $out .= "END LIST UPS\n";
            } elseif ($cmd === 'LIST' && ($p[1] ?? '') === 'VAR') {
                if (is_file(sys_get_temp_dir() . "/fake_nut_$port.fail")) {
                    $out = "ERR DATA-STALE\n";
                } elseif (!isset($upsList[$p[2] ?? ''])) {
                    $out = "ERR UNKNOWN-UPS\n";
                } else {
                    $u = $p[2];
                    $out = "BEGIN LIST VAR $u\n";
                    foreach ($vars as $k => $v) {
                        $out .= "VAR $u $k \"$v\"\n";
                    }
                    $out .= "END LIST VAR $u\n";
                }
            } elseif ($cmd === 'LIST' && ($w[1] ?? '') === 'CMD') {
                $out = "BEGIN LIST CMD usv\nCMD usv beeper.toggle\nCMD usv test.battery.start.quick\nEND LIST CMD usv\n";
            } elseif ($cmd === 'LIST' && ($w[1] ?? '') === 'RW') {
                $out = "BEGIN LIST RW usv\nRW usv ups.delay.shutdown \"20\"\nRW usv input.transfer.low \"180\"\nEND LIST RW usv\n";
            } elseif ($cmd === 'LIST' && ($w[1] ?? '') === 'ENUM') {
                $out = (($w[3] ?? '') === 'ups.delay.shutdown')
                    ? "BEGIN LIST ENUM usv ups.delay.shutdown\nENUM usv ups.delay.shutdown \"20\"\nENUM usv ups.delay.shutdown \"30\"\nEND LIST ENUM usv ups.delay.shutdown\n"
                    : "BEGIN LIST ENUM usv {$w[3]}\nEND LIST ENUM usv {$w[3]}\n";
            } elseif ($cmd === 'LIST' && ($w[1] ?? '') === 'RANGE') {
                $out = (($w[3] ?? '') === 'input.transfer.low')
                    ? "BEGIN LIST RANGE usv input.transfer.low\nRANGE usv input.transfer.low \"160\" \"190\"\nEND LIST RANGE usv input.transfer.low\n"
                    : "BEGIN LIST RANGE usv {$w[3]}\nEND LIST RANGE usv {$w[3]}\n";
            } elseif ($cmd === 'LIST' && ($w[1] ?? '') === 'CLIENT') {
                $out = "BEGIN LIST CLIENT usv\nCLIENT usv 127.0.0.1\nCLIENT usv 192.168.1.5\nEND LIST CLIENT usv\n";
            } elseif ($cmd === 'VER') {
                $out = "Network UPS Tools upsd 2.8.0 - https://www.networkupstools.org/\n";
            } elseif ($cmd === 'NETVER') {
                $out = "1.3\n";
            } elseif ($cmd === 'GET' && ($w[1] ?? '') === 'NUMLOGINS') {
                $out = "NUMLOGINS usv 2\n";
            } elseif ($cmd === 'GET' && ($w[1] ?? '') === 'UPSDESC') {
                $out = "UPSDESC usv \"Fake USV\"\n";
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
