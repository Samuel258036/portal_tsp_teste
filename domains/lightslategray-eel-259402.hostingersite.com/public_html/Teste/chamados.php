<?php
require_once __DIR__ . '/partials.php'; require_login();
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $assunto=trim($_POST['assunto']??''); $categoria=trim($_POST['categoria']??'Geral'); $mensagem=trim($_POST['mensagem']??'');
  if ($assunto && $mensagem) {
    array_unshift($_SESSION['chamados'], ['id'=>'TSP-'.random_int(1000,9999),'assunto'=>$assunto,'categoria'=>$categoria,'status'=>'Recebido','data'=>date('d/m/Y'),'mensagens'=>[['autor'=>'Você','texto'=>$mensagem]]]);
    header('Location: chamados.php?ok=1'); exit;
  }
}
page_header('Chamados','chamados');
?>
<header class="page-head"><div><p class="eyebrow">ATENDIMENTO</p><h1>Chamados</h1><p class="muted">Envie uma solicitação e acompanhe as respostas da equipe.</p></div><button class="btn primary" onclick="document.getElementById('novo').showModal()">+ Novo chamado</button></header>
<?php if(isset($_GET['ok'])):?><div class="alert success">Chamado criado com sucesso.</div><?php endif;?>
<div class="stack">
<?php foreach($_SESSION['chamados'] as $c): ?><section class="card ticket"><div class="section-title"><div><small><?= e($c['id'].' • '.$c['categoria'].' • '.$c['data']) ?></small><h2><?= e($c['assunto']) ?></h2></div><span class="pill"><?= e($c['status']) ?></span></div>
<?php foreach($c['mensagens'] as $m): ?><div class="message <?= $m['autor']==='Equipe TSP'?'team':'' ?>"><b><?= e($m['autor']) ?></b><p><?= e($m['texto']) ?></p></div><?php endforeach;?></section><?php endforeach;?>
</div>
<dialog id="novo"><form method="post" class="modal"><div class="section-title"><h2>Novo chamado</h2><button type="button" class="close" onclick="document.getElementById('novo').close()">×</button></div><label>Assunto<input name="assunto" required></label><label>Categoria<select name="categoria"><option>Processo</option><option>Financeiro</option><option>Documentos</option><option>Geral</option></select></label><label>Mensagem<textarea name="mensagem" rows="5" required></textarea></label><button class="btn primary full">Enviar chamado</button></form></dialog>
<?php page_footer(); ?>