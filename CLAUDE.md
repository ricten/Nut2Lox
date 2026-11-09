# NUT USV – LoxBerry-Plugin

**Version: siehe `nut/bin/lib/AppVersion.php` (muss mit `nut/plugin.cfg` und [CHANGELOG.md](CHANGELOG.md) übereinstimmen; `build.sh` prüft das). Ziel-PHP: 8.4 (CLI + Web).**

Ersetzt den ioBroker-Adapter `nut`. Liest NUT-Server (Port 3493) und veröffentlicht per MQTT; richtet auf Wunsch einen lokalen NUT-Server für eine USB-USV ein.
Übergeordnete Regeln (Topics, Status, Befehle, Versionierung): `../CLAUDE.md` und `../README.md`. Bei Widerspruch gelten die Regeln dieser Datei.

## Layout
- `nut/` = Inhalt des Plugin-ZIP (plugin.cfg, bin/, config/, cron/, daemon/, dpkg/, uninstall/, webfrontend/, *.sh)
- `../common/php/` = gemeinsamer Code (Util, Logger, MqttClient). `build.sh` kopiert ihn nach `bin/lib` des ZIP; nie aus einem anderen Plugin importieren
- `tests/` = `php tests/run.php` (Unit + Ende-zu-Ende mit Fake-NUT/Fake-Broker), `bash tests/test_apply.sh` (Root-Skript im Testmodus) – **nicht** im ZIP

## Harte Regeln
- Keine hartkodierten `/opt/loxberry`-Pfade. PHP: `loxberry_system.php` (`LBPCONFIGDIR` …); Shell/daemon/cron: Platzhalter `REPLACELBPBINDIR` usw.; `nut-apply.sh` leitet seine Pfade aus dem eigenen Ort ab.
- Dateien mit LF-Zeilenenden. `.gitattributes`/`.editorconfig` beachten.
- Plugin-Identität nie ändern (`AUTHOR.NAME` + `AUTHOR.EMAIL` + `PLUGIN.NAME` + `PLUGIN.FOLDER`), sonst entsteht beim Installieren ein zweites Plugin.
- Plugin-Ordner nie fest eintragen (kann `nut_000` heißen): Skripte nehmen ihn aus `$3` bzw. aus ihrem Ort. MQTT-Client-ID enthält den Ordner (`nut_<Ordner>`).
- Weboberfläche und Dienst: `LBWeb::lbheader()` und `mqtt_connectiondetails()` überschreiben die globale Variable `$cfg`. Eigene Konfiguration heißt deshalb `$nut`.
- Laufzeitstatus (`status.json`, PID, Sperre) nur nach `/dev/shm/<Ordner>/`, nicht auf die SD-Karte. Konfiguration atomar schreiben (Temp + rename), Rechte 0600.
- Kein MQTT-Paket als Abhängigkeit: eigener Client (`MqttClient`). Broker-Daten nur aus `mqtt_connectiondetails()`.
- Passwörter (NUT-Benutzer, Broker) nie loggen (Logger maskiert registrierte Secrets), nie per MQTT senden, nie im Klartext in der Oberfläche anzeigen.
- Befehle: ab Werk gesperrt, nur die Positivliste `NutClient::CMD_ALLOWED` (beeper.*, test.battery.*, test.panel.*), Quittung unter `<topic>/quittung`, nie retained. Nie `shutdown.*`, `load.off` o. Ä. zulassen.
- Ohne gültigen Wert wird nichts gesendet. Fällt eine Quelle aus: `verbunden`=0 und `status/ok`=0, `status/zaehler` läuft weiter.
- **Lokaler NUT-Server:** einzige Ausnahme von „keine Änderungen außerhalb des Plugin-Ordners“. Nur über `bin/nut-apply.sh` (root, per sudo; die sudo-Erlaubnis für genau `apply` und `scan` legt `postroot.sh` als `/etc/sudoers.d/nut-plugin-<Ordner>` an, die Deinstallation entfernt sie), nur `/etc/nut/{nut.conf,ups.conf,upsd.conf,upsd.users}`, jeden Wert prüfen, fremde Dateien einmalig sichern (`*.vor-nut-plugin`), Betriebsart `netserver` (kein upsmon – der LoxBerry soll nicht von selbst herunterfahren). Beim Abschalten nur die selbst angelegte Konfiguration anfassen.

## Entwicklung
- PHP lokal nötig für die Tests; fehlt es, das offen sagen und nichts als „getestet“ melden.
- Nicht lokal testbar (nur auf dem LoxBerry): LoxBerry-Bibliotheken (`LBWeb`, `LBLog`, `mqtt_connectiondetails`), Weboberfläche, `sudo -n`, echte systemd-Dienste von NUT.
- Dienst lokal: `NUT_DEV_DIR=<Ordner mit nut.json> NUT_DEV_MQTT=127.0.0.1:18830 php nut/bin/nutd.php` (Fake-Server: `php tests/fake_nut.php 13493`, `php tests/fake_mqtt.php 18830 <Ordner>`).
- Build: `bash build.sh` → `nut-<version>.zip`.
