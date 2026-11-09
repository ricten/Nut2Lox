# Changelog

Alle nennenswerten Änderungen an NUT USV. Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
Versionierung nach [Semantic Versioning](https://semver.org/lang/de/) (`MAJOR.MINOR.PATCH`).

Release-Ablauf: Version in `nut/plugin.cfg` und `nut/bin/lib/AppVersion.php` erhöhen, hier einen Eintrag ergänzen,
`bash build.sh` ausführen (prüft die Übereinstimmung), danach Git-Tag `vX.Y.Z` setzen.

## [Unveröffentlicht]

## [0.4.3] – 2026-11-09

### Geändert
- Beim lokalen NUT-Server gibt es nur noch **eine** Erreichbarkeitsanzeige: ohne Netzfreigabe wird 127.0.0.1 geprüft, mit Freigabe (oder Synology-Modus) die Netzwerkadresse des LoxBerry (0.0.0.0 deckt localhost mit ab). Lauscht der Server trotz Freigabe nur lokal, weist die Anzeige darauf hin.

## [0.4.2] – 2026-11-09

### Behoben
- **NUT-Server lauschte trotz Netzfreigabe nur auf 127.0.0.1 (Synology bekam keine Verbindung):** `nut-apply.sh` schrieb bei Netzfreigabe zwei Zeilen `LISTEN 127.0.0.1` und `LISTEN 0.0.0.0` in `upsd.conf`. Beide kollidieren auf Port 3493; der zweite Bind scheiterte, upsd lief nur lokal. Jetzt steht bei Netzfreigabe nur `LISTEN 0.0.0.0 3493` (deckt localhost mit ab), sonst nur `LISTEN 127.0.0.1 3493`. Das Update wendet die Konfiguration über `postroot.sh` automatisch neu an.

## [0.4.1] – 2026-11-09

### Hinzugefügt
- Beim lokalen NUT-Server zeigt eine zweite Anzeige, ob er auch **aus dem Netz** erreichbar ist (Prüfung über die Adresse, unter der die Seite aufgerufen wurde). Bisher prüfte nur 127.0.0.1; ein Server, der nur lokal lauscht, erschien trotzdem grün, obwohl z. B. eine Synology ihn nicht erreichte.

## [0.4.0] – 2026-11-09

### Hinzugefügt
- **Erreichbarkeitsanzeige** in den Einstellungen: Vor jedem NUT-Server und beim lokalen Server zeigt ein grünes (erreichbar, mit USV-Namen), rotes (nicht erreichbar oder Fehler) oder graues Kästchen (kein Host eingetragen) den Zustand. Die Prüfung läuft beim Öffnen der Seite live, auch bevor der Server aktiviert ist (Zeitgrenze 2 s je Server).

## [0.3.1] – 2026-11-09

### Behoben
- **Spalte „Aktiv“ bei den NUT-Servern war leer:** Die Kontrollkästchen in der Tabelle wurden von der LoxBerry-Oberfläche (jQuery Mobile) ausgeblendet. Ein Server ließ sich dadurch nicht aktivieren und wurde nie abgefragt. Die Kontrollkästchen sind jetzt sichtbar (`data-role="none"`).

## [0.3.0] – 2026-11-09

### Hinzugefügt
- **Alles, was der NUT-Server liefert, geht an MQTT:** zusätzlich zu den Variablen jetzt unter `nut/<name>/info/…` (alle 60 s): `beschreibung`, `server_version`, `protokoll`, `anmeldungen` (NUMLOGINS), `clients` (LIST CLIENT), `befehle` (LIST CMD, kommagetrennt), `beschreibbar` (LIST RW), `enum/<variable>` und `range/<variable>` (erlaubte Werte bzw. Bereiche der schreibbaren Variablen). Nicht unterstützte Abfragen werden übersprungen.
- **Mehrere USVs an einem Server:** ohne festen USV-Namen werden alle USVs des Servers abgefragt. Bei genau einer USV bleibt der Gerätename wie bisher `<name>`, bei mehreren heißt er `<name>_<usv>`. Befehle gehen an das jeweilige Gerät.

## [0.2.1] – 2026-11-09

### Behoben
- Plugin-Icons fehlten (Installer meldete „Icons could not be installed“). `nut/icons/icon_{64,128,256,512}.png` liegen jetzt im ZIP; erzeugt mit `python tools/make_icon.py`.

## [0.2.0] – 2026-11-09

### Hinzugefügt
- **Synology als NUT-Client:** Option „Synology (DSM) als Client“ beim lokalen NUT-Server. DSM erwartet fest die USV `ups` und den Benutzer `monuser` (Passwort `secret`, Rolle slave). Das Plugin benennt die USV dann `ups`, erzwingt die Netzfreigabe und legt `monuser` nur als Client an (`nut-apply.sh` prüft den Namen). Grundlage: bjoerns-techblog.de, „Synology DiskStation als NUT-Client“.

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
