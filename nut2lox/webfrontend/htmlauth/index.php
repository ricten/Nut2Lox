<?php
// Weboberflaeche Nut2Lox: Einstellungen · MQTT · Einbindung in Loxone · Test · Logdateien
// Achtung: LBWeb::lbheader() und mqtt_connectiondetails() ueberschreiben die globale Variable $cfg -> eigene Konfiguration heisst $nut.

require_once 'loxberry_system.php';
require_once 'loxberry_web.php';
require_once 'loxberry_io.php';
require_once LBPBINDIR . '/lib/bootstrap.php';

session_start();
if (empty($_SESSION['nut_token'])) {
    $_SESSION['nut_token'] = bin2hex(random_bytes(16));
}
$token = $_SESSION['nut_token'];

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Root-Skript per sudo aufrufen (apply | scan). */
function runRoot(string $arg): array
{
    $out = [];
    $rc = 0;
    exec('sudo -n ' . escapeshellarg(LBPBINDIR . '/nut-apply.sh') . ' ' . escapeshellarg($arg) . ' 2>&1', $out, $rc);
    return [$rc, implode("\n", $out)];
}

$paths = Paths::detect();
$nut = NutConfig::load($paths->config);
$tab = $_GET['tab'] ?? 'einstellungen';
if (!in_array($tab, ['einstellungen', 'mqtt', 'loxone', 'test', 'logs'], true)) {
    $tab = 'einstellungen';
}
$meldungen = [];
$ausgabe = '';

// ------------------------------------------------------------ Aktionen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($token, (string)($_POST['token'] ?? ''))) {
        http_response_code(403);
        exit('Ungueltiges Formular-Token, Seite neu laden.');
    }
    $aktion = (string)($_POST['aktion'] ?? '');
    if ($aktion === 'speichern') {
        $neu = NutConfig::fromPost($nut, $_POST);
        $lokalGeaendert = $neu['lokal'] != $nut['lokal'];
        if (Util::jsonSave($paths->config, $neu)) {
            $meldungen[] = 'Einstellungen gespeichert.';
        } else {
            $meldungen[] = 'FEHLER: Einstellungen konnten nicht gespeichert werden.';
        }
        $nut = $neu;
        NutConfig::writeLokal($paths->lokal, $nut['lokal']);
        if ($lokalGeaendert) {
            [$rc, $ausgabe] = runRoot('apply');
            $meldungen[] = $rc === 0 ? 'Lokaler NUT-Server eingerichtet.' : 'FEHLER beim Einrichten des lokalen NUT-Servers (Ausgabe unten).';
        }
    } elseif ($aktion === 'anwenden') {
        NutConfig::writeLokal($paths->lokal, $nut['lokal']);
        [$rc, $ausgabe] = runRoot('apply');
        $meldungen[] = $rc === 0 ? 'Lokaler NUT-Server eingerichtet.' : 'FEHLER beim Einrichten (Ausgabe unten).';
    } elseif ($aktion === 'scan') {
        [, $ausgabe] = runRoot('scan');
        $meldungen[] = 'USB-Suche beendet.';
    } elseif ($aktion === 'neustart') {
        exec(escapeshellarg(LBPBINDIR . '/stop.sh') . ' 2>&1');
        exec(escapeshellarg(LBPBINDIR . '/start.sh') . ' 2>&1');
        $meldungen[] = 'Dienst neu gestartet.';
    }
}

$status = Util::jsonLoad($paths->statusFile());
$alter = isset($status['time']) ? time() - (int)$status['time'] : null;
$laeuft = $alter !== null && $alter <= 10;
$basis = '?tab=';

