<?php
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

$logfiles = ['/var/log/svxlink', '/var/log/svxlink.log'];
$confFile = '/etc/svxlink/svxlink.conf';

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

$logfile = null;
$bestMtime = -1;
foreach ($logfiles as $candidate) {
    if (!file_exists($candidate) || !is_readable($candidate)) continue;
    $size = @filesize($candidate);
    $mtime = @filemtime($candidate) ?: 0;
    if ($size === 0) continue;
    if ($mtime >= $bestMtime) {
        $bestMtime = $mtime;
        $logfile = $candidate;
    }
}

if (!$logfile) {
    echo json_encode([]);
    exit;
}

$ownCall = own_reflector_callsign($confFile);
$lines = file($logfile);
$lines = array_slice($lines, -5000);

$chamadas = [];
$localInicio = null;
$remoteActive = [];

foreach ($lines as $line) {
    if (preg_match('/^(.*?\d{4}): Rx1: The squelch is OPEN/', $line, $m)) {
        $localInicio = strtotime($m[1]);
        continue;
    }

    if (preg_match('/^(.*?\d{4}): Rx1: The squelch is CLOSED/', $line, $m) && $localInicio) {
        $fim = strtotime($m[1]);
        if ($fim !== false) {
            $duracao = max(0, $fim - $localInicio);
            $chamadas[] = [
                'ts' => $localInicio,
                'horario' => date('d/m/Y H:i:s', $localInicio),
                'duracao' => $duracao . ' s',
                'origem' => 'Local',
                'status' => 'Finalizada'
            ];
        }
        $localInicio = null;
        continue;
    }

    if (preg_match('/^(.*?\d{4}): ReflectorLogic: Talker start on TG #([0-9]+): (\S+)/', $line, $m)) {
        $inicio = strtotime($m[1]);
        $tg = $m[2];
        $call = $m[3];
        if ($inicio !== false && (!$ownCall || $call !== $ownCall)) {
            $remoteActive[$call] = ['inicio' => $inicio, 'tg' => $tg, 'call' => $call];
        }
        continue;
    }

    if (preg_match('/^(.*?\d{4}): ReflectorLogic: Talker stop on TG #([0-9]+): (\S+)/', $line, $m)) {
        $fim = strtotime($m[1]);
        $call = $m[3];
        if ($fim !== false && isset($remoteActive[$call])) {
            $inicio = $remoteActive[$call]['inicio'];
            $tg = $remoteActive[$call]['tg'];
            $duracao = max(0, $fim - $inicio);
            $chamadas[] = [
                'ts' => $inicio,
                'horario' => date('d/m/Y H:i:s', $inicio),
                'duracao' => $duracao . ' s',
                'origem' => $call,
                'status' => 'Rede TG ' . $tg
            ];
            unset($remoteActive[$call]);
        }
    }
}

usort($chamadas, function($a, $b) {
    return ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0);
});

$saida = array_map(function($c) {
    unset($c['ts']);
    return $c;
}, array_slice($chamadas, 0, 20));

echo json_encode($saida, JSON_UNESCAPED_UNICODE);
