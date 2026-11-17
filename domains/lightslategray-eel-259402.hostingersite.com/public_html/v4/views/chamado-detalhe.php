<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/auth.php';
require_login();

function tk_h($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$user     = current_user();
$userId   = (int)($user['id'] ?? 0);
$clientId = (int)($user['client_id'] ?? 0);
$isStaff  = is_staff();
$error    = null;

$uploadDir = __DIR__ . '/../storage/documents/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$ticketId) {
    header('Location: chamados.php');
    exit;
}

if (empty($_SESSION['tk_msg_csrf'])) {
    $_SESSION['tk_msg_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['tk_msg_csrf'];

// --- 1. BUSCA O CHAMADO E VALIDA ACESSO (RBAC) ---
try {
    $stmt = $pdo->prepare("
        SELECT t.*, cl.name AS client_name, u.name AS creator_name, cs.case_number, cs.title AS case_title 
        FROM tickets t 
        LEFT JOIN clients cl ON cl.id = t.client_id 
        LEFT JOIN users u ON u.id = t.opened_by 
        LEFT JOIN cases cs ON cs.id = t.case_id 
        WHERE t.id = ? 
        LIMIT 1
    ");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(404);
        exit('Chamado não encontrado.');
    }

    if (!$isStaff && (int)$ticket['client_id'] !== $clientId) {
        http_response_code(403);
        exit('Acesso negado. Você não possui permissão para visualizar este chamado.');
    }

} catch (Throwable $e) {
    error_log('Erro ao carregar chamado: ' . $e->getMessage());
    http_response_code(500);
    exit('Erro interno ao carregar o chamado.');
}

// --- 2. PROCESSAMENTO DE RESPOSTA E ANEXO DE ARQUIVO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'reply') {
            $msg        = trim((string)($_POST['message'] ?? ''));
            $isInternal = ($isStaff && isset($_POST['internal'])) ? 1 : 0;
            $file       = $_FILES['attachment'] ?? null;
            $docId      = null;

            if ($msg === '' && (!$file || $file['error'] !== UPLOAD_ERR_OK)) {
                $error = 'Digite uma mensagem ou selecione um arquivo válido.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Processa Upload do Anexo (se enviado)
                    if ($file && $file['error'] === UPLOAD_ERR_OK) {
                        $allowedExt = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'zip'];
                        $maxSize    = 20 * 1024 * 1024; // 20 MB
                        $ext        = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));

                        if ($file['size'] > $maxSize) {
                            throw new Exception('O arquivo excede o limite máximo de 20MB.');
                        }
                        if (!in_array($ext, $allowedExt, true)) {
                            throw new Exception('Extensão de arquivo não permitida.');
                        }

                        $safeName   = bin2hex(random_bytes(16)) . '.' . $ext;
                        $targetPath = $uploadDir . $safeName;

                        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                            throw new Exception('Falha ao armazenar o arquivo no servidor.');
                        }

                        // Se o chamado tiver processo vinculado, cadastra diretamente na case_documents
                        if (!empty($ticket['case_id'])) {
                            $stmtDoc = $pdo->prepare("
                                INSERT INTO case_documents (case_id, uploaded_by_user_id, filename, original_name, file_path, mime_type, file_size, visible_to_client, is_confidential, description, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, ?, NOW())
                            ");
                            $stmtDoc->execute([
                                (int)$ticket['case_id'],
                                $userId,
                                $safeName,
                                (string)$file['name'],
                                $targetPath,
                                (string)$file['type'],
                                (int)$file['size'],
                                'Anexo enviado no Chamado #' . $ticketId
                            ]);
                            $docId = (int)$pdo->lastInsertId();
                        }
                    }

                    // Grava texto da mensagem (concatenando referência ao anexo se houver)
                    $finalMessage = $msg;
                    if ($docId) {
                        $finalMessage .= "\n\n📎 *Anexo adicionado ao processo:* " . (string)$file['name'];
                    }

                    $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, internal, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$ticketId, $userId, trim($finalMessage), $isInternal]);

                    // Atualiza status do chamado
                    if (!$isInternal) {
                        $newStatus = $isStaff ? 'waiting_client' : 'in_progress';
                        $stmtUpd   = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
                        $stmtUpd->execute([$newStatus, $ticketId]);
                    } else {
                        $stmtUpd = $pdo->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?");
                        $stmtUpd->execute([$ticketId]);
                    }

                    $pdo->commit();
                    flash_set('ok', 'Resposta e documento registrados com sucesso.');
                    header('Location: chamado-detalhe.php?id=' . $ticketId);
                    exit;

                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('Erro ao processar anexo/resposta do chamado: ' . $e->getMessage());
                    $error = $e->getMessage() ?: 'Erro ao salvar a resposta.';
                }
            }
        } elseif ($action === 'update_status' && ($isStaff || isset($_POST['close_by_client']))) {
            $newStatus = trim((string)($_POST['status'] ?? 'closed'));
            $allowedStatus = ['open', 'in_progress', 'waiting_client', 'resolved', 'closed'];

            if (in_array($newStatus, $allowedStatus, true)) {
                $closedAtSql = in_array($newStatus, ['resolved', 'closed'], true) ? ", closed_at = NOW()" : ", closed_at = NULL";
                $stmtUpd = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = NOW() {$closedAtSql} WHERE id = ?");
                $stmtUpd->execute([$newStatus, $ticketId]);
                
                flash_set('ok', 'Status do chamado atualizado com sucesso.');
                header('Location: chamado-detalhe.php?id=' . $ticketId);
                exit;
            }
        }
    }
}

