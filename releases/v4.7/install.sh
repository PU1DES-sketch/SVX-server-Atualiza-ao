#!/bin/sh
set -eu

VERSION="4.7"
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
backup_if_exists /usr/local/bin/repeater-selfheal
backup_if_exists /usr/local/bin/status_monitor.py
backup_if_exists /etc/sudoers.d/repeater-downgrade
backup_if_exists /etc/sudoers.d/repeater-maint-ssh
backup_if_exists /etc/ssh/sshd_config.d/95-protoradio-maint.conf
backup_if_exists /etc/repeater/maintenance_ssh_password
backup_if_exists /etc/systemd/system/repeater-time-announce.service
backup_if_exists /etc/systemd/system/repeater-time-announce.timer
backup_if_exists /etc/systemd/system/repeater-selfheal.service
backup_if_exists /etc/systemd/system/repeater-selfheal.timer
backup_if_exists /var/lib/repeater/time_voice

install -m 755 files/usr/local/bin/repeater-apply-config /usr/local/bin/repeater-apply-config
install -m 755 files/usr/local/bin/repeater_time_announce.py /usr/local/bin/repeater_time_announce.py
install -m 755 files/usr/local/bin/repeater_tot.py /usr/local/bin/repeater_tot.py
install -m 755 files/usr/local/bin/repeater-dtmf-action /usr/local/bin/repeater-dtmf-action
install -m 755 files/usr/local/bin/repeater-dtmf-config-save /usr/local/bin/repeater-dtmf-config-save
install -m 755 files/usr/local/bin/repeater-downgrade /usr/local/bin/repeater-downgrade
install -m 755 files/usr/local/bin/repeater-maint-ssh /usr/local/bin/repeater-maint-ssh
install -m 755 files/usr/local/bin/repeater-selfheal /usr/local/bin/repeater-selfheal
install -m 755 files/usr/local/bin/status_monitor.py /usr/local/bin/status_monitor.py
install -m 644 files/usr/share/svxlink/events.d/local/RepeaterLogic.tcl /usr/share/svxlink/events.d/local/RepeaterLogic.tcl

install -m 644 files/etc/systemd/system/repeater-time-announce.service /etc/systemd/system/repeater-time-announce.service
install -m 644 files/etc/systemd/system/repeater-time-announce.timer /etc/systemd/system/repeater-time-announce.timer
install -m 644 files/etc/systemd/system/repeater-selfheal.service /etc/systemd/system/repeater-selfheal.service
install -m 644 files/etc/systemd/system/repeater-selfheal.timer /etc/systemd/system/repeater-selfheal.timer

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
/usr/local/bin/repeater-maint-ssh disable >/dev/null 2>&1 || true

mkdir -p /etc/repeater
printf '%s\n' "$VERSION" >/etc/repeater/version

systemctl daemon-reload
systemctl enable --now repeater-time-announce.timer >/dev/null 2>&1 || true
systemctl enable --now repeater-selfheal.timer >/dev/null 2>&1 || true
systemctl restart status.service 2>/dev/null || true
/usr/local/bin/repeater-dtmf-action apply >/dev/null 2>&1 || true
/usr/local/bin/repeater-apply-config >/dev/null 2>&1 || true
systemctl restart svxlink 2>/dev/null || true

echo "Atualizacao $VERSION instalada."
echo "Backup: $BACKUP_DIR"
