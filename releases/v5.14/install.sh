#!/bin/sh
VERSION="5.14"
PTT="/sys/class/gpio/gpio534/value"

unkey() {
  if [ -w "$PTT" ]; then
    echo 0 > "$PTT" || true
  fi
}

systemctl stop svxlink || true
sleep 1
unkey

card="$(awk '/USB-Audio/{print $1; exit}' /proc/asound/cards || true)"
if [ -n "$card" ] && [ -e "/sys/class/sound/card${card}/device" ]; then
  dev="$(readlink -f "/sys/class/sound/card${card}/device" || true)"
  while [ -n "$dev" ] && [ "$dev" != "/" ]; do
    if [ -f "$dev/product" ] && grep -qi "audio" "$dev/product"; then
      echo "Reiniciando a placa USB."
      echo 0 > "$dev/authorized" || true
      sleep 2
      echo 1 > "$dev/authorized" || true
      break
    fi
    dev="$(dirname "$dev")"
  done
fi

i=0
card=""
while [ "$i" -lt 20 ]; do
  card="$(awk '/USB-Audio/{print $1; exit}' /proc/asound/cards || true)"
  if [ -n "$card" ]; then
    break
  fi
  i=$((i + 1))
  sleep 1
done

if [ -z "$card" ]; then
  echo "Placa USB nao voltou."
  printf '%s\n' "$VERSION" >/etc/repeater/version
  systemctl start svxlink || true
  exit 1
fi

amixer -c "$card" set Speaker 90% unmute || true
amixer -c "$card" set Mic 50% cap || true
alsactl store >/dev/null 2>&1 || true

python3 - <<'PY'
import math, struct, wave
path = "/tmp/repeater-tone.wav"
rate = 48000
count = int(rate * 1.5)
out = wave.open(path, "w")
out.setnchannels(2)
out.setsampwidth(2)
out.setframerate(rate)
frames = bytearray()
for i in range(count):
    sample = int(20000 * math.sin(2 * math.pi * 1000 * i / rate))
    frames += struct.pack("<hh", sample, sample)
out.writeframes(frames)
out.close()
print("Tom de teste gerado.")
PY

echo 1 > "$PTT" || true
aplay -D "plughw:${card},0" /tmp/repeater-tone.wav || true
unkey

printf '%s\n' "$VERSION" >/etc/repeater/version
systemctl reset-failed svxlink.service 2>/dev/null || true
systemctl start svxlink
sleep 3
if journalctl -u svxlink --since "12 seconds ago" --no-pager | grep -q "Could not initialize RX"; then
  systemctl stop svxlink || true
  sleep 3
  systemctl start svxlink || true
fi

echo "Atualizacao $VERSION instalada."
echo "Placa USB reiniciada. Um tom de 1,5s foi para o ar."
