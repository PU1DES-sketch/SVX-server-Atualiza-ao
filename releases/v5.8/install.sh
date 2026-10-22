#!/bin/sh
set -eu

VERSION="5.8"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="/var/backups/repeater/update-$VERSION-$STAMP"
LOGIC="/usr/share/svxlink/events.d/local/RepeaterLogic.tcl"

mkdir -p "$BACKUP_DIR/usr/share/svxlink/events.d/local" /etc/repeater
if [ -f "$LOGIC" ]; then
  cp -a "$LOGIC" "$BACKUP_DIR$LOGIC"
fi

python3 - <<'PY'
from pathlib import Path
import re

logic = Path("/usr/share/svxlink/events.d/local/RepeaterLogic.tcl")
text = logic.read_text(encoding="utf-8")
if "bip audivel amplitude 0-1000" in text:
    print("Bip ja estava corrigido.")
    raise SystemExit(0)

# playTone receives frequency, amplitude (0-1000), then duration in ms.
# The courtesy tones had the duration in the amplitude field, so the beep
# went out at about 12% level and could not be heard on the air.
updated, count = re.subn(
    r"playTone (\d+) (\d+) \$tone_level",
    r"playTone \1 1000 \2",
    text,
)
if count < 1:
    raise SystemExit("Rotina de bip nao encontrada; atualizacao cancelada.")

updated = updated.replace(
    "    playTone 1200 1000 120\n",
    "    playTone 1200 1000 220\n",
    1,
)
updated = updated.replace(
    "  set tone_level 320\n",
    "  set tone_level 1000\n",
    1,
)
if "bip audivel amplitude 0-1000" not in updated:
    updated = "# bip audivel amplitude 0-1000\n" + updated
logic.write_text(updated, encoding="utf-8")
print(f"Tons de cortesia corrigidos: {count}")
PY

printf '%s\n' "$VERSION" >/etc/repeater/version
systemctl reset-failed svxlink.service 2>/dev/null || true
systemctl restart svxlink
echo "Atualizacao $VERSION instalada."
echo "Backup da rotina de bip: $BACKUP_DIR"
