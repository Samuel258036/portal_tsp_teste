<?php 
require_once __DIR__ . '/../api/auth.php';
require_login();
require_once __DIR__ . '/portal-layout.php';
portal_header('Chamados', 'chamados'); 

$user = current_user();
$isStaff = is_staff();

if ($isStaff) {
    // Equipa interna visualiza todos os chamados do sistema
    $stmt = $pdo->query("SELECT t.*, u.name AS user_name, c.name AS client_name FROM tickets t LEFT JOIN users u ON u.id = t.user_id LEFT JOIN clients c ON c.id = t.client_id ORDER BY t.created_at DESC");
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Cliente visualiza apenas os chamados abertos pela sua conta/empresa
    $stmt = $pdo->prepare("SELECT t.* FROM tickets t WHERE t.user_id = ? OR t.client_id = ? ORDER BY t.created_at DESC");
    $stmt->execute([$user['id'], $user['client_id']]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?><section class="panel"><span>PORTAL TSP</span>
    <h2>Chamados</h2>
    <p>Módulo estabilizado no novo layout. A funcionalidade completa será ativada na próxima etapa.</p>
</section><?php portal_footer(); ?>