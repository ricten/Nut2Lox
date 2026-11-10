# MQTT-Topics

Schema: `nut/<name>/<wert>`. `<name>` ist der Gerätename (siehe [Konfiguration](Konfiguration.md)), im Beispiel `usv`. Werte sind reine Zahlen mit Punkt (ohne Einheit), Zustände 0/1, Text nur wo es Text ist.
Ein Wert ohne gültigen Inhalt wird **nicht** gesendet. Alle Topics werden **nicht retained** veröffentlicht (Ausnahme: die Letzter-Wille-Nachricht von `nut/status/ok`).

## Messwerte (nut/&lt;name&gt;/…)

Das Plugin veröffentlicht **jede** Variable, die der NUT-Server liefert. Der NUT-Name wird übernommen, der Punkt wird zu `_`: `battery.charge` → `nut/usv/battery_charge`. Welche Variablen es gibt, hängt von der USV ab. Häufige:

| Topic | Einheit | Bedeutung |
|---|---|---|
| `ups_status` | Text | Status, z. B. `OL`, `OB`, `OL CHRG`, `OB DISCHRG LB` |
| `battery_charge` | % | Ladezustand der Batterie |
| `battery_runtime` | s | Restlaufzeit |
| `battery_voltage` | V | Batteriespannung |
| `input_voltage` | V | Eingangsspannung (Netz) |
| `output_voltage` | V | Ausgangsspannung |
| `input_frequency` | Hz | Netzfrequenz |
| `ups_load` | % | Last |
| `ups_realpower` | W | Wirkleistung (nicht bei jeder USV) |
| `ups_temperature` | °C | Temperatur (nicht bei jeder USV) |
| `ups_mfr`, `ups_model`, `ups_serial` | Text | Hersteller, Modell, Seriennummer |

Seriennummern mit führenden Nullen bleiben Text und werden nicht in Zahlen umgewandelt.

## Zustände (nut/&lt;name&gt;/zustand/…), 0/1

Aus `ups.status` abgeleitet:

| Topic | NUT-Flag | Bedeutung |
|---|---|---|
| `zustand/online` | OL | Netzbetrieb |
| `zustand/batterie` | OB | Batteriebetrieb (**Netzausfall**) |
| `zustand/batterie_schwach` | LB | Batterie schwach |
| `zustand/batterie_tauschen` | RB | Batterie tauschen |
| `zustand/laedt` | CHRG | Batterie lädt |
| `zustand/entlaedt` | DISCHRG | Batterie entlädt |
| `zustand/ueberlast` | OVER | Überlast |
| `zustand/bypass` | BYPASS | Bypass aktiv |
| `zustand/alarm` | ALARM | Alarm |

## Verbindung und Zusatzinfos

| Topic | Bedeutung |
|---|---|
| `verbunden` | 1 = NUT-Server liefert Werte, 0 = nicht erreichbar oder Fehler |
| `info/beschreibung` | Beschreibung der USV (NUT) |
| `info/server_version` | Version des NUT-Servers |
| `info/protokoll` | NUT-Protokollversion |
| `info/anmeldungen` | Anzahl angemeldeter Clients (NUMLOGINS) |
| `info/clients` | Anzahl verbundener Clients (LIST CLIENT) |
| `info/befehle` | Sofortbefehle der USV, kommagetrennt (LIST CMD) |
| `info/beschreibbar` | Schreibbare Variablen, kommagetrennt (LIST RW) |
| `info/enum/<variable>` | Erlaubte Werte einer schreibbaren Variable, kommagetrennt |
| `info/range/<variable>` | Erlaubter Bereich einer schreibbaren Variable, `min..max` (mehrere mit `;`) |

Die `info/*`-Topics werden alle 60 s aktualisiert. Fragt der Server eine Information nicht ab, fehlt das Topic.

## Status des Plugins

| Topic | Bedeutung |
|---|---|
| `nut/status/ok` | 1 = letzter Durchgang erfolgreich (alle Server erreichbar), 0 = Fehler oder kein Server konfiguriert |
| `nut/status/zaehler` | Zählt je Durchgang 0…999 weiter (Lebenszeichen) |
| `nut/status/ts` | Unix-Zeitstempel des letzten Durchgangs |

Bricht der Dienst ab, setzt der Broker `nut/status/ok` per Letzter-Wille-Nachricht auf 0 (retained). Beim sauberen Beenden sendet der Dienst ebenfalls 0.

## Befehle

Siehe [Loxone-Anbindung](Loxone-Anbindung.md#befehle-aus-loxone).

| Topic | Wirkung |
|---|---|
| `nut/befehl/<name>/beeper_toggle` | Piepser umschalten |
| `nut/befehl/<name>/beeper_mute` | Piepser stummschalten |
| `nut/befehl/<name>/beeper_enable` / `beeper_disable` | Piepser ein / aus |
| `nut/befehl/<name>/test_battery_start_quick` / `test_battery_start_deep` | Kurzer / tiefer Batterietest |
| `nut/befehl/<name>/test_battery_stop` | Batterietest abbrechen |
| `nut/befehl/<name>/test_panel_start` / `test_panel_stop` | Anzeigentest starten / beenden |
| `nut/befehl/<name>/<befehl>/quittung` | Antwort (nie retained) |
