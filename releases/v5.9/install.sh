#!/bin/sh
set -eu

VERSION="5.9"
BACKUP_DIR="/var/backups/repeater/update-$VERSION-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"

backup_if_exists() {
  if [ -e "$1" ]; then
    mkdir -p "$BACKUP_DIR$(dirname "$1")"
    cp -a "$1" "$BACKUP_DIR$1"
  fi
}

backup_if_exists /var/www/html/repeater
backup_if_exists /usr/share/svxlink/events.d/local/RepeaterLogic.tcl
backup_if_exists /usr/local/bin/repeater-apply-config
backup_if_exists /usr/local/bin/repeater_time_announce.py
backup_if_exists /usr/local/bin/repeater_tot.py
backup_if_exists /usr/local/bin/repeater-dtmf-action
backup_if_exists /usr/local/bin/repeater-dtmf-config-save
backup_if_exists /usr/local/bin/repeater-downgrade
backup_if_exists /usr/local/bin/repeater-maint-ssh
backup_if_exists /usr/local/bin/status_monitor.py
backup_if_exists /etc/sudoers.d/repeater-downgrade
backup_if_exists /etc/sudoers.d/repeater-maint-ssh
backup_if_exists /etc/ssh/sshd_config.d/95-protoradio-maint.conf
backup_if_exists /etc/repeater/maintenance_ssh_password
backup_if_exists /etc/systemd/system/repeater-time-announce.service
backup_if_exists /etc/systemd/system/repeater-time-announce.timer
backup_if_exists /var/lib/repeater/time_voice

install -m 755 files/usr/local/bin/repeater-apply-config /usr/local/bin/repeater-apply-config
install -m 755 files/usr/local/bin/repeater_time_announce.py /usr/local/bin/repeater_time_announce.py
install -m 755 files/usr/local/bin/repeater_tot.py /usr/local/bin/repeater_tot.py
install -m 755 files/usr/local/bin/repeater-dtmf-action /usr/local/bin/repeater-dtmf-action
install -m 755 files/usr/local/bin/repeater-dtmf-config-save /usr/local/bin/repeater-dtmf-config-save
install -m 755 files/usr/local/bin/repeater-downgrade /usr/local/bin/repeater-downgrade
install -m 755 files/usr/local/bin/repeater-maint-ssh /usr/local/bin/repeater-maint-ssh
install -m 755 files/usr/local/bin/status_monitor.py /usr/local/bin/status_monitor.py
install -m 644 files/usr/share/svxlink/events.d/local/RepeaterLogic.tcl /usr/share/svxlink/events.d/local/RepeaterLogic.tcl

install -m 644 files/etc/systemd/system/repeater-time-announce.service /etc/systemd/system/repeater-time-announce.service
install -m 644 files/etc/systemd/system/repeater-time-announce.timer /etc/systemd/system/repeater-time-announce.timer

