<?php
require_once "auth.php";
require_login();

$message = '';
$output = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = trim($_POST['confirm'] ?? '');
    if ($confirm !== 'RESETAR') {
        $message = 'Digite RESETAR para confirmar.';
    } else {
        $cmd = 'sudo /usr/local/bin/repeater-factory-reset --confirm RESETAR 2>&1';
        $lines = [];
        $rc = 0;
        exec($cmd, $lines, $rc);
        $output = implode("\n", $lines);
        $ok = ($rc === 0);
        $message = $ok ? 'Repetidor resetado. Configuracoes limpas e servicos reiniciados.' : 'Falha ao resetar repetidor.';
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Resetar Repetidor</title>
<style>
body{margin:0;font-family:Arial;background:#111827;color:#e5e7eb}
.wrap{max-width:780px;margin:40px auto;padding:0 18px}
.card{background:#1f2937;border:1px solid #374151;border-radius:10px;padding:22px}
a{color:#93c5fd;text-decoration:none}
.danger{background:#7f1d1d;border:1px solid #ef4444;color:#fecaca;border-radius:8px;padding:14px;margin:16px 0}
.ok{background:#14532d;color:#bbf7d0;border-radius:8px;padding:14px;margin:16px 0}
input{width:100%;padding:12px;border-radius:8px;border:1px solid #4b5563;background:#111827;color:white;margin:8px 0 14px}
button{background:#dc2626;color:white;border:0;border-radius:8px;padding:12px 16px;font-weight:bold;cursor:pointer}
pre{white-space:pre-wrap;background:#020617;border:1px solid #374151;border-radius:8px;padding:12px;max-height:260px;overflow:auto}
</style>
</head>
<body>
<div class="wrap">
<p><a href="index.php">Voltar para configuracoes</a></p>
<div class="card">
<h1>Resetar Repetidor</h1>
<div class="danger">
<strong>Atencao:</strong> isso vai apagar indicativo, frequencia, servidor, senha do servidor, Wi-Fi salvo, audio de ID, logs, status e historico temporario.
O sistema, o painel, as atualizacoes, DTMF padrao e o pacote de voz permanecem instalados.
</div>
<?php if ($message): ?><div class="<?php echo $ok ? 'ok' : 'danger'; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<form method="post" onsubmit="return confirm('Tem certeza que deseja resetar e limpar os dados deste repetidor?');">
<label>Digite RESETAR para confirmar:</label>
<input name="confirm" autocomplete="off" placeholder="RESETAR">
<button type="submit">CONFIRMAR RESET</button>
</form>
<?php if ($output): ?><h2>Resultado</h2><pre><?php echo htmlspecialchars($output); ?></pre><?php endif; ?>
</div>
</div>
</body>
</html>
