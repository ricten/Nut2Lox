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

$tabs = ['einstellungen' => 'Einstellungen USV/NUT', 'mqtt' => 'MQTT', 'loxone' => 'Einbindung in Loxone', 'test' => 'Test', 'logs' => 'Logdateien'];
// Menüleiste der LoxBerry-Oberfläche: $navbar muss vor lbheader() definiert sein
$navbar = [];
$nr = 10;
foreach ($tabs as $k => $t) {
    $navbar[$nr] = ['Name' => $t, 'URL' => $basis . $k] + ($tab === $k ? ['active' => true] : []);
    $nr += 10;
}
// UDP-Port des Gateways für Befehle aus Loxone (einstellbar, steht in den MQTT-Verbindungsdaten)
$udp = GatewayInfo::udpPort(function_exists('mqtt_connectiondetails') ? mqtt_connectiondetails() : null, GatewayInfo::general());

// true als 4. Parameter: ohne jQuery Mobile, die Seite nutzt das LoxBerry Design System (lb-*-Klassen)
LBWeb::lbheader('Nut2Lox ' . AppVersion::CURRENT, '', '', true);
?>
<style>
 .nut pre { background:var(--lb-gray-100,#f4f4f4); color:var(--lb-text,#222); padding:8px; overflow:auto; max-height:420px; border-radius:var(--lb-radius-sm,3px); }
 .nut .ok { color:var(--lb-success,#2a8a2a); font-weight:bold; } .nut .fehler { color:var(--lb-danger,#c0392b); font-weight:bold; }
 .nut .hinweis { color:var(--lb-text-muted,#666); font-size:90%; }
 .nut .lb-table td, .nut .lb-table th { vertical-align:top; }
 .nut .lb-table input.lb-input, .nut .lb-table select.lb-select { min-width:70px; }
 .nut-led { display:inline-block; width:18px; height:18px; border-radius:3px; vertical-align:middle; border:1px solid rgba(0,0,0,.35); }
 .nut-led-ok { background:var(--lb-success,#2ecc40); } .nut-led-fehler { background:var(--lb-danger,#e74c3c); } .nut-led-aus { background:var(--lb-gray-400,#bbb); }
 .nut-meldung-ok { border-left-color:var(--lb-success,#2a8a2a); } .nut-meldung-fehler { border-left-color:var(--lb-danger,#c0392b); }
</style>
<div class="lb-content nut">
<?php foreach ($meldungen as $m) { echo '<div class="lb-callout ' . (str_starts_with($m, 'FEHLER') ? 'nut-meldung-fehler' : 'nut-meldung-ok') . '"><span class="' . (str_starts_with($m, 'FEHLER') ? 'fehler' : 'ok') . '">' . h($m) . '</span></div>'; } ?>
<?php if ($ausgabe !== '') { echo '<pre>' . h($ausgabe) . '</pre>'; } ?>

<?php if ($tab === 'einstellungen') { ?>
<form method="post" action="<?= $basis ?>einstellungen">
<input type="hidden" name="token" value="<?= h($token) ?>">

<h3>Allgemein</h3>
<div class="lb-form-row">
 <label class="lb-form-label" for="intervall">Abfrageintervall (Sekunden, 2–300)</label>
 <div class="lb-form-field"><input class="lb-input" type="number" id="intervall" name="intervall" min="2" max="300" value="<?= (int)$nut['intervall'] ?>" style="max-width:120px"></div>
</div>
<div class="lb-form-row">
 <label class="lb-form-label">Befehle aus Loxone freigeben (Piepser, Batterietest)</label>
 <div class="lb-form-field"><label class="lb-toggle"><input type="checkbox" name="befehle_frei" value="1" <?= $nut['befehle_frei'] ? 'checked' : '' ?>><span class="lb-toggle-slider"></span></label></div>
</div>
<div class="lb-form-help">Ab Werk gesperrt. Erlaubt sind nur beeper.* und test.battery.* / test.panel.* – nie Abschalten oder Last aus.</div>

<h3>NUT-Server im Netz</h3>
<div class="lb-table-scroll"><table class="lb-table">
<tr><th>Status</th><th>Aktiv</th><th>Name im Topic</th><th>Host/IP</th><th>Port</th><th>USV-Name</th><th>Benutzer</th><th>Passwort</th></tr>
<?php foreach ($nut['server'] as $i => $s) { ?>
<tr>
 <td><?php [$led, $tip] = $s['host'] === '' ? ['aus', 'kein Host eingetragen'] : NutClient::check($s['host'], (int)$s['port'] ?: 3493); ?>
 <span class="nut-led nut-led-<?= $led ?>" title="<?= h($tip) ?>"></span> <span class="hinweis"><?= h($tip) ?></span></td>
 <td><label class="lb-toggle"><input type="checkbox" name="srv[<?= $i ?>][aktiv]" value="1" <?= !empty($s['aktiv']) ? 'checked' : '' ?>><span class="lb-toggle-slider"></span></label></td>
 <td><input class="lb-input" type="text" name="srv[<?= $i ?>][alias]" value="<?= h($s['alias']) ?>" size="10"></td>
 <td><input class="lb-input" type="text" name="srv[<?= $i ?>][host]" value="<?= h($s['host']) ?>" size="16"></td>
 <td><input class="lb-input" type="number" name="srv[<?= $i ?>][port]" value="<?= (int)$s['port'] ?>" style="width:90px"></td>
 <td><input class="lb-input" type="text" name="srv[<?= $i ?>][ups]" value="<?= h($s['ups']) ?>" size="10" placeholder="automatisch"></td>
 <td><input class="lb-input" type="text" name="srv[<?= $i ?>][benutzer]" value="<?= h($s['benutzer']) ?>" size="10"></td>
 <td><input class="lb-input" type="password" name="srv[<?= $i ?>][passwort]" value="" size="10" autocomplete="new-password" placeholder="<?= $s['passwort'] !== '' ? '(gesetzt)' : '' ?>"></td>
</tr>
<?php } ?>
</table></div>
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
<div class="lb-form-row">
 <label class="lb-form-label">Lokalen NUT-Server auf dem LoxBerry einrichten und abfragen</label>
 <div class="lb-form-field"><label class="lb-toggle"><input type="checkbox" name="lokal[aktiv]" value="1" <?= !empty($l['aktiv']) ? 'checked' : '' ?>><span class="lb-toggle-slider"></span></label></div>
</div>
<div class="lb-table-scroll"><table class="lb-table">
<tr><th>Name der USV</th><td><input class="lb-input" type="text" name="lokal[name]" value="<?= h($l['name']) ?>"></td></tr>
<tr><th>Treiber</th><td><select class="lb-select" name="lokal[treiber]">
<?php foreach (NutConfig::TREIBER as $t) { echo '<option value="' . h($t) . '"' . ($l['treiber'] === $t ? ' selected' : '') . '>' . h($t) . '</option>'; } ?>
</select></td></tr>
<tr><th>USB Vendor-ID / Product-ID</th><td>
<input class="lb-input" type="text" name="lokal[vendorid]" value="<?= h($l['vendorid']) ?>" size="5" maxlength="4" placeholder="optional" style="width:90px;display:inline-block">
 / <input class="lb-input" type="text" name="lokal[productid]" value="<?= h($l['productid']) ?>" size="5" maxlength="4" placeholder="optional" style="width:90px;display:inline-block"></td></tr>
<tr><th>Synology (DSM) als Client</th><td><label class="lb-toggle"><input type="checkbox" name="lokal[synology]" value="1" <?= !empty($l['synology']) ? 'checked' : '' ?>><span class="lb-toggle-slider"></span></label>
 Synology DiskStation soll diese USV als „Synology USV-Server“ nutzen<br>
<span class="hinweis">DSM erwartet fest die USV <b>ups</b> und den Benutzer <b>monuser</b> mit Passwort <b>secret</b> (nicht änderbar). Das Plugin benennt die USV dann „ups“, gibt den Server im Netz frei und legt monuser nur als Client (slave) an.
In DSM: Systemsteuerung → Hardware &amp; Energie → USV → USV-Unterstützung aktivieren, „Synology USV-Server“, IP des LoxBerry. Das feste Passwort ist bekannt – Port 3493 nur im Heimnetz erreichbar lassen.</span></td></tr>
<tr><th>Im Netz freigeben</th><td><label class="lb-toggle"><input type="checkbox" name="lokal[netz]" value="1" <?= !empty($l['netz']) ? 'checked' : '' ?>><span class="lb-toggle-slider"></span></label>
 NUT-Server auch für andere Geräte erreichbar (Port 3493)</td></tr>
</table></div>
<p class="hinweis">Das Plugin schreibt dafür /etc/nut/{nut.conf,ups.conf,upsd.conf,upsd.users} (Betriebsart netserver, ohne upsmon – der LoxBerry fährt dadurch nicht selbst herunter).
Bestehende Dateien werden einmalig als *.vor-nut-plugin gesichert. Ist die Option aus, wird nur die vom Plugin angelegte Konfiguration abgeschaltet.</p>

<p><button class="lb-btn lb-btn-primary" type="submit" name="aktion" value="speichern">Speichern</button>
<button class="lb-btn" type="submit" name="aktion" value="anwenden">Lokalen Server neu einrichten</button>
<button class="lb-btn" type="submit" name="aktion" value="scan">USB-Geräte suchen</button></p>
</form>

<?php } elseif ($tab === 'mqtt') {
    $own = NutConfig::ownBroker($nut);
    $d = $own !== null ? ['brokerhost' => $own[0], 'brokerport' => $own[1]] : (function_exists('mqtt_connectiondetails') ? mqtt_connectiondetails() : []);
    $gw = GatewayInfo::subscription('nut/#', GatewayInfo::general());
    ?>
<h3>MQTT</h3>
<div class="lb-table-scroll"><table class="lb-table">
<tr><th><?= $own !== null ? 'Broker (eigener, aus den Plugin-Einstellungen)' : 'Broker (aus der LoxBerry-Konfiguration)' ?></th><td><?= h(($d['brokerhost'] ?? '?') . ':' . ($d['brokerport'] ?? '?')) ?></td></tr>
<tr><th>Gateway-Abo</th><td><?= GatewayInfo::feldHtml($gw, 'nut/#') ?></td></tr>
<tr><th>Dienst</th><td><?= $laeuft ? '<span class="ok">läuft</span> (Version ' . h($status['version'] ?? '?') . ')' : '<span class="fehler">läuft nicht</span> (der Wächter startet ihn spätestens nach einer Minute)' ?></td></tr>
<tr><th>Broker-Verbindung des Dienstes</th><td><?= !empty($status['mqtt']) ? '<span class="ok">verbunden</span>' : '<span class="fehler">getrennt</span> ' . h($status['mqtt_error'] ?? '') ?></td></tr>
<tr><th>status/ok</th><td><?= isset($status['ok']) ? (int)$status['ok'] : '-' ?></td></tr>
<tr><th>status/zaehler</th><td><?= isset($status['zaehler']) ? (int)$status['zaehler'] : '-' ?></td></tr>
<tr><th>status/ts</th><td><?= !empty($status['ts']) ? h(date('d.m.Y H:i:s', (int)$status['ts'])) : '-' ?></td></tr>
</table></div>
<?php foreach (($status['server'] ?? []) as $a => $r) {
    echo '<p>' . h($a) . ': ' . (!empty($r['ok']) ? '<span class="ok">erreichbar</span>' : '<span class="fehler">' . h($r['error'] ?? 'Fehler') . '</span>') . '</p>';
} ?>
<form method="post" action="<?= $basis ?>mqtt">
<input type="hidden" name="token" value="<?= h($token) ?>">
<h3>MQTT-Broker</h3>
<?php $mq = $nut['mqtt']; ?>
<div class="lb-form-row">
 <label class="lb-form-label">Eigenen MQTT-Broker verwenden (statt dem Broker des LoxBerry)</label>
 <div class="lb-form-field"><label class="lb-toggle"><input type="checkbox" name="mqtt[eigener_broker]" value="1" <?= !empty($mq['eigener_broker']) ? 'checked' : '' ?>><span class="lb-toggle-slider"></span></label></div>
</div>
<div class="lb-table-scroll"><table class="lb-table">
<tr><th>Host/IP</th><td><input class="lb-input" type="text" name="mqtt[host]" value="<?= h($mq['host']) ?>" size="24"></td>
<th>Port</th><td><input class="lb-input" type="number" name="mqtt[port]" value="<?= (int)$mq['port'] ?>" style="width:100px"></td></tr>
<tr><th>Benutzer</th><td><input class="lb-input" type="text" name="mqtt[benutzer]" value="<?= h($mq['benutzer']) ?>" size="16" autocomplete="off"></td>
<th>Passwort</th><td><input class="lb-input" type="password" name="mqtt[passwort]" value="" size="16" autocomplete="new-password" placeholder="<?= $mq['passwort'] !== '' ? '(gesetzt)' : '' ?>"></td></tr>
</table></div>
<p class="hinweis">Ohne Haken verwendet das Plugin den MQTT-Broker aus der LoxBerry-Konfiguration (empfohlen, Zugangsdaten verwaltet LoxBerry). Mit eigenem Broker erreichen die Werte den Miniserver nur, wenn das MQTT-Gateway diesen Broker abonniert. Ein leeres Passwortfeld behält das gespeicherte Passwort.</p>
<p><button class="lb-btn lb-btn-primary" type="submit" name="aktion" value="speichern">Speichern</button></p>
</form>

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
<div class="lb-table-scroll"><table class="lb-table">
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
</table></div>
<p class="hinweis">Beim Absturz des Dienstes setzt der Broker nut/status/ok per Letzter-Wille-Nachricht auf 0 (retained).</p>

<h3>Befehle aus Loxone</h3>
<p>Gesperrt, bis in den Einstellungen freigegeben. Virtueller Ausgang: Adresse <code>/dev/udp/&lt;LoxBerry-IP&gt;/<?= (int)$udp ?></code>,
Befehl bei EIN <code>publish nut/befehl/&lt;name&gt;/&lt;befehl&gt; 1</code> (ohne HTTP-Header/Body, Trennzeichen leer).</p>
<div class="lb-table-scroll"><table class="lb-table">
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
</table></div>
<p class="hinweis">Nicht jede USV unterstützt alle Befehle; das meldet NUT in der Quittung als abgelehnt.</p>

<?php } elseif ($tab === 'test') { ?>
<h3>Test</h3>
<form method="post" action="<?= $basis ?>test">
<input type="hidden" name="token" value="<?= h($token) ?>">
<button class="lb-btn lb-btn-primary" type="submit" name="aktion" value="abruf">Sofort abrufen</button>
<button class="lb-btn" type="submit" name="aktion" value="selbst">Selbstprüfung</button>
<button class="lb-btn lb-btn-danger" type="submit" name="aktion" value="neustart">Dienst neu starten</button>
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
            echo '<div class="lb-table-scroll"><table class="lb-table"><tr><th>Variable</th><th>Topic</th><th>Wert</th></tr>';
            foreach ($vars as $n => $v) {
                echo '<tr><td>' . h($n) . '</td><td>nut/' . h($s['alias']) . '/' . h(NutClient::varName($n)) . '</td><td>' . h(NutClient::value($v)) . '</td></tr>';
            }
            echo '</table></div>';
        }
    } elseif ($a === 'selbst') {
        $zeile = function (string $name, bool $ok, string $text): void {
            echo '<tr><td>' . h($name) . '</td><td class="' . ($ok ? 'ok' : 'fehler') . '">' . ($ok ? 'OK' : 'Fehler') . '</td><td>' . h($text) . '</td></tr>';
        };
        echo '<div class="lb-table-scroll"><table class="lb-table"><tr><th>Prüfung</th><th>Ergebnis</th><th>Details</th></tr>';
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
        $gw = GatewayInfo::subscription('nut/#', GatewayInfo::general());
        $zeile('Gateway-Abo', $gw['state'] === 'ok', $gw['text']);
        $zeile('Dienst', $laeuft, $laeuft ? 'läuft, letzte Statusmeldung vor ' . $alter . ' s' : 'keine aktuelle Statusmeldung');
        $sock = @stream_socket_client('udp://127.0.0.1:' . $udp, $en, $es, 2);
        if ($sock) {
            $sent = @fwrite($sock, 'publish nut/status/bruecketest 1');
            fclose($sock);
            $zeile('Befehlsbrücke (UDP ' . $udp . ')', $sent > 0, 'Testnachricht an nut/status/bruecketest gesendet (UDP liefert keine Antwort)');
        } else {
            $zeile('Befehlsbrücke (UDP ' . $udp . ')', false, 'Socket nicht möglich: ' . $es);
        }
        echo '</table></div>';
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
