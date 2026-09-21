#!/bin/sh
set -eu

VERSION="5.1"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="/var/backups/repeater/update-$VERSION-$STAMP"

backup_file() {
  source="$1"
  target="$BACKUP_DIR$source"
  if [ -f "$source" ]; then
    mkdir -p "$(dirname "$target")"
    cp -a "$source" "$target"
  fi
}

backup_file /usr/local/bin/repeater-selfheal
backup_file /usr/local/bin/repeater-apply-config
backup_file /usr/share/svxlink/events.d/local/RepeaterLogic.tcl
backup_file /etc/svxlink/svxlink.conf

install -m 755 files/usr/local/bin/repeater-selfheal /usr/local/bin/repeater-selfheal
install -m 755 files/usr/local/bin/repeater-apply-config /usr/local/bin/repeater-apply-config
install -m 644 files/usr/share/svxlink/events.d/local/RepeaterLogic.tcl /usr/share/svxlink/events.d/local/RepeaterLogic.tcl

# Apply only the two live values needed for the courtesy tone. This preserves
# the user's current server, Wi-Fi, GPIO and audio settings.
python3 - <<'PY'
import json
import re
from pathlib import Path

config_file = Path('/etc/repeater/config.json')
svx_file = Path('/etc/svxlink/svxlink.conf')
try:
    config = json.loads(config_file.read_text(encoding='utf-8') or '{}')
except Exception:
    config = {}

try:
    hang_time = int(str(config.get('hang_time', '1500')).strip())
except Exception:
    hang_time = 1500
hang_time = max(0, min(hang_time, 10000))
text = svx_file.read_text(encoding='utf-8')

def set_value(text, section, key, value):
    pattern = r'(?ms)(^\[' + re.escape(section) + r'\]\s*$)(.*?)(?=^\[|\Z)'
    match = re.search(pattern, text)
    if not match:
        return text
    body = match.group(2)
    key_pattern = r'(?m)^\s*' + re.escape(key) + r'\s*=.*$'
    line = key + '=' + str(value)
    if re.search(key_pattern, body):
        body = re.sub(key_pattern, line, body)
    else:
        body = body.rstrip() + '\n' + line + '\n'
    return text[:match.start(2)] + body + text[match.end(2):]

text = set_value(text, 'RepeaterLogic', 'RGR_SOUND_DELAY', 0)
text = set_value(text, 'Rx1', 'SQL_HANGTIME', hang_time)
svx_file.write_text(text, encoding='utf-8')
PY

mkdir -p /etc/repeater
printf '%s\n' "$VERSION" >/etc/repeater/version
systemctl reset-failed repeater-selfheal.service 2>/dev/null || true
systemctl enable --now repeater-selfheal.timer >/dev/null 2>&1 || true

# A short controlled SvxLink restart loads the new radio timing. No reboot,
# network, Wi-Fi or IP change occurs.
systemctl restart svxlink

echo "Atualizacao $VERSION instalada."
echo "Backup: $BACKUP_DIR"
