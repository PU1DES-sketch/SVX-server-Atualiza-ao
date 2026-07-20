<?php
require_once __DIR__ . '/auth.php';
require_login();

$message = null;
$output = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    if ($_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $name = basename($_FILES['backup_file']['name']);
        if (preg_match('/\.tar\.gz$/', $name)) {
            $tmp = '/tmp/repeater_restore_' . date('Ymd_His') . '.tar.gz';
            if (move_uploaded_file($_FILES['backup_file']['tmp_name'], $tmp)) {
                $output = shell_exec('sudo /usr/local/bin/repeater-restore ' . escapeshellarg($tmp) . ' 2>&1');
                $message = 'Restauracao executada. Confira o resultado abaixo.';
            } else {
                $message = 'Nao consegui salvar o arquivo enviado.';
            }
        } else {
            $message = 'Envie um arquivo .tar.gz gerado pelo backup da repetidora.';
        }
    } else {
        $message = 'Falha no envio do arquivo.';
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Restaurar Backup</title>
<style>
body{font-family:Arial,sans-serif;background:#111827;color:#e5e7eb;margin:0;padding:22px}
a{color:#7dd3fc}.box{max-width:760px;background:#1f2937;border:1px solid #374151;border-radius:8px;padding:18px}
button,.btn{display:inline-block;background:#2563eb;color:white;border:0;border-radius:7px;padding:10px 14px;font-weight:700;text-decoration:none;cursor:pointer}
input{display:block;width:100%;padding:10px;background:#111827;color:#e5e7eb;border:1px solid #374151;border-radius:7px;margin:10px 0 14px}
.warn{color:#facc15}pre{white-space:pre-wrap;background:#0b1220;border:1px solid #334155;border-radius:7px;padding:12px}
</style>
</head>
<body>
<div class="box">
<p><a href="index.php">Voltar</a> | <a href="backup.php">Backup</a></p>
<h1>Restaurar backup</h1>
<p class="warn">A restauracao substitui as configuracoes salvas pela copia enviada.</p>
<form method="post" enctype="multipart/form-data">
  <label>Arquivo de backup (.tar.gz)</label>
  <input type="file" name="backup_file" accept=".gz,.tar.gz" required>
  <button type="submit">Restaurar agora</button>
</form>
<?php if ($message): ?><p><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if ($output): ?><pre><?php echo htmlspecialchars($output); ?></pre><?php endif; ?>
</div>
</body>
</html>
