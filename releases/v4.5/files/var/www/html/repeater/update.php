<?php
require_once __DIR__ . '/auth.php';
require_login();

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    $result = shell_exec('sudo /usr/local/bin/repeater-update-apply 2>&1');
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'output' => $result ?: 'Atualizacao finalizada.',
            'version' => trim(@file_get_contents('/etc/repeater/version')) ?: '',
        ]);
        exit;
    }
}

$result = $result ?? null;
$error = null;
$raw = shell_exec('/usr/local/bin/repeater-update-check 2>&1');
$info = json_decode($raw, true);
if (!is_array($info)) {
    $error = $raw;
    $info = [
        'current' => trim(@file_get_contents('/etc/repeater/version')) ?: 'desconhecida',
        'latest' => 'indisponivel',
        'update_available' => false,
        'title' => '',
        'description' => '',
    ];
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Atualizacoes</title>
<style>
body{font-family:Arial,sans-serif;background:#111827;color:#e5e7eb;margin:0;padding:22px}
a{color:#7dd3fc}.box{max-width:900px;background:#1f2937;border:1px solid #374151;border-radius:8px;padding:18px}
.ok{color:#22c55e}.warn{color:#facc15}.err{color:#f87171}.muted{color:#9ca3af}
button,.btn{display:inline-block;background:#2563eb;color:white;border:0;border-radius:7px;padding:10px 14px;font-weight:700;text-decoration:none;cursor:pointer}
button:disabled{opacity:.65;cursor:wait}.btn.secondary{background:#374151}.actions{display:flex;gap:10px;flex-wrap:wrap;margin:14px 0}
.progress-wrap{display:none;background:#111827;border:1px solid #374151;border-radius:8px;padding:14px;margin:16px 0}
.bar{height:18px;background:#0f172a;border-radius:999px;overflow:hidden;border:1px solid #334155}
.bar span{display:block;height:100%;width:0;background:linear-gradient(90deg,#22c55e,#38bdf8);transition:width .45s ease}
.progress-top{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:8px}
.log{white-space:pre-wrap;background:#0b1220;border:1px solid #334155;border-radius:7px;padding:12px;max-height:280px;overflow:auto;color:#cbd5e1}
pre{white-space:pre-wrap;background:#0b1220;border:1px solid #334155;border-radius:7px;padding:12px}
</style>
</head>
<body>
<div class="box">
<p><a href="index.php">Voltar</a></p>
<h1>Atualizacoes</h1>
<div class="actions">
  <form method="get"><button type="submit">Verificar agora</button></form>
  <a class="btn secondary" href="backup.php">Backup</a>
</div>
<p>Versao instalada: <strong><?php echo htmlspecialchars($info['current']); ?></strong></p>
<p>Ultima versao: <strong><?php echo htmlspecialchars($info['latest']); ?></strong></p>
<p class="muted">Consulta feita em <?php echo date('d/m/Y H:i:s'); ?>.</p>
<?php if ($info['update_available']): ?>
  <p class="warn">Atualizacao disponivel.</p>
  <h2><?php echo htmlspecialchars($info['title']); ?></h2>
  <p><?php echo nl2br(htmlspecialchars($info['description'])); ?></p>
  <form method="post" id="updateForm">
    <input type="hidden" name="action" value="apply">
    <button id="applyBtn" type="submit">Atualizar agora</button>
  </form>
  <div class="progress-wrap" id="progressBox">
    <div class="progress-top">
      <strong id="progressText">Preparando atualizacao...</strong>
      <strong id="progressPct">0%</strong>
    </div>
    <div class="bar"><span id="progressBar"></span></div>
    <pre class="log" id="progressLog">Aguardando inicio...</pre>
  </div>
<?php else: ?>
  <p class="ok">Nenhuma atualizacao nova disponivel agora.</p>
<?php endif; ?>
<?php if ($error): ?><h2 class="err">Erro</h2><pre><?php echo htmlspecialchars($error); ?></pre><?php endif; ?>
<?php if ($result): ?><h2>Resultado</h2><pre><?php echo htmlspecialchars($result); ?></pre><?php endif; ?>
</div>
<script>
const form = document.getElementById('updateForm');
const box = document.getElementById('progressBox');
const btn = document.getElementById('applyBtn');
const bar = document.getElementById('progressBar');
const pct = document.getElementById('progressPct');
const txt = document.getElementById('progressText');
const log = document.getElementById('progressLog');
let progress = 0;
let timer = null;

function setProgress(value, message) {
  progress = Math.max(progress, Math.min(value, 100));
  bar.style.width = progress + '%';
  pct.textContent = progress + '%';
  if (message) {
    txt.textContent = message;
    log.textContent += "\n" + new Date().toLocaleTimeString() + " - " + message;
    log.scrollTop = log.scrollHeight;
  }
}

function startFakeProgress() {
  const steps = [
    [8, 'Conectando ao repositorio de atualizacoes...'],
    [18, 'Baixando manifesto...'],
    [32, 'Baixando pacote da nova versao...'],
    [48, 'Verificando integridade do pacote...'],
    [64, 'Instalando arquivos do painel...'],
    [78, 'Aplicando ajustes do sistema...'],
    [88, 'Reiniciando servicos da repetidora...'],
    [94, 'Finalizando atualizacao...']
  ];
  let i = 0;
  setProgress(3, 'Iniciando atualizacao...');
  timer = setInterval(() => {
    if (i < steps.length) {
      setProgress(steps[i][0], steps[i][1]);
      i++;
    } else if (progress < 96) {
      setProgress(progress + 1, 'Aguardando resposta do sistema...');
    }
  }, 1300);
}

if (form) {
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    box.style.display = 'block';
    btn.disabled = true;
    log.textContent = 'Atualizacao iniciada. Nao desligue o Raspberry.';
    startFakeProgress();
    try {
      const response = await fetch('update.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'fetch'},
        body: new FormData(form)
      });
      const data = await response.json();
      clearInterval(timer);
      setProgress(100, 'Atualizacao concluida.');
      log.textContent += "\n\nResultado:\n" + (data.output || 'Atualizacao finalizada.');
      log.textContent += "\n\nRecarregando painel em alguns segundos...";
      setTimeout(() => window.location.href = 'update.php', 4500);
    } catch (err) {
      clearInterval(timer);
      txt.textContent = 'Nao consegui confirmar o fim da atualizacao.';
      log.textContent += "\n\nSe o painel reiniciou, aguarde alguns segundos e atualize a pagina.";
      btn.disabled = false;
    }
  });
}
</script>
</body>
</html>
