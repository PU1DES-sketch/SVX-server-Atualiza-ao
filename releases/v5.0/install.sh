#!/bin/sh
set -eu

VERSION="5.0"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="/var/backups/repeater/update-$VERSION-$STAMP"

mkdir -p "$BACKUP_DIR/usr/local/bin" /etc/repeater

if [ -f /usr/local/bin/repeater-selfheal ]; then
  cp -a /usr/local/bin/repeater-selfheal "$BACKUP_DIR/usr/local/bin/repeater-selfheal"
fi

install -m 755 files/usr/local/bin/repeater-selfheal /usr/local/bin/repeater-selfheal
printf '%s\n' "$VERSION" >/etc/repeater/version

# The timer runs the script as a oneshot service. The next scheduled run uses
# the new file; no network, audio or SvxLink service is restarted here.
systemctl reset-failed repeater-selfheal.service 2>/dev/null || true
systemctl enable --now repeater-selfheal.timer >/dev/null 2>&1 || true

echo "Atualizacao $VERSION instalada."
echo "Backup da autocura anterior: $BACKUP_DIR"
