#!/usr/bin/env python3
import argparse
import json
import os
import pwd
import grp
import subprocess
import time
from datetime import datetime
from pathlib import Path

CONFIG = Path("/etc/repeater/config.json")
COS = Path("/sys/class/gpio/gpio535/value")
PTT = Path("/sys/class/gpio/gpio534/value")
FLAG = Path("/tmp/repeater_time_announce")
WAV = Path("/tmp/repeater_time_announce.wav")
STAMP = Path("/tmp/repeater_time_announce.last")
LOG = Path("/var/log/repeater-time-announce.log")
DURATION = Path("/tmp/repeater_time_announce.duration")
PENDING = Path("/tmp/repeater_time_announce.pending")
VOICE_DIR = Path("/var/lib/repeater/time_voice/pt_BR_female")


def log(message):
    LOG.parent.mkdir(parents=True, exist_ok=True)
    with LOG.open("a", encoding="utf-8") as fh:
        fh.write(f"{datetime.now():%F %T} {message}\n")


def read_config():
    try:
        return json.loads(CONFIG.read_text(encoding="utf-8"))
    except Exception:
        return {}


def gpio_active(path, active_value):
    try:
        return path.read_text().strip() == active_value
    except Exception:
        return False


def gpio_write(path, value):
    path.write_text(str(value))


def idle():
    return not gpio_active(COS, "1") and not gpio_active(PTT, "1")


def number_key(value):
    return f"n{int(value):02d}"


def phrase_parts(now, include_minutes):
    hour = now.hour
    minute = now.minute
    parts = ["hora_certa", "agora_sao", number_key(hour), "hora" if hour == 1 else "horas"]
    if include_minutes and minute > 0:
        parts += ["e", number_key(minute), "minuto" if minute == 1 else "minutos"]
    return parts


def wav_for(part):
    path = VOICE_DIR / f"{part}.wav"
    if not path.exists():
        raise FileNotFoundError(f"arquivo de voz ausente: {path}")
    return path


def prepare_announcement(now, include_minutes):
    for path in (FLAG, DURATION):
        try:
            path.unlink()
        except FileNotFoundError:
            pass
    parts = phrase_parts(now, include_minutes)
    concat = Path("/tmp/repeater_time_announce.concat.txt")
    lines = []
    for part in parts:
        lines.append(f"file '{wav_for(part)}'")
        lines.append(f"file '{VOICE_DIR / 'silence_120.wav'}'")
    concat.write_text("\n".join(lines) + "\n", encoding="utf-8")
    tmp = WAV.with_name("repeater_time_announce.tmp.wav")
    subprocess.run([
        "ffmpeg", "-y", "-hide_banner", "-loglevel", "error",
        "-f", "concat", "-safe", "0", "-i", str(concat),
        "-ac", "1", "-ar", "16000", "-c:a", "pcm_s16le", str(tmp)
    ], check=True)
    tmp.replace(WAV)
    duration = subprocess.run([
        "ffprobe", "-v", "error", "-show_entries", "format=duration",
        "-of", "default=nk=1:nw=1", str(WAV)
    ], check=True, text=True, capture_output=True).stdout.strip()
    DURATION.write_text(duration or "10", encoding="utf-8")
    FLAG.write_text(str(WAV), encoding="utf-8")
    try:
        uid = pwd.getpwnam("svxlink").pw_uid
        gid = grp.getgrnam("svxlink").gr_gid
        for path in (WAV, FLAG, DURATION):
            os.chown(path, uid, gid)
            os.chmod(path, 0o664)
    except Exception as exc:
        log(f"nao consegui ajustar dono dos arquivos da hora: {exc}")
    return " ".join(parts)


def usb_playback_device():
    for card in range(0, 8):
        try:
            out = subprocess.run(["amixer", "-c", str(card), "scontrols"], check=True, text=True, capture_output=True).stdout
        except Exception:
            continue
        if "'Speaker'" in out:
            return f"plughw:{card},0"
    return "default"


def play_direct():
    # Toca direto para teste do botao. Se a placa USB estiver ocupada pelo SvxLink,
    # o erro sobe e a web informa que ficou preparado para o proximo ciclo.
    device = usb_playback_device()
    gpio_write(PTT, "1")
    time.sleep(0.35)
    try:
        subprocess.run(["aplay", "-q", "-D", device, str(WAV)], check=True, timeout=25)
    finally:
        time.sleep(0.25)
        gpio_write(PTT, "0")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--now", action="store_true", help="gera a hora atual imediatamente")
    parser.add_argument("--play-direct", action="store_true", help="tenta transmitir o audio direto pelo PTT")
    args = parser.parse_args()

    cfg = read_config()
    enabled = str(cfg.get("time_announce_enabled", "0")).lower() in ("1", "true", "on", "yes")
    if not enabled and not args.now:
        return

    now = datetime.now()
    hour_key = now.strftime("%Y%m%d%H")
    if not args.now and STAMP.exists() and STAMP.read_text().strip() == hour_key:
        return

    if not args.now:
        if now.minute == 0:
            PENDING.write_text(hour_key, encoding="utf-8")
        elif not PENDING.exists():
            return
        else:
            pending_key = PENDING.read_text(encoding="utf-8").strip()
            if pending_key != hour_key:
                PENDING.unlink(missing_ok=True)
                return

    if not idle():
        if not args.now:
            PENDING.write_text(hour_key, encoding="utf-8")
        log("repetidora ocupada; anuncio da hora adiado")
        raise SystemExit(2 if args.now else 0)

    try:
        desc = prepare_announcement(now, include_minutes=True)
        if not args.now:
            STAMP.write_text(hour_key, encoding="utf-8")
            PENDING.unlink(missing_ok=True)
        if args.play_direct:
            play_direct()
            FLAG.unlink(missing_ok=True)
            log(f"anuncio falado direto: {desc}")
            print("Hora certa falada direto")
        else:
            log(f"anuncio preparado: {desc}")
            print("Hora certa preparada")
    except Exception as exc:
        log(f"falha ao gerar/falar anuncio: {exc}")
        raise


if __name__ == "__main__":
    main()
