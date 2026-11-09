# Fehlersuche

Zuerst: Plugin öffnen → **Test → Selbstprüfung** (Konfiguration, NUT-Server, Anmeldung, Broker, Gateway-Abo, Dienst, Befehlsbrücke) und **Logdateien**. Das vollständige Log liegt im LoxBerry-Log-Manager (`nut.log`). Für mehr Details den **Loglevel** in der Plugin-Verwaltung auf Debug stellen (wirkt innerhalb von 30 s).

| Symptom | Ursache / Lösung |
|---|---|
| „Kein NUT-Server konfiguriert“, `status/ok` = 0 | Server eintragen **und Aktiv anhaken**, speichern. Nur aktive Server werden abgefragt |
| Kästchen vor dem Server rot, „nicht erreichbar“ | IP/Port falsch, NUT-Server aus, Firewall. Von einem anderen Rechner `upsc <usv>@<ip>` bzw. Port 3493 testen |
| Kästchen rot, Text „NUT: …“ | Der Server antwortet mit einem Fehler, z. B. `UNKNOWN-UPS` (USV-Name falsch) oder `ACCESS-DENIED` |
| Sofort abrufen zeigt Werte, aber nichts in MQTT | Server nicht auf **Aktiv**, oder Dienst läuft nicht (Reiter MQTT) |
| Dienst „läuft nicht“ | Der Watchdog startet ihn jede Minute. Bleibt er aus: `php -v` (≥ 8.4), `start-error.log` im Log-Ordner ansehen |
| Broker-Verbindung „getrennt“ | MQTT-Broker auf dem LoxBerry aktiv? Mit eigenem Broker Host/Port/Zugang prüfen (Selbstprüfung → Broker) |
| Keine Werte in Loxone | MQTT-Gateway-Abonnement `nut/#` prüfen, Eingangsnamen (`nut_<name>_…`), bei eigenem Broker: abonniert das Gateway ihn? |
| Aktiv-Spalte leer (Kästchen fehlen) | Bis 0.3.0 blendete die Oberfläche sie aus; ab 0.3.1 sichtbar |
| „sudo: a password is required“ beim Einrichten des lokalen Servers | Die sudo-Erlaubnis fehlt. Sie legt die Installation unter `/etc/sudoers.d/nut-plugin-nut2lox` an: Plugin neu installieren bzw. aktualisieren, dann `ls -l /etc/sudoers.d/nut-plugin-*` prüfen |
| Lokaler Server: Kästchen bleibt rot | Treiber braucht einige Sekunden; `sudo journalctl -u nut-server -u 'nut-driver@*' --no-pager \| tail -30`; USV mit **USB-Geräte suchen** prüfen, Treiber/IDs setzen |
| Lokaler Server nur lokal erreichbar (Hinweis in der Anzeige) | **Lokalen Server neu einrichten**. Prüfen: `ss -ltnp \| grep 3493` zeigt bei Freigabe `0.0.0.0:3493`, sonst `127.0.0.1:3493` |
| Synology: „Verbindung zum Netzwerk-USV-Server nicht möglich“ | Netzfreigabe aktiv? (Synology-Haken setzt sie.) `ss -ltnp \| grep 3493` muss `0.0.0.0` zeigen; sonst „Lokalen Server neu einrichten“ |
| Synology: „Keine USV angeschlossen“ | In DSM *USV-Unterstützung aktivieren* nicht angehakt, oder USV heißt nicht `ups` |
| Befehl: `abgelehnt: Befehle sind gesperrt` | Freigabe in den Einstellungen fehlt |
| Befehl: `abgelehnt: NUT: ACCESS-DENIED` / „Anmeldung fehlgeschlagen“ | Benutzer/Passwort beim Server prüfen (Selbstprüfung → Anmeldung) |
| Befehl: `abgelehnt: NUT: CMD-NOT-SUPPORTED` | Die USV kennt den Befehl nicht (siehe `info/befehle`) |
| Loglevel nicht einstellbar | Ab 0.4.4 in der Plugin-Verwaltung verfügbar |
| Gerät heißt `<name>_<usv>` statt `<name>` | Der Server hat mehrere USVs und der USV-Name ist leer. USV-Namen eintragen, wenn nur eine gemeint ist |

## MQTT direkt prüfen

```bash
mosquitto_sub -h localhost -v -t 'nut/#'
```

## NUT direkt prüfen

```bash
upsc ups@localhost          # lokaler Server (Name = Name der USV)
upsc usv@192.168.1.220      # Server im Netz
```

Der Selbsttest und die Ausgabe von **Sofort abrufen** zeigen dasselbe aus Sicht des Plugins.
