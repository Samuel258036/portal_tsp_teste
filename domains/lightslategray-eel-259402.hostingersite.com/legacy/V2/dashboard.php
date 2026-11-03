<?php require_once 'partials.php'; header_page('Visão geral','dash'); $r=role();$cases=visible_cases(); ?>
<header class="pagehead"><div><span class="kicker">PORTAL TSP</span><h1><?= $r==='cliente'?'Olá, '.e(explode(' ',$_SESSION['user']['name'])[0]).'.':'Visão geral' ?></h1><p><?= $r==='admin'?'Administração completa do portal, acessos e operação.':($r==='gestao'?'Acompanhamento da carteira e operação do escritório.':($r==='adv'?'Sua carteira, atualizações e pendências em um só lugar.':'Acompanhe as informações do seu atendimento.')) ?></p></div><span class="badge"><?=e(label_role($r))?></span></header>
<div class="metrics">
<?php if(in_array($r,['admin','gestao'])):?><div class="metric"><small>CLIENTES ATIVOS</small><strong><?=count(array_filter($_SESSION['clients'],fn($x)=>$x['status']==='Ativo'))?></strong><span>base cadastrada</span></div><?php endif;?>
<div class="metric"><small><?= $r==='adv'?'MINHA CARTEIRA':'PROCESSOS' ?></small><strong><?=count($cases)?></strong><span>em acompanhamento</span></div>
<div class="metric"><small>CHAMADOS</small><strong><?=count($_SESSION['tickets'])?></strong><span>registrados</span></div>
<div class="metric"><small>ATUALIZAÇÕES</small><strong><?=count($_SESSION['moves'])?></strong><span>movimentações</span></div></div>
<section class="card"><div class="sectionhead"><div><span class="kicker">ACOMPANHAMENTO</span><h2>Processos recentes</h2></div><a class="link" href="processos.php">Ver todos →</a></div>
<?php foreach(array_slice($cases,0,3) as $c):?><div class="row"><div><b><?=e($c['title'])?></b><small><?=e($c['number'])?> · <?=e($c['area'])?></small></div><div><small>Próximo passo</small><b><?=e($c['next'])?></b></div><span class="status"><?=e($c['status'])?></span></div><?php endforeach;?></section>
<?php footer_page(); ?>