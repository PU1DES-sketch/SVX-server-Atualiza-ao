<?php
require_once __DIR__ . '/auth.php';
require_login();

$message = null;
$output = null;

function backup_files() {
    $files = glob('/var/backups/repeater/*.tar.gz') ?: [];
    usort($files, function($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });
    return $files;
}

function send_backup($path) {
    $name = basename($path);
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_download') {
    $before = backup_files();
    $before_map = array_flip($before);
    $output = shell_exec('sudo /usr/local/bin/repeater-backup 2>&1');

    $after = backup_files();
    $candidate = null;
    foreach ($after as $file) {
        if (!isset($before_map[$file])) {
            $candidate = $file;
            break;
        }
    }
    if (!$candidate && $after) {
        $candidate = $after[0];
    }

    if ($candidate && is_file($candidate)) {
        send_backup($candidate);
    }
    $message = 'Nao encontrei o arquivo final do backup. Confira o resultado abaixo.';
}

if (isset($_GET['download'])) {
    $name = basename($_GET['download']);
    $path = '/var/backups/repeater/' . $name;
    if (is_file($path) && preg_match('/\.tar\.gz$/', $name)) {
        send_backup($path);
    }
    $message = 'Arquivo de backup nao encontrado.';
}

$files = backup_files();
$latest = $files[0] ?? null;
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Backup</title>
<style>
body{font-family:Arial,sans-serif;background:#111827;color:#e5e7eb;margin:0;padding:22px}
a{color:#7dd3fc}.box{max-width:860px;background:#1f2937;border:1px solid #374151;border-radius:8px;padding:18px}
button,.btn{display:inline-block;background:#2563eb;color:white;border:0;border-radius:7px;padding:10px 14px;font-weight:700;text-decoration:none;cursor:pointer}
.btn.secondary{background:#374151}.muted{color:#9ca3af}.ok{color:#22c55e}.warn{color:#facc15}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}.last{background:#111827;border:1px solid #374151;border-radius:7px;padding:12px;margin-top:14px}
pre{white-space:pre-wrap;background:#0b1220;border:1px solid #334155;border-radius:7px;padding:12px}
</style>
</head>
<body>
<div class="box">
<p><a href="index.php">Voltar</a></p>
<h1>Backup da repetidora</h1>
<p class="muted">Clique em gerar para baixar um unico arquivo de backup no computador. Esse mesmo arquivo e usado na restauracao.</p>
<div class="actions">
  <form method="post">
    <input type="hidden" name="action" value="create_download">
    <button type="submit">Gerar e baixar backup</button>
  </form>
  <a class="btn secondary" href="restore.php">Restaurar backup</a>
  <a class="btn secondary" href="downgrade.php">Downgrade / versao anterior</a>
</div>
<?php if ($message): ?><p class="warn"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if ($latest): $name = basename($latest); ?>
  <div class="last">
    <strong>Ultimo backup:</strong> <?php echo htmlspecialchars($name); ?><br>
    <span class="muted"><?php echo date('d/m/Y H:i:s', filemtime($latest)); ?> - <?php echo number_format(filesize($latest) / 1024, 1, ',', '.'); ?> KB</span><br><br>
    <a class="btn secondary" href="?download=<?php echo urlencode($name); ?>">Baixar ultimo backup novamente</a>
  </div>
<?php else: ?>
  <p class="muted">Nenhum backup encontrado ainda.</p>
<?php endif; ?>
<?php if ($output): ?><pre><?php echo htmlspecialchars($output); ?></pre><?php endif; ?>
</div>
</body>
</html>
