#!/bin/bash
# $1 Temp-Name, $2 Plugin-Name, $3 Ordner, $4 Version, $5 LBHOMEDIR
mkdir -p "$5/log/plugins/$3" "$5/data/plugins/$3"
# Umstieg vom frueheren Plugin "nut" (jetzt nut2lox): Einstellungen uebernehmen, solange das alte Plugin noch installiert ist.
# Eine vorhandene Konfiguration dieses Plugins wird nie ueberschrieben.
OLD="$5/config/plugins/nut"
NEW="$5/config/plugins/$3"
if [ "$3" != "nut" ] && [ -d "$OLD" ]; then
    for f in nut.json lokal.conf; do
        if [ -f "$OLD/$f" ] && [ ! -f "$NEW/$f" ]; then
            cp -p "$OLD/$f" "$NEW/$f" && chmod 0600 "$NEW/$f"
        fi
    done
fi
# Bei Neuinstallation Dienst starten (bei Upgrade startet postupgrade.sh)
"$5/bin/plugins/$3/start.sh"
exit 0
