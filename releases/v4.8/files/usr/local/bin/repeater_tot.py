#!/usr/bin/env python3
import json, time, subprocess, os, pwd, grp
from pathlib import Path

CONFIG = Path("/etc/repeater/config.json")
SVXCONF = Path("/etc/svxlink/svxlink.conf")
COS = Path("/sys/class/gpio/gpio535/value")
PTT = Path("/sys/class/gpio/gpio534/value")
LOCK = Path("/tmp/repeater_tot.lock")
ALARM = Path("/tmp/repeater_tot_alarm")
LOGICS_BKP = Path("/tmp/repeater_tot_logics.bkp")

def log(msg):
    print(msg, flush=True)

def write_alarm():
    ALARM.write_text("TOT")
    try:
        uid = pwd.getpwnam("svxlink").pw_uid
        gid = grp.getgrnam("svxlink").gr_gid
        os.chown(ALARM, uid, gid)
    except Exception:
        pass

def read_tot():
    try:
        data = json.loads(CONFIG.read_text())
        return max(10, int(data.get("tot", 180)))
    except Exception:
        return 180

def cos_active():
    try:
        return COS.read_text().strip() == "1"
    except Exception:
        return False

def tx_active():
    try:
        return PTT.read_text().strip() == "1"
    except Exception:
        return False

def ptt_off():
    try:
        PTT.write_text("0")
    except Exception:
        pass

def get_conf(key):
    try:
        for line in SVXCONF.read_text().splitlines():
            if line.startswith(key + "="):
                return line.split("=", 1)[1].strip()
    except Exception:
        pass
    return ""

def set_conf(key, value):
    txt = SVXCONF.read_text()
    lines = []
    changed = False
    for line in txt.splitlines():
        if line.startswith(key + "="):
            lines.append(f"{key}={value}")
            changed = True
        else:
            lines.append(line)
    if not changed:
        lines.append(f"{key}={value}")
    SVXCONF.write_text("\n".join(lines) + "\n")

def restart_svx():
    subprocess.run(["systemctl", "restart", "svxlink"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

def block_repeater():
    log("TOT: bloqueando repetidora por COS/TX")

    current_logics = get_conf("LOGICS")
    if current_logics:
        LOGICS_BKP.write_text(current_logics)

    set_conf("OPEN_ON_SQL", "0")

    # Desliga ReflectorLogic durante bloqueio para cortar TX vindo do link.
    # Nao usar GPIO inexistente: se reiniciar no bloqueio, o SvxLink nao sobe.
    set_conf("LOGICS", "RepeaterLogic")

    restart_svx()
    ptt_off()

def unblock_repeater():
    log("TOT: liberando repetidora")

    set_conf("OPEN_ON_SQL", "100")

    if LOGICS_BKP.exists():
        old_logics = LOGICS_BKP.read_text().strip()
        if old_logics:
            set_conf("LOGICS", old_logics)
        LOGICS_BKP.unlink()

    restart_svx()

    if LOCK.exists():
        LOCK.unlink()

def main():
    active_since = None
    blocked = LOCK.exists()
    log("TOT: servico iniciado com protecao COS + TX")

    while True:
        cos = cos_active()
        tx = tx_active()
        active = cos or tx
        tot = read_tot()

        if blocked:
            ptt_off()

            if not cos and not tx:
                log("TOT: COS/TX solto, aguardando 60 segundos")
                time.sleep(60)
                unblock_repeater()
                blocked = False
                active_since = None

            time.sleep(0.2)
            continue

        if active:
            if active_since is None:
                active_since = time.time()
                origem = "COS" if cos else "TX"
                log(f"TOT: {origem} ativo, limite={tot}s")
            elif time.time() - active_since >= tot:
                log(f"TOT: limite excedido ({tot}s)")
                LOCK.write_text("TOT")
                write_alarm()
                block_repeater()
                blocked = True
        else:
            active_since = None

        time.sleep(0.2)

if __name__ == "__main__":
    main()
