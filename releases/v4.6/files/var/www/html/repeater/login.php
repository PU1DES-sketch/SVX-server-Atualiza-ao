<?php
require_once __DIR__ . '/auth.php';

$error = '';
$next = safe_next_path($_GET['next'] ?? $_POST['next'] ?? 'index.php?page=config');

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: index.php');
    exit;
}

if (is_logged_in() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $next);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode((string)@file_get_contents('/etc/repeater/web_auth.json'), true);
    $user = trim((string)($_POST['user'] ?? ''));
    $pass = (string)($_POST['pass'] ?? '');

    if ($user === (string)($data['user'] ?? 'admin') && password_verify($pass, (string)($data['pass'] ?? ''))) {
        $_SESSION['repeater_logged'] = true;
        header('Location: ' . $next);
        exit;
    }
    $error = 'Usuario ou senha invalidos';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Login Protoradio</title>
<style>
body{background:#111;color:#fff;font-family:Arial,Helvetica,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
.box{background:#1e1e1e;padding:30px;border-radius:12px;width:320px;box-shadow:0 0 20px #000}
.brand{display:flex;flex-direction:column;align-items:center;gap:10px;margin-bottom:10px}
.brand img{width:46px;height:46px;border-radius:12px;box-shadow:0 8px 18px rgba(0,0,0,.32)}
input,button,a{width:100%;padding:12px;margin:8px 0;border-radius:6px;border:0;box-sizing:border-box}
button{background:#00aaff;color:white;font-weight:bold;cursor:pointer}
.back{display:block;text-align:center;background:#2d3748;color:#fff;text-decoration:none}
.err{color:#ff6666;text-align:center}
</style>
</head>
<body>
<div class="box">
<div class="brand"><img src="protoradio-mark.svg" alt="Protoradio"><h2 style="margin:0">Painel Protoradio</h2></div>
<?php if($error): ?><div class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">
<input type="text" name="user" placeholder="Usuario" required>
<input type="password" name="pass" placeholder="Senha" required>
<button type="submit">ENTRAR NO MODO DE EDICAO</button>
</form>
<a class="back" href="index.php">VOLTAR PARA DASHBOARD</a>
</div>
</body>
</html>
