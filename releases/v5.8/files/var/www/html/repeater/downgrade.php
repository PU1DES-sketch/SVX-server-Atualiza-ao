<?php
require_once __DIR__ . '/auth.php';
require_login();

$message = null;
$output = null;

function downgrade_points(): array {
    $items = [];
    foreach (glob('/var/backups/repeater/update-*') ?: [] as $path) {
        if (!is_dir($path)) continue;
        $base = basename($path);
        if (!preg_match('/^update-([0-9.]+)-([0-9]{8})-([0-9]{6})$/', $base, $m)) continue;
        $items[] = [
            'id' => $base,
            'version' => $m[1],
            'time' => filemtime($path) ?: 0,
            'date' => DateTime::createFromFormat('Ymd His', $m[2] . ' ' . $m[3]),
            'files' => (int)trim((string)shell_exec('find ' . escapeshellarg($path) . ' -type f | wc -l')),
            'size' => (int)trim((string)shell_exec('du -sk ' . escapeshellarg($path) . " | awk '{print $1}'")),
        ];
    }
    usort($items, fn($a, $b) => $b['time'] <=> $a['time']);
    return array_slice($items, 0, 10);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
    $id = basename((string)($_POST['id'] ?? ''));
    if (preg_match('/^update-[0-9.]+-[0-9]{8}-[0-9]{6}$/', $id)) {
        $output = shell_exec('sudo /usr/local/bin/repeater-downgrade ' . escapeshellarg($id) . ' 2>&1');
        $message = 'Downgrade executado. Confira o resultado abaixo.';
    } else {
        $message = 'Ponto de restauracao invalido.';
    }
}

$items = downgrade_points();
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Downgrade</title>
<style>
body{font-family:Arial,sans-serif;background:#111827;color:#e5e7eb;margin:0;padding:22px}
a{color:#7dd3fc}.box{max-width:980px;background:#1f2937;border:1px solid #374151;border-radius:8px;padding:18px}
button,.btn{display:inline-block;background:#2563eb;color:white;border:0;border-radius:7px;padding:9px 12px;font-weight:700;text-decoration:none;cursor:pointer}
.btn.secondary{background:#374151}.btn.red,button.red{background:#b91c1c}.muted{color:#9ca3af}.warn{color:#facc15}
table{width:100%;border-collapse:collapse;margin-top:14px}th,td{text-align:left;border-bottom:1px solid #374151;padding:9px;font-size:13px}
pre{white-space:pre-wrap;background:#0b1220;border:1px solid #334155;border-radius:7px;padding:12px}
</style>
</head>
<body>
<div class="box">
<p><a href="index.php">Voltar</a> | <a href="backup.php">Backup</a></p>
<h1>Downgrade / restaurar versao anterior</h1>
<p class="muted">Lista os 10 ultimos backups automaticos criados antes de atualizacoes. Use para voltar para uma versao que estava funcionando.</p>
<?php if ($message): ?><p class="warn"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (!$items): ?>
  <p class="muted">Nenhum ponto de restauracao encontrado.</p>
<?php else: ?>
<table>
  <thead><tr><th>Backup</th><th>Versao anterior</th><th>Data</th><th>Arquivos</th><th>Tamanho</th><th>Acao</th></tr></thead>
  <tbody>
  <?php foreach ($items as $item): ?>
    <tr>
      <td><?php echo htmlspecialchars($item['id']); ?></td>
      <td><?php echo htmlspecialchars($item['version']); ?></td>
      <td><?php echo $item['date'] ? htmlspecialchars($item['date']->format('d/m/Y H:i:s')) : '-'; ?></td>
      <td><?php echo (int)$item['files']; ?></td>
      <td><?php echo number_format($item['size'] / 1024, 1, ',', '.'); ?> MB</td>
      <td>
        <form method="post" onsubmit="return confirm('Restaurar <?php echo htmlspecialchars($item['id']); ?>? O sistema vai reiniciar o SvxLink.');">
          <input type="hidden" name="action" value="restore">
          <input type="hidden" name="id" value="<?php echo htmlspecialchars($item['id']); ?>">
          <button class="red" type="submit">Restaurar</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php if ($output): ?><h2>Resultado</h2><pre><?php echo htmlspecialchars($output); ?></pre><?php endif; ?>
</div>
</body>
</html>
