<?php
require_once __DIR__ . '/auth.php';
require_login();

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'poweroff') {
    $sent = true;
    shell_exec('sudo /usr/sbin/shutdown -h now >/tmp/repeater_shutdown.log 2>/tmp/repeater_shutdown_error.log &');
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Desligar Raspberry</title>
<style>
body{font-family:Arial,sans-serif;background:#111827;color:#e5e7eb;margin:0;padding:22px}
a{color:#7dd3fc}.box{max-width:720px;background:#1f2937;border:1px solid #374151;border-radius:8px;padding:18px}
button,.btn{display:inline-block;background:#dc2626;color:white;border:0;border-radius:7px;padding:11px 15px;font-weight:700;text-decoration:none;cursor:pointer}
.btn.secondary{background:#374151}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.muted{color:#9ca3af}.ok{color:#22c55e}
.bar{height:18px;background:#0f172a;border-radius:999px;overflow:hidden;border:1px solid #334155;margin-top:14px}
.bar span{display:block;height:100%;width:100%;background:linear-gradient(90deg,#f97316,#22c55e);animation:pulse 1.1s infinite}
@keyframes pulse{50%{opacity:.55}}
</style>
</head>
<body>
<div class="box">
<p><a href="index.php">Voltar</a></p>
<?php if ($sent): ?>
  <h1>Desligando Raspberry</h1>
  <p class="ok">Comando enviado. Aguarde a luz verde parar de piscar antes de remover a energia ou o cartao.</p>
  <div class="bar"><span></span></div>
<?php else: ?>
  <h1>Desligar Raspberry</h1>
  <p class="muted">Use essa opcao antes de tirar da tomada ou remover o cartao de memoria.</p>
  <form method="post" id="powerForm">
    <input type="hidden" name="action" value="poweroff">
    <div class="actions">
      <button type="submit" id="powerBtn">Desligar agora</button>
      <a class="btn secondary" href="index.php">Cancelar</a>
    </div>
  </form>
<?php endif; ?>
</div>
<script>
const form = document.getElementById('powerForm');
if (form) {
  form.addEventListener('submit', () => {
    const btn = document.getElementById('powerBtn');
    btn.disabled = true;
    btn.textContent = 'Enviando comando...';
  });
}
</script>
</body>
</html>
