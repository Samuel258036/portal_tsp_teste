<?php
require_once __DIR__ . '/partials.php'; require_login(); page_header('Financeiro','financeiro');
?>
<header class="page-head"><div><p class="eyebrow">FINANCEIRO</p><h1>Parcelas e pagamentos</h1><p class="muted">Consulte a situação do seu contrato.</p></div><span class="pill ok">● Sem parcelas vencidas</span></header>
<div class="cards three stats"><div class="card"><small>VALOR CONTRATADO</small><h2>R$ 3.000,00</h2></div><div class="card"><small>TOTAL PAGO</small><h2>R$ 1.500,00</h2></div><div class="card"><small>SALDO</small><h2>R$ 1.500,00</h2></div></div>
<section class="card mt table-card"><div class="section-title"><h2>Parcelas</h2><span class="muted">Contrato demonstrativo</span></div>
<div class="table-wrap"><table><thead><tr><th>Parcela</th><th>Vencimento</th><th>Valor</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach($parcelas as $p): ?><tr><td>#<?= $p['numero'] ?></td><td><?= e($p['vencimento']) ?></td><td><?= e($p['valor']) ?></td><td><span class="pill <?= $p['status']==='Paga'?'ok':'warn' ?>"><?= e($p['status']) ?></span></td><td><?= $p['status']==='Em aberto' ? '<button class="btn small" onclick="alert(\'Integração Pix será conectada na versão final.\')">Pagar</button>' : '✓' ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php page_footer(); ?>