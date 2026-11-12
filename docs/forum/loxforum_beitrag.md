**Titel:** [LoxBerry-Plugin] Nut2Lox – USV-Werte (NUT) per MQTT an den Miniserver

---

Hallo zusammen,

ich habe ein LoxBerry-Plugin gebaut, das die Werte einer **USV** über **NUT** (Network UPS Tools) ausliest und per **MQTT** bereitstellt. Über das LoxBerry-MQTT-Gateway landen sie als virtuelle Eingänge im Miniserver. Es ersetzt bei mir den ioBroker-Adapter `nut`.

**Was kann das Plugin?**

- Liest bis zu **4 NUT-Server** im Netz (auch Server mit mehreren USVs) und veröffentlicht **alle** Variablen als `nut/<name>/…`, z. B. `battery_charge`, `battery_runtime`, `ups_load`, `input_voltage`.
- **Zustände als 0/1:** `zustand/batterie` (Netzausfall), `batterie_schwach`, `batterie_tauschen`, `laedt`, `ueberlast` …
- **Zusatzinfos** des Servers unter `info/*`: Sofortbefehle, schreibbare Variablen mit Werten und Bereichen, Clients, Version.
- **Eigener NUT-Server auf dem LoxBerry** für eine **USB-USV** (optional, per Knopfdruck), auf Wunsch auch für eine **Synology DiskStation als Client**. Die Betriebsart ist `netserver` ohne upsmon, der LoxBerry fährt also nicht von selbst herunter.
- **Befehle aus Loxone** (Piepser, Batterietest): ab Werk gesperrt, mit Quittung; Abschalten oder „Last aus“ gibt es bewusst nicht.
- **MQTT-Broker** des LoxBerry oder ein **eigener Broker**.
- Lebenszeichen `nut/status/ok|zaehler|ts`, Autostart, Watchdog, Erreichbarkeitsanzeige und Selbstprüfung in der Oberfläche.

**Voraussetzungen**

- LoxBerry 4.0 (PHP 7.4 bis 8.4) mit MQTT-Broker bzw. MQTT-Gateway
- ein erreichbarer NUT-Server **oder** eine USV per USB am LoxBerry

**Installation in drei Schritten**

1. ZIP von der Release-Seite herunterladen und in der Plugin-Verwaltung installieren.
2. Im Plugin den NUT-Server (Host) eintragen, **Aktiv** anhaken, speichern. Oder: „Lokalen NUT-Server einrichten“ für eine USB-USV.
3. Im MQTT Gateway das Abo `nut/#` eintragen. Beim Gateway V1 macht das die Abo-Datei des Plugins, beim **Gateway V2** muss es von Hand unter „Abonnements“ stehen (der Reiter MQTT zeigt, ob es eingetragen ist). Die virtuellen Eingänge heißen z. B. `nut_usv_battery_charge`, `nut_usv_zustand_batterie`.

*[Screenshot: Plugin-Oberfläche mit Erreichbarkeitsanzeige]*

**Beispiel-Topics**

| Topic | Bedeutung |
|---|---|
| `nut/usv/battery_charge` | Ladezustand (%) |
| `nut/usv/battery_runtime` | Restlaufzeit (s) |
| `nut/usv/ups_load` | Last (%) |
| `nut/usv/ups_status` | z. B. `OL CHRG` |
| `nut/usv/zustand/batterie` | 1 = Netzausfall |
| `nut/status/zaehler` | Lebenszeichen |

**Stand und Bitte um Rückmeldung**

Getestet habe ich das Plugin an einem LoxBerry mit einer **CyberPower 1200R** (USB, `usbhid-ups`), einem NUT-Server im Netz und einer **Synology** als Client. Rückmeldungen wären hilfreich zu anderen USV-Modellen und Treibern, zu den Befehlen aus Loxone (nicht jede USV unterstützt alle) und zum Betrieb mit eigenem MQTT-Broker.

**Hinweise**

- Zum Lesen der Werte braucht NUT keinen Benutzer, nur für Befehle.
- Die Synology erwartet fest die USV `ups` und den Benutzer `monuser` / `secret` (nicht änderbar); bitte Port 3493 nur im Heimnetz erreichbar lassen.
- Das Plugin ist inoffiziell und nicht vom NUT-Projekt.

**Links**

- Download und Quellcode: https://github.com/ricten/Nut2Lox (aktuelle Version über „Releases“)
- Dokumentation: im Repository unter `docs/wiki/`
- Fehler bitte als GitHub-Issue melden oder hier im Thread, mit Version, USV-Modell, Treiber und den Logzeilen (Zugangsdaten werden im Log maskiert).

Lizenz: MIT.

Viele Grüße
Rico
