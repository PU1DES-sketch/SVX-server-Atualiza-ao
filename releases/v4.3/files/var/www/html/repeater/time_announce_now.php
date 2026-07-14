<?php
require_once "auth.php";
require_login();
header('Content-Type: application/json; charset=utf-8');
$out = [];
$rc = 0;
exec('sudo /usr/local/bin/repeater_time_announce.py --now 2>&1', $out, $rc);
if ($rc === 0) {
    echo json_encode(['ok' => true, 'message' => 'Hora certa preparada. O SvxLink vai falar no proximo ciclo.', 'output' => implode("\n", $out)], JSON_UNESCAPED_UNICODE);
} elseif ($rc === 2) {
    echo json_encode(['ok' => false, 'message' => 'Repetidora ocupada. Tente novamente quando estiver em repouso.', 'output' => implode("\n", $out)], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['ok' => false, 'message' => 'Nao consegui preparar a hora certa agora.', 'output' => implode("\n", $out)], JSON_UNESCAPED_UNICODE);
}