LBWeb::lbheader('Nut2Lox ' . AppVersion::CURRENT, '', '');
?>
<style>
 .nut-tabs a { display:inline-block; padding:6px 14px; margin:0 4px 8px 0; border:1px solid #999; border-radius:4px; text-decoration:none; color:inherit; }
 .nut-tabs a.aktiv { background:#6dac20; color:#fff; border-color:#6dac20; }
 .nut table { border-collapse:collapse; margin:8px 0 16px 0; }
 .nut th, .nut td { border:1px solid #bbb; padding:4px 8px; text-align:left; vertical-align:top; }
 .nut pre { background:#f4f4f4; color:#222; padding:8px; overflow:auto; max-height:420px; }
 .nut .ok { color:#2a8a2a; font-weight:bold; } .nut .fehler { color:#c0392b; font-weight:bold; }
 .nut .hinweis { color:#666; font-size:90%; }
 .nut-led { display:inline-block; width:18px; height:18px; border-radius:3px; vertical-align:middle; border:1px solid rgba(0,0,0,.35); }
 .nut-led-ok { background:#2ecc40; } .nut-led-fehler { background:#e74c3c; } .nut-led-aus { background:#bbb; }
 .nut input.nut-chk { display:inline-block; position:static; opacity:1; width:22px; height:22px; margin:4px; }
</style>
<div class="nut">
<div class="nut-tabs">
<?php
$tabs = ['einstellungen' => 'Einstellungen', 'mqtt' => 'MQTT', 'loxone' => 'Einbindung in Loxone', 'test' => 'Test', 'logs' => 'Logdateien'];
foreach ($tabs as $k => $t) {
    echo '<a href="' . $basis . $k . '" class="' . ($tab === $k ? 'aktiv' : '') . '" data-ajax="false">' . h($t) . '</a>';
}
?>
</div>
<?php foreach ($meldungen as $m) { echo '<p class="' . (str_starts_with($m, 'FEHLER') ? 'fehler' : 'ok') . '">' . h($m) . '</p>'; } ?>
<?php if ($ausgabe !== '') { echo '<pre>' . h($ausgabe) . '</pre>'; } ?>

<?php if ($tab === 'einstellungen') { ?>
<form method="post" action="<?= $basis ?>einstellungen" data-ajax="false">
<input type="hidden" name="token" value="<?= h($token) ?>">

<h3>Allgemein</h3>
<p>Abfrageintervall (Sekunden, 2–300):
<input type="number" name="intervall" min="2" max="300" value="<?= (int)$nut['intervall'] ?>"></p>
<p><label><input type="checkbox" name="befehle_frei" value="1" <?= $nut['befehle_frei'] ? 'checked' : '' ?>>
 Befehle aus Loxone freigeben (Piepser, Batterietest)</label><br>
<span class="hinweis">Ab Werk gesperrt. Erlaubt sind nur beeper.* und test.battery.* / test.panel.* – nie Abschalten oder Last aus.</span></p>

<h3>MQTT-Broker</h3>
<?php $mq = $nut['mqtt']; ?>
<p><label><input type="checkbox" name="mqtt[eigener_broker]" value="1" <?= !empty($mq['eigener_broker']) ? 'checked' : '' ?>>
 Eigenen MQTT-Broker verwenden (statt dem Broker des LoxBerry)</label></p>
<table>
<tr><th>Host/IP</th><td><input type="text" name="mqtt[host]" value="<?= h($mq['host']) ?>" size="24"></td>
<th>Port</th><td><input type="number" name="mqtt[port]" value="<?= (int)$mq['port'] ?>" style="width:90px"></td></tr>
<tr><th>Benutzer</th><td><input type="text" name="mqtt[benutzer]" value="<?= h($mq['benutzer']) ?>" size="16" autocomplete="off"></td>
<th>Passwort</th><td><input type="password" name="mqtt[passwort]" value="" size="16" autocomplete="new-password" placeholder="<?= $mq['passwort'] !== '' ? '(gesetzt)' : '' ?>"></td></tr>
</table>
<p class="hinweis">Ohne Haken verwendet das Plugin den MQTT-Broker aus der LoxBerry-Konfiguration (empfohlen, Zugangsdaten verwaltet LoxBerry). Mit eigenem Broker erreichen die Werte den Miniserver nur, wenn das MQTT-Gateway diesen Broker abonniert. Ein leeres Passwortfeld behält das gespeicherte Passwort.</p>

<h3>NUT-Server im Netz</h3>
<table>
<tr><th>Status</th><th>Aktiv</th><th>Name im Topic</th><th>Host/IP</th><th>Port</th><th>USV-Name</th><th>Benutzer</th><th>Passwort</th></tr>
<?php foreach ($nut['server'] as $i => $s) { ?>
<tr>
 <td><?php [$led, $tip] = $s['host'] === '' ? ['aus', 'kein Host eingetragen'] : NutClient::check($s['host'], (int)$s['port'] ?: 3493); ?>
 <span class="nut-led nut-led-<?= $led ?>" title="<?= h($tip) ?>"></span> <span class="hinweis"><?= h($tip) ?></span></td>
 <td><input type="checkbox" data-role="none" class="nut-chk" name="srv[<?= $i ?>][aktiv]" value="1" <?= !empty($s['aktiv']) ? 'checked' : '' ?>></td>
 <td><input type="text" name="srv[<?= $i ?>][alias]" value="<?= h($s['alias']) ?>" size="10"></td>
 <td><input type="text" name="srv[<?= $i ?>][host]" value="<?= h($s['host']) ?>" size="16"></td>
 <td><input type="number" name="srv[<?= $i ?>][port]" value="<?= (int)$s['port'] ?>" style="width:80px"></td>
 <td><input type="text" name="srv[<?= $i ?>][ups]" value="<?= h($s['ups']) ?>" size="10" placeholder="automatisch"></td>
 <td><input type="text" name="srv[<?= $i ?>][benutzer]" value="<?= h($s['benutzer']) ?>" size="10"></td>
 <td><input type="password" name="srv[<?= $i ?>][passwort]" value="" size="10" autocomplete="new-password" placeholder="<?= $s['passwort'] !== '' ? '(gesetzt)' : '' ?>"></td>
</tr>
<?php } ?>
</table>
<p class="hinweis">Benutzer/Passwort sind nur für Befehle nötig, zum Lesen der Werte nicht. Ein leeres Passwortfeld behält das gespeicherte Passwort.
Ohne Name im Topic wird der USV-Name verwendet (nur a–z, 0–9, _ und -).</p>

<h3>USV per USB am LoxBerry</h3>
<?php
$l = $nut['lokal'];
// Eine Anzeige genuegt: ohne Netzfreigabe lauscht der Server auf 127.0.0.1, mit Freigabe auf 0.0.0.0 (deckt localhost mit ab).
// Dann wird ueber die Netzwerkadresse geprueft, unter der diese Seite aufgerufen wurde.
$lanIp = (string)($_SERVER['SERVER_ADDR'] ?? '');
$freigabe = !empty($l['netz']) || !empty($l['synology']);
$addr = ($freigabe && $lanIp !== '' && $lanIp !== '::1') ? $lanIp : '127.0.0.1';
[$led, $tip] = NutClient::check($addr, 3493);
if ($led !== 'ok' && $addr !== '127.0.0.1' && NutClient::check('127.0.0.1', 3493)[0] === 'ok') {
    $tip = 'nur lokal erreichbar - „Lokalen Server neu einrichten“ drücken (Netzfreigabe ist noch nicht aktiv)';
}
?>
<p>Lokaler NUT-Server (<?= h($addr) ?>:3493<?= $addr === '127.0.0.1' ? ', nur lokal' : ', im Netz freigegeben' ?>): <span class="nut-led nut-led-<?= $led ?>"></span> <span class="hinweis"><?= h($tip) ?></span></p>
<p><label><input type="checkbox" name="lokal[aktiv]" value="1" <?= !empty($l['aktiv']) ? 'checked' : '' ?>>
 Lokalen NUT-Server auf dem LoxBerry einrichten und abfragen</label></p>
<table>
<tr><th>Name der USV</th><td><input type="text" name="lokal[name]" value="<?= h($l['name']) ?>"></td></tr>
<tr><th>Treiber</th><td><select name="lokal[treiber]">
<?php foreach (NutConfig::TREIBER as $t) { echo '<option value="' . h($t) . '"' . ($l['treiber'] === $t ? ' selected' : '') . '>' . h($t) . '</option>'; } ?>
</select></td></tr>
<tr><th>USB Vendor-ID / Product-ID</th><td>
<input type="text" name="lokal[vendorid]" value="<?= h($l['vendorid']) ?>" size="5" maxlength="4" placeholder="optional">
 / <input type="text" name="lokal[productid]" value="<?= h($l['productid']) ?>" size="5" maxlength="4" placeholder="optional"></td></tr>
<tr><th>Synology (DSM) als Client</th><td><label><input type="checkbox" name="lokal[synology]" value="1" <?= !empty($l['synology']) ? 'checked' : '' ?>>
 Synology DiskStation soll diese USV als „Synology USV-Server“ nutzen</label><br>
<span class="hinweis">DSM erwartet fest die USV <b>ups</b> und den Benutzer <b>monuser</b> mit Passwort <b>secret</b> (nicht änderbar). Das Plugin benennt die USV dann „ups“, gibt den Server im Netz frei und legt monuser nur als Client (slave) an.
In DSM: Systemsteuerung → Hardware &amp; Energie → USV → USV-Unterstützung aktivieren, „Synology USV-Server“, IP des LoxBerry. Das feste Passwort ist bekannt – Port 3493 nur im Heimnetz erreichbar lassen.</span></td></tr>
<tr><th>Im Netz freigeben</th><td><label><input type="checkbox" name="lokal[netz]" value="1" <?= !empty($l['netz']) ? 'checked' : '' ?>>
 NUT-Server auch für andere Geräte erreichbar (Port 3493)</label></td></tr>
</table>
<p class="hinweis">Das Plugin schreibt dafür /etc/nut/{nut.conf,ups.conf,upsd.conf,upsd.users} (Betriebsart netserver, ohne upsmon – der LoxBerry fährt dadurch nicht selbst herunter).
Bestehende Dateien werden einmalig als *.vor-nut-plugin gesichert. Ist die Option aus, wird nur die vom Plugin angelegte Konfiguration abgeschaltet.</p>

<p><button type="submit" name="aktion" value="speichern" data-ajax="false">Speichern</button>
<button type="submit" name="aktion" value="anwenden" data-ajax="false">Lokalen Server neu einrichten</button>
<button type="submit" name="aktion" value="scan" data-ajax="false">USB-Geräte suchen</button></p>
</form>

<?php } elseif ($tab === 'mqtt') {
    $own = NutConfig::ownBroker($nut);
    $d = $own !== null ? ['brokerhost' => $own[0], 'brokerport' => $own[1]] : (function_exists('mqtt_connectiondetails') ? mqtt_connectiondetails() : []);
    $abo = is_file(LBPCONFIGDIR . '/mqtt_subscriptions.cfg');
    ?>
<h3>MQTT</h3>
<table>
<tr><th><?= $own !== null ? 'Broker (eigener, aus den Plugin-Einstellungen)' : 'Broker (aus der LoxBerry-Konfiguration)' ?></th><td><?= h(($d['brokerhost'] ?? '?') . ':' . ($d['brokerport'] ?? '?')) ?></td></tr>
<tr><th>Gateway-Abo</th><td>nut/# <?= $abo ? '<span class="ok">hinterlegt</span>' : '<span class="fehler">fehlt</span>' ?></td></tr>
<tr><th>Dienst</th><td><?= $laeuft ? '<span class="ok">läuft</span> (Version ' . h($status['version'] ?? '?') . ')' : '<span class="fehler">läuft nicht</span> (der Wächter startet ihn spätestens nach einer Minute)' ?></td></tr>
<tr><th>Broker-Verbindung des Dienstes</th><td><?= !empty($status['mqtt']) ? '<span class="ok">verbunden</span>' : '<span class="fehler">getrennt</span> ' . h($status['mqtt_error'] ?? '') ?></td></tr>
<tr><th>status/ok</th><td><?= isset($status['ok']) ? (int)$status['ok'] : '-' ?></td></tr>
<tr><th>status/zaehler</th><td><?= isset($status['zaehler']) ? (int)$status['zaehler'] : '-' ?></td></tr>
<tr><th>status/ts</th><td><?= !empty($status['ts']) ? h(date('d.m.Y H:i:s', (int)$status['ts'])) : '-' ?></td></tr>
</table>
<?php foreach (($status['server'] ?? []) as $a => $r) {
    echo '<p>' . h($a) . ': ' . (!empty($r['ok']) ? '<span class="ok">erreichbar</span>' : '<span class="fehler">' . h($r['error'] ?? 'Fehler') . '</span>') . '</p>';
} ?>
<p class="hinweis">Standard: Zugangsdaten zum Broker verwaltet LoxBerry (MQTT-Gateway), das Plugin hat keine eigenen. Nur bei „Eigenen MQTT-Broker verwenden“ liegen sie in der Plugin-Konfiguration (Rechte 0600) und werden nie angezeigt oder geloggt.</p>

<?php } elseif ($tab === 'loxone') {
    $aliase = array_map(fn($s) => $s['alias'], NutConfig::servers($nut)) ?: ['usv'];
    $werte = [
        ['ups_status', '', 'Status als Text (OL, OB, OL CHRG ...)'],
        ['battery_charge', '%', 'Ladezustand der Batterie'],
        ['battery_runtime', 's', 'Restlaufzeit'],
        ['battery_voltage', 'V', 'Batteriespannung'],
        ['input_voltage', 'V', 'Eingangsspannung (Netz)'],
        ['output_voltage', 'V', 'Ausgangsspannung'],
        ['input_frequency', 'Hz', 'Netzfrequenz'],
        ['ups_load', '%', 'Last'],
        ['ups_realpower', 'W', 'Wirkleistung (nicht bei jeder USV)'],
        ['ups_temperature', '°C', 'Temperatur (nicht bei jeder USV)'],
        ['zustand/online', '0/1', 'Netzbetrieb (OL)'],
        ['zustand/batterie', '0/1', 'Batteriebetrieb (OB) – Netzausfall'],
        ['zustand/batterie_schwach', '0/1', 'Batterie schwach (LB)'],
        ['zustand/batterie_tauschen', '0/1', 'Batterie tauschen (RB)'],
        ['zustand/laedt', '0/1', 'Batterie lädt (CHRG)'],
        ['zustand/entlaedt', '0/1', 'Batterie entlädt (DISCHRG)'],
        ['zustand/ueberlast', '0/1', 'Überlast (OVER)'],
        ['zustand/bypass', '0/1', 'Bypass aktiv'],
        ['zustand/alarm', '0/1', 'Alarm'],
        ['verbunden', '0/1', 'NUT-Server erreichbar'],
        ['info/beschreibung', 'Text', 'Beschreibung der USV (NUT)'],
        ['info/server_version', 'Text', 'Version des NUT-Servers'],
        ['info/protokoll', 'Text', 'NUT-Protokollversion'],
        ['info/anmeldungen', '', 'Anzahl angemeldeter Clients (NUMLOGINS)'],
        ['info/clients', '', 'Anzahl verbundener Clients (LIST CLIENT)'],
        ['info/befehle', 'Text', 'Sofortbefehle der USV, kommagetrennt (LIST CMD)'],
        ['info/beschreibbar', 'Text', 'Schreibbare Variablen, kommagetrennt (LIST RW)'],
        ['info/enum/<variable>', 'Text', 'Erlaubte Werte einer schreibbaren Variable, kommagetrennt'],
        ['info/range/<variable>', 'Text', 'Erlaubter Bereich einer schreibbaren Variable (min..max)'],
    ];
    ?>
<h3>Einbindung in Loxone</h3>
<p>Der Eingangsname ist das Topic mit _ statt /. Die Werte kommen über das MQTT-Gateway (Abo nut/#) als virtuelle Eingänge.
Weitere NUT-Variablen kommen automatisch als nut/&lt;name&gt;/&lt;variable&gt; (Punkt wird zu _, z. B. battery_charge_low).
Die <i>info</i>-Topics (Befehle, schreibbare Variablen, Clients ...) werden alle 60 s aktualisiert, nicht jedes Intervall.
Hat ein Server ohne festen USV-Namen mehrere USVs, heißen die Geräte &lt;name&gt;_&lt;usv&gt; (bei genau einer USV nur &lt;name&gt;).</p>
<table>
<tr><th>Topic</th><th>Eingangsname</th><th>Einheit</th><th>Bedeutung</th><th>retained</th></tr>
<?php
foreach ($aliase as $a) {
    foreach ($werte as $w) {
        $topic = 'nut/' . $a . '/' . $w[0];
        echo '<tr><td>' . h($topic) . '</td><td>' . h(str_replace('/', '_', $topic)) . '</td><td>' . h($w[1]) . '</td><td>' . h($w[2]) . '</td><td>nein</td></tr>';
    }
}
foreach ([['nut/status/ok', '0/1', 'Letzter Durchgang erfolgreich (No Cache)'], ['nut/status/zaehler', '0–999', 'Lebenszeichen, zählt je Durchgang (No Cache)'],
    ['nut/status/ts', 's', 'Unix-Zeitstempel des letzten Durchgangs']] as $w) {
    echo '<tr><td>' . h($w[0]) . '</td><td>' . h(str_replace('/', '_', $w[0])) . '</td><td>' . h($w[1]) . '</td><td>' . h($w[2]) . '</td><td>nein</td></tr>';
}
?>
</table>
<p class="hinweis">Beim Absturz des Dienstes setzt der Broker nut/status/ok per Letzter-Wille-Nachricht auf 0 (retained).</p>

<h3>Befehle aus Loxone</h3>
<p>Gesperrt, bis in den Einstellungen freigegeben. Virtueller Ausgang: Adresse <code>/dev/udp/&lt;LoxBerry-IP&gt;/11884</code>,
Befehl bei EIN <code>publish nut/befehl/&lt;name&gt;/&lt;befehl&gt; 1</code> (ohne HTTP-Header/Body, Trennzeichen leer).</p>
<table>
<tr><th>Topic</th><th>Wirkung</th></tr>
<?php
$befehle = ['beeper_toggle' => 'Piepser umschalten', 'beeper_mute' => 'Piepser stummschalten', 'beeper_enable' => 'Piepser ein', 'beeper_disable' => 'Piepser aus',
    'test_battery_start_quick' => 'Kurzer Batterietest', 'test_battery_start_deep' => 'Tiefer Batterietest', 'test_battery_stop' => 'Batterietest abbrechen',
    'test_panel_start' => 'Anzeigentest starten', 'test_panel_stop' => 'Anzeigentest beenden'];
foreach ($aliase as $a) {
    foreach ($befehle as $k => $t) {
        echo '<tr><td>nut/befehl/' . h($a) . '/' . h($k) . '</td><td>' . h($t) . '</td></tr>';
    }
    echo '<tr><td>nut/befehl/' . h($a) . '/&lt;befehl&gt;/quittung</td><td>angenommen, ausgefuehrt oder abgelehnt: &lt;grund&gt; (nie retained)</td></tr>';
}
?>
</table>
<p class="hinweis">Nicht jede USV unterstützt alle Befehle; das meldet NUT in der Quittung als abgelehnt.</p>

<?php } elseif ($tab === 'test') { ?>
<h3>Test</h3>
<form method="post" action="<?= $basis ?>test" data-ajax="false">
<input type="hidden" name="token" value="<?= h($token) ?>">
<button type="submit" name="aktion" value="abruf" data-ajax="false">Sofort abrufen</button>
<button type="submit" name="aktion" value="selbst" data-ajax="false">Selbstprüfung</button>
<button type="submit" name="aktion" value="neustart" data-ajax="false">Dienst neu starten</button>
</form>
<?php
    $a = (string)($_POST['aktion'] ?? '');
    $servers = NutConfig::servers($nut);
    if ($a === 'abruf') {
        if (!$servers) {
            echo '<p class="fehler">Kein NUT-Server konfiguriert.</p>';
        }
        foreach ($servers as $s) {
            $err = '';
            $o = NutClient::open($s, $err);
            echo '<h4>' . h($s['alias']) . ' (' . h($s['host'] . ':' . $s['port']) . ')</h4>';
            if ($o === null) {
                echo '<p class="fehler">' . h($err) . '</p>';
                continue;
            }
            [$c, $ups] = $o;
            $vars = $c->listVars($ups);
            $verr = $c->error;
            $c->close();
            if ($vars === null) {
                echo '<p class="fehler">' . h($verr) . '</p>';
                continue;
            }
            echo '<table><tr><th>Variable</th><th>Topic</th><th>Wert</th></tr>';
            foreach ($vars as $n => $v) {
                echo '<tr><td>' . h($n) . '</td><td>nut/' . h($s['alias']) . '/' . h(NutClient::varName($n)) . '</td><td>' . h(NutClient::value($v)) . '</td></tr>';
            }
            echo '</table>';
        }
    } elseif ($a === 'selbst') {
        $zeile = function (string $name, bool $ok, string $text): void {
            echo '<tr><td>' . h($name) . '</td><td class="' . ($ok ? 'ok' : 'fehler') . '">' . ($ok ? 'OK' : 'Fehler') . '</td><td>' . h($text) . '</td></tr>';
        };
        echo '<table><tr><th>Prüfung</th><th>Ergebnis</th><th>Details</th></tr>';
        $zeile('Konfiguration', count($servers) > 0, count($servers) . ' NUT-Server konfiguriert');
        foreach ($servers as $s) {
            $err = '';
            $o = NutClient::open($s, $err);
            if ($o === null) {
                $zeile('NUT ' . $s['alias'], false, $err);
                continue;
            }
            [$c, $ups] = $o;
            $vars = $c->listVars($ups);
            $zeile('NUT ' . $s['alias'], !empty($vars), $vars !== null ? 'USV ' . $ups . ', ' . count($vars) . ' Werte' : $c->error);
            $c->close();
            if ($s['benutzer'] !== '') {
                $c2 = new NutClient($s['host'], $s['port']);
                if ($c2->connect()) {
                    $okl = $c2->login($s['benutzer'], $s['passwort']);
                    $zeile('Anmeldung ' . $s['alias'], $okl, $okl ? 'Benutzer ' . $s['benutzer'] . ' angemeldet' : $c2->error);
                    $c2->close();
                }
            }
        }
        $own = NutConfig::ownBroker($nut);
        $d = $own !== null ? ['brokerhost' => $own[0], 'brokerport' => $own[1], 'brokeruser' => $own[2], 'brokerpass' => $own[3]] : (function_exists('mqtt_connectiondetails') ? mqtt_connectiondetails() : []);
        $mq = new MqttClient((string)($d['brokerhost'] ?? 'localhost'), (int)($d['brokerport'] ?? 1883), (string)($d['brokeruser'] ?? ''), (string)($d['brokerpass'] ?? ''), 'nut_test_' . $paths->folder, 'nut/status/test');
        $okm = $mq->connect();
        $zeile('Broker', $okm, $okm ? 'Verbindung möglich' : $mq->lastError());
        $mq->close();
        $zeile('Gateway-Abo', is_file(LBPCONFIGDIR . '/mqtt_subscriptions.cfg'), 'nut/# in mqtt_subscriptions.cfg');
        $zeile('Dienst', $laeuft, $laeuft ? 'läuft, letzte Statusmeldung vor ' . $alter . ' s' : 'keine aktuelle Statusmeldung');
        $sock = @stream_socket_client('udp://127.0.0.1:11884', $en, $es, 2);
        if ($sock) {
            $sent = @fwrite($sock, 'publish nut/status/bruecketest 1');
            fclose($sock);
            $zeile('Befehlsbrücke (UDP 11884)', $sent > 0, 'Testnachricht an nut/status/bruecketest gesendet (UDP liefert keine Antwort)');
        } else {
            $zeile('Befehlsbrücke (UDP 11884)', false, 'Socket nicht möglich: ' . $es);
        }
        echo '</table>';
    }
} elseif ($tab === 'logs') {
    echo '<h3>Logdateien</h3>';
    $dateien = glob(LBPLOGDIR . '/*.log') ?: [];
    if (!$dateien) {
        echo '<p>Noch keine Logdatei vorhanden.</p>';
    }
    foreach ($dateien as $f) {
        $size = (int)filesize($f);
        $text = (string)@file_get_contents($f, false, null, max(0, $size - 20000));   // nur das Ende lesen
        $zeilen = array_slice(explode("\n", $text), -200);
        echo '<h4>' . h(basename($f)) . ' (letzte Zeilen)</h4><pre>' . h(implode("\n", $zeilen)) . '</pre>';
    }
}
?>
</div>
<?php LBWeb::lbfooter(); ?>
