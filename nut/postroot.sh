#!/bin/bash
# Laeuft als root nach Installation/Update. $1 Temp-Name, $2 Plugin-Name, $3 Ordner, $4 Version, $5 LBHOMEDIR
# Rechte setzen und eine vorhandene Konfiguration des lokalen NUT-Servers anwenden.
chmod 0755 "$5/bin/plugins/$3/nut-apply.sh" 2>/dev/null
if [ -f "$5/config/plugins/$3/lokal.conf" ]; then
    "$5/bin/plugins/$3/nut-apply.sh" apply
fi
exit 0
