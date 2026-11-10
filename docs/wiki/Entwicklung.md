# Entwicklung

## Aufbau

```
nut2lox/                    Inhalt des Plugin-ZIP
├── plugin.cfg
├── postinstall.sh preupgrade.sh postupgrade.sh postroot.sh
├── sudoers/sudoers          (sudo-Erlaubnis für nut-apply.sh apply|scan)
├── uninstall/uninstall  daemon/daemon  cron/cron.01min
├── dpkg/apt12 apt          Pakete (nut-server, nut-client, usbutils)
├── bin/
│   ├── nutd.php            Dienst (Abfrage, MQTT, Befehle)
│   ├── healthcheck         LoxBerry-Healthcheck (Dienst, Broker, Quelle)
│   ├── start.sh stop.sh
│   ├── nut-apply.sh        lokaler NUT-Server (root, per sudo)
│   └── lib/                 NutClient, NutConfig, bootstrap, AppVersion
│                            + Util, Logger, MqttClient (aus ../common/php, kopiert build.sh)
├── config/mqtt_subscriptions.cfg
├── webfrontend/htmlauth/index.php
└── icons/
tests/                       Unit-/Ende-zu-Ende-Tests und Fake-Server (nicht im ZIP)
tools/make_logo.py           Logo und Plugin-Icons (Stil wie Senec2Lox)
docs/                        Wiki, Logo, Vorlagen (nicht im ZIP)
```

Der gemeinsame Code liegt außerhalb des Repos in `loxberry-plugins/common/php/`. `build.sh` kopiert ihn ins ZIP; kein Plugin importiert aus einem anderen.

## Tests

```bash
php tests/run.php        # Unit + Ende-zu-Ende (Dienst gegen Fake-NUT und Fake-Broker)
bash tests/test_apply.sh # nut-apply.sh im Testmodus (schreibt nur in ein Temp-Verzeichnis)
```

`tests/run.php` prüft zusätzlich den **Healthcheck** (`bin/healthcheck`, alle Zustände) und rendert **jeden Reiter der Weboberfläche** mit Attrappen der LoxBerry-Bibliotheken (`tests/stubs/`, `tests/ui_page.php`): Das findet Laufzeitfehler, prüft Menüleiste, Gateway-Abo und UDP-Port. Das echte Aussehen in LoxBerry (Design, jQuery Mobile) lässt sich so nicht prüfen. Der gemeinsame Code `GatewayInfo` hat eigene Tests: `php ../common/tests/run.php`.

`tests/run.php` startet `tests/fake_nut.php` und `tests/fake_mqtt.php` selbst. Geprüft werden NUT-Client (Variablen, Zusatzinfos, Befehle, Anmeldung), MQTT-Client (Abos, Letzter Wille), Konfiguration, Logger-Maskierung und der komplette Dienst: Werte, Zustände, Lebenszeichen, Ausfall, Befehle samt Quittungen, Sperre, mehrere USVs, eigener Broker.

**Nicht lokal testbar** (nur auf dem LoxBerry): LoxBerry-Bibliotheken (`LBWeb`, `LBLog`, `mqtt_connectiondetails`), die Weboberfläche, `sudo`, echte systemd-Dienste von NUT, Verhalten mit echter USV oder DSM.

## Dienst lokal starten

```bash
php tests/fake_nut.php 13493 &
php tests/fake_mqtt.php 18830 /tmp/nuttest &
mkdir -p /tmp/nuttest   # nut.json mit eigenem Broker 127.0.0.1:18830 und Server 127.0.0.1:13493 ablegen
NUT_DEV_DIR=/tmp/nuttest php nut2lox/bin/nutd.php
```

Umgebungsvariablen im Entwicklungsmodus: `NUT_DEV_DIR` (Basisverzeichnis für Konfiguration, Log, Status), `NUT_DEV_FOLDER`, `NUT_DEV_RUN`, `NUT_DEV_MQTT=host:port`, `NUT_DEV_LOGLEVEL`.

## Build und Release

1. Version in `nut2lox/plugin.cfg` **und** `nut2lox/bin/lib/AppVersion.php` erhöhen (Bugfix → PATCH, Funktion → MINOR, inkompatible Topics → MAJOR).
2. Eintrag in `CHANGELOG.md` (`## [x.y.z] – Datum`).
3. `bash build.sh` prüft Versionen und Changelog, kopiert den gemeinsamen Code, prüft PHP-/Shell-Syntax und erzeugt `nut2lox-<version>.zip` (alte ZIPs werden gelöscht).
4. Tag `vX.Y.Z` setzen, pushen, GitHub-Release mit dem ZIP anlegen.

## Wichtige Regeln

Siehe [CLAUDE.md](../../CLAUDE.md): keine festen Pfade (`REPLACELBPBINDIR` …), Plugin-Identität nie ändern, eigene Konfiguration nie `$cfg` nennen (LoxBerry überschreibt sie), Laufzeitstatus nur in `/dev/shm`, Zugangsdaten nie loggen, Befehle nur per Positivliste.
