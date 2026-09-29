<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_login('links.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: links.php');
    exit;
}

$auth = json_decode((string)@file_get_contents('/etc/repeater/web_auth.json'), true);
$password = (string)($_POST['confirm_pass'] ?? '');
if ($password === '' || !is_array($auth) || !password_verify($password, (string)($auth['pass'] ?? ''))) {
    header('Location: links.php?erro=senha');
    exit;
}

$links = $_POST['links'] ?? null;
if (!is_array($links) || count($links) !== 3) {
    http_response_code(400);
    exit('Configuracao de links invalida.');
}

$boolean = static function (array $source, string $key): bool { return isset($source[$key]) && $source[$key] === '1'; };
$clean = static function ($value, int $limit): string { return substr(trim(str_replace(["\r", "\n"], '', (string)$value)), 0, $limit); };
$result = ['schema_version' => 1, 'links' => []];
foreach ($links as $index => $link) {
    $number = $index + 1;
    if (!is_array($link) || ($link['id'] ?? '') !== "link{$number}") {
        http_response_code(400); exit('Link invalido.');
    }
    $cos = $clean($link['cos_gpio'] ?? '', 2); $ptt = $clean($link['ptt_gpio'] ?? '', 2);
    if ((!$cos && $cos !== '') || (!$ptt && $ptt !== '') || !preg_match('/^$|^\d{1,2}$/', $cos) || !preg_match('/^$|^\d{1,2}$/', $ptt) || ($cos !== '' && $cos === $ptt)) {
        http_response_code(400); exit('GPIO invalido.');
    }
    $result['links'][] = [
        'id' => "link{$number}", 'enabled' => $boolean($link, 'enabled'),
        'name' => $clean($link['name'] ?? '', 32), 'frequency' => $clean($link['frequency'] ?? '', 24),
        'audio_device' => $clean($link['audio_device'] ?? '', 96), 'audio_card_id' => $clean($link['audio_card_id'] ?? '', 96),
        'cos_gpio' => $cos, 'ptt_gpio' => $ptt,
        'input_gain' => max(0, min(100, (int)($link['input_gain'] ?? 50))),
        'output_volume' => max(0, min(100, (int)($link['output_volume'] ?? 50))),
        'tot_seconds' => max(10, min(600, (int)($link['tot_seconds'] ?? 100))),
        'master_to_link' => $boolean($link, 'master_to_link'), 'link_to_master' => $boolean($link, 'link_to_master'),
        'forward_id' => $boolean($link, 'forward_id'), 'forward_time' => $boolean($link, 'forward_time'),
    ];
}

$tmp = tempnam('/tmp', 'protoradio-links-');
file_put_contents($tmp, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$command = 'sudo /usr/local/bin/repeater-multilink-save ' . escapeshellarg($tmp) . ' 2>&1';
exec($command, $output, $code);
@unlink($tmp);
if ($code !== 0) { http_response_code(500); exit('Nao foi possivel salvar a configuracao MultiLink.'); }
header('Location: links.php?ok=1');
exit;
