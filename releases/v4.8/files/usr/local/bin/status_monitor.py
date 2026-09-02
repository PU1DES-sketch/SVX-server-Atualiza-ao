#!/usr/bin/env python3
import time
import json
import subprocess
import re
import urllib.parse
import urllib.request

STATUS_FILE = "/var/www/html/repeater/status.json"
CONF_FILE = "/etc/svxlink/svxlink.conf"
CFG_FILE = "/etc/repeater/config.json"
VERSION_FILE = "/etc/repeater/version"
GPIO_TX = 534
GPIO_SQL = 535
REPORT_INTERVAL = 60

def gpio_read(pin):
    try:
        with open(f"/sys/class/gpio/gpio{pin}/value", "r") as f:
            return f.read().strip()
    except Exception:
        return "0"

def sql_inverted():
    try:
        with open(CONF_FILE, "r") as f:
            text = f.read()
        m = re.search(r"^\s*GPIO_SQL_PIN\s*=\s*(!?gpio\d+)", text, re.M)
        return bool(m and m.group(1).startswith("!"))
    except Exception:
        return False

def get_cpu_temp():
    try:
        temp = subprocess.check_output(["vcgencmd", "measure_temp"]).decode()
        return temp.replace("temp=", "").replace("'C", "°C").strip()
    except Exception:
        return "0°C"

def load_cfg():
    try:
        with open(CFG_FILE, "r", encoding="utf-8") as f:
            return json.load(f)
    except Exception:
        return {}

def repeater_version():
    try:
        with open(VERSION_FILE, "r", encoding="utf-8") as f:
            return f.read().strip()
    except Exception:
        return ""

def reflector_callsign():
    cfg = load_cfg()
    base = str(cfg.get("callsign", "")).strip().upper()
    return (base + "-R") if base else ""

def local_ip():
    try:
        output = subprocess.check_output(["hostname", "-I"], text=True).strip()
        return output.split()[0] if output else ""
    except Exception:
        return ""

def report_version():
    cfg = load_cfg()
    server_ip = str(cfg.get("server_ip", "")).strip()
    auth = str(cfg.get("server_pass", "")).strip()
    callsign = reflector_callsign()
    version = repeater_version()
    ip = local_ip()
    if not (server_ip and auth and callsign and version):
        return
    payload = urllib.parse.urlencode({
        "callsign": callsign,
        "auth": auth,
        "version": version,
        "ip": ip,
    }).encode("utf-8")
    urls = [
        f"http://{server_ip}/version_report.php",
        f"http://{server_ip}:8081/version_report.php",
    ]
    for url in urls:
        try:
            req = urllib.request.Request(url, data=payload, method="POST")
            with urllib.request.urlopen(req, timeout=3) as resp:
                if 200 <= getattr(resp, "status", 200) < 300:
                    return
        except Exception:
            continue

last_report = 0
while True:
    tx_value = gpio_read(GPIO_TX)
    raw_sql = gpio_read(GPIO_SQL)
    logical_sql = "1" if ((raw_sql == "0") if sql_inverted() else (raw_sql == "1")) else "0"
    payload = {
        "tx": "ON" if tx_value == "1" else "OFF",
        "sql": logical_sql,
        "sql_raw": raw_sql,
        "temp": get_cpu_temp(),
    }
    try:
        with open(STATUS_FILE, "w") as f:
            json.dump(payload, f)
    except Exception:
        pass
    now = time.time()
    if now - last_report >= REPORT_INTERVAL:
        report_version()
        last_report = now
    time.sleep(1)