mkdir -p /var/www/html/repeater
for f in files/var/www/html/repeater/*.php; do
  install -o www-data -g www-data -m 644 "$f" "/var/www/html/repeater/$(basename "$f")"
done

mkdir -p /var/lib/repeater/time_voice
cp -a files/var/lib/repeater/time_voice/. /var/lib/repeater/time_voice/
chown -R root:root /var/lib/repeater/time_voice
find /var/lib/repeater/time_voice -type f -exec chmod 0644 {} \; 2>/dev/null || true

cat >/etc/sudoers.d/repeater-time-announce-web <<'EOF'
www-data ALL=(root) NOPASSWD: /usr/local/bin/repeater_time_announce.py --now
www-data ALL=(root) NOPASSWD: /usr/local/bin/repeater_time_announce.py --now --play-direct
EOF
chmod 0440 /etc/sudoers.d/repeater-time-announce-web
visudo -cf /etc/sudoers.d/repeater-time-announce-web >/dev/null

cat >/etc/sudoers.d/repeater-downgrade <<'EOF'
www-data ALL=(root) NOPASSWD: /usr/local/bin/repeater-downgrade *
EOF
chmod 0440 /etc/sudoers.d/repeater-downgrade
visudo -cf /etc/sudoers.d/repeater-downgrade >/dev/null

cat >/etc/sudoers.d/repeater-maint-ssh <<'EOF'
www-data ALL=(root) NOPASSWD: /usr/local/bin/repeater-maint-ssh *
EOF
chmod 0440 /etc/sudoers.d/repeater-maint-ssh
visudo -cf /etc/sudoers.d/repeater-maint-ssh >/dev/null

/usr/local/bin/repeater-maint-ssh status >/dev/null 2>&1 || true
# Older images could already have protoadmin without sudo membership. Keep the
# emergency account usable after an update while SSH itself remains controlled
# by repeater-maint-ssh.
usermod -aG sudo protoadmin

mkdir -p /etc/repeater
printf '%s\n' "$VERSION" >/etc/repeater/version

systemctl daemon-reload
systemctl enable --now repeater-time-announce.timer >/dev/null 2>&1 || true
systemctl restart status.service 2>/dev/null || true
/usr/local/bin/repeater-dtmf-action apply >/dev/null 2>&1 || true
/usr/local/bin/repeater-apply-config >/dev/null 2>&1 || true
systemctl restart svxlink 2>/dev/null || true

# Cumulative 5.7 radio-tail correction. It is intentionally applied after the
# full 4.6 base so a jump from a clean 3.8/4.5 image keeps every prior feature.
python3 - <<'PY'
import re
from pathlib import Path

apply = Path('/usr/local/bin/repeater-apply-config')
logic = Path('/usr/share/svxlink/events.d/local/RepeaterLogic.tcl')
svx = Path('/etc/svxlink/svxlink.conf')

if not apply.exists() or not logic.exists() or not svx.exists():
    raise SystemExit('Arquivos esperados do ProtoRadio nao foram encontrados; atualizacao cancelada')

apply_text = apply.read_text(encoding='utf-8')
rgr_pattern = r'(?m)^txt = set_value\(txt, "RepeaterLogic", "RGR_SOUND_DELAY", [^)]+\)$'
rgr_line = 'txt = set_value(txt, "RepeaterLogic", "RGR_SOUND_DELAY", "0")'
if not re.search(rgr_pattern, apply_text):
    raise SystemExit('Formato do aplicador desconhecido; atualizacao cancelada')
apply_text = re.sub(rgr_pattern, rgr_line, apply_text, count=1)

sql_pattern = r'(?m)^txt = set_value\(txt, "Rx1", "SQL_HANGTIME", [^)]+\)$'
sql_line = 'txt = set_value(txt, "Rx1", "SQL_HANGTIME", "0")'
if re.search(sql_pattern, apply_text):
    apply_text = re.sub(sql_pattern, sql_line, apply_text, count=1)
else:
    apply_text = apply_text.replace(rgr_line, rgr_line + '\n' + sql_line, 1)
apply.write_text(apply_text, encoding='utf-8')

svx_text = svx.read_text(encoding='utf-8')
svx_text = re.sub(r'(?m)^\s*RGR_SOUND_DELAY=.*$', 'RGR_SOUND_DELAY=0', svx_text)
svx_text = re.sub(r'(?m)^\s*SQL_HANGTIME=.*$', 'SQL_HANGTIME=0', svx_text)
svx.write_text(svx_text, encoding='utf-8')

logic_text = logic.read_text(encoding='utf-8')
if 'Keep RF keyed with digital silence' not in logic_text and 'Keep the RF transmitter keyed with digital silence' not in logic_text:
    marker = r'(?m)^  set tone_level \d+\n  puts "BDNET: bip de cortesia \$bip"$'
    replacement = '''  set tone_level 320
  # Keep RF keyed with digital silence instead of retransmitting receiver tail noise.
  set silent_ms [get_config_value "hang_time" "1500"]
  if {![string is integer -strict $silent_ms]} {
    set silent_ms 1500
  }
  set silent_ms [expr {max(0, min($silent_ms, 10000))}]
  if {$silent_ms > 0} {
    playSilence $silent_ms
  }
  puts "BDNET: bip de cortesia $bip"'''
    logic_text, count = re.subn(marker, replacement, logic_text, count=1)
    if count != 1:
        raise SystemExit('Formato do evento de bip desconhecido; atualizacao cancelada')
logic.write_text(logic_text, encoding='utf-8')
PY

printf '%s\n' "$VERSION" >/etc/repeater/version
systemctl restart svxlink 2>/dev/null || true

echo "Atualizacao $VERSION instalada."
echo "Pacote cumulativo: painel, acesso tecnico e rabicho silencioso."
echo "Backup: $BACKUP_DIR"
