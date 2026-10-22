#!/bin/sh
set -eu

VERSION="5.9"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="/var/backups/repeater/update-$VERSION-$STAMP"

mkdir -p "$BACKUP_DIR" /etc/repeater
if [ -f /etc/svxlink/svxlink.conf ]; then
  cp -a /etc/svxlink/svxlink.conf "$BACKUP_DIR/svxlink.conf"
fi

printf '%s\n' "$VERSION" >/etc/repeater/version

# The USB sound card disappears while SvxLink is restarted. If the repeater
# logic starts in that window it stays without RX/TX audio and a later
# playback can hold the PTT until the watchdog kills the process.
systemctl stop svxlink
sleep 1

i=0
while [ "$i" -lt 20 ]; do
  if grep -q "USB-Audio" /proc/asound/cards; then
    break
  fi
  i=$((i + 1))
  sleep 1
done

if ! grep -q "USB-Audio" /proc/asound/cards; then
  systemctl start svxlink || true
  echo "Placa USB de audio nao voltou; SvxLink foi iniciado mesmo assim."
  exit 1
fi

systemctl reset-failed svxlink.service 2>/dev/null || true
systemctl start svxlink
sleep 3

if journalctl -u svxlink --since "15 seconds ago" --no-pager | grep -q "Could not initialize RX"; then
  systemctl stop svxlink
  sleep 3
  systemctl start svxlink
fi

echo "Atualizacao $VERSION instalada."
echo "SvxLink reiniciado somente depois da placa USB aparecer."
echo "Backup: $BACKUP_DIR"
