#!/bin/bash
# Baut nut2lox-<version>.zip aus nut2lox/ plus den gemeinsamen Klassen aus ../common/php (ohne tests/, .git, docs/).
# Aufruf: bash build.sh              -> baut nut2lox-<version>.zip
#         bash build.sh release-cfg  -> schreibt zusaetzlich release.cfg (Wurzel des Repos, nicht im ZIP) fuer die automatischen Updates.
#                                       Erst ausfuehren, wenn das GitHub-Release mit dem ZIP schon besteht!
cd "$(dirname "$0")" || exit 1
REPO="ricten/Nut2Lox"
MODE="${1:-build}"
case "$MODE" in build|release-cfg) ;; *) echo "Aufruf: bash build.sh [release-cfg]" >&2; exit 1 ;; esac
VER="$(grep '^VERSION=' nut2lox/plugin.cfg | cut -d= -f2 | tr -d '\r')"
CODEVER="$(grep -o "CURRENT = '[^']*'" nut2lox/bin/lib/AppVersion.php | cut -d"'" -f2)"
if [ "$VER" != "$CODEVER" ]; then
    echo "Versionen passen nicht: plugin.cfg=$VER, AppVersion.php=$CODEVER" >&2
    exit 1
fi
if ! grep -q "^## \[$VER\]" CHANGELOG.md; then
    echo "CHANGELOG.md hat keinen Eintrag fuer Version $VER" >&2
    exit 1
fi
for c in Util Logger MqttClient GatewayInfo; do
    [ -f "../common/php/$c.php" ] || { echo "../common/php/$c.php fehlt" >&2; exit 1; }
done
# sudoers/sudoers: Der Installer prueft die Datei nicht, ein Fehler sperrt sudo im ganzen System -> hier streng pruefen
SUD="nut2lox/sudoers/sudoers"
[ -f "$SUD" ] || { echo "$SUD fehlt" >&2; exit 1; }
if grep -v '^#' "$SUD" | grep -v '^[[:space:]]*$' | grep -qv '^loxberry ALL=(root) NOPASSWD: REPLACELBPBINDIR/nut-apply\.sh apply, REPLACELBPBINDIR/nut-apply\.sh scan$'; then
    echo "$SUD enthaelt unerlaubte Zeilen (erlaubt: nur die Regel fuer nut-apply.sh apply/scan)" >&2
    exit 1
fi
grep -q "/opt/loxberry" "$SUD" && { echo "$SUD: kein fester /opt/loxberry-Pfad" >&2; exit 1; }
[ -z "$(tail -c1 "$SUD")" ] || { echo "$SUD muss mit Zeilenumbruch enden" >&2; exit 1; }
PY="$(command -v python3 || command -v python)" || { echo "python noetig (ersetzt zip)" >&2; exit 1; }

# Staging: Plugin + gemeinsamer Code
STAGE="$PWD/build/stage"
rm -rf build
mkdir -p "$STAGE"
cp -r nut2lox/. "$STAGE/"
cp ../common/php/Util.php ../common/php/Logger.php ../common/php/MqttClient.php ../common/php/GatewayInfo.php "$STAGE/bin/lib/"

# PHP-Syntaxpruefung, sofern php vorhanden
if command -v php >/dev/null 2>&1; then
    while IFS= read -r f; do
        php -l "$f" >/dev/null || { echo "Syntaxfehler in $f" >&2; exit 1; }
    done < <(find "$STAGE" -name '*.php')
else
    echo "Hinweis: php nicht gefunden, Syntaxpruefung uebersprungen" >&2
fi
# Healthcheck (PHP-Skript ohne Endung)
if command -v php >/dev/null 2>&1 && [ -f "$STAGE/bin/healthcheck" ]; then
    php -l "$STAGE/bin/healthcheck" >/dev/null || { echo "Syntaxfehler in bin/healthcheck" >&2; exit 1; }
fi
# Shell-Syntax
for f in "$STAGE"/*.sh "$STAGE"/bin/*.sh "$STAGE"/cron/cron.01min "$STAGE"/daemon/daemon "$STAGE"/uninstall/uninstall; do
    bash -n "$f" || { echo "Shell-Syntaxfehler in $f" >&2; exit 1; }
done

OUT="$PWD/nut2lox-$VER.zip"
rm -f nut2lox-*.zip nut-*.zip      # alte Installer entfernen, nur der aktuelle bleibt
"$PY" - "$OUT" "$STAGE" <<'PYEOF'
import os, sys, zipfile
out, root = sys.argv[1], sys.argv[2]
names = ['plugin.cfg']
for d, dirs, files in os.walk(root):
    dirs.sort()
    for f in sorted(files):
        rel = os.path.relpath(os.path.join(d, f), root).replace(os.sep, '/')
        if rel != 'plugin.cfg':
            names.append(rel)
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for rel in names:
        data = open(os.path.join(root, rel), 'rb').read()
        if rel.endswith(('.sh', '.php', '.cfg', '.json', 'apt', 'apt12', 'daemon', 'uninstall', 'healthcheck', 'sudoers')) or rel.startswith('cron/'):
            data = data.replace(b'\r\n', b'\n')       # Linux: nur LF
        info = zipfile.ZipInfo(rel, (2026, 1, 1, 0, 0, 0))
        exe = rel.endswith(('.sh', '.php')) or rel in ('daemon/daemon', 'uninstall/uninstall', 'bin/healthcheck') or rel.startswith('cron/')
        info.external_attr = (0o100755 if exe else 0o100644) << 16
        info.compress_type = zipfile.ZIP_DEFLATED
        z.writestr(info, data)
PYEOF
rm -rf build
# Zusaetzlich ein ZIP ohne Version im Namen: https://github.com/ricten/<Repo>/releases/latest/download/nut2lox.zip zeigt dann immer auf das neueste Release
cp -f "$OUT" "$PWD/nut2lox.zip"
echo "$OUT"
echo "$PWD/nut2lox.zip"

# release.cfg: LoxBerry liest sie ueber RELEASECFG (plugin.cfg) und installiert die dort genannte Version, wenn sie neuer ist
if [ "$MODE" = "release-cfg" ]; then
    {
        printf '%s
' '[AUTOUPDATE]'             '# release.cfg fuer die automatischen Updates von LoxBerry (RELEASECFG in plugin.cfg).'             '# Wird von "bash build.sh release-cfg" geschrieben - erst nach dem GitHub-Release mit dem ZIP einchecken.'             ''             '# Version des neuen Releases'             "VERSION=$VER"             ''             '# Direkter Download des ZIP (das echte ZIP, keine Seite)'             "ARCHIVEURL=https://github.com/$REPO/releases/download/v$VER/nut2lox-$VER.zip"             ''             '# Seite mit Informationen zu diesem Release'             "INFOURL=https://github.com/$REPO/releases/tag/v$VER"
    } > release.cfg
    echo "release.cfg geschrieben (Version $VER)"
elif [ -f release.cfg ]; then
    RCV="$(grep '^VERSION=' release.cfg | cut -d= -f2 | tr -d '')"
    [ "$RCV" = "$VER" ] || echo "Hinweis: release.cfg zeigt auf $RCV, plugin.cfg hat $VER (erst nach dem Release mit 'bash build.sh release-cfg' aktualisieren)" >&2
fi
