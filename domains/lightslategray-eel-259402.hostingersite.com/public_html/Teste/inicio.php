<?php
require_once __DIR__ . '/partials.php'; require_login(); page_header('Início','inicio');
$first = explode(' ', $_SESSION['cliente']['nome'])[0];
?>
<header class="page-head"><div><p class="eyebrow">VISÃO GERAL</p><h1>Olá, <?= e($first) ?>.</h1><p class="muted">Acompanhe aqui as principais informações do seu atendimento.</p></div><span class="pill ok">● Financeiro em dia</span></header>
<section class="status-card">
  <div><span class="status-icon">✓</span></div>
  <div class="grow"><b>Seu financeiro está em dia</b><p>Nenhuma pendência vencida no momento.</p></div>
  <a class="btn primary" href="financeiro.php">Ver financeiro</a>
</section>
<div class="cards three">
  <a class="card link-card" href="processos.php"><span class="iconbox">§</span><small>PROCESSOS</small><h3><?= count($processos) ?> processos</h3><p>Acompanhe fases e próximos passos.</p><b>Ver andamento →</b></a>
  <a class="card link-card" href="financeiro.php"><span class="iconbox">R$</span><small>FINANCEIRO</small><h3>Parcelas e pagamentos</h3><p>Consulte vencimentos e situação financeira.</p><b>Ver financeiro →</b></a>
  <a class="card link-card" href="chamados.php"><span class="iconbox">?</span><small>ATENDIMENTO</small><h3>Fale com a equipe</h3><p>Abra e acompanhe solicitações.</p><b>Acessar chamados →</b></a>
</div>
<section class="card mt"><div class="section-title"><div><small>ÚLTIMA ATUALIZAÇÃO</small><h2><?= e($processos[0]['titulo']) ?></h2></div><span class="pill">TJSP</span></div><p><?= e($processos[0]['resumo']) ?></p><div class="meta-row"><span><b>Status</b><?= e($processos[0]['status']) ?></span><span><b>Próximo passo</b><?= e($processos[0]['proximo']) ?></span><span><b>Previsão</b><?= e($processos[0]['data']) ?></span></div></section>
<?php page_footer(); ?>