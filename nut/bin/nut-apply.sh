#!/bin/bash
# Richtet den lokalen NUT-Server (USB-USV am LoxBerry) ein. Laeuft als root (sudo aus der Weboberflaeche, postroot.sh).
# Liest nur config/plugins/<ordner>/lokal.conf (key=value), prueft jeden Wert und schreibt ausschliesslich /etc/nut/*.
# Aufruf: nut-apply.sh apply | scan
#
# Betriebsart netserver: nur Treiber + upsd, KEIN upsmon -> der LoxBerry faehrt bei leerer Batterie nicht selbst herunter.

# Pfade aus dem Ort des Skripts ableiten: <LBHOME>/bin/plugins/<ordner>/nut-apply.sh (kein fester Ordnername)
DIR="$(cd "$(dirname "$0")" && pwd)"
FOLDER="$(basename "$DIR")"
LBHOME="$(cd "$DIR/../../.." && pwd)"
CONF="$LBHOME/config/plugins/$FOLDER/lokal.conf"
NUTDIR="${NUT_APPLY_ETC:-/etc/nut}"
MARK="# verwaltet vom LoxBerry-Plugin nut - Aenderungen werden ueberschrieben"

die() { echo "FEHLER: $*" >&2; exit 1; }

[ "$(id -u)" = "0" ] || [ -n "${NUT_APPLY_ETC:-}" ] || die "muss als root laufen"

case "${1:-}" in
    scan)
        if command -v nut-scanner >/dev/null 2>&1; then
            nut-scanner -U -q 2>&1
        else
            echo "nut-scanner nicht installiert, USB-Geraete:"
            lsusb 2>&1
        fi
        exit 0
        ;;
    apply) ;;
    *) die "Aufruf: $0 apply|scan" ;;
esac

[ -f "$CONF" ] || die "Konfiguration $CONF fehlt"

declare -A C
while IFS='=' read -r k v; do
    case "$k" in
        aktiv|name|treiber|vendorid|productid|netz|synology|benutzer|passwort) C[$k]="$v" ;;
    esac
done < "$CONF"

aktiv="${C[aktiv]:-0}"
name="${C[name]:-usv}"
treiber="${C[treiber]:-usbhid-ups}"
vendorid="${C[vendorid]:-}"
productid="${C[productid]:-}"
netz="${C[netz]:-0}"
synology="${C[synology]:-0}"
benutzer="${C[benutzer]:-nutplugin}"
passwort="${C[passwort]:-}"

[[ "$aktiv" =~ ^[01]$ ]] || die "aktiv ungueltig"
[[ "$netz" =~ ^[01]$ ]] || die "netz ungueltig"
[[ "$synology" =~ ^[01]$ ]] || die "synology ungueltig"

ist_unser() { [ -f "$NUTDIR/nut.conf" ] && grep -qF "$MARK" "$NUTDIR/nut.conf"; }

if [ "$aktiv" = "0" ]; then
    if ist_unser; then
        echo "Lokaler NUT-Server wird abgeschaltet (nur vom Plugin angelegte Konfiguration)"
        printf '%s\nMODE=none\n' "$MARK" > "$NUTDIR/nut.conf"
        if [ -z "${NUT_APPLY_ETC:-}" ]; then
            systemctl stop nut-server.service 2>/dev/null
            systemctl stop nut-driver.target 2>/dev/null
            systemctl stop "nut-driver@${name}.service" 2>/dev/null
        fi
    else
        echo "Lokaler NUT-Server ist nicht aktiv, nichts zu tun"
    fi
    exit 0
fi

[[ "$name" =~ ^[a-z0-9_-]{1,32}$ ]] || die "name ungueltig (a-z 0-9 _ -)"
# Synology als NUT-Client: erwartet fest die USV "ups", den Benutzer monuser (Passwort secret, Rolle slave) und Zugriff ueber das Netz
if [ "$synology" = "1" ]; then
    [ "$name" = "ups" ] || die "Synology-Modus: Die USV muss ups heissen"
    netz=1
fi
case "$treiber" in
    usbhid-ups|blazer_usb|nutdrv_qx|richcomm_usb|tripplite_usb|riello_usb|bcmxcp_usb) ;;
    *) die "treiber nicht erlaubt" ;;
