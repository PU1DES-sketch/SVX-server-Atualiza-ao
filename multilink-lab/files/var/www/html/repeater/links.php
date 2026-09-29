<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function defaults(): array {
    $links = [];
    for ($index = 1; $index <= 3; $index++) {
        $links[] = [
            'id' => "link{$index}", 'enabled' => false, 'name' => "Link {$index}",
            'frequency' => '', 'audio_device' => '', 'audio_card_id' => '',
            'cos_gpio' => '', 'ptt_gpio' => '', 'input_gain' => 50,
            'output_volume' => 50, 'tot_seconds' => 100, 'master_to_link' => true,
            'link_to_master' => true, 'forward_id' => false, 'forward_time' => false,
        ];
    }
    return ['schema_version' => 1, 'links' => $links];
}

function read_cards(): array {
    $cards = @file_get_contents('/proc/asound/cards');
    if (!is_string($cards)) return [];
    $result = [];
    foreach (preg_split('/\R/', $cards) as $line) {
        if (preg_match('/^\s*(\d+)\s+\[([^\]]+)\]\:\s*(.+)$/', $line, $match)) {
            $result[] = ['number' => $match[1], 'id' => trim($match[2]), 'label' => trim($match[3])];
        }
    }
    return $result;
}

$config = defaults();
$saved = @file_get_contents('/etc/repeater/links.json');
$decoded = is_string($saved) ? json_decode($saved, true) : null;
if (is_array($decoded) && isset($decoded['links']) && is_array($decoded['links']) && count($decoded['links']) === 3) {
    $config = $decoded;
}
$cards = read_cards();
$logged = is_logged_in();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ProtoRadio | MultiLink Lab</title>
<style>
body{margin:0;background:#e9eef3;color:#1f2d3d;font-family:Arial,sans-serif}.top{background:#101820;color:#fff;padding:18px 4vw}.top a{color:#62b5ff;text-decoration:none}.wrap{max-width:1120px;margin:24px auto;padding:0 18px}.notice{border-left:4px solid #f0a500;background:#fff5d8;padding:13px 16px;margin-bottom:18px}.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}.tabs button{border:0;background:#32465a;color:#fff;padding:11px 16px;cursor:pointer}.tabs button.active{background:#1479c9}.panel{background:#fff;padding:20px;box-shadow:0 1px 4px #0002}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.field{display:flex;flex-direction:column;gap:6px}.field input,.field select{padding:10px;border:1px solid #b8c4d0;border-radius:3px}.switch{display:flex;align-items:center;gap:8px;min-height:40px}.actions{display:flex;gap:12px;margin-top:22px}.save{background:#148943;color:#fff;border:0;padding:12px 18px;cursor:pointer}.back{background:#50657a;color:#fff;text-decoration:none;padding:12px 18px}.cards{font-size:13px;color:#536373;margin:12px 0}@media(max-width:760px){.grid{grid-template-columns:1fr}.wrap{padding:0 10px}}
</style>
</head>
<body>
<header class="top"><strong>PROTORADIO</strong> &middot; MultiLink Lab <span style="float:right"><a href="index.php">Voltar ao painel</a></span></header>
<main class="wrap">
<div class="notice"><strong>Ambiente de bancada:</strong> salvar esta tela apenas registra os parâmetros. Nenhuma rota de áudio, GPIO, PTT ou COS é ativada ainda.</div>
<?php if (isset($_GET['ok'])): ?><div class="notice" style="border-color:#148943;background:#e6f7ec">Configuração salva. O roteamento RF continua desligado.</div><?php endif; ?>
<div class="tabs"><button class="active" data-tab="master">Repetidor Master</button><?php for ($i=1;$i<=3;$i++): ?><button data-tab="link<?= $i ?>">Link <?= $i ?></button><?php endfor; ?></div>
<section class="panel" id="master"><h2>Repetidor Master</h2><p>O Master mantém as configurações já existentes: indicativo, identificação, hora, DTMF, refletor e interface de áudio principal.</p><p>Os Links não alteram esses dados. Eles só terão interface USB e GPIO próprios na etapa de RF.</p><?php if ($cards): ?><div class="cards"><strong>Placas ALSA detectadas:</strong> <?php foreach ($cards as $card) echo h("card {$card['number']}: {$card['id']} ({$card['label']}) "); ?></div><?php endif; ?></section>
<form action="links-save.php" method="post" id="links-form">
<?php foreach ($config['links'] as $position => $link): $number=$position+1; ?>
<section class="panel link-panel" id="link<?= $number ?>" hidden>
<h2>Link <?= $number ?></h2>
<input type="hidden" name="links[<?= $position ?>][id]" value="link<?= $number ?>">
<div class="grid">
<label class="switch"><input type="checkbox" name="links[<?= $position ?>][enabled]" value="1" <?= !empty($link['enabled'])?'checked':'' ?>> Preparar Link <?= $number ?> para ativação futura</label>
<label class="field">Nome do Link<input name="links[<?= $position ?>][name]" maxlength="32" value="<?=h((string)$link['name'])?>"></label>
<label class="field">Frequência / descrição<input name="links[<?= $position ?>][frequency]" maxlength="24" value="<?=h((string)$link['frequency'])?>" placeholder="Ex.: VHF 146.000"></label>
<label class="field">Placa de áudio (ID ALSA)<input name="links[<?= $position ?>][audio_card_id]" maxlength="96" value="<?=h((string)$link['audio_card_id'])?>" placeholder="Ex.: Device"></label>
<label class="field">Dispositivo ALSA<input name="links[<?= $position ?>][audio_device]" maxlength="96" value="<?=h((string)$link['audio_device'])?>" placeholder="Ex.: hw:Device,0"></label>
<label class="field">GPIO COS<input inputmode="numeric" name="links[<?= $position ?>][cos_gpio]" maxlength="2" value="<?=h((string)$link['cos_gpio'])?>"></label>
<label class="field">GPIO PTT<input inputmode="numeric" name="links[<?= $position ?>][ptt_gpio]" maxlength="2" value="<?=h((string)$link['ptt_gpio'])?>"></label>
<label class="field">Ganho de entrada<input type="range" min="0" max="100" name="links[<?= $position ?>][input_gain]" value="<?=h((string)$link['input_gain'])?>"></label>
<label class="field">Volume de saída<input type="range" min="0" max="100" name="links[<?= $position ?>][output_volume]" value="<?=h((string)$link['output_volume'])?>"></label>
<label class="field">TOT (segundos)<input type="number" min="10" max="600" name="links[<?= $position ?>][tot_seconds]" value="<?=h((string)$link['tot_seconds'])?>"></label>
<label class="switch"><input type="checkbox" name="links[<?= $position ?>][master_to_link]" value="1" <?= !empty($link['master_to_link'])?'checked':'' ?>> Master pode encaminhar ao Link</label>
<label class="switch"><input type="checkbox" name="links[<?= $position ?>][link_to_master]" value="1" <?= !empty($link['link_to_master'])?'checked':'' ?>> Link pode encaminhar ao Master</label>
<label class="switch"><input type="checkbox" name="links[<?= $position ?>][forward_id]" value="1" <?= !empty($link['forward_id'])?'checked':'' ?>> Repassar identificação</label>
<label class="switch"><input type="checkbox" name="links[<?= $position ?>][forward_time]" value="1" <?= !empty($link['forward_time'])?'checked':'' ?>> Repassar hora certa</label>
</div></section>
<?php endforeach; ?>
<section class="panel link-panel" id="save" hidden><h2>Salvar configuração de bancada</h2><?php if ($logged): ?><label class="field" style="max-width:380px">Confirme a senha do painel<input type="password" required name="confirm_pass" autocomplete="current-password"></label><div class="actions"><button class="save" type="submit">SALVAR LINKS (SEM ATIVAR RF)</button></div><?php else: ?><p>Para alterar, <a href="login.php?next=links.php">entre com a senha do painel</a>. A visualização permanece liberada.</p><?php endif; ?></section>
</form>
</main>
<script>
const buttons=document.querySelectorAll('[data-tab]'), panels=document.querySelectorAll('.panel');
function show(tab){panels.forEach(p=>p.hidden=p.id!==tab && !(p.id==='save'&&tab.startsWith('link')));buttons.forEach(b=>b.classList.toggle('active',b.dataset.tab===tab));}
buttons.forEach(b=>b.addEventListener('click',()=>show(b.dataset.tab)));
</script>
</body></html>
