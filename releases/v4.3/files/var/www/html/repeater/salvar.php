<?php
require_once __DIR__ . '/auth.php';
require_login();

date_default_timezone_set('America/Sao_Paulo');

$configFile = '/etc/repeater/config.json';
$svxFile    = '/etc/svxlink/svxlink.conf';

function clean($v) {
    return trim(str_replace(["\n", "\r"], '', $v ?? ''));
}

function update_ini_value($txt, $section, $key, $value) {
    $value = clean($value);
    $pattern = '/(\[' . preg_quote($section, '/') . '\][\s\S]*?)(^' . preg_quote($key, '/') . '\s*=.*$)/m';

    if (preg_match($pattern, $txt)) {
        return preg_replace($pattern, '$1' . $key . '=' . $value, $txt, 1);
    }

    $sectionPattern = '/(\[' . preg_quote($section, '/') . '\][^\n]*\n)/';
    if (preg_match($sectionPattern, $txt)) {
        return preg_replace($sectionPattern, '$1' . $key . '=' . $value . "\n", $txt, 1);
    }

    return rtrim($txt) . "\n\n[$section]\n$key=$value\n";
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$authData = json_decode((string)@file_get_contents('/etc/repeater/web_auth.json'), true);
$confirmPass = (string)($_POST['confirm_pass'] ?? '');
if ($confirmPass === '' || !password_verify($confirmPass, (string)($authData['pass'] ?? ''))) {
    header("Location: index.php?page=apply&erro=" . urlencode('Senha de confirmacao invalida. Nada foi salvo.'));
    exit;
}

if (!is_dir('/etc/repeater')) {
    mkdir('/etc/repeater', 0755, true);
}

$config = [];
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
    if (!is_array($config)) $config = [];
}

foreach ($_POST as $key => $value) {
    $config[$key] = clean($value);
}

$config['time_announce_enabled'] = isset($_POST['time_announce_enabled']) ? '1' : '0';

// Upload do áudio de identificação
$uploadedAudioTmp = '';

if (!empty($_FILES['audio_file']['name'])) {
    $uploadName = $_FILES['audio_file']['name'];
    $tmpName = $_FILES['audio_file']['tmp_name'];
    $ext = strtolower(pathinfo($uploadName, PATHINFO_EXTENSION));

    if (in_array($ext, ['mp3', 'wav'])) {
        $dest = "/tmp/repeater_id_upload." . $ext;
        if (move_uploaded_file($tmpName, $dest)) {
            chmod($dest, 0644);
            $uploadedAudioTmp = $dest;
            $config['id_audio'] = '/var/lib/repeater/id.wav';
        }
    }
}

/* ===== WIFI_NMCLI_START ===== */
$wifi_ssid = trim($_POST['wifi_ssid'] ?? ($config['wifi_ssid'] ?? ''));
$wifi_pass = trim($_POST['wifi_pass'] ?? ($config['wifi_pass'] ?? ''));

$config['wifi_ssid'] = $wifi_ssid;
$config['wifi_pass'] = $wifi_pass;

if ($wifi_ssid !== '' && $wifi_pass !== '') {
    $ssid_arg = escapeshellarg($wifi_ssid);
    $pass_arg = escapeshellarg($wifi_pass);

    shell_exec("sudo /usr/local/bin/repeater-wifi-connect $ssid_arg $pass_arg >/tmp/repeater_wifi_connect.log 2>/tmp/repeater_wifi_error.log");
}
/* ===== WIFI_NMCLI_END ===== */

file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Backup do svxlink.conf
if (file_exists($svxFile)) {
    copy($svxFile, $svxFile . '.bak-' . date('Ymd-His'));
}

$svx = file_exists($svxFile) ? file_get_contents($svxFile) : '';

// Identificação
if (!empty($config['callsign'])) {
    $baseCall = strtoupper($config['callsign']);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'CALLSIGN', $baseCall);
    $svx = update_ini_value($svx, 'ReflectorLogic', 'CALLSIGN', $baseCall . '-R');
    $svx = update_ini_value($svx, 'ModuleEchoLink', 'CALLSIGN', $baseCall . '-L');
}
// Intervalo ID
if (isset($config['id_interval']) && $config['id_interval'] !== '') {
    $svx = update_ini_value($svx, 'RepeaterLogic', 'ID_INTERVAL', (int)$config['id_interval']);
}


// Identificacao automatica
$idInterval = isset($config['id_interval']) && $config['id_interval'] !== '' ? (int)$config['id_interval'] : 10;
$idMode = $config['id_mode'] ?? 'cw';
$idAudio = $config['id_audio'] ?? '/var/lib/repeater/id.wav';

$svx = update_ini_value($svx, 'RepeaterLogic', 'SHORT_IDENT_INTERVAL', $idInterval);
$svx = update_ini_value($svx, 'RepeaterLogic', 'LONG_IDENT_INTERVAL', $idInterval);
$svx = update_ini_value($svx, 'RepeaterLogic', 'IDENT_ONLY_AFTER_TX', 0);

