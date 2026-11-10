# Loxone-Anbindung

Der Weg: **NUT-Server → Nut2Lox → LoxBerry-MQTT-Broker → LoxBerry-MQTT-Gateway → Miniserver (virtuelle Eingänge)**.

## 1. MQTT-Gateway einrichten

**Abonnement `nut/#` im MQTT Gateway:** Beim **Gateway V1** (Perl) trägt das Plugin es über die Datei `mqtt_subscriptions.cfg` selbst ein (eine Zeile je Topic). Das **Gateway V2** (Python) liest diese Datei nicht (LoxBerry-Quellcode): Dort das Abo **von Hand** im MQTT Gateway unter „Abonnements“ eintragen: `nut/#`. Das Plugin prüft im Reiter *MQTT* und in der Selbstprüfung, ob es eingetragen ist. Mit **eigenem Broker** (Reiter MQTT) bekommt der Miniserver die Werte nur, wenn das Gateway diesen Broker abonniert.

Für Lebenszeichen und Fehlererkennung die Topics `nut/status/ok` und `nut/status/zaehler` im Gateway auf **No Cache** stellen.

## 2. Virtuelle Eingänge in Loxone Config

Das Gateway ersetzt `/` durch `_`. Aus `nut/usv/battery_charge` wird der Eingang `nut_usv_battery_charge`. Die Tabelle aller Topics samt Eingangsnamen zeigt die Plugin-Oberfläche im Reiter **Einbindung in Loxone**. Typische Eingänge:

| Eingang | Bedeutung |
|---|---|
| `nut_usv_battery_charge` | Ladezustand (%) |
| `nut_usv_battery_runtime` | Restlaufzeit (s) |
| `nut_usv_ups_load` | Last (%) |
| `nut_usv_input_voltage` | Netzspannung (V) |
| `nut_usv_ups_status` | Status als Text |
| `nut_usv_zustand_batterie` | 1 = Netzausfall (Batteriebetrieb) |
| `nut_usv_zustand_batterie_schwach` | 1 = Batterie schwach |
| `nut_usv_verbunden` | 1 = NUT-Server liefert Werte |
| `nut_status_ok` | 1 = Plugin arbeitet fehlerfrei |
| `nut_status_zaehler` | Lebenszeichen 0…999 |

Hinweise:

- **Netzausfall melden:** `nut_usv_zustand_batterie` auf 1 → Meldung; `…_batterie_schwach` für „bald aus“.
- **Ausfallerkennung:** `nut_status_zaehler` ändert sich bei jedem Durchgang (Standard alle 10 s). Bleibt er stehen, läuft der Dienst nicht mehr.
- Keine Leerzeichen in Gerätenamen verwenden, sonst findet das Gateway die Eingänge nicht.

## Befehle aus Loxone

Befehle sind ab Werk **gesperrt**; freigeben unter *Einstellungen USV/NUT → Befehle aus Loxone freigeben*. Erlaubt sind nur Piepser (`beeper_*`) und Tests (`test_battery_*`, `test_panel_*`), nie Abschalten oder „Last aus“. Für Befehle muss beim NUT-Server ein Benutzer mit Passwort eingetragen sein (beim lokalen Server legt das Plugin ihn selbst an).

Virtueller Ausgang in Loxone Config:

| Feld | Wert |
|---|---|
| Adresse | `/dev/udp/<LoxBerry-IP>/<UDP-Port>` |
| Befehl bei EIN | `publish nut/befehl/usv/beeper_toggle 1` |
| Trennzeichen / HTTP | leer, **ohne** HTTP-Header/Body |

`<UDP-Port>` ist der Befehlseingang des MQTT-Gateways (UDP, Standard 11884; der Port ist einstellbar und steht im Plugin im Reiter „Einbindung in Loxone“). Die Antwort steht unter `nut/befehl/usv/beeper_toggle/quittung` (nie retained):

| Quittung | Bedeutung |
|---|---|
| `angenommen` | Befehl erkannt und wird ausgeführt |
| `ausgefuehrt` | Von der USV bestätigt |
| `abgelehnt: Befehle sind gesperrt` | Freigabe fehlt |
| `abgelehnt: Befehl nicht erlaubt` | Nicht in der Positivliste |
| `abgelehnt: zum Ausloesen 1 senden` | Wert war nicht `1` |
| `abgelehnt: unbekanntes Geraet` | Gerätename passt zu keinem Server |
| `abgelehnt: NUT: …` | Die USV oder der Server lehnt ab (z. B. `CMD-NOT-SUPPORTED`, Anmeldung) |

Nicht jede USV unterstützt alle Befehle; die Liste der unterstützten steht in `nut/<name>/info/befehle`.
