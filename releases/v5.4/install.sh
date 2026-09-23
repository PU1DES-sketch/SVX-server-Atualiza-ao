#!/bin/sh
set -eu

VERSION="5.4"
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

backup_file /usr/local/bin/repeater-apply-config
backup_file /usr/share/svxlink/events.d/local/RepeaterLogic.tcl
backup_file /etc/svxlink/svxlink.conf

python3 - <<'PY'
import re
from pathlib import Path

apply = Path('/usr/local/bin/repeater-apply-config')
logic = Path('/usr/share/svxlink/events.d/local/RepeaterLogic.tcl')
svx = Path('/etc/svxlink/svxlink.conf')

if not apply.exists() or not logic.exists() or not svx.exists():
    raise SystemExit('Arquivos esperados do ProtoRadio nao foram encontrados; atualizacao cancelada')

apply_text = apply.read_text(encoding='utf-8')
apply_text = apply_text.replace(
    'txt = set_value(txt, "Rx1", "SQL_HANGTIME", hang_time)',
    'txt = set_value(txt, "Rx1", "SQL_HANGTIME", "0")',
)
if 'txt = set_value(txt, "Rx1", "SQL_HANGTIME", "0")' not in apply_text:
    raise SystemExit('Formato do aplicador desconhecido; atualizacao cancelada')
apply.write_text(apply_text, encoding='utf-8')

svx_text = svx.read_text(encoding='utf-8')
svx_text = re.sub(r'(?m)^\s*RGR_SOUND_DELAY=.*$', 'RGR_SOUND_DELAY=0', svx_text)
svx_text = re.sub(r'(?m)^\s*SQL_HANGTIME=.*$', 'SQL_HANGTIME=0', svx_text)
svx.write_text(svx_text, encoding='utf-8')

logic_text = logic.read_text(encoding='utf-8')
marker = 'set tone_level 320\n  puts "BDNET: bip de cortesia $bip"'
replacement = '''set tone_level 320
  # Keep the RF transmitter keyed with digital silence, never with tail noise
  # from the receiver audio. The web hang_time controls this silent interval.
  set silent_ms [get_config_value "hang_time" "1500"]
  if {![string is integer -strict $silent_ms]} {
    set silent_ms 1500
  }
  set silent_ms [expr {max(0, min($silent_ms, 10000))}]
  if {$silent_ms > 0} {
    playSilence $silent_ms
  }
  puts "BDNET: bip de cortesia $bip"'''
if marker in logic_text:
    logic_text = logic_text.replace(marker, replacement, 1)
elif 'Keep the RF transmitter keyed with digital silence' not in logic_text:
    raise SystemExit('Formato do evento de bip desconhecido; atualizacao cancelada')
logic.write_text(logic_text, encoding='utf-8')
PY

printf '%s\n' "$VERSION" >/etc/repeater/version
systemctl restart svxlink
echo "Atualizacao $VERSION instalada."
echo "Rabicho usa silencio digital antes do bip; audio de entrada fecha imediatamente."
echo "Backup: $BACKUP_DIR"
