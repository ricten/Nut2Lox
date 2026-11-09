# Lokaler NUT-Server (USB-USV am LoxBerry)

Hängt die USV per USB am LoxBerry, richtet das Plugin auf Wunsch einen NUT-Server dafür ein und fragt ihn selbst ab. Andere Geräte (z. B. eine Synology) können sich als Client anmelden.

## Einrichten

1. USV per USB an den LoxBerry anschließen.
2. Reiter „Einstellungen USV/NUT“ → **USB-Geräte suchen**. Die Ausgabe zeigt Treiber sowie Vendor- und Product-ID (Beispiel CyberPower: `usbhid-ups`, `0764`/`0601`). Die Meldungen „Cannot load SNMP/XML/IPMI library“ sind harmlos.
3. Unter **USV per USB am LoxBerry** den Haken **Lokalen NUT-Server einrichten** setzen, ggf. Treiber und IDs eintragen, **Speichern**.
4. Das Kästchen „Lokaler NUT-Server“ wird grün, sobald der Treiber läuft (kann einige Sekunden dauern).

| Einstellung | Beschreibung |
|---|---|
| Name der USV | Name in NUT und im Topic (`nut/<name>/…`). Bei Synology fest `ups` |
| Treiber | `usbhid-ups` passt für die meisten USB-USVs; weitere: `blazer_usb`, `nutdrv_qx`, `richcomm_usb`, `tripplite_usb`, `riello_usb`, `bcmxcp_usb` |
| Vendor-ID / Product-ID | Optional, macht die Zuordnung eindeutig (vier Hexziffern) |
| Im Netz freigeben | NUT-Server lauscht auf `0.0.0.0:3493` (sonst nur `127.0.0.1`) |
| Synology als Client | siehe unten |

**Lokaler NUT-Server neu einrichten** schreibt die Konfiguration erneut und startet die Dienste neu.

## Was das Plugin dabei ändert

`bin/nut-apply.sh` läuft als root (per sudo, nur die Aufrufe `apply` und `scan` sind erlaubt) und schreibt ausschließlich:

| Datei | Inhalt |
|---|---|
| `/etc/nut/nut.conf` | `MODE=netserver` |
| `/etc/nut/ups.conf` | USV mit Treiber, `port = auto`, optional Vendor-/Product-ID |
| `/etc/nut/upsd.conf` | **eine** `LISTEN`-Zeile: `127.0.0.1` oder bei Freigabe `0.0.0.0` |
| `/etc/nut/upsd.users` | Benutzer `nutplugin` mit eingeschränkten Sofortbefehlen (kein Abschalten); bei Synology zusätzlich `monuser` |

- **Betriebsart `netserver` ohne `upsmon`:** Der LoxBerry fährt bei leerer Batterie **nicht** selbst herunter.
- Bestehende Dateien werden einmalig als `*.vor-nut-plugin` gesichert und danach nicht mehr überschrieben.
- Jeder Wert wird vor dem Schreiben geprüft (Name, Treiberliste, IDs, Benutzer, Passwort).
- Ist die Option aus, wird **nur eine vom Plugin angelegte** Konfiguration abgeschaltet (`MODE=none`); fremde Konfiguration bleibt unberührt.
- Die sudo-Erlaubnis legt die Installation an (`/etc/sudoers.d/nut-plugin-nut2lox`, vorher mit `visudo -c` geprüft) und entfernt die Deinstallation.

## Synology DiskStation als Client

DSM erwartet fest (nicht änderbar): USV-Name **`ups`**, Benutzer **`monuser`**, Passwort **`secret`**. Mit dem Haken **Synology (DSM) als Client** erledigt das Plugin:

- benennt die USV `ups`,
- gibt den Server im Netz frei,
- legt `monuser` mit Passwort `secret` und der Rolle **slave** an (nur Lesen, keine Steuerung).

In DSM: **Systemsteuerung → Hardware & Energie → USV** → *USV-Unterstützung aktivieren* → USV-Typ **Synology USV-Server** → **Netzwerk-USV-Server IP** = IP des LoxBerry → *Geräteinformationen* (zeigt Hersteller, Modell, Status, Batterie). Dort stellst du auch ein, nach welcher Zeit im Batteriebetrieb die Synology herunterfährt.

**Sicherheit:** Das Passwort `secret` ist bekannt und in DSM nicht änderbar. `monuser` darf nur lesen, trotzdem sollte Port 3493 nur im Heimnetz erreichbar sein.

Quelle der Vorgaben: [Synology DiskStation als NUT-Client](https://www.bjoerns-techblog.de/2024/04/synology-diskstation-als-nut-client/).

## Hinweise

- Die Synology fährt sich bei fast leerer Batterie selbst herunter, der LoxBerry nicht (kein `upsmon`).
- Mehrere USVs am selben LoxBerry sind nicht vorgesehen; Seriennummern wie `000000000000` (z. B. CyberPower) unterscheiden Geräte nicht.
