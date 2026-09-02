<?php
require_once __DIR__ . '/auth.php';
require_login();

$configFile = '/etc/repeater/config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
if (!is_array($config)) $config = [];

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enabled = isset($_POST['dtmf_enabled']) ? '1' : '0';
    $pass = $_POST['dtmf_password'] ?? '1234';
    $link_on = $_POST['dtmf_link_on'] ?? '1';
    $link_off = $_POST['dtmf_link_off'] ?? '2';
    $rep_on = $_POST['dtmf_repeater_on'] ?? '3';
    $rep_off = $_POST['dtmf_repeater_off'] ?? '4';
    $cmd = 'sudo /usr/local/bin/repeater-dtmf-config-save '
        . escapeshellarg($enabled) . ' '
        . escapeshellarg($pass) . ' '
        . escapeshellarg($link_on) . ' '
        . escapeshellarg($link_off) . ' '
        . escapeshellarg($rep_on) . ' '
        . escapeshellarg($rep_off) . ' 2>&1';
    $message = shell_exec($cmd);
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : $config;
    if (!is_array($config)) $config = [];
}

function cfg($key, $default) {
    global $config;
    return htmlspecialchars($config[$key] ?? $default, ENT_QUOTES);
}

$pass = $config['dtmf_password'] ?? '1234';
$codes = [
    ['Ativar link com servidor', $pass . ($config['dtmf_link_on'] ?? '1') . '#'],
    ['Desativar link com servidor', $pass . ($config['dtmf_link_off'] ?? '2') . '#'],
    ['Ativar repetidora', $pass . ($config['dtmf_repeater_on'] ?? '3') . '#'],
    ['Desativar repetidora', $pass . ($config['dtmf_repeater_off'] ?? '4') . '#'],
];
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>DTMF</title>
<style>
body{font-family:Arial,sans-serif;background:#111827;color:#e5e7eb;margin:0;padding:22px}
a{color:#7dd3fc}.box{max-width:900px;background:#1f2937;border:1px solid #374151;border-radius:8px;padding:18px}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.field label{display:block;font-size:12px;color:#cbd5e1;margin-bottom:5px;font-weight:bold}
input{width:100%;padding:9px;border:1px solid #374151;border-radius:7px;background:#111827;color:#e5e7eb}
button,.btn{display:inline-block;background:#2563eb;color:white;border:0;border-radius:7px;padding:10px 14px;font-weight:700;text-decoration:none;cursor:pointer}
.btn.secondary{background:#374151}.actions{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}.muted{color:#9ca3af}.ok{color:#22c55e}
table{width:100%;border-collapse:collapse;margin-top:14px}td,th{border-bottom:1px solid #374151;padding:10px;text-align:left}
.check{display:flex;align-items:center;gap:8px;margin:12px 0}.check input{width:auto}
</style>
</head>
<body>
<div class="box">
<p><a href="index.php">Voltar</a></p>
<h1>Comandos DTMF</h1>
<p class="muted">Aperte o PTT, digite o codigo DTMF completo e finalize com #.</p>
<?php if ($message): ?><p class="ok"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<form method="post">
  <label class="check"><input type="checkbox" name="dtmf_enabled" value="1" <?php echo (($config['dtmf_enabled'] ?? '1') === '1') ? 'checked' : ''; ?>> Habilitar comandos por DTMF</label>
  <div class="grid">
    <div class="field"><label>Senha DTMF</label><input name="dtmf_password" value="<?php echo cfg('dtmf_password', '1234'); ?>"></div>
    <div class="field"><label>Codigo para ativar link</label><input name="dtmf_link_on" value="<?php echo cfg('dtmf_link_on', '1'); ?>"></div>
    <div class="field"><label>Codigo para desativar link</label><input name="dtmf_link_off" value="<?php echo cfg('dtmf_link_off', '2'); ?>"></div>
    <div class="field"><label>Codigo para ativar repetidora</label><input name="dtmf_repeater_on" value="<?php echo cfg('dtmf_repeater_on', '3'); ?>"></div>
    <div class="field"><label>Codigo para desativar repetidora</label><input name="dtmf_repeater_off" value="<?php echo cfg('dtmf_repeater_off', '4'); ?>"></div>
  </div>
  <div class="actions"><button type="submit">Salvar DTMF</button><a class="btn secondary" href="index.php">Voltar</a></div>
</form>
<h2>Lista de codigos</h2>
<table>
  <thead><tr><th>Funcao</th><th>Codigo no radio</th></tr></thead>
  <tbody>
  <?php foreach ($codes as $row): ?>
    <tr><td><?php echo htmlspecialchars($row[0]); ?></td><td><strong><?php echo htmlspecialchars($row[1]); ?></strong></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
</body>
</html>
