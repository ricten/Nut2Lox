#!/bin/bash
# Laeuft als root nach Installation/Update. $1 Temp-Name, $2 Plugin-Name, $3 Ordner, $4 Version, $5 LBHOMEDIR
# 1. Rechte setzen
# 2. sudo-Erlaubnis fuer GENAU zwei Aufrufe anlegen (der Benutzer loxberry darf sonst kein sudo ohne Passwort):
#    nut-apply.sh apply und nut-apply.sh scan. Datei wird vor dem Einbau mit visudo geprueft.
# 3. Vorhandene Konfiguration des lokalen NUT-Servers anwenden
APPLY="$5/bin/plugins/$3/nut-apply.sh"
chmod 0755 "$APPLY" 2>/dev/null

SUDOERS="/etc/sudoers.d/nut-plugin-$3"
TMP="$(mktemp)"
cat > "$TMP" <<EOT
# vom LoxBerry-Plugin $2 ($3): Weboberflaeche darf den lokalen NUT-Server einrichten
loxberry ALL=(root) NOPASSWD: $APPLY apply, $APPLY scan
EOT
if visudo -cf "$TMP" >/dev/null 2>&1; then
    install -m 0440 -o root -g root "$TMP" "$SUDOERS"
else
    echo "WARNUNG: sudoers-Datei ungueltig, nicht installiert"
fi
rm -f "$TMP"

if [ -f "$5/config/plugins/$3/lokal.conf" ]; then
    "$APPLY" apply
fi
exit 0
