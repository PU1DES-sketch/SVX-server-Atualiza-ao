#!/bin/sh
set -eu

VERSION="5.12"

card="$(awk '/USB-Audio/{print $1; exit}' /proc/asound/cards || true)"
if [ -z "$card" ]; then
  echo "Placa USB nao encontrada."
  exit 1
fi

echo "Placa USB no card $card."
echo "=== VOLUME ANTES ==="
amixer -c "$card" sget Speaker || true
amixer -c "$card" sget Mic || true
amixer -c "$card" sget PCM || true

amixer -c "$card" set Speaker 80% unmute
amixer -c "$card" set Mic 50% cap
amixer -c "$card" set PCM 90% unmute
amixer -c "$card" set 'Auto Gain Control' off >/dev/null 2>&1 || true
alsactl store >/dev/null 2>&1 || true

echo "=== VOLUME DEPOIS ==="
amixer -c "$card" sget Speaker || true
amixer -c "$card" sget Mic || true
amixer -c "$card" sget PCM || true

printf '%s\n' "$VERSION" >/etc/repeater/version
echo "Atualizacao $VERSION instalada."
echo "Volume da placa USB reaberto sem reiniciar o SvxLink."
