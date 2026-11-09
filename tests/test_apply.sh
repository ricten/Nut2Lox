#!/bin/bash
# Testet nut-apply.sh ohne root im Testmodus (NUT_APPLY_ETC): schreibt nur in ein Temp-Verzeichnis, fasst keine Dienste an.
# Aufruf: bash tests/test_apply.sh
set -u
HERE="$(cd "$(dirname "$0")" && pwd)"
T="$HERE/tmp/apply"
rm -rf "$T"; mkdir -p "$T/lb/bin/plugins/nut" "$T/lb/config/plugins/nut" "$T/etc"
cp "$HERE/../nut/bin/nut-apply.sh" "$T/lb/bin/plugins/nut/"
APPLY="$T/lb/bin/plugins/nut/nut-apply.sh"
CONF="$T/lb/config/plugins/nut/lokal.conf"
export NUT_APPLY_ETC="$T/etc"
fails=0
ok()   { echo "ok   $1"; }
fail() { echo "FAIL $1"; fails=$((fails+1)); }

printf 'aktiv=1\nname=usv\ntreiber=usbhid-ups\nvendorid=051d\nproductid=0002\nnetz=0\nbenutzer=nutplugin\npasswort=abcdef0123456789\n' > "$CONF"
echo "# fremde Konfiguration" > "$T/etc/ups.conf"
bash "$APPLY" apply >"$T/out.txt" 2>&1 && ok "apply" || { fail "apply"; cat "$T/out.txt"; }
grep -q "MODE=netserver" "$T/etc/nut.conf" && ok "MODE=netserver" || fail "MODE"
grep -q "driver = usbhid-ups" "$T/etc/ups.conf" && ok "ups.conf Treiber" || fail "ups.conf"
grep -q "vendorid = 051d" "$T/etc/ups.conf" && ok "vendorid" || fail "vendorid"
grep -q "LISTEN 127.0.0.1 3493" "$T/etc/upsd.conf" && ! grep -q "0.0.0.0" "$T/etc/upsd.conf" && ok "nur localhost" || fail "LISTEN"
grep -q "instcmds = beeper.toggle" "$T/etc/upsd.users" && ! grep -q "shutdown" "$T/etc/upsd.users" && ok "instcmds ohne shutdown" || fail "instcmds"
[ "$(cat "$T/etc/ups.conf.vor-nut-plugin")" = "# fremde Konfiguration" ] && ok "Sicherung der fremden ups.conf" || fail "Sicherung"

sed -i 's/^netz=0/netz=1/' "$CONF"
bash "$APPLY" apply >/dev/null 2>&1; grep -q "LISTEN 0.0.0.0 3493" "$T/etc/upsd.conf" && ! grep -q "127.0.0.1" "$T/etc/upsd.conf" && ok "Netzfreigabe: nur eine LISTEN-Zeile" || fail "Netzfreigabe"

# zweiter Lauf ueberschreibt die Sicherung nicht
bash "$APPLY" apply >/dev/null 2>&1; [ "$(cat "$T/etc/ups.conf.vor-nut-plugin")" = "# fremde Konfiguration" ] && ok "Sicherung bleibt" || fail "Sicherung ueberschrieben"

# Synology-Modus: USV muss ups heissen, monuser als slave, Netzfreigabe erzwungen
printf 'aktiv=1\nname=usv\ntreiber=usbhid-ups\nvendorid=\nproductid=\nnetz=0\nsynology=1\nbenutzer=nutplugin\npasswort=abcdef0123456789\n' > "$CONF"
bash "$APPLY" apply >/dev/null 2>&1 && fail "Synology mit Name usv abgelehnt" || ok "Synology mit Name usv abgelehnt"
sed -i 's|^name=usv|name=ups|' "$CONF"
bash "$APPLY" apply >/dev/null 2>&1 && ok "Synology apply" || fail "Synology apply"
grep -q "^\[ups\]" "$T/etc/ups.conf" && ok "Synology: USV heisst ups" || fail "Synology: Name"
grep -q "^\[monuser\]" "$T/etc/upsd.users" && grep -q "upsmon slave" "$T/etc/upsd.users" && grep -q "password = secret" "$T/etc/upsd.users" && ok "Synology: monuser slave" || fail "Synology: monuser"
grep -q "LISTEN 0.0.0.0 3493" "$T/etc/upsd.conf" && ! grep -q "127.0.0.1" "$T/etc/upsd.conf" && ok "Synology: Netzfreigabe erzwungen" || fail "Synology: Netz"
printf 'aktiv=1\nname=usv\ntreiber=usbhid-ups\nvendorid=051d\nproductid=0002\nnetz=1\nsynology=0\nbenutzer=nutplugin\npasswort=abcdef0123456789\n' > "$CONF"
bash "$APPLY" apply >/dev/null 2>&1; ! grep -q "monuser" "$T/etc/upsd.users" && ok "ohne Synology kein monuser" || fail "monuser ohne Synology"

# Ungueltige Werte werden abgelehnt
for bad in 'name=a b' 'name=../x' 'treiber=rm' 'vendorid=zz' 'benutzer=x;y' 'passwort=kurz' 'passwort=abc def ghij k'; do
    key="${bad%%=*}"
    sed -i "s|^$key=.*|$bad|" "$CONF"
    bash "$APPLY" apply >/dev/null 2>&1 && fail "abgelehnt: $bad" || ok "abgelehnt: $bad"
    printf 'aktiv=1\nname=usv\ntreiber=usbhid-ups\nvendorid=051d\nproductid=0002\nnetz=1\nbenutzer=nutplugin\npasswort=abcdef0123456789\n' > "$CONF"
done

# Abschalten: nur eigene Konfiguration
sed -i 's/^aktiv=1/aktiv=0/' "$CONF"
bash "$APPLY" apply >/dev/null 2>&1; grep -q "MODE=none" "$T/etc/nut.conf" && ok "abgeschaltet" || fail "abschalten"

# Fremde nut.conf darf nicht angefasst werden
echo "MODE=standalone" > "$T/etc/nut.conf"
bash "$APPLY" apply >/dev/null 2>&1; grep -q "MODE=standalone" "$T/etc/nut.conf" && ok "fremde nut.conf unveraendert" || fail "fremde nut.conf"

echo; [ "$fails" = 0 ] && echo "Alle Pruefungen bestanden" || echo "$fails Fehler"
exit "$fails"
