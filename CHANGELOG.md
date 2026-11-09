# Changelog

Alle nennenswerten Änderungen an NUT USV. Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
Versionierung nach [Semantic Versioning](https://semver.org/lang/de/) (`MAJOR.MINOR.PATCH`).

Release-Ablauf: Version in `nut/plugin.cfg` und `nut/bin/lib/AppVersion.php` erhöhen, hier einen Eintrag ergänzen,
`bash build.sh` ausführen (prüft die Übereinstimmung), danach Git-Tag `vX.Y.Z` setzen.

## [Unveröffentlicht]

## [0.1.1] – 2026-11-09

### Behoben
- **Lokalen NUT-Server einrichten scheiterte mit „sudo: a password is required“:** Der Benutzer loxberry darf kein sudo ohne Passwort. `postroot.sh` (läuft als root) legt jetzt `/etc/sudoers.d/nut-plugin-<Ordner>` an, die genau `nut-apply.sh apply` und `nut-apply.sh scan` erlaubt (vorher mit `visudo -c` geprüft). Die Deinstallation entfernt die Datei. Die Oberfläche weist bei fehlender Erlaubnis darauf hin.

## [0.1.0] – 2026-11-09

### Hinzugefügt
- Erste Version: Abfrage von bis zu 4 NUT-Servern im Netz, Veröffentlichung per MQTT (`nut/<name>/<variable>`, `zustand/*`, `verbunden`, `status/ok|zaehler|ts`)
- Befehle (Piepser, Batterietest) ab Werk gesperrt, mit Quittung; Abschalten und „Last aus“ nie erlaubt
- Optionaler lokaler NUT-Server für eine USB-USV am LoxBerry: Pakete über `dpkg/apt12`, Konfiguration von `/etc/nut` durch `bin/nut-apply.sh` (Betriebsart netserver, kein upsmon)
- Weboberfläche mit Einstellungen, MQTT, Einbindung in Loxone, Test, Logdateien
- Eigener MQTT-Client (keine Abhängigkeit von phpMQTT), Letzter-Wille-Nachricht auf `nut/status/ok`
- Tests (`php tests/run.php`): Hilfsfunktionen, NUT-Client, MQTT-Client und der komplette Dienst gegen Fake-NUT und Fake-Broker