esac
[[ -z "$vendorid" || "$vendorid" =~ ^[0-9a-fA-F]{4}$ ]] || die "vendorid ungueltig"
[[ -z "$productid" || "$productid" =~ ^[0-9a-fA-F]{4}$ ]] || die "productid ungueltig"
[[ "$benutzer" =~ ^[a-z0-9_]{1,32}$ ]] || die "benutzer ungueltig"
[[ "$passwort" =~ ^[A-Za-z0-9]{8,64}$ ]] || die "passwort ungueltig (8-64 Zeichen, nur Buchstaben/Ziffern)"

[ -d "$NUTDIR" ] || die "$NUTDIR fehlt - ist das Paket nut-server installiert? (Plugin neu installieren)"
if [ -z "${NUT_APPLY_ETC:-}" ]; then
    command -v upsd >/dev/null 2>&1 || [ -x /usr/sbin/upsd ] || die "upsd fehlt - Paket nut-server nicht installiert"
fi

# Bestehende fremde Konfiguration einmalig sichern
for f in nut.conf ups.conf upsd.conf upsd.users; do
    if [ -f "$NUTDIR/$f" ] && ! grep -qF "$MARK" "$NUTDIR/$f" && [ ! -f "$NUTDIR/$f.vor-nut-plugin" ]; then
        cp -a "$NUTDIR/$f" "$NUTDIR/$f.vor-nut-plugin"
    fi
done

umask 027
{
    echo "$MARK"
    echo "MODE=netserver"
} > "$NUTDIR/nut.conf"

{
    echo "$MARK"
    echo "maxretry = 3"
    echo
    echo "[$name]"
    echo "    driver = $treiber"
    echo "    port = auto"
    echo "    desc = \"USV am LoxBerry\""
    [ -n "$vendorid" ] && echo "    vendorid = $vendorid"
    [ -n "$productid" ] && echo "    productid = $productid"
} > "$NUTDIR/ups.conf"

{
    echo "$MARK"
    echo "LISTEN 127.0.0.1 3493"
    [ "$netz" = "1" ] && echo "LISTEN 0.0.0.0 3493"
} > "$NUTDIR/upsd.conf"

{
    echo "$MARK"
    echo "[$benutzer]"
    echo "    password = $passwort"
    echo "    actions = SET"
    for c in beeper.enable beeper.disable beeper.mute beeper.toggle \
             test.battery.start test.battery.start.quick test.battery.start.deep test.battery.stop \
             test.panel.start test.panel.stop; do
        echo "    instcmds = $c"
    done
    if [ "$synology" = "1" ]; then
        # Synology-Vorgabe: Benutzer und Passwort sind in DSM fest (nicht aenderbar). Nur Lesezugriff als Client (slave).
        echo
        echo "[monuser]"
        echo "    password = secret"
        echo "    upsmon slave"
    fi
} > "$NUTDIR/upsd.users"

if [ -n "${NUT_APPLY_ETC:-}" ]; then
    echo "OK (Testlauf, keine Dienste angefasst)"
    exit 0
fi

chown root:nut "$NUTDIR/nut.conf" "$NUTDIR/ups.conf" "$NUTDIR/upsd.conf" "$NUTDIR/upsd.users" 2>/dev/null
chmod 0640 "$NUTDIR/nut.conf" "$NUTDIR/ups.conf" "$NUTDIR/upsd.conf" "$NUTDIR/upsd.users"

echo "Konfiguration geschrieben, Dienste werden neu gestartet"
udevadm control --reload-rules 2>/dev/null
udevadm trigger --subsystem-match=usb 2>/dev/null
systemctl enable nut-server.service >/dev/null 2>&1
systemctl restart nut-driver-enumerator.service 2>/dev/null
if ! systemctl restart "nut-driver@${name}.service" 2>/dev/null; then
    systemctl restart nut-driver.service 2>/dev/null || { upsdrvctl stop >/dev/null 2>&1; upsdrvctl start; }
fi
systemctl restart nut-server.service || die "nut-server konnte nicht gestartet werden (journalctl -u nut-server)"
echo "OK"
