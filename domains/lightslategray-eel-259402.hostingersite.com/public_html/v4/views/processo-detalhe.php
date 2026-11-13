<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/auth.php';
require_login();
require_once __DIR__ . '/../api/access_control.php';

// Helper de auditoria opcional
if (file_exists(__DIR__ . '/../api/audit.php')) {
    require_once __DIR__ . '/../api/audit.php';
}

function proc_h($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function proc_date($v): string {
    if (empty($v)) return '—';
    $time = strtotime((string)$v);
    return $time ? date('d/m/Y H:i', $time) : '—';
}

$user    = current_user();
$userId  = (int)($user['id'] ?? 0);
$isStaff = is_staff();

$caseId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$caseId) {
    header('Location: processos.php');
    exit;
}

if (empty($_SESSION['case_csrf'])) {
    $_SESSION['case_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['case_csrf'];

// --- 1. PROCESSAMENTO DE ALTERAÇÃO DE SIGILO (STAFF APENAS) ---
if ($isStaff && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_confidential') {
    $postCsrf = (string)($_POST['csrf'] ?? '');
    
    if (!hash_equals($csrf, $postCsrf)) {
        flash_set('error', 'Sessão expirada ou token de segurança inválido. Tente novamente.');
    } else {
        $newConfidentialStatus = (int)($_POST['set_confidential'] ?? 0);
        
        try {
            $stmtUpd = $pdo->prepare("UPDATE cases SET is_confidential = ?, updated_at = NOW() WHERE id = ?");
            $stmtUpd->execute([$newConfidentialStatus, $caseId]);

            if (function_exists('log_action')) {
                log_action('toggle_confidential', 'cases', $caseId, "Sigilo processual alterado para: {$newConfidentialStatus}");
            }

            flash_set('ok', $newConfidentialStatus === 1 ? 'Processo marcado como SIGILOSO com sucesso.' : 'Sigilo removido do processo.');
        } catch (Throwable $e) {
            error_log('Erro ao alterar sigilo do processo: ' . $e->getMessage());
            flash_set('error', 'Erro ao alterar o sigilo no banco de dados: ' . $e->getMessage());
        }
    }
    
    header('Location: processo-detalhe.php?id=' . $caseId);
    exit;
}

// --- 2. CONSULTA DOS DADOS DO PROCESSO E REGRAS RBAC ---
$case    = null;
$updates = [];

try {
    if ($isStaff) {
        $stmt = $pdo->prepare("
            SELECT c.*, cl.name AS client_name 
            FROM cases c 
            LEFT JOIN clients cl ON cl.id = c.client_id 
            WHERE c.id = ? AND c.active = 1 
            LIMIT 1
        ");
        $stmt->execute([$caseId]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $queryData = get_authorized_cases_query($pdo, $userId);
        
        $stmt = $pdo->prepare("
            SELECT c.* 
            FROM cases c 
            WHERE c.id = :case_id 
              AND {$queryData['where']} 
              AND c.active = 1 
              AND (c.is_confidential = 0 OR c.is_confidential IS NULL)
            LIMIT 1
        ");
        
        $params = array_merge([':case_id' => $caseId], $queryData['params']);
        $stmt->execute($params);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$case) {
        http_response_code(403);
        require_once __DIR__ . '/portal-layout.php';
        portal_header('Acesso Negado', 'processos');
        echo '<section class="panel"><h2>Processo indisponível ou acesso não autorizado.</h2><a href="processos.php">Voltar aos processos</a></section>';
        portal_footer();
        exit;
    }

    // --- 3. CONSULTA DA LINHA DO TEMPO ---
    if ($isStaff) {
        $stmtUpdates = $pdo->prepare("
            SELECT u.*, usr.name AS approver_name 
            FROM case_updates u 
            LEFT JOIN users usr ON usr.id = u.approved_by_user_id 
            WHERE u.case_id = ? 
            ORDER BY COALESCE(u.event_date, u.created_at) DESC
        ");
        $stmtUpdates->execute([$caseId]);
        $updates = $stmtUpdates->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtUpdates = $pdo->prepare("
            SELECT u.id, u.title, u.client_message, u.client_action_required, u.event_date, u.published_at 
            FROM case_updates u 
            WHERE u.case_id = ? 
              AND u.status = 'published' 
              AND u.visible_to_client = 1 
              AND u.published_at IS NOT NULL 
            ORDER BY COALESCE(u.event_date, u.published_at) DESC
        ");
        $stmtUpdates->execute([$caseId]);
        $updates = $stmtUpdates->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Throwable $e) {
    error_log('Erro ao carregar detalhes do processo: ' . $e->getMessage());
    header('Location: processos.php');
    exit;
}

require_once __DIR__ . '/portal-layout.php';
portal_header('Detalhes do Processo', 'processos');
?>

<!-- NAVEGAÇÃO DE RETORNO -->
<div style="margin-bottom:15px">
    <a href="processos.php" style="color:#183049;text-decoration:none;font-weight:bold;font-size:14px">← Voltar para a lista</a>
</div>

<!-- FICHA TÉCNICA DO PROCESSO -->
<section class="panel">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
        <div>
            <span style="font-size:13px;background:#183049;color:#fff;padding:3px 10px;border-radius:4px;font-weight:600">
                CNJ: <?= proc_h($case['case_number']) ?>
            </span>
            <h1 style="font-size:24px;color:#0f172a;margin-top:10px"><?= proc_h($case['title']) ?></h1>
            <?php if ($isStaff && !empty($case['client_name'])): ?>
                <small style="color:#64748b;display:block;margin-top:4px">Cliente: <strong><?= proc_h($case['client_name']) ?></strong></small>
            <?php endif; ?>
        </div>

        <div style="text-align:right">
            <span style="display:inline-block;padding:6px 14px;border-radius:20px;background:#e2e8f0;color:#334155;font-weight:bold;font-size:13px">
                <?= proc_h($case['status_name'] ?? 'Em andamento') ?>
            </span>

            <!-- BOTÃO DE ALTERNÂNCIA DE SIGILO (STAFF APENAS) -->
            <?php if ($isStaff): ?>
                <div style="margin-top:12px">
                    <form action="processo-detalhe.php?id=<?= (int)$caseId ?>" method="post" style="display:inline">
                        <input type="hidden" name="csrf" value="<?= proc_h($csrf) ?>">
                        <input type="hidden" name="action" value="toggle_confidential">
                        <?php if (!empty($case['is_confidential'])): ?>
                            <span style="background:#fee2e2;color:#991b1b;padding:5px 10px;border-radius:4px;font-size:12px;font-weight:bold;margin-right:6px">
                                SIGILOSO
                            </span>
                            <input type="hidden" name="set_confidential" value="0">
                            <button type="submit" style="padding:6px 12px;background:#e2e8f0;color:#334155;border:0;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px">
                                Remover Sigilo
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="set_confidential" value="1">
                            <button type="submit" style="padding:6px 12px;background:#c2410c;color:#fff;border:0;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px">
                                Marcar Sigiloso
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <hr style="border:0;border-top:1px solid #e2e8f0;margin:20px 0">

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px">
        <div>
            <small style="color:#64748b;font-weight:bold;display:block">TRIBUNAL / ÓRGÃO</small>
            <span style="color:#1e293b;font-size:15px"><?= proc_h($case['court_name'] ?? 'Não informado') ?></span>
        </div>
        <div>
            <small style="color:#64748b;font-weight:bold;display:block">VARA / COMARCA</small>
            <span style="color:#1e293b;font-size:15px"><?= proc_h($case['court_unit'] ?? 'Não informada') ?></span>
        </div>
        <div>
            <small style="color:#64748b;font-weight:bold;display:block">ÁREA DO DIREITO</small>
            <span style="color:#1e293b;font-size:15px"><?= proc_h($case['area'] ?? 'Cível / Geral') ?></span>
        </div>
    </div>

    <?php if (!empty($case['next_step'])): ?>
    <div style="margin-top:20px;padding:14px;background:#f8fafc;border-left:4px solid #183049;border-radius:4px">
        <strong style="color:#183049;display:block;font-size:13px">PRÓXIMA ETAPA PREVISTA:</strong>
        <p style="margin-top:4px;color:#334155;font-size:14px"><?= proc_h($case['next_step']) ?></p>
    </div>
    <?php endif; ?>
</section>

<!-- LINHA DO TEMPO PROCESSUAL -->
<section class="panel" style="margin-top:20px">
    <h2>Linha do Tempo e Orientações</h2>

    <?php if (empty($updates)): ?>
        <p style="color:#64748b;margin-top:10px">Nenhum andamento publicado para este processo até o momento.</p>
    <?php else: ?>
        <div style="display:grid;gap:16px;margin-top:18px">
            <?php foreach ($updates as $item): ?>
            <article style="padding:18px;border:1px solid <?= !empty($item['client_action_required']) ? '#fdba74' : '#e2e8f0' ?>;border-radius:10px;background:<?= !empty($item['client_action_required']) ? '#fff7ed' : '#fff' ?>">
                
                <?php if (!empty($item['client_action_required'])): ?>
                    <span style="background:#d97706;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:bold;float:right">AÇÃO DO CLIENTE NECESSÁRIA</span>
                <?php endif; ?>

                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                    <strong><?= proc_h($item['title']) ?></strong>
                    <small style="color:#64748b"><?= proc_h(proc_date($item['event_date'] ?: ($item['published_at'] ?? $item['created_at'] ?? null))) ?></small>
                </div>

                <?php if (trim((string)($item['client_message'] ?? '')) !== ''): ?>
                    <p style="white-space:pre-wrap;margin-top:10px;line-height:1.5;color:#334155"><?= proc_h($item['client_message']) ?></p>
                <?php endif; ?>

                <?php if ($isStaff && !empty($item['internal_note'])): ?>
                    <div style="margin-top:10px;padding:10px;background:#f1f5f9;border-left:4px solid #183049;border-radius:4px;font-size:13px">
                        <strong>Nota Interna (Reservada):</strong> <?= proc_h($item['internal_note']) ?>
                    </div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php portal_footer(); ?>