#!/bin/sh
set -eu

VERSION="5.10"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="/var/backups/repeater/update-$VERSION-$STAMP"
LOGIC="/usr/share/svxlink/events.d/local/RepeaterLogic.tcl"

mkdir -p "$BACKUP_DIR/usr/share/svxlink/events.d/local" /etc/repeater
if [ -f "$LOGIC" ]; then
  cp -a "$LOGIC" "$BACKUP_DIR$LOGIC"
fi

# Drop a stuck carrier before touching the audio card.
systemctl stop svxlink || true
sleep 1

python3 - <<'PY'
from pathlib import Path
import re

logic = Path("/usr/share/svxlink/events.d/local/RepeaterLogic.tcl")
text = logic.read_text(encoding="utf-8")
text, count = re.subn(
    r"playTone (\d+) 1000 (\d+)",
    r"playTone \1 500 \2",
    text,
)
if "bip com limite de 4s" not in text:
    start = text.find("proc send_rgr_sound {}")
    end = text.find("\nproc gpio_write", start)
    if start < 0 or end < 0:
        raise SystemExit("Rotina de bip nao encontrada; atualizacao cancelada.")
    body = text[start:end]
    if "alarm 4" not in body:
        body = body.replace(
            '  if {$silent_ms > 0} {',
            '  # bip com limite de 4s para nao segurar o PTT se a placa USB travar\n  alarm 4\n  if {$silent_ms > 0} {',
            1,
        )
        marker = "    playTone 1000 500 120\n  }\n"
        if marker not in body:
            raise SystemExit("Fim da rotina de bip nao encontrado; atualizacao cancelada.")
        body = body.replace(marker, marker + "  alarm 0\n", 1)
    text = text[:start] + body + text[end:]
logic.write_text(text, encoding="utf-8")
print(f"Tons ajustados para metade da escala: {count}")
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
echo "PTT solto e bip limitado a 4 segundos."
echo "Backup: $BACKUP_DIR"
