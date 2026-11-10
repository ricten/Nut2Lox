#!/bin/bash
# Laeuft als root nach Installation/Update. $1 Temp-Name, $2 Plugin-Name, $3 Ordner, $4 Version, $5 LBHOMEDIR
# 1. Rechte von nut-apply.sh setzen
# 2. Die sudo-Erlaubnis kommt aus sudoers/sudoers (der Installer legt sie unter system/sudoers/<Plugin-Name> ab).
#    Eine fehlerhafte Datei wuerde sudo im ganzen System sperren, deshalb pruefen wir sie mit visudo und loeschen sie bei Fehler.
#    Die Datei der Vorgaengerversionen (/etc/sudoers.d/nut-plugin-<Ordner>) wird entfernt.
# 3. Vorhandene Konfiguration des lokalen NUT-Servers anwenden
APPLY="$5/bin/plugins/$3/nut-apply.sh"
chmod 0755 "$APPLY" 2>/dev/null

rm -f "/etc/sudoers.d/nut-plugin-$3"

SUDOERS="$5/system/sudoers/$2"
if [ -f "$SUDOERS" ]; then
    if command -v visudo >/dev/null 2>&1 && ! visudo -cf "$SUDOERS" >/dev/null 2>&1; then
        echo "WARNUNG: sudoers-Datei $SUDOERS ist ungueltig und wurde geloescht (Nut2Lox kann den lokalen NUT-Server dann nicht einrichten)"
        rm -f "$SUDOERS"
    fi
else
    echo "WARNUNG: sudoers-Datei $SUDOERS fehlt (Nut2Lox kann den lokalen NUT-Server dann nicht einrichten)"
fi

if [ -f "$5/config/plugins/$3/lokal.conf" ]; then
    "$APPLY" apply
fi
exit 0
