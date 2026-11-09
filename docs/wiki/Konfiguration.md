# Konfiguration

Alle Einstellungen stehen in der Plugin-Oberfläche. Änderungen wirken **ohne Neustart** (der Dienst lädt die Konfiguration neu, sobald sich die Datei ändert).
Reiter: **Einstellungen USV/NUT · MQTT · Einbindung in Loxone · Test · Logdateien**. Jeder Reiter speichert nur seine eigenen Einstellungen; die der anderen Reiter bleiben unverändert.

## Einstellungen USV/NUT

### Allgemein

| Einstellung | Standard | Beschreibung |
|---|---|---|
| Abfrageintervall | 10 s | Wie oft die NUT-Server abgefragt werden (2–300 s). Die `info/*`-Topics werden unabhängig davon alle 60 s aktualisiert |
| Befehle aus Loxone freigeben | aus | Erlaubt Piepser und Batterietest per MQTT. Ab Werk **gesperrt**; nie erlaubt sind Abschalten und „Last aus“ |

### NUT-Server im Netz

Bis zu vier Server. Vor jeder Zeile zeigt ein Kästchen, ob der Server erreichbar ist (grün: erreichbar mit USV-Name, rot: nicht erreichbar oder Fehler, grau: kein Host). Die Prüfung läuft beim Öffnen der Seite, auch vor dem Aktivieren.

| Spalte | Beschreibung |
|---|---|
| Aktiv | Nur aktive Server werden abgefragt und veröffentlicht |
| Name im Topic | Gerätename in den Topics (`nut/<name>/…`); erlaubt `a–z`, `0–9`, `_`, `-`. Leer = USV-Name |
| Host/IP, Port | Adresse des NUT-Servers (Standardport 3493) |
| USV-Name | Name der USV am Server. **Leer** = alle USVs des Servers abfragen |
| Benutzer, Passwort | Nur für Befehle nötig, zum Lesen der Werte nicht. Ein leeres Passwortfeld behält das gespeicherte Passwort |

Hat ein Server ohne festen USV-Namen **mehrere** USVs, heißen die Geräte `<name>_<usv>`; bei genau einer USV nur `<name>`.

### USV per USB am LoxBerry

Siehe [Lokaler NUT-Server](Lokaler-NUT-Server).

## MQTT

Oben der Status: verwendeter Broker (LoxBerry oder eigener), Gateway-Abo, Dienst- und Verbindungsstatus, Zustand jedes NUT-Servers sowie `status/ok`, `status/zaehler` und `status/ts`. Darunter die Broker-Einstellungen.

### MQTT-Broker (eigener Broker)

Standard: Das Plugin nutzt den **MQTT-Broker des LoxBerry**; Host, Port und Zugangsdaten kommen aus der LoxBerry-Konfiguration (MQTT-Gateway). Das Plugin hat dann keine eigenen Zugangsdaten.

Mit **Eigenen MQTT-Broker verwenden** lässt sich stattdessen ein anderer Broker eintragen:

| Einstellung | Standard | Beschreibung |
|---|---|---|
| Host/IP | leer | Adresse des Brokers. Bei leerem Host gilt weiter der LoxBerry-Broker |
| Port | 1883 | Unverschlüsselt; TLS wird nicht unterstützt |
| Benutzer, Passwort | leer | Optional. Das Passwort wird nie angezeigt oder geloggt; ein leeres Feld behält das gespeicherte |

Eine Änderung wirkt ohne Neustart: der Dienst trennt und verbindet neu. Die Selbstprüfung testet den gewählten Broker. **Wichtig:** Mit eigenem Broker erreichen die Werte den Miniserver nur, wenn das MQTT-Gateway diesen Broker abonniert.

## Einbindung in Loxone

Tabelle aller Topics mit Eingangsname, Einheit, Bedeutung und retained-Angabe, dazu die Befehle. Siehe auch [Loxone-Anbindung](Loxone-Anbindung).

## Test

| Aktion | Wirkung |
|---|---|
| Sofort abrufen | Liest alle Variablen der konfigurierten Server und zeigt Variable, Topic und Wert |
| Selbstprüfung | Prüft Konfiguration, NUT-Server (und Anmeldung, falls Benutzer eingetragen), Broker, Gateway-Abo, Dienst und Befehlsbrücke (UDP 11884) |
| Dienst neu starten | Beendet und startet den Dienst |

## Logdateien

Zeigt das Ende der Logdatei. Das vollständige Log liegt im LoxBerry-Log-Manager (`nut.log`). Der **Loglevel** wird in der LoxBerry-Plugin-Verwaltung eingestellt und vom Dienst alle 30 s neu gelesen (Standard Info; Debug zeigt jede Befehlsnachricht).
