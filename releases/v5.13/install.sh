#!/bin/sh
VERSION="5.13"

card="$(awk '/USB-Audio/{print $1; exit}' /proc/asound/cards || true)"
if [ -z "$card" ]; then
  echo "Placa USB nao encontrada."
  exit 1
fi

echo "Placa USB no card $card."
echo "=== CONTROLES ANTES ==="
amixer -c "$card" contents || true

amixer -c "$card" cset name='PCM Playback Switch' on || true
amixer -c "$card" cset name='PCM Playback Volume' 0 || true
amixer -c "$card" cset name='Speaker Playback Switch' on || true
amixer -c "$card" set Speaker 90% unmute || true
amixer -c "$card" set Mic 50% cap || true
alsactl store >/dev/null 2>&1 || true

echo "=== CONTROLES DEPOIS ==="
amixer -c "$card" contents || true

printf '%s\n' "$VERSION" >/etc/repeater/version
echo "Atualizacao $VERSION instalada."
echo "Saida da placa USB desmutada sem reiniciar o SvxLink."
