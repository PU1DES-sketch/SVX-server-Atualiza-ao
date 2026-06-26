#!/bin/sh
set -eu

MANIFEST_URL="${REPEATER_UPDATE_MANIFEST_URL:-https://raw.githubusercontent.com/PU1DES-sketch/SVX-server-Atualiza-ao/main/releases/manifest.json}"
WORK="/tmp/repeater-bootstrap-update"
mkdir -p "$WORK"

curl -fsSL "$MANIFEST_URL" -o "$WORK/manifest.json"
python3 - "$WORK/manifest.json" <<'PY' > "$WORK/env"
import json, shlex, sys
data = json.load(open(sys.argv[1], encoding="utf-8"))
latest = data["latest"]
info = data["releases"][latest]
print(f"LATEST={shlex.quote(latest)}")
print(f"PACKAGE_URL={shlex.quote(info['package_url'])}")
print(f"SHA256={shlex.quote(info['sha256'])}")
PY
. "$WORK/env"

PKG="$WORK/update-$LATEST.tar.gz"
curl -fsSL "$PACKAGE_URL" -o "$PKG"
echo "$SHA256  $PKG" | sha256sum -c -
rm -rf "$WORK/extract"
mkdir -p "$WORK/extract"
tar -xzf "$PKG" -C "$WORK/extract"
cd "$WORK/extract"
sh install.sh
