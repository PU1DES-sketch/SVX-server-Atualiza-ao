#!/bin/sh
set -eu

VERSION="5.3"
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
backup_file /etc/svxlink/svxlink.conf

python3 - <<'PY'
from pathlib import Path

apply = Path('/usr/local/bin/repeater-apply-config')
if not apply.exists():
    raise SystemExit('Aplicador de configuracoes nao encontrado')

text = apply.read_text(encoding='utf-8')
old = 'txt = set_value(txt, "RepeaterLogic", "RGR_SOUND_DELAY", hang_time)'
new = 'txt = set_value(txt, "RepeaterLogic", "RGR_SOUND_DELAY", "0")'
if old not in text and new not in text:
    raise SystemExit('Formato do aplicador desconhecido; atualizacao cancelada sem alterar configuracoes')
apply.write_text(text.replace(old, new), encoding='utf-8')

svx = Path('/etc/svxlink/svxlink.conf')
cfg = svx.read_text(encoding='utf-8')
lines = []
for line in cfg.splitlines():
    lines.append('RGR_SOUND_DELAY=0' if line.strip().startswith('RGR_SOUND_DELAY=') else line)
svx.write_text('\n'.join(lines) + '\n', encoding='utf-8')
PY

printf '%s\n' "$VERSION" >/etc/repeater/version
systemctl restart svxlink
echo "Atualizacao $VERSION instalada."
echo "Rabicho usa SQL_HANGTIME; RGR_SOUND_DELAY foi fixado em 0."
echo "Backup: $BACKUP_DIR"
