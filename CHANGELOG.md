# Changelog

Alle nennenswerten Änderungen an Nut2Lox. Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
Versionierung nach [Semantic Versioning](https://semver.org/lang/de/) (`MAJOR.MINOR.PATCH`).

Release-Ablauf: Version in `nut2lox/plugin.cfg` und `nut2lox/bin/lib/AppVersion.php` erhöhen, hier einen Eintrag ergänzen,
`bash build.sh` ausführen (prüft die Übereinstimmung), danach Git-Tag `vX.Y.Z` setzen.

## [0.8.1] - 2026-11-11

### Behoben
- Läuft jetzt auch mit PHP 8.2 und 8.3. Bisher war PHP 8.4 nötig: typisierte Klassenkonstanten gibt es erst ab PHP 8.3, ältere PHP-Versionen brachen mit einem Syntaxfehler ab (HTTP 500 in der Weboberfläche, Dienst startete nicht). Keine Funktionsänderung.

## [Unveröffentlicht]

## [0.8.0] – 2026-11-10

### Neu
- **ZIP ohne Version im Namen** (`<name>.zip`) an jedem Release: `…/releases/latest/download/<name>.zip` zeigt immer auf das neueste Release (`bash build.sh` erzeugt beide ZIPs).
- **Automatische Updates:** `plugin.cfg` verweist mit `RELEASECFG` auf die `release.cfg` im GitHub-Repository (`AUTOMATIC_UPDATES=true`: neue Installationen bekommen Release-Updates automatisch, in der Plugin-Verwaltung änderbar). `bash build.sh release-cfg` schreibt die Datei erst nach dem GitHub-Release. Schon installierte Plugins bekommen den Schlüssel erst durch die einmalige Neuinstallation von 0.8.0.
- **`sudoers/sudoers`:** Die sudo-Erlaubnis für genau `nut-apply.sh apply` und `scan` legt jetzt der LoxBerry-Installer an (Vorgabe der Plugin-Entwicklung) statt `postroot.sh`. `build.sh` prüft die Datei streng (der Installer prüft sie nicht, ein Fehler würde sudo im ganzen System sperren), `postroot.sh` prüft sie zusätzlich mit `visudo -cf` und löscht sie bei einem Fehler. Die Datei der Vorgängerversionen (`/etc/sudoers.d/nut-plugin-<Ordner>`) wird entfernt.

### Geändert
- **Weboberfläche im LoxBerry Design System** (`lb-*`-Klassen, `lbheader(…, true)`) statt jQuery Mobile; Farben folgen dem gewählten Theme (auch dunkel). Felder, Funktionen und Topics sind unverändert.
- Doku: Seitenlinks in `docs/wiki/` funktionieren im Repository (mit `.md`), Abschnitt „Automatische Updates“ in `Installation.md` und `README.md`.

## [0.7.4] – 2026-11-10

Abgleich mit dem LoxBerry-Entwicklerwiki und dem LoxBerry-Quellcode.

### Behoben
- **Loglevel in der Plugin-Verwaltung:** Die Auswahl steuert in `plugin.cfg` der Schlüssel `CUSTOM_LOGLEVELS` (der Installer liest `LOGLEVELS_ENABLED` nicht). Er steht jetzt auf `true`, der überflüssige Schlüssel ist entfernt. Bei einer Aktualisierung erscheint die Auswahl, Standard ist Stufe 3.
- **Abo-Datei `mqtt_subscriptions.cfg`:** Das Gateway liest sie zeilenweise (ein Topic je Zeile), nicht als INI-Datei. Sie enthält jetzt nur `nut/#`. Vorher meldete das Gateway V1 die Zeilen `[nut]`, `TOPIC=…` und `NAME=…` als Abos an und `nut/#` selbst nicht.
- **Gateway-Abo wird jetzt wirklich geprüft** (Reiter MQTT und Selbstprüfung). Das Gateway V2 liest die Abo-Datei von Plugins nicht (laut LoxBerry-Quellcode): Dort muss `nut/#` im MQTT Gateway unter „Abonnements“ von Hand eingetragen werden. Die Prüfung liest die Abo-Liste des Gateways und erklärt, was fehlt. Vorher zeigte die Prüfung „hinterlegt“, sobald die Datei existierte.
- **UDP-Port des Gateways** wird aus den MQTT-Verbindungsdaten gelesen (`udpinport`) statt fest 11884 anzunehmen; Oberfläche, Anleitung für Loxone Config und Test der Befehlsbrücke zeigen den echten Port.

### Neu
- **Healthcheck** (`bin/healthcheck`): Die LoxBerry-Healthcheck-Seite zeigt den Zustand des Plugins (Dienst, MQTT-Broker, Quelle). Ein toter Dienst ist ein Fehler und löst die tägliche Benachrichtigung samt E-Mail aus; Ausfälle der Quelle sind eine Warnung; nicht eingerichtet ist eine Info.
- **Menüleiste** der LoxBerry-Oberfläche (`$navbar`) statt eigener Reiter.
- Neuer gemeinsamer Code `GatewayInfo` (Gateway-Version, UDP-Port, Abo-Prüfung) in `common/php`.

### Geändert
- **Gateway-Abo im Reiter MQTT als farbiges Feld:** grün, sobald mindestens ein passendes Abo im Gateway eingetragen ist (auch wenn nur ein Teil der Topics abonniert ist; dann mit Hinweis), rot nur, wenn keines eingetragen ist. Die eingetragenen Abos werden darunter aufgelistet.
- Leeres `preinstall.sh` entfernt (die Doku rät, ungenutzte Installationsskripte nicht mitzuliefern).
- `plugin.cfg`: `WEBSITE` verweist auf das GitHub-Repository (Link in der Plugin-Verwaltung).

## [0.7.3] – 2026-11-09

### Geändert
- Logo: Geräte und Name sitzen jetzt mittig im Kreis (vorher zu weit oben).

## [0.7.2] – 2026-11-09

### Geändert
- Logo: Das linke Symbol ist jetzt eine **Rack-USV** (19 Zoll, zwei Einheiten: USV mit Display, Einschalter und Lüftungsschlitzen, darunter ein Batteriemodul) statt eines Tower-Geräts.

## [0.7.1] – 2026-11-09

### Geändert
- **Neues Logo** im Stil von Senec2Lox: runde grüne Fläche mit dem Namen „Nut2Lox“, links eine USV (Batterie mit Blitz, Steckdosen), rechts der Miniserver, dazwischen ein Pfeil. Erzeugt mit `python tools/make_logo.py` (ersetzt `tools/make_icon.py`).

## [0.7.0] – 2026-11-09

### Geändert
- **Menü neu geordnet:** Die MQTT-Broker-Einstellungen (eigener Broker) liegen jetzt im Reiter **MQTT** (unter dem Status), der Reiter „Einstellungen“ heißt **„Einstellungen USV/NUT“** und enthält Allgemein, NUT-Server im Netz und den lokalen USB-Server.
- Jeder Reiter speichert nur seine eigenen Einstellungen; die der anderen Reiter bleiben unverändert (`NutConfig::fromPost` arbeitet abschnittsweise).

## [0.6.0] – 2026-11-09

### Hinzugefügt
- **Eigener MQTT-Broker:** In den Einstellungen lässt sich statt dem LoxBerry-Broker ein eigener Broker eintragen (Host, Port, Benutzer, Passwort). Standard bleibt der LoxBerry-Broker. Das Passwort wird nie angezeigt oder geloggt; eine Änderung wirkt ohne Neustart (der Dienst verbindet neu). Die Selbstprüfung testet den gewählten Broker.
- **Dokumentation** wie bei Senec2Lox: Wiki unter `docs/wiki/` (Installation, Konfiguration, Lokaler NUT-Server, MQTT-Topics, Loxone-Anbindung, Fehlersuche, Entwicklung), Vorlage für das LoxBerry-Wiki, Forenbeitrag, Logo.

### Geändert
- **Rundes Logo/Icon** (`tools/make_icon.py` erzeugt Plugin-Icons und Logo).

## [0.5.0] – 2026-11-09

### Geändert
- **Plugin umbenannt in Nut2Lox** (Plugin-Name und -Ordner `nut2lox`, Titel „Nut2Lox“, ZIP `nut2lox-<version>.zip`), passend zum Repository und zu Senec2Lox. Die **Topics bleiben unverändert** (`nut/…`), Loxone-Eingänge sind nicht betroffen.
- Für LoxBerry ist das ein **neues Plugin**: Das alte Plugin „nut“ muss deinstalliert werden. Die Einstellungen des alten Plugins (`nut.json`, `lokal.conf`) übernimmt `postinstall.sh` bei der Installation automatisch, sofern das alte Plugin noch installiert ist.

## [0.4.4] – 2026-11-09

### Behoben
- **Loglevel in der Plugin-Verwaltung nicht einstellbar:** In `plugin.cfg` fehlte `LOGLEVELS_ENABLED=1`. Die Auswahl erscheint jetzt; der Dienst liest den Level alle 30 s neu und übernimmt eine Änderung ohne Neustart. Ohne gültigen Wert gilt Info.

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
