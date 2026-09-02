<?php
require_once __DIR__ . '/auth.php';
require_login();

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $minutes = (int)($_POST['minutes'] ?? 120);
    if ($minutes < 15) $minutes = 15;
    if ($minutes > 480) $minutes = 480;

    if ($action === 'enable') {
        $raw = shell_exec('sudo /usr/local/bin/repeater-maint-ssh enable ' . $minutes . ' 2>&1');
        $message = 'Acesso tecnico liberado temporariamente.';
    } elseif ($action === 'disable') {
        $raw = shell_exec('sudo /usr/local/bin/repeater-maint-ssh disable 2>&1');
        $message = 'Acesso tecnico desligado.';
    } elseif ($action === 'rotate') {
        $raw = shell_exec('sudo /usr/local/bin/repeater-maint-ssh rotate 2>&1');
        $message = 'Senha tecnica renovada.';
    } else {
        $raw = '';
        $error = 'Acao invalida.';
    }
}

$statusRaw = shell_exec('sudo /usr/local/bin/repeater-maint-ssh status 2>&1');
$status = json_decode((string)$statusRaw, true);
if (!is_array($status) || empty($status['ok'])) {
    $status = [
        'active' => 0,
        'user' => 'protoadmin',
        'password' => 'indisponivel',
        'expires' => '',
    ];
    if ($error === '') {
        $error = 'Nao consegui ler o status do acesso tecnico.';
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Acesso tecnico</title>
<style>
body{font-family:Arial,sans-serif;background:#111827;color:#e5e7eb;margin:0;padding:22px}
a{color:#7dd3fc}.box{max-width:900px;background:#1f2937;border:1px solid #374151;border-radius:8px;padding:18px}
.ok{background:#052e16;color:#bbf7d0;border:1px solid #166534;border-radius:8px;padding:12px;margin:14px 0}
.warn{background:#3f2a00;color:#fde68a;border:1px solid #92400e;border-radius:8px;padding:12px;margin:14px 0}
.err{background:#450a0a;color:#fecaca;border:1px solid #b91c1c;border-radius:8px;padding:12px;margin:14px 0}
.muted{color:#9ca3af}
button,.btn{display:inline-block;background:#2563eb;color:white;border:0;border-radius:7px;padding:10px 14px;font-weight:700;text-decoration:none;cursor:pointer}
.btn.secondary{background:#374151}.btn.danger{background:#b91c1c}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin:14px 0}
.card{background:#0b1220;border:1px solid #334155;border-radius:8px;padding:14px;margin:14px 0}
label{display:block;margin-bottom:6px;font-size:13px}
select{padding:10px;border-radius:6px;border:1px solid #475569;background:#111827;color:#fff}
code{background:#0f172a;padding:2px 6px;border-radius:5px}
</style>
</head>
<body>
<div class="box">
<p><a href="index.php?page=apply">Voltar</a></p>
<h1>Acesso tecnico de emergencia</h1>
<p class="muted">Este acesso serve somente para manutencao. O SSH fica desligado por padrao e pode ser liberado por tempo limitado.</p>

<?php if ($message): ?><div class="ok"><?php echo h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="err"><?php echo h($error); ?></div><?php endif; ?>

<div class="card">
  <p><strong>Status:</strong> <?php echo !empty($status['active']) ? 'ATIVO' : 'DESLIGADO'; ?></p>
  <p><strong>Usuario tecnico:</strong> <code><?php echo h($status['user'] ?? 'protoadmin'); ?></code></p>
  <p><strong>Senha tecnica:</strong> <code><?php echo h($status['password'] ?? 'indisponivel'); ?></code></p>
  <p><strong>Expira em:</strong> <?php echo h($status['expires'] ?? ''); ?><?php if (empty($status['expires'])) echo ' --'; ?></p>
</div>

<div class="warn">
  Guarde essa senha com cuidado. Ela nao aparece na dashboard publica. Quando o acesso tecnico estiver desligado, a porta SSH permanece fechada.
</div>

<div class="actions">
  <form method="post">
    <input type="hidden" name="action" value="enable">
    <label for="minutes">Liberar por:</label>
    <select id="minutes" name="minutes">
      <option value="30">30 minutos</option>
      <option value="60">1 hora</option>
      <option value="120" selected>2 horas</option>
      <option value="240">4 horas</option>
      <option value="480">8 horas</option>
    </select>
    <button type="submit">LIGAR ACESSO TECNICO</button>
  </form>

  <form method="post">
    <input type="hidden" name="action" value="rotate">
    <button class="btn secondary" type="submit">GERAR NOVA SENHA</button>
  </form>

  <form method="post" onsubmit="return confirm('Desligar o acesso tecnico agora?');">
    <input type="hidden" name="action" value="disable">
    <button class="btn danger" type="submit">DESLIGAR AGORA</button>
  </form>
</div>
</div>
</body>
</html>
