#!/bin/sh
set -eu

VERSION="5.2"
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
backup_file /usr/share/svxlink/events.d/local/RepeaterLogic.tcl
backup_file /etc/svxlink/svxlink.conf

cat >/usr/local/bin/repeater-selfheal <<'PY'
#!/usr/bin/env python3
"""Local-only health monitor for ProtoRadio."""
import json
import subprocess
import time
from datetime import datetime
from pathlib import Path

STATUS_FILE = Path('/var/www/html/repeater/status.json')
LOG_FILE = Path('/var/log/repeater-selfheal.log')
STATE_FILE = Path('/var/lib/repeater/selfheal/state.json')
STATUS_STALE_SEC = 180
AUDIO_RETRY_COOLDOWN_SEC = 900

def log(message):
    LOG_FILE.parent.mkdir(parents=True, exist_ok=True)
    with LOG_FILE.open('a', encoding='utf-8') as handle:
        handle.write(f'{datetime.now():%F %T} {message}\n')

def run(command, timeout=30):
    return subprocess.run(command, text=True, capture_output=True, timeout=timeout)

def active(unit):
    return subprocess.run(['systemctl', 'is-active', '--quiet', unit]).returncode == 0

def restart(unit):
    result = run(['systemctl', 'restart', unit], timeout=45)
    if result.returncode == 0:
        log(f'servico reiniciado: {unit}')
        return True
    log(f'falha ao reiniciar {unit}: {(result.stderr or result.stdout).strip()}')
    return False

def fresh(path, max_age):
    try:
        return time.time() - path.stat().st_mtime <= max_age
    except FileNotFoundError:
        return False

def usb_audio_present():
    try:
        cards = Path('/proc/asound/cards').read_text(encoding='utf-8', errors='ignore')
    except OSError:
        return False
    return 'USB-Audio' in cards or 'USB Audio Device' in cards

def load_state():
    try:
        return json.loads(STATE_FILE.read_text(encoding='utf-8'))
    except Exception:
        return {'last_audio_recovery': 0}

def recover_usb_audio():
    log('placa de audio USB ausente; tentando recuperar ALSA local')
    if active('svxlink'):
        run(['systemctl', 'stop', 'svxlink'], timeout=40)
    run(['modprobe', '-r', 'snd_usb_audio'], timeout=25)
    run(['modprobe', 'snd_usb_audio'], timeout=25)
    time.sleep(4)
    if usb_audio_present():
        log('placa de audio USB recuperada')
        restart('svxlink')
    else:
        log('placa de audio USB continua ausente; radio local preservado sem reboot')

def main():
    state = load_state()
    if not fresh(STATUS_FILE, STATUS_STALE_SEC):
        log('monitor de status sem atualizacao; reiniciando apenas status.service')
        restart('status.service')
    if not active('svxlink'):
        log('svxlink inativo; tentando reiniciar apenas o SvxLink')
        restart('svxlink')
    if not usb_audio_present():
        now = int(time.time())
        if now - int(state.get('last_audio_recovery', 0)) >= AUDIO_RETRY_COOLDOWN_SEC:
            state['last_audio_recovery'] = now
            recover_usb_audio()
        else:
            log('placa de audio USB ausente; aguardando proxima tentativa local')
    STATE_FILE.parent.mkdir(parents=True, exist_ok=True)
    STATE_FILE.write_text(json.dumps(state), encoding='utf-8')

if __name__ == '__main__':
    main()
PY
chmod 755 /usr/local/bin/repeater-selfheal

python3 - <<'PY'
import json
import re
from pathlib import Path

cfg = Path('/etc/repeater/config.json')
svx = Path('/etc/svxlink/svxlink.conf')
logic = Path('/usr/share/svxlink/events.d/local/RepeaterLogic.tcl')
try:
    hang = int(str(json.loads(cfg.read_text(encoding='utf-8') or '{}').get('hang_time', 1500)).strip())
except Exception:
    hang = 1500
hang = max(0, min(hang, 10000))
text = svx.read_text(encoding='utf-8')
def set_value(text, section, key, value):
    pattern = r'(?ms)(^\[' + re.escape(section) + r'\]\s*$)(.*?)(?=^\[|\Z)'
    match = re.search(pattern, text)
    if not match:
        return text
    body = match.group(2)
    line = key + '=' + str(value)
    key_pattern = r'(?m)^\s*' + re.escape(key) + r'\s*=.*$'
    body = re.sub(key_pattern, line, body) if re.search(key_pattern, body) else body.rstrip() + '\n' + line + '\n'
    return text[:match.start(2)] + body + text[match.end(2):]
svx.write_text(set_value(set_value(text, 'RepeaterLogic', 'RGR_SOUND_DELAY', 0), 'Rx1', 'SQL_HANGTIME', hang), encoding='utf-8')
if logic.exists():
    code = logic.read_text(encoding='utf-8')
    code = re.sub(r'set tone_level \d+', 'set tone_level 320', code)
    logic.write_text(code, encoding='utf-8')
PY

printf '%s\n' "$VERSION" >/etc/repeater/version
systemctl reset-failed repeater-selfheal.service 2>/dev/null || true
systemctl enable --now repeater-selfheal.timer >/dev/null 2>&1 || true
systemctl restart svxlink
echo "Atualizacao $VERSION instalada."
echo "Backup: $BACKUP_DIR"
