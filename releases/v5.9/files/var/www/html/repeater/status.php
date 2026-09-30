<?php
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

$statusFile = file_exists('/var/www/html/repeater/status.json')
    ? '/var/www/html/repeater/status.json'
    : '/var/www/html/status.json';
$rxStateFile = '/tmp/repeater_rx_live_start';
$confFile = '/etc/svxlink/svxlink.conf';
$logFiles = ['/var/log/svxlink', '/var/log/svxlink.log'];

$data = [
    "tx" => "OFF",
    "temp" => "---",
    "sql" => "0",
    "rx_live" => false,
    "rx_inicio" => null,
    "rx_inicio_txt" => null,
    "rx_duracao" => 0,
    "rx_origem" => null
];

if (file_exists($statusFile)) {
    $json = json_decode(file_get_contents($statusFile), true);
    if (is_array($json)) {
        $data = array_merge($data, $json);
    }
}

function own_reflector_callsign($confFile) {
    if (!file_exists($confFile)) return null;
    $text = file_get_contents($confFile);
    if (preg_match('/\[ReflectorLogic\](.*?)(\n\[|\z)/s', $text, $m)) {
        if (preg_match('/^\s*CALLSIGN\s*=\s*(\S+)/m', $m[1], $c)) {
            return trim($c[1]);
        }
    }
    return null;
}

function latest_remote_event($logFiles, $ownCall) {
    $events = [];
    foreach ($logFiles as $logfile) {
        if (!file_exists($logfile) || !is_readable($logfile)) continue;
        $lines = @file($logfile);
        if (!$lines) continue;
        $lines = array_slice($lines, -4000);
        foreach ($lines as $line) {
            if (preg_match('/^(.*?\d{4}): ReflectorLogic: Talker (start|stop) on TG #[0-9]+: (\S+)/', $line, $m)) {
                $ts = strtotime($m[1]);
                $call = $m[3];
                if ($ts !== false && (!$ownCall || $call !== $ownCall)) {
                    $events[] = ['ts' => $ts, 'kind' => $m[2], 'call' => $call];
                }
            }
        }
    }
    if (!$events) return [null, null];
    usort($events, function($a, $b) { return $b['ts'] <=> $a['ts']; });
    $last = $events[0];
    if ($last['kind'] === 'start' && (time() - $last['ts']) < 180) {
        return [$last['ts'], $last['call']];
    }
    return [null, null];
}

$sqlAberto = isset($data["sql"]) && (string)$data["sql"] === "1";
$txAberto = isset($data["tx"]) && (string)$data["tx"] === "ON";
$ownCall = own_reflector_callsign($confFile);
[$remoteStart, $remoteCall] = latest_remote_event($logFiles, $ownCall);

if ($remoteStart || $sqlAberto || $txAberto) {
    $origem = $remoteStart ? "Rede: " . $remoteCall : ($txAberto ? "TX" : "COS");
    $inicio = $remoteStart ?: (file_exists($rxStateFile) ? (int)trim(@file_get_contents($rxStateFile)) : 0);
    if ($inicio <= 0) {
        $inicio = time();
    }
    @file_put_contents($rxStateFile, (string)$inicio);
    $data["rx_live"] = true;
    $data["rx_inicio"] = $inicio;
    $data["rx_inicio_txt"] = date("d/m/Y H:i:s", $inicio);
    $data["rx_duracao"] = max(0, time() - $inicio);
    $data["rx_origem"] = $origem;
} else {
    @unlink($rxStateFile);
    $data["rx_live"] = false;
    $data["rx_inicio"] = null;
    $data["rx_inicio_txt"] = null;
    $data["rx_duracao"] = 0;
    $data["rx_origem"] = null;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
