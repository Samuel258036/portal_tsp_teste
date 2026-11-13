<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/auth.php';
require_login();
require_once __DIR__ . '/../api/access_control.php';
require_once __DIR__ . '/portal-layout.php';

$user     = current_user();
$userId   = (int)($user['id'] ?? 0);
$clientId = (int)($user['client_id'] ?? 0);
$isStaff  = is_staff();

$error = null;
$cases = [];
$recentUpdates = [];
$actionRequiredUpdates = [];
$stats = ['total_cases' => 0, 'published_updates' => 0, 'actions_pending' => 0];

try {
    if ($isStaff) {
        // --- VISÃO DA EQUIPE INTERNA ---
        $stats['total_cases']       = (int)$pdo->query("SELECT COUNT(*) FROM cases WHERE active = 1")->fetchColumn();
        $stats['published_updates'] = (int)$pdo->query("SELECT COUNT(*) FROM case_updates WHERE status = 'published'")->fetchColumn();
        $stats['actions_pending']   = (int)$pdo->query("SELECT COUNT(*) FROM case_updates WHERE status = 'pending_review'")->fetchColumn();

        // Processos recentes
        $stmt = $pdo->query("SELECT c.*, cl.name AS client_name FROM cases c LEFT JOIN clients cl ON cl.id = c.client_id WHERE c.active = 1 ORDER BY c.created_at DESC LIMIT 5");
        $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // --- VISÃO RESTRITA DO CLIENTE / TERCEIRO AUTORIZADO ---
        $queryData = get_authorized_cases_query($pdo, $userId);

        // 1. Processos Autorizados do Cliente
        $stmtCases = $pdo->prepare("
            SELECT c.* 
            FROM cases c 
            WHERE {$queryData['where']} 
              AND c.active = 1 
              AND (c.is_confidential = 0 OR c.is_confidential IS NULL)
            ORDER BY c.created_at DESC
        ");
        $stmtCases->execute($queryData['params']);
        $cases = $stmtCases->fetchAll(PDO::FETCH_ASSOC);
        $stats['total_cases'] = count($cases);

        // 2. Ações Exigidas do Cliente (Pendências em laranja)
        $stmtActions = $pdo->prepare("
            SELECT u.id, u.title, u.client_message, u.event_date, u.published_at, c.id AS case_id, c.case_number 
            FROM case_updates u 
            INNER JOIN cases c ON c.id = u.case_id 
            WHERE {$queryData['where']} 
              AND (c.is_confidential = 0 OR c.is_confidential IS NULL) 
              AND u.status = 'published' 
              AND u.visible_to_client = 1 
              AND u.client_action_required = 1
            ORDER BY COALESCE(u.event_date, u.published_at) DESC
        ");
        $stmtActions->execute($queryData['params']);
        $actionRequiredUpdates = $stmtActions->fetchAll(PDO::FETCH_ASSOC);
        $stats['actions_pending'] = count($actionRequiredUpdates);

        // 3. Contagem de Andamentos Publicados Disponíveis
        $stmtUpdatesCount = $pdo->prepare("
            SELECT COUNT(*) 
            FROM case_updates u 
            INNER JOIN cases c ON c.id = u.case_id 
            WHERE {$queryData['where']} 
              AND (c.is_confidential = 0 OR c.is_confidential IS NULL) 
              AND u.status = 'published' 
              AND u.visible_to_client = 1
        ");
        $stmtUpdatesCount->execute($queryData['params']);
        $stats['published_updates'] = (int)$stmtUpdatesCount->fetchColumn();
    }
} catch (Throwable $e) {
    error_log('Erro no Dashboard: ' . $e->getMessage());
    $error = 'Não foi possível carregar as informações do dashboard.';
}

portal_header('Visão geral', 'dashboard');
?>

<section class="panel">
    <span>PORTAL TSP</span>
    <h2>Olá, <?= e($user['name'] ?? 'Usuário') ?></h2>
    <p><?= $isStaff ? 'Resumo operacional e pendências do escritório.' : 'Acompanhe o andamento dos seus processos e solicitações pendentes.' ?></p>
</section>

<!-- CARDS DE ESTATÍSTICAS -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;margin-top:20px">
    <div style="padding:20px;background:#fff;border:1px solid #cbd5e1;border-radius:12px">
        <small style="color:#64748b;font-weight:600;text-transform:uppercase"><?= $isStaff ? 'Processos Ativos' : 'Seus Processos' ?></small>
        <h2 style="font-size:32px;margin-top:8px;color:#183049"><?= $stats['total_cases'] ?></h2>
    </div>
    
    <div style="padding:20px;background:#fff;border:1px solid #cbd5e1;border-radius:12px">
        <small style="color:#64748b;font-weight:600;text-transform:uppercase"><?= $isStaff ? 'Atualizações Publicadas' : 'Andamentos Disponíveis' ?></small>
        <h2 style="font-size:32px;margin-top:8px;color:#183049"><?= $stats['published_updates'] ?></h2>
    </div>

    <div style="padding:20px;background:<?= $stats['actions_pending'] > 0 ? '#fff7ed' : '#fff' ?>;border:1px solid <?= $stats['actions_pending'] > 0 ? '#fdba74' : '#cbd5e1' ?>;border-radius:12px">
        <small style="color:<?= $stats['actions_pending'] > 0 ? '#c2410c' : '#64748b' ?>;font-weight:600;text-transform:uppercase">
            <?= $isStaff ? 'Aguardando Aprovação' : 'Ações Necessárias' ?>
        </small>
        <h2 style="font-size:32px;margin-top:8px;color:<?= $stats['actions_pending'] > 0 ? '#c2410c' : '#183049' ?>">
            <?= $stats['actions_pending'] ?>
        </h2>
    </div>
</div>

<!-- ALERTA DE AÇÃO NECESSÁRIA PARA O CLIENTE -->
<?php if (!$isStaff && !empty($actionRequiredUpdates)): ?>
<section class="panel" style="margin-top:20px;border-left:6px solid #d97706;background:#fffbe0">
    <h3 style="color:#92400e;margin-bottom:10px">⚠️ Ação pendente solicitada pela TSP</h3>
    <div style="display:grid;gap:12px">
        <?php foreach ($actionRequiredUpdates as $act): ?>
        <div style="padding:14px;background:#fff;border:1px solid #fef08a;border-radius:8px">
            <strong>Processo #<?= e($act['case_number']) ?> — <?= e($act['title']) ?></strong>
            <p style="margin-top:6px;font-size:14px;color:#334155"><?= e($act['client_message']) ?></p>
            <a href="processo-detalhe.php?id=<?= (int)$act['case_id'] ?>" style="display:inline-block;margin-top:8px;font-size:13px;color:#183049;font-weight:bold">Ver detalhes do processo →</a>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- LISTA DE PROCESSOS -->
<section class="panel" style="margin-top:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <h2><?= $isStaff ? 'Processos recentes' : 'Seus processos' ?></h2>
        <a href="processos.php" style="color:#183049;font-weight:bold;font-size:14px;text-decoration:none">Ver todos →</a>
    </div>

    <?php if (empty($cases)): ?>
        <p style="color:#64748b">Nenhum processo encontrado.</p>
    <?php else: ?>
        <div style="display:grid;gap:12px">
            <?php foreach ($cases as $c): ?>
            <div style="padding:16px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
                <div>
                    <span style="font-size:12px;background:#f1f5f9;color:#475569;padding:2px 8px;border-radius:4px;font-weight:600">
                        CNJ: <?= e($c['case_number']) ?>
                    </span>
                    <h3 style="margin-top:6px;font-size:16px;color:#0f172a">
                        <a href="processo-detalhe.php?id=<?= (int)$c['id'] ?>" style="color:inherit;text-decoration:none">
                            <?= e($c['title']) ?>
                        </a>
                    </h3>
                    <small style="color:#64748b">
                        Tribunal: <?= e($c['court_name'] ?? $c['court'] ?? 'Não informado') ?>
                    </small>
                </div>
                <a href="processo-detalhe.php?id=<?= (int)$c['id'] ?>" 
                   style="padding:8px 16px;border-radius:6px;background:#183049;color:#fff;text-decoration:none;font-size:13px;font-weight:600">
                    Acompanhar Processo
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php portal_footer(); ?>