if ($idMode === 'audio') {
    $svx = update_ini_value($svx, 'RepeaterLogic', 'SHORT_VOICE_ID_ENABLE', 0);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'LONG_VOICE_ID_ENABLE', 0);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'SHORT_CW_ID_ENABLE', 0);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'LONG_CW_ID_ENABLE', 0);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'SHORT_ANNOUNCE_ENABLE', 1);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'LONG_ANNOUNCE_ENABLE', 1);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'SHORT_ANNOUNCE_FILE', $idAudio);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'LONG_ANNOUNCE_FILE', $idAudio);
} elseif ($idMode === 'alt') {
    $svx = update_ini_value($svx, 'RepeaterLogic', 'SHORT_VOICE_ID_ENABLE', 0);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'LONG_VOICE_ID_ENABLE', 0);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'SHORT_CW_ID_ENABLE', 1);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'LONG_CW_ID_ENABLE', 1);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'SHORT_ANNOUNCE_ENABLE', 1);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'LONG_ANNOUNCE_ENABLE', 1);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'SHORT_ANNOUNCE_FILE', $idAudio);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'LONG_ANNOUNCE_FILE', $idAudio);
} else {
    $svx = update_ini_value($svx, 'RepeaterLogic', 'SHORT_VOICE_ID_ENABLE', 0);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'LONG_VOICE_ID_ENABLE', 0);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'SHORT_CW_ID_ENABLE', 1);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'LONG_CW_ID_ENABLE', 1);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'SHORT_ANNOUNCE_ENABLE', 0);
    $svx = update_ini_value($svx, 'RepeaterLogic', 'LONG_ANNOUNCE_ENABLE', 0);
}


// Rabicho / tempo antes do bip de cortesia
if (isset($config['hang_time']) && $config['hang_time'] !== '') {
    $svx = update_ini_value($svx, 'RepeaterLogic', 'RGR_SOUND_DELAY', (int)$config['hang_time']);
}

// Refletor
if (!empty($config['server_ip'])) {
    $svx = update_ini_value($svx, 'ReflectorLogic', 'HOST', $config['server_ip']);
}
if (!empty($config['server_port'])) {
    $svx = update_ini_value($svx, 'ReflectorLogic', 'PORT', (int)$config['server_port']);
}
if (isset($config['server_pass']) && $config['server_pass'] !== '') {
    $svx = update_ini_value($svx, 'ReflectorLogic', 'AUTH_KEY', $config['server_pass']);
}

/* ===== ID_MODE_AUTO_START ===== */
$id_mode = $_POST['id_mode'] ?? ($config['id_mode'] ?? 'cw');
$config['id_mode'] = $id_mode;

if ($id_mode === "audio") {
    $svx = preg_replace('/^SHORT_VOICE_ID_ENABLE=.*/m', 'SHORT_VOICE_ID_ENABLE=0', $svx);
    $svx = preg_replace('/^LONG_VOICE_ID_ENABLE=.*/m', 'LONG_VOICE_ID_ENABLE=0', $svx);
    $svx = preg_replace('/^SHORT_CW_ID_ENABLE=.*/m', 'SHORT_CW_ID_ENABLE=0', $svx);
    $svx = preg_replace('/^LONG_CW_ID_ENABLE=.*/m', 'LONG_CW_ID_ENABLE=0', $svx);
    $svx = preg_replace('/^SHORT_ANNOUNCE_ENABLE=.*/m', 'SHORT_ANNOUNCE_ENABLE=1', $svx);
    $svx = preg_replace('/^LONG_ANNOUNCE_ENABLE=.*/m', 'LONG_ANNOUNCE_ENABLE=1', $svx);
    $svx = preg_replace('/^SHORT_ANNOUNCE_FILE=.*/m', 'SHORT_ANNOUNCE_FILE=/var/lib/repeater/id.wav', $svx);
    $svx = preg_replace('/^LONG_ANNOUNCE_FILE=.*/m', 'LONG_ANNOUNCE_FILE=/var/lib/repeater/id.wav', $svx);
} elseif ($id_mode === "cw") {
    $svx = preg_replace('/^SHORT_VOICE_ID_ENABLE=.*/m', 'SHORT_VOICE_ID_ENABLE=0', $svx);
    $svx = preg_replace('/^LONG_VOICE_ID_ENABLE=.*/m', 'LONG_VOICE_ID_ENABLE=0', $svx);
    $svx = preg_replace('/^SHORT_CW_ID_ENABLE=.*/m', 'SHORT_CW_ID_ENABLE=1', $svx);
    $svx = preg_replace('/^LONG_CW_ID_ENABLE=.*/m', 'LONG_CW_ID_ENABLE=1', $svx);
    $svx = preg_replace('/^SHORT_ANNOUNCE_ENABLE=.*/m', 'SHORT_ANNOUNCE_ENABLE=0', $svx);
    $svx = preg_replace('/^LONG_ANNOUNCE_ENABLE=.*/m', 'LONG_ANNOUNCE_ENABLE=0', $svx);
}
/* ===== ID_MODE_AUTO_END ===== */

file_put_contents($svxFile, $svx);

// Áudio USB: no seu sistema a placa USB está como card 2

$mic = isset($config['mic_gain']) ? (int)$config['mic_gain'] : 50;
$spk = isset($config['spk_vol']) ? (int)$config['spk_vol'] : 50;

$micVal = round(($mic * 28) / 100);
$spkVal = round(($spk * 30) / 100);

shell_exec("amixer -c 2 set 'Mic' capture {$micVal} >/dev/null 2>&1");
shell_exec("amixer -c 2 set 'Speaker' {$spkVal} >/dev/null 2>&1");

// Prepara GPIOs configuráveis antes de reiniciar o SVXLink
shell_exec('sudo /usr/local/bin/repeater-gpio-setup 2>/dev/null');

// Aplica a configuracao no SVXLink como root e converte audio enviado para WAV.
$audioArg = $uploadedAudioTmp !== '' ? escapeshellarg($uploadedAudioTmp) : '';
shell_exec("sudo /usr/local/bin/repeater-apply-config $audioArg >/tmp/repeater_apply_config.log 2>/tmp/repeater_apply_config_error.log");
shell_exec('sudo /usr/local/bin/repeater-dtmf-action apply >/tmp/repeater_dtmf_apply.log 2>/tmp/repeater_dtmf_apply_error.log');

header("Location: index.php?sucesso=1");
exit;
?>
