#!/bin/sh
set -eu

VERSION="5.11"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="/var/backups/repeater/update-$VERSION-$STAMP"
LOGIC="/usr/share/svxlink/events.d/local/RepeaterLogic.tcl"

mkdir -p "$BACKUP_DIR/usr/share/svxlink/events.d/local" /etc/repeater
if [ -f "$LOGIC" ]; then
  cp -a "$LOGIC" "$BACKUP_DIR$LOGIC"
fi

systemctl stop svxlink || true
sleep 1

python3 - <<'PY'
from pathlib import Path
logic = Path("/usr/share/svxlink/events.d/local/RepeaterLogic.tcl")
text = logic.read_text(encoding="utf-8")
text = text.replace("  # bip com limite de 4s para nao segurar o PTT se a placa USB travar\n", "")
text = text.replace("  alarm 4\n", "")
text = text.replace("  alarm 0\n", "")
if "alarm 4" in text or "alarm 0" in text:
    raise SystemExit("O limite invalido do bip ainda ficou no arquivo.")
logic.write_text(text, encoding="utf-8")
print("Limite invalido do bip removido.")
PY

printf '%s\n' "$VERSION" >/etc/repeater/version

i=0
while [ "$i" -lt 20 ]; do
  if grep -q "USB-Audio" /proc/asound/cards; then
    break
  fi
  i=$((i + 1))
  sleep 1
done

systemctl reset-failed svxlink.service 2>/dev/null || true
systemctl start svxlink
sleep 3

if journalctl -u svxlink --since "12 seconds ago" --no-pager | grep -q "Could not initialize RX"; then
  systemctl stop svxlink || true
  sleep 3
  systemctl start svxlink
fi

echo "Atualizacao $VERSION instalada."
echo "Bip restaurado, sem o comando alarm."
echo "Backup: $BACKUP_DIR"
