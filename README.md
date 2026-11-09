# NUT USV – LoxBerry-Plugin

**Version 0.4.3** · Benötigt LoxBerry 4 (PHP ≥ 8.4) · [Changelog](CHANGELOG.md)

Liest USV-Werte von NUT-Servern (Port 3493) und veröffentlicht sie per MQTT am LoxBerry-Broker (→ MQTT-Gateway → Loxone).
Optional richtet das Plugin auf dem LoxBerry selbst einen NUT-Server für eine USB-USV ein. Ersetzt den ioBroker-Adapter `nut`.

## Installation
1. `bash build.sh` → `nut-<version>.zip`
2. In LoxBerry → Plugin-Verwaltung → ZIP installieren (holt die Pakete `nut-server`, `nut-client`, `usbutils`).
3. Plugin öffnen:
   - USV an einem anderen Gerät: unter „NUT-Server im Netz“ Host eintragen, aktivieren, speichern.
   - USV per USB am LoxBerry: „Lokalen NUT-Server einrichten“ aktivieren, ggf. mit „USB-Geräte suchen“ die IDs ermitteln, speichern.
4. Reiter „Test“ → „Selbstprüfung“. Werte erscheinen unter `nut/<name>/*`. Die Topic-Tabelle steht im Reiter „Einbindung in Loxone“.

## Topics (Auszug)
`nut/<name>/battery_charge`, `…/battery_runtime`, `…/ups_status`, `…/zustand/online|batterie|batterie_schwach|…` (0/1), `…/verbunden`, `nut/status/ok|zaehler|ts`.
Befehle (gesperrt bis zur Freigabe): `nut/befehl/<name>/beeper_toggle` = 1, Quittung unter `…/quittung`.

## Lokaler NUT-Server
`bin/nut-apply.sh` (root, per sudo – die Erlaubnis legt die Installation unter `/etc/sudoers.d/` an) schreibt `/etc/nut/{nut.conf,ups.conf,upsd.conf,upsd.users}`. Betriebsart `netserver` ohne upsmon: der LoxBerry fährt bei leerer Batterie **nicht** selbst herunter.
Fremde Dateien werden einmalig als `*.vor-nut-plugin` gesichert. Deinstallation lässt `/etc/nut` und die Pakete bestehen.

### Synology als Client
Option „Synology (DSM) als Client“: DSM erwartet fest die USV `ups` und den Benutzer `monuser` / `secret` (Rolle slave, in DSM nicht änderbar).
Das Plugin benennt die USV dann `ups`, gibt den Server im Netz frei und legt `monuser` nur als Client an. In DSM: Systemsteuerung → Hardware & Energie → USV → „Synology USV-Server“ → IP des LoxBerry.
Quelle: https://www.bjoerns-techblog.de/2024/04/synology-diskstation-als-nut-client/

## Entwicklung
- Tests: `php tests/run.php` und `bash tests/test_apply.sh`
- Details und Regeln: [CLAUDE.md](CLAUDE.md)

## Noch nicht auf einem LoxBerry geprüft
Weboberfläche, LoxBerry-Logging, Format von `config/mqtt_subscriptions.cfg`, Dienstnamen von NUT unter Debian 12 (`nut-driver@…`), `sudo -n` für den Benutzer loxberry, Paketlisten `dpkg/apt12`.
