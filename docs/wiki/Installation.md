# Installation

## ZIP bauen

```bash
bash build.sh
```

Ergebnis: `nut2lox-<version>.zip` (enthält nur den Inhalt von `nut2lox/` plus die gemeinsamen Klassen, keine Tests und keine Doku). Fertige ZIPs gibt es unter [Releases](https://github.com/ricten/Nut2Lox/releases).

## Auf dem LoxBerry installieren

1. LoxBerry-Oberfläche → **Plugin-Verwaltung** → **Plugin installieren**.
2. ZIP hochladen und installieren. Die Installation holt die Pakete `nut-server`, `nut-client` und `usbutils`, richtet die sudo-Erlaubnis ein und startet den Dienst.
3. Plugin **Nut2Lox** öffnen.
4. NUT-Server eintragen (siehe [Konfiguration](Konfiguration.md)), **Aktiv** anhaken, **Speichern**.
5. Nach spätestens einem Abfrageintervall (Standard 10 s) erscheinen Werte unter `nut/<name>/*` (im LoxBerry-MQTT-Finder prüfbar).

## Was die Installation einrichtet

| Teil | Ort (von LoxBerry gesetzt) |
|---|---|
| Dienst und Bibliotheken | `bin/plugins/nut2lox/` |
| Konfiguration | `config/plugins/nut2lox/nut.json` (Rechte 0600) |
| Konfiguration des lokalen NUT-Servers | `config/plugins/nut2lox/lokal.conf` (Rechte 0600) |
| Logdatei | `log/plugins/nut2lox/nut.log` |
| Live-Status (RAM) | `/dev/shm/nut2lox/status.json` |
| Autostart | Daemon beim Boot (root → startet als `loxberry`) |
| Watchdog | Cronjob jede Minute startet den Dienst, falls er nicht läuft |
| sudo-Erlaubnis | `/etc/sudoers.d/nut-plugin-nut2lox` (nur `nut-apply.sh apply` und `nut-apply.sh scan`) |
| Pakete | `nut-server`, `nut-client`, `usbutils` |

Die sudo-Erlaubnis und `/etc/nut` sind die einzigen Stellen außerhalb des Plugin-Ordners. Wozu, steht unter [Lokaler NUT-Server](Lokaler-NUT-Server.md).

## Update

Beim Update sichern `preupgrade.sh`/`postupgrade.sh` **Konfiguration und Daten** und spielen sie zurück. Neue Konfigurationsschlüssel ergänzt der Dienst beim Laden automatisch mit Standardwerten. Die Installation läuft auch beim Update als root und schreibt die Konfiguration des lokalen NUT-Servers neu.

## Umstieg vom früheren Plugin „nut“

Bis Version 0.4.4 hieß das Plugin `nut`. Seit 0.5.0 heißt es `nut2lox`; für LoxBerry ist das ein **neues** Plugin.
Die Topics (`nut/…`) bleiben gleich, Loxone-Eingänge sind nicht betroffen.

1. `nut2lox` **zuerst** installieren: `postinstall.sh` übernimmt `nut.json` und `lokal.conf` vom alten Plugin.
2. Prüfen, dass Werte ankommen (Reiter Test).
3. Das alte Plugin `nut` deinstallieren. Der lokale NUT-Server (`/etc/nut`) und die Pakete bleiben bestehen.

## Deinstallation

Plugin in der Plugin-Verwaltung entfernen. Der Dienst wird beendet, `/dev/shm/nut2lox` und die sudo-Erlaubnis werden gelöscht.
`/etc/nut` und die NUT-Pakete bleiben bestehen, sie gehören dem System. Wer den lokalen NUT-Server nicht mehr braucht, schaltet ihn vorher in den Einstellungen ab.
