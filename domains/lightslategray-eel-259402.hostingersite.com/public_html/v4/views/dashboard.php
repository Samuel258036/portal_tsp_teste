<?php
require_once __DIR__ . '/../api/auth.php';
require_login();
require_once __DIR__ . '/portal-layout.php';
function cq($pdo, $sql, $p = [])
{
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return (int)$s->fetchColumn();
}
if (is_admin()) {
    $a = cq($pdo, "SELECT COUNT(*) FROM clients WHERE active=1");
    $b = cq($pdo, "SELECT COUNT(*) FROM cases");
    $c = cq($pdo, "SELECT COUNT(*) FROM financial_installments");
    $d = cq($pdo, "SELECT COUNT(*) FROM tickets");
} else {
    $id = (int)(current_user()['client_id'] ?? 0);
    $a = 1;
    $b = cq($pdo, "SELECT COUNT(*) FROM cases WHERE client_id=?", [$id]);
    $c = 0;
    $d = cq($pdo, "SELECT COUNT(*) FROM tickets WHERE client_id=?", [$id]);
}
portal_header('Olá, ' . current_user()['name'], 'dashboard'); ?><div class="connected">● MySQL conectado</div>
<div class="cards">
    <article><small><?= is_admin() ? 'CLIENTES ATIVOS' : 'SEU CADASTRO' ?></small><b><?= $a ?></b></article>
    <article><small>PROCESSOS</small><b><?= $b ?></b></article>
    <article><small>REGISTROS FINANCEIROS</small><b><?= $c ?></b></article>
    <article><small>CHAMADOS</small><b><?= $d ?></b></article>
</div>
<section class="panel"><span>CENTRAL TSP</span>
    <h2><?= is_admin() ? 'Administração do portal' : 'Sua área do cliente' ?></h2>
    <p><?= is_admin() ? 'Cadastre clientes, crie acessos e acompanhe o portal.' : 'Aqui você verá somente os dados vinculados ao seu acesso.' ?></p>
</section><?php portal_footer(); ?>