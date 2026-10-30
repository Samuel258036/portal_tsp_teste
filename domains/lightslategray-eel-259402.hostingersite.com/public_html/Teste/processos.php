<?php
require_once __DIR__ . '/partials.php'; require_login(); page_header('Processos','processos');
?>
<header class="page-head"><div><p class="eyebrow">ACOMPANHAMENTO</p><h1>Meus processos</h1><p class="muted">Informações resumidas e atualizadas pela equipe TSP.</p></div></header>
<div class="stack">
<?php foreach ($processos as $i=>$p): ?>
<section class="card process-card">
 <div class="section-title"><div><small><?= e($p['numero']) ?></small><h2><?= e($p['titulo']) ?></h2></div><span class="pill ok"><?= e($p['status']) ?></span></div>
 <div class="process-grid"><div><span>Tribunal</span><b><?= e($p['tribunal']) ?></b></div><div><span>Vara</span><b><?= e($p['vara']) ?></b></div><div><span>Próximo passo</span><b><?= e($p['proximo']) ?></b></div><div><span>Data</span><b><?= e($p['data']) ?></b></div></div>
 <div class="timeline">
 <?php foreach ($p['fases'] as $idx=>$fase): ?><div class="step <?= $idx <= $p['fase'] ? 'done':'' ?>"><i></i><span><?= e($fase) ?></span></div><?php endforeach; ?>
 </div>
 <div class="summary"><b>Resumo em linguagem simples</b><p><?= e($p['resumo']) ?></p></div>
</section>
<?php endforeach; ?>
</div>
<?php page_footer(); ?>