<?php
require_once __DIR__ . '/../api/auth.php';
require_login();
require_once __DIR__ . '/portal-layout.php';
portal_header('Financeiro', 'financeiro'); 

$user = current_user();
$canManageFinancial = is_admin_or_gestao();

if ($canManageFinancial) {
    // Acesso total a relatórios e edições financeiras
    $stmt = $pdo->query("SELECT * FROM financial_records ORDER BY due_date DESC");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif (has_role(['adv'])) {
    // Advogados possuem apenas leitura do financeiro geral
    $stmt = $pdo->query("SELECT id, title, amount, status, due_date FROM financial_records ORDER BY due_date DESC");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Cliente visualiza estritamente os honorários/faturas associados ao seu client_id
    $stmt = $pdo->prepare("SELECT id, title, amount, status, due_date FROM financial_records WHERE client_id = ? ORDER BY due_date DESC");
    $stmt->execute([$user['client_id']]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?><section class="panel"><span>PORTAL TSP</span>
    <h2>Financeiro</h2>
    <p>Módulo estabilizado no novo layout. A funcionalidade completa será ativada na próxima etapa.</p>
</section><?php portal_footer(); ?>