<?php
require_once __DIR__ . '/auth.php';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$isLogged = is_logged_in();
$requestedPage = $_GET['page'] ?? 'dash';
$activePage = in_array($requestedPage, ['config', 'apply'], true) ? 'config' : 'dash';
$canEdit = $requestedPage === 'apply' && $isLogged;
$readonly = !$canEdit;
$lockAttr = $readonly ? ' disabled' : '';
$success = isset($_GET['sucesso']);
$error = trim((string)($_GET['erro'] ?? ''));

$config_path = '/etc/repeater/config.json';
$config = file_exists($config_path) ? json_decode((string)file_get_contents($config_path), true) : [];
if (!is_array($config)) {
    $config = [];
}

$cpu_temp = shell_exec("vcgencmd measure_temp | egrep -o '[0-9]*\\.[0-9]*'") . " C";
$mem_info = shell_exec("free -m | awk 'NR==2{printf \"%s/%s MB\", $3,$2}'");
$local_ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
$server_status = !empty($config['server_ip']) ? $config['server_ip'] : 'Desconectado';
$bips = ['Simples', 'Motorola', 'Kenwood', 'Icom', 'Yaesu', 'Hytera', 'Harris', 'Tait', 'Sepura', 'Custom'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Protoradio Panel v2.0</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; display: flex; background: #cbd5e0; color: #333; height: 100vh; overflow: hidden; }
        #sidebar { width: 220px; background: #111; color: #fff; display: flex; flex-direction: column; box-shadow: 2px 0 10px rgba(0,0,0,0.3); }
        #sidebar .header { padding: 20px 10px; text-align: center; background: #000; font-weight: bold; border-bottom: 1px solid #222; color: #3498db; letter-spacing: 1px; display:flex; flex-direction:column; align-items:center; gap:8px; }
        .brand-icon { width: 42px; height: 42px; border-radius: 12px; box-shadow: 0 8px 18px rgba(0,0,0,0.32); }
        .menu-item { padding: 15px 20px; cursor: pointer; border-bottom: 1px solid #1a1a1a; transition: 0.2s; font-size: 13px; color: #999; }
        .menu-item:hover { background: #222; color: #fff; }
        .menu-item.active { background: #3498db; color: #fff; border-right: 4px solid #fff; }
        #main { flex: 1; overflow-y: auto; padding: 20px; }
        .page { display: none; }
        .page.active { display: block; }
        .status-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 20px; }
        .stat-card { background: #fff; padding: 12px; border-radius: 4px; border-top: 3px solid #3498db; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card small { display: block; font-size: 9px; color: #888; text-transform: uppercase; margin-bottom: 4px; }
        .stat-card b { font-size: 13px; color: #2c3e50; }
        #tx-status { padding: 12px; text-align: center; border-radius: 4px; font-weight: bold; margin-bottom: 20px; background: #7f8c8d; color: #fff; text-transform: uppercase; font-size: 14px; }
        .tx-on { background: #c0392b !important; animation: blink 1s infinite; }
        @keyframes blink { 50% { opacity: 0.6; } }
        .box { background: #fff; border-radius: 4px; padding: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 14px; }
        .box h3 { margin: 0 0 10px 0; font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 6px; color: #2c3e50; }
        .config-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 9px 12px; align-items: end; }
        .field-group { margin-bottom: 4px; }
        .field-group label { display: block; font-size: 11px; font-weight: bold; margin-bottom: 3px; color: #666; }
        .field-group input, .field-group select { width: 80%; padding: 5px 7px; border: 1px solid #ddd; border-radius: 3px; font-size: 12px; background: #fafafa; min-height: 29px; }
        .field-group input[type="range"], .field-group input[type="file"], .wifi-actions select, .wide-2 input, .wide-2 select, .system-panel input, .system-panel select { width: 100% !important; }
        .field-group input:disabled, .field-group select:disabled { background:#f1f5f9; color:#64748b; cursor:not-allowed; }
        .section-head { grid-column: span 3; background: #f8f9fa; padding: 5px 10px; font-size: 11px; font-weight: bold; margin-top: 7px; border-radius: 2px; color: #3498db; border-left: 3px solid #3498db; }
        .btn-save { background: #27ae60; color: #fff; border: none; padding: 10px; border-radius: 3px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 14px; font-size: 14px; }
        .btn-update { background: #34495e; color: #fff; border: none; padding: 5px 10px; border-radius: 3px; font-size: 10px; cursor: pointer; }
        .btn-update:disabled { background:#94a3b8 !important; cursor:not-allowed; }
        .btn-danger { background: #b91c1c !important; }
        .btn-mini { background: #0f766e; color: white; border: none; padding: 7px 9px; border-radius: 3px; font-size: 10px; cursor: pointer; white-space: nowrap; }
        .btn-mini:disabled { background:#94a3b8; cursor:not-allowed; }
        .wide-2 { grid-column: span 2; }
        .full-width { grid-column: span 3; }
        .system-panel { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 5px; padding: 12px; }
        .system-title { font-size: 11px; font-weight: bold; color: #475569; margin-bottom: 8px; text-transform: uppercase; }
        .system-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .system-grid3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .system-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 5px; padding: 10px; min-height: 78px; }
        .checkline { display: inline-flex; align-items: center; gap: 7px; font-size: 12px; color: #334155; font-weight: bold; }
        .checkline input { width: auto; }
        .wifi-actions { display: flex; gap: 6px; align-items: center; }
        .wifi-actions select { flex: 1; min-width: 0; }
        .hint { display: block; margin-top: 4px; color: #6b7280; font-size: 10px; }
        .note { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; border-radius:6px; padding:10px 12px; margin-bottom:12px; font-size:12px; }
        .success { background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; border-radius:6px; padding:10px 12px; margin-bottom:12px; font-size:12px; }
        .error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; border-radius:6px; padding:10px 12px; margin-bottom:12px; font-size:12px; }
        .brand-footer { margin-top: auto; padding: 12px 10px; font-size: 10px; color: #6b7280; text-align: center; border-top: 1px solid #222; line-height: 1.4; }
        .watermark { position: fixed; right: 14px; bottom: 8px; color: rgba(17,24,39,.42); font-size: 11px; letter-spacing: .2px; pointer-events: none; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th { text-align: left; background: #f4f4f4; padding: 8px; border-bottom: 2px solid #ddd; }
        td { padding: 8px; border-bottom: 1px solid #eee; }
        .saving-bar { height: 16px; background:#111827; border-radius:999px; overflow:hidden; margin-top:14px; border:1px solid #374151; }
        .saving-bar span { display:block; height:100%; width:100%; background:linear-gradient(90deg,#22c55e,#38bdf8); animation:savingPulse 1s infinite; }
        @keyframes savingPulse { 50% { opacity:.45; } }
    </style>
</head>
<body>
    <div id="sidebar">
        <div class="header"><img class="brand-icon" src="protoradio-mark.svg" alt="Protoradio"><div>PROTORADIO</div></div>
        <div class="menu-item <?php echo $activePage === 'dash' ? 'active' : ''; ?>" onclick="showPage('dash', this)">DASHBOARD</div>
        <div class="menu-item <?php echo $activePage === 'config' && !$canEdit ? 'active' : ''; ?>" onclick="showPage('config', this)">VISUALIZAR CONFIGURACOES</div>
        <div class="menu-item <?php echo $canEdit ? 'active' : ''; ?>" onclick="<?php echo $isLogged ? "window.location.href='index.php?page=apply'" : "window.location.href='login.php?next=index.php%3Fpage%3Dapply'"; ?>">CONFIGURACOES</div>
        <div class="menu-item" onclick="location.reload()">ATUALIZAR PAINEL</div>
        <?php if ($isLogged): ?>
            <div class="menu-item" onclick="window.location.href='login.php?logout=1'">SAIR DO PAINEL</div>
        <?php endif; ?>
        <div class="brand-footer">Produzido por PI<br>Desenvolvido por PU1DES</div>
    </div>

    <div id="main">
        <div id="dash" class="page <?php echo $activePage === 'dash' ? 'active' : ''; ?>">
            <div id="tx-status">SISTEMA EM STANDBY</div>
            <div class="status-bar">
                <div class="stat-card"><small>CPU Temp</small><b id="cpu-temp"><?php echo h($cpu_temp); ?></b></div>
                <div class="stat-card"><small>Memoria</small><b><?php echo h($mem_info); ?></b></div>
                <div class="stat-card"><small>IP Local</small><b><?php echo h($local_ip); ?></b></div>
                <div class="stat-card"><small>Servidor</small><b><?php echo h($server_status); ?></b></div>
                <div class="stat-card"><small>Frequencia</small><b><?php echo h($config['frequencia'] ?? '---.---'); ?> MHz</b></div>
            </div>
            <div class="box">
                <h3>Ultimas 20 Chamadas</h3>
                <table>
                    <thead><tr><th>Horario</th><th>Duracao</th><th>Origem</th><th>Status</th></tr></thead>
                    <tbody id="call-log"><tr><td colspan="4" style="text-align:center; padding:20px; color:#999;">Aguardando trafego...</td></tr></tbody>
                </table>
            </div>
        </div>

        <div id="config" class="page <?php echo $activePage === 'config' ? 'active' : ''; ?>">
            <?php if ($success): ?><div class="success">Configuracoes aplicadas com sucesso.</div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>
            <?php if ($canEdit): ?>
                <div class="note">Modo de edicao liberado. Aqui voce pode alterar os parametros do repetidor. Ao salvar, o painel vai pedir a senha novamente para confirmar a aplicacao das configuracoes.</div>
            <?php else: ?>
                <div class="note">Modo somente leitura. Aqui qualquer pessoa pode visualizar como o repetidor esta configurado, mas nada pode ser salvo ou alterado.</div>
            <?php endif; ?>

            <div class="box">
                <h3><?php echo $canEdit ? 'Configuracoes da Repetidora' : 'Visualizacao das Configuracoes'; ?></h3>
                <form action="salvar.php" method="post" enctype="multipart/form-data" id="configForm">
                    <div class="config-grid">
                        <div class="section-head">Identificacao e Radio</div>
                        <div class="field-group"><label>Indicativo (CW):</label><input type="text" name="callsign" value="<?php echo h($config['callsign'] ?? ''); ?>"<?php echo $lockAttr; ?>></div>
                        <div class="field-group"><label>Intervalo CW (Min):</label><input type="number" name="id_interval" value="<?php echo h($config['id_interval'] ?? ''); ?>"<?php echo $lockAttr; ?>></div>
                        <div class="field-group"><label>TOT TX (Seg):</label><input type="number" name="tot" value="<?php echo h($config['tot'] ?? ''); ?>"<?php echo $lockAttr; ?>></div>
                        <div class="field-group"><label>Frequencia (MHz):</label><input type="text" name="frequencia" value="<?php echo h($config['frequencia'] ?? ''); ?>"<?php echo $lockAttr; ?>></div>
                        <div class="field-group"><label>Rabicho / HangTime (ms):</label><input type="number" name="hang_time" value="<?php echo h($config['hang_time'] ?? ''); ?>"<?php echo $lockAttr; ?>></div>
                        <div class="field-group">
                            <label>Bip de Cortesia:</label>
                            <select name="bip_cortesia"<?php echo $lockAttr; ?>>
                                <?php foreach ($bips as $b): $value = strtolower($b); ?>
                                    <option value="<?php echo h($value); ?>" <?php echo (($config['bip_cortesia'] ?? '') === $value) ? 'selected' : ''; ?>><?php echo h($b); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="section-head">Conexao com Servidor (Refletor)</div>
                        <div class="field-group"><label>Endereco IP / Host:</label><input type="text" name="server_ip" value="<?php echo h($config['server_ip'] ?? ''); ?>"<?php echo $lockAttr; ?>></div>
                        <div class="field-group"><label>Porta:</label><input type="number" name="server_port" value="<?php echo h($config['server_port'] ?? '5210'); ?>"<?php echo $lockAttr; ?>></div>
                        <div class="field-group"><label>Senha do Servidor:</label><input type="password" name="server_pass" value="<?php echo h($config['server_pass'] ?? ''); ?>"<?php echo $lockAttr; ?>></div>

                        <div class="section-head">Hardware e Audio Digital</div>
                        <div class="field-group"><label>Pino Cooler (GPIO):</label><input type="number" name="gpio_cooler" value="<?php echo h($config['gpio_cooler'] ?? ''); ?>"<?php echo $lockAttr; ?>></div>
                        <div class="field-group"><label>Tempo Cooler (Seg):</label><input type="number" name="cooler_tempo" value="<?php echo h($config['cooler_tempo'] ?? ''); ?>"<?php echo $lockAttr; ?>></div>
                        <div class="field-group"><label>Ganho Mic (USB):</label><input type="range" name="mic_gain" min="0" max="100" value="<?php echo h($config['mic_gain'] ?? 50); ?>"<?php echo $lockAttr; ?>></div>
                        <div class="field-group"><label>Volume Spk (USB):</label><input type="range" name="spk_vol" min="0" max="100" value="<?php echo h($config['spk_vol'] ?? 50); ?>"<?php echo $lockAttr; ?>></div>
                        <div class="field-group"><label>Modo ID:</label>
                            <select name="id_mode"<?php echo $lockAttr; ?>>
                                <option value="cw" <?php if (($config['id_mode'] ?? 'cw') === 'cw') echo 'selected'; ?>>CW</option>
                                <option value="audio" <?php if (($config['id_mode'] ?? 'cw') === 'audio') echo 'selected'; ?>>Audio</option>
                                <option value="alt" <?php if (($config['id_mode'] ?? 'cw') === 'alt') echo 'selected'; ?>>Alternado</option>
                            </select>
                        </div>
                        <div class="field-group"><label>Arquivo MP3:</label><input type="file" name="audio_file" style="font-size:10px;"<?php echo $lockAttr; ?>></div>

                        <div class="section-head">Rede e Sistema</div>
                        <div class="field-group wide-2">
                            <label>Rede Wi-Fi:</label>
                            <div class="wifi-actions">
                                <select name="wifi_ssid" id="wifi_ssid" data-current="<?php echo h($config['wifi_ssid'] ?? ''); ?>"<?php echo $lockAttr; ?>>
                                    <option value="<?php echo h($config['wifi_ssid'] ?? ''); ?>"><?php echo h(($config['wifi_ssid'] ?? '') ?: 'Buscar redes disponiveis'); ?></option>
                                </select>
                                <button type="button" class="btn-mini" onclick="loadWifiNetworks()"<?php echo $readonly ? ' disabled' : ''; ?>>BUSCAR</button>
                            </div>
                            <span class="hint" id="wifi_hint">Se nao conectar em nenhum Wi-Fi no boot, o AP REPETIDOR-CONFIG sera ativado.</span>
                        </div>
                        <div class="field-group"><label>Senha Wi-Fi:</label><input type="password" name="wifi_pass" placeholder="Digite a senha da rede selecionada"<?php echo $lockAttr; ?>></div>
                        <div class="field-group full-width system-panel">
                            <div class="system-grid3">
                                <div class="system-card">
                                    <div class="system-title">Automacao</div>
                                    <label class="checkline"><input type="checkbox" name="time_announce_enabled" value="1" <?php echo !empty($config['time_announce_enabled']) && $config['time_announce_enabled'] !== '0' ? 'checked' : ''; ?><?php echo $lockAttr; ?>> Falar hora certa a cada hora</label>
                                    <button type="button" class="btn-update" onclick="testTimeAnnounce()"<?php echo $readonly ? ' disabled' : ''; ?>>FALAR AGORA</button>
                                    <div class="hint" id="time_announce_result"></div>
                                </div>
                                <div class="system-card">
                                    <div class="system-title">DTMF</div>
                                    <div class="system-actions">
                                        <button type="button" class="btn-update" onclick="window.location.href='dtmf.php'"<?php echo $readonly ? ' disabled' : ''; ?>>CONFIGURAR DTMF</button>
                                    </div>
                                </div>
                                <div class="system-card">
                                    <div class="system-title">Manutencao</div>
                                    <div class="system-actions">
                                        <button type="button" class="btn-update" onclick="window.location.href='update.php'"<?php echo $readonly ? ' disabled' : ''; ?>>BUSCAR ATUALIZACOES</button>
                                        <button type="button" class="btn-update" onclick="window.location.href='backup.php'"<?php echo $readonly ? ' disabled' : ''; ?>>BACKUP</button>
                                        <button type="button" class="btn-update" onclick="window.location.href='restore.php'"<?php echo $readonly ? ' disabled' : ''; ?>>RESTAURAR BACKUP</button>
                                        <button type="button" class="btn-update" onclick="window.location.href='maintenance_ssh.php'"<?php echo $readonly ? ' disabled' : ''; ?>>ACESSO TECNICO</button>
                                        <button type="button" class="btn-update" onclick="window.location.href='senha.php'"<?php echo $readonly ? ' disabled' : ''; ?>>ALTERAR SENHA</button>
                                        <button type="button" class="btn-update btn-danger" onclick="window.location.href='reset.php'"<?php echo $readonly ? ' disabled' : ''; ?>>RESETAR REPETIDOR</button>
                                        <button type="button" class="btn-update btn-danger" onclick="window.location.href='shutdown.php'"<?php echo $readonly ? ' disabled' : ''; ?>>DESLIGAR</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if ($canEdit): ?>
                            <button type="submit" class="btn-save">SALVAR E REINICIAR REPETIDORA</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        async function testTimeAnnounce() {
            const target = document.getElementById('time_announce_result');
            if (target) target.textContent = 'Preparando fala da hora...';
            try {
                const res = await fetch('time_announce_now.php', { method: 'POST', cache: 'no-store' });
                const data = await res.json();
                if (target) target.textContent = data.message || (data.ok ? 'Hora preparada.' : 'Falha ao preparar hora.');
            } catch (err) {
                if (target) target.textContent = 'Falha ao chamar o teste da hora.';
            }
        }

        function showPage(id, btn) {
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
            document.getElementById(id).classList.add('active');
            if (btn) btn.classList.add('active');
        }

        function updateStatus() {
            fetch('status.php')
                .then(r => r.json())
                .then(data => {
                    liveRx = data;
                    document.getElementById('cpu-temp').innerText = data.temp;
                    const txBox = document.getElementById('tx-status');
                    if (data.tx === 'ON' || data.rx_live) {
                        txBox.innerText = data.rx_origem ? ('REPETIDORA RECEBENDO - ' + data.rx_origem) : 'REPETIDORA EM TRANSMISSAO (TX)';
                        txBox.classList.add('tx-on');
                    } else if (data.sql === '1' || data.sql === 1 || data.rx === 'ON') {
                        txBox.innerText = 'REPETIDORA RECEBENDO (COS)';
                        txBox.classList.add('tx-on');
                    } else {
                        txBox.innerText = 'SISTEMA EM STANDBY';
                        txBox.classList.remove('tx-on');
                    }
                })
                .catch(() => console.log('Aguardando dados do controlador...'));
        }

        let liveRx = null;

        function updateLogs() {
            Promise.all([
                fetch('log_parser.php').then(r => r.json()),
                fetch('status.php').then(r => r.json())
            ]).then(([data, status]) => {
                let html = '';

                if (status.rx_live) {
                    html += `<tr style="background:#d8f8df; font-weight:bold;">
                        <td>${status.rx_inicio_txt}</td>
                        <td>${status.rx_duracao} s</td>
                        <td>${status.rx_origem || 'Local'}</td>
                        <td style="color:#078b28;">AO VIVO</td>
                    </tr>`;
                }

                if (data.length > 0) {
                    data.forEach(call => {
                        html += `<tr>
                            <td>${call.horario}</td>
                            <td>${call.duracao || '--'}</td>
                            <td>${call.origem}</td>
                            <td>${call.status}</td>
                        </tr>`;
                    });
                } else if (!status.rx_live) {
                    html = '<tr><td colspan="4" style="text-align:center; padding:20px; color:#999;">Aguardando trafego...</td></tr>';
                }

                document.getElementById('call-log').innerHTML = html;
            }).catch(() => console.log('Erro ao carregar logs/status.'));
        }

        function loadWifiNetworks() {
            const select = document.getElementById('wifi_ssid');
            const hint = document.getElementById('wifi_hint');
            if (!select || select.disabled) return;
            hint.innerText = 'Buscando redes...';
            fetch('wifi_scan.php')
                .then(r => r.json())
                .then(items => {
                    const current = select.dataset.current || select.value || '';
                    select.innerHTML = '';
                    if (!items.length) {
                        const opt = document.createElement('option');
                        opt.value = current;
                        opt.textContent = current || 'Nenhuma rede encontrada';
                        select.appendChild(opt);
                        hint.innerText = 'Nenhuma rede encontrada agora.';
                        return;
                    }
                    items.forEach(item => {
                        const opt = document.createElement('option');
                        opt.value = item.ssid;
                        opt.textContent = item.ssid + ' - ' + item.signal + '%' + (item.security ? ' - ' + item.security : '');
                        if (item.ssid === current || item.active) opt.selected = true;
                        select.appendChild(opt);
                    });
                    hint.innerText = 'Selecione a rede, digite a senha e salve.';
                })
                .catch(() => {
                    hint.innerText = 'Nao consegui listar redes agora.';
                });
        }

        setInterval(updateStatus, 1000);
        updateStatus();
        setInterval(updateLogs, 1000);
        updateLogs();
    </script>

<?php if ($canEdit): ?>
<div id="confirmModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.65); z-index:9999; align-items:center; justify-content:center;">
  <div style="background:#1e1e1e; color:#fff; width:420px; max-width:92%; padding:25px; border-radius:14px; text-align:center; box-shadow:0 0 30px #000;">
    <div style="font-size:52px; color:#ffcc00;">!</div>
    <h2>Confirmar alteracoes</h2>
    <p>Digite a senha do painel para aplicar e reiniciar a repetidora.</p>
    <input id="confirmPass" type="password" placeholder="Senha do painel" style="width:100%; padding:12px; border-radius:8px; border:0; margin-top:10px;">
    <div style="display:flex; gap:10px; margin-top:20px;">
      <button type="button" onclick="closeConfirm()" style="flex:1; padding:12px; border:0; border-radius:8px; background:#555; color:white;">Cancelar</button>
      <button type="button" onclick="submitConfirm()" style="flex:1; padding:12px; border:0; border-radius:8px; background:#d9534f; color:white; font-weight:bold;">Salvar e Reiniciar</button>
    </div>
  </div>
</div>

<script>
let allowSubmit = false;
const cfgForm = document.getElementById('configForm');
if (cfgForm) {
  cfgForm.addEventListener('submit', function(e) {
    if (!allowSubmit) {
      e.preventDefault();
      document.getElementById('confirmModal').style.display = 'flex';
    }
  });
}
function closeConfirm() {
  document.getElementById('confirmModal').style.display = 'none';
}
function submitConfirm() {
  const pass = document.getElementById('confirmPass').value.trim();
  if (!pass) {
    alert('Digite a senha do painel para confirmar.');
    return;
  }
  let hidden = document.getElementById('confirm_pass');
  if (!hidden) {
    hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'confirm_pass';
    hidden.id = 'confirm_pass';
    cfgForm.appendChild(hidden);
  }
  hidden.value = pass;
  allowSubmit = true;
  const modal = document.getElementById('confirmModal');
  const panel = modal ? modal.querySelector('div') : null;
  if (panel) {
    panel.innerHTML = '<h2>Salvando configuracoes</h2><p>Aplicando ajustes e reiniciando a repetidora. Aguarde alguns segundos.</p><div class="saving-bar"><span></span></div>';
  }
  cfgForm.submit();
}
</script>
<?php endif; ?>

    <div class="watermark">Produzido por PI | Desenvolvido por PU1DES</div>
</body>
</html>