// --- 3. HISTÓRICO DE MENSAGENS ---
$messages = [];
try {
    $internalWhere = $isStaff ? "" : " AND m.internal = 0";
    $stmtMsg = $pdo->prepare("
        SELECT m.*, u.name AS sender_name, u.role AS sender_role 
        FROM ticket_messages m 
        LEFT JOIN users u ON u.id = m.user_id 
        WHERE m.ticket_id = ? {$internalWhere} 
        ORDER BY m.created_at ASC
    ");
    $stmtMsg->execute([$ticketId]);
    $messages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Erro ao carregar mensagens: ' . $e->getMessage());
}

require_once __DIR__ . '/portal-layout.php';
portal_header('Chamado #' . $ticketId, 'chamados');
?>

<div style="margin-bottom:16px">
    <a href="chamados.php" style="color:#183049;font-weight:bold;text-decoration:none">← Voltar para lista de chamados</a>
</div>

<!-- DETALHES DO CHAMADO -->
<section class="panel">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
        <div>
            <span>PROTOCOLO #<?= (int)$ticket['id'] ?> · Categoria: <?= tk_h($ticket['category'] ?? 'Geral') ?></span>
            <h2 style="margin-top:4px"><?= tk_h($ticket['subject']) ?></h2>
            <p style="margin-top:6px;color:#475569;font-size:14px">
                Cliente: <strong><?= tk_h($ticket['client_name']) ?></strong> · Solicitante: <?= tk_h($ticket['creator_name'] ?? 'Usuário') ?> em <?= date('d/m/Y H:i', strtotime((string)$ticket['created_at'])) ?>
            </p>
            <?php if (!empty($ticket['case_number'])): ?>
                <p style="margin-top:4px;font-size:13px;color:#183049">
                    <strong>Processo Vinculado:</strong> 
                    <a href="processo-detalhe.php?id=<?= (int)$ticket['case_id'] ?>" style="color:inherit;font-weight:bold">
                        CNJ <?= tk_h($ticket['case_number']) ?> — <?= tk_h($ticket['case_title']) ?> →
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <div style="text-align:right">
            <?php
                $statusClass = match ($ticket['status']) {
                    'resolved'       => 'background:#d1e7dd;color:#0f5132',
                    'in_progress'    => 'background:#cff4fc;color:#055160',
                    'waiting_client' => 'background:#fff3cd;color:#664d03',
                    'closed'         => 'background:#e2e8f0;color:#475569',
                    default          => 'background:#fee2e2;color:#991b1b'
                };
                $statusLabel = match ($ticket['status']) {
                    'resolved'       => 'Resolvido',
                    'in_progress'    => 'Em Atendimento',
                    'waiting_client' => 'Aguardando Cliente',
                    'closed'         => 'Fechado',
                    default          => 'Aberto'
                };
            ?>
            <span style="padding:6px 14px;border-radius:20px;font-size:13px;font-weight:bold;<?= $statusClass ?>">
                <?= tk_h($statusLabel) ?>
            </span>

            <?php if ($isStaff && !in_array($ticket['status'], ['closed', 'resolved'], true)): ?>
                <form method="post" style="margin-top:12px">
                    <input type="hidden" name="csrf" value="<?= tk_h($csrf) ?>">
                    <input type="hidden" name="action" value="update_status">
                    <select name="status" onchange="this.form.submit()" style="padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;font-size:13px">
                        <option value="">Alterar status...</option>
                        <option value="in_progress">Em Atendimento</option>
                        <option value="waiting_client">Aguardando Cliente</option>
                        <option value="resolved">Resolvido</option>
                        <option value="closed">Encerrar Chamado</option>
                    </select>
                </form>
            <?php elseif (!$isStaff && !in_array($ticket['status'], ['closed', 'resolved'], true)): ?>
                <form method="post" style="margin-top:12px">
                    <input type="hidden" name="csrf" value="<?= tk_h($csrf) ?>">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="close_by_client" value="1">
                    <input type="hidden" name="status" value="closed">
                    <button type="submit" style="padding:6px 12px;background:#e2e8f0;color:#334155;border:none;border-radius:6px;cursor:pointer;font-size:12px;font-weight:bold">
                        Encerrar Chamado
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div style="margin-top:18px;padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px">
        <strong style="color:#0f172a;display:block;margin-bottom:6px">Descrição Inicial:</strong>
        <div style="font-size:14px;color:#334155;white-space:pre-wrap;line-height:1.5"><?= tk_h($ticket['description']) ?></div>
    </div>
</section>

<?php if ($error): ?>
<section class="panel" style="margin-top:16px;color:#8b2525">
    <p role="alert"><?= tk_h($error) ?></p>
</section>
<?php endif; ?>

<!-- HISTÓRICO DE MENSAGENS -->
<section class="panel" style="margin-top:20px">
    <h2>Histórico de Atendimento</h2>

    <?php if (empty($messages)): ?>
        <p style="color:#64748b;margin-top:10px">Nenhuma resposta registrada ainda.</p>
    <?php else: ?>
        <div style="display:grid;gap:16px;margin-top:16px">
            <?php foreach ($messages as $m): ?>
            <?php 
                $isInternal    = !empty($m['internal']);
                $isStaffSender = in_array($m['sender_role'] ?? '', ['admin', 'gestao', 'adv'], true);
                
                $bg          = $isInternal ? '#fff7ed' : ($isStaffSender ? '#f0f9ff' : '#ffffff');
                $borderColor = $isInternal ? '#fdba74' : ($isStaffSender ? '#bae6fd' : '#e2e8f0');
                $badgeColor  = $isInternal ? 'background:#c2410c;color:#fff' : ($isStaffSender ? 'background:#183049;color:#fff' : 'background:#e2e8f0;color:#334155');
            ?>
            <div style="padding:16px;border:1px solid <?= $borderColor ?>;border-radius:10px;background:<?= $bg ?>">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <div>
                        <strong style="color:#0f172a"><?= tk_h($m['sender_name'] ?? 'Usuário') ?></strong>
                        <span style="font-size:11px;padding:2px 6px;border-radius:4px;font-weight:bold;margin-left:6px;<?= $badgeColor ?>">
                            <?= $isInternal ? 'NOTA INTERNA' : strtoupper((string)($m['sender_role'] ?? 'cliente')) ?>
                        </span>
                    </div>
                    <small style="color:#64748b"><?= date('d/m/Y H:i', strtotime((string)$m['created_at'])) ?></small>
                </div>
                <div style="font-size:14px;color:#334155;white-space:pre-wrap;line-height:1.5"><?= tk_h($m['message']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- FORMULÁRIO COM CAMPO DE ANEXO DE ARQUIVO -->
<?php if (!in_array($ticket['status'], ['closed', 'resolved'], true)): ?>
<section class="panel" style="margin-top:20px">
    <h2>Enviar Resposta</h2>
    <form method="post" enctype="multipart/form-data" style="margin-top:12px">
        <input type="hidden" name="csrf" value="<?= tk_h($csrf) ?>">
        <input type="hidden" name="action" value="reply">

        <label>Sua mensagem
            <textarea name="message" rows="4" placeholder="Escreva aqui a sua resposta..." style="display:block;width:100%;padding:12px;margin-top:6px"></textarea>
        </label>

        <label style="display:block;margin-top:12px">Anexar arquivo (PDF, DOCX, PNG, JPG, ZIP — Máx 20MB)
            <input type="file" name="attachment" style="display:block;width:100%;padding:8px;margin-top:6px;background:#fff;border:1px solid #cbd5e1;border-radius:6px">
        </label>

        <?php if ($isStaff): ?>
        <div style="margin-top:12px">
            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;color:#c2410c;font-weight:600;font-size:13px">
                <input type="checkbox" name="internal" value="1"> Nota Interna (visível apenas para a equipe)
            </label>
        </div>
        <?php endif; ?>

        <div style="margin-top:14px">
            <button type="submit" style="padding:12px 22px;background:#183049;color:#fff;border:0;border-radius:8px;cursor:pointer;font-weight:600">
                Enviar Resposta
            </button>
        </div>
    </form>
</section>
<?php else: ?>
<section class="panel" style="margin-top:20px;background:#f8fafc;border:1px dashed #cbd5e1;text-align:center">
    <p style="color:#64748b;font-weight:bold">Este chamado foi encerrado em <?= !empty($ticket['closed_at']) ? date('d/m/Y H:i', strtotime((string)$ticket['closed_at'])) : '—' ?>. Caso necessite de novo atendimento, abra uma nova solicitação.</p>
</section>
<?php endif; ?>

<?php portal_footer(); ?>