<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/auth.php';
require_login();
require_once __DIR__ . '/../api/access_control.php';

function tk_h($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$user     = current_user();
$userId   = (int)($user['id'] ?? 0);
$clientId = (int)($user['client_id'] ?? 0);
$isStaff  = is_staff();
$error    = null;

if (empty($_SESSION['tickets_csrf'])) {
    $_SESSION['tickets_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['tickets_csrf'];

// --- 1. PROCESSAMENTO DA ABERTURA DE CHAMADO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $subject        = trim((string)($_POST['subject'] ?? ''));
        $description    = trim((string)($_POST['description'] ?? ''));
        $category       = trim((string)($_POST['category'] ?? 'Dúvida Geral'));
        $priority       = trim((string)($_POST['priority'] ?? 'normal'));
        $caseId         = filter_input(INPUT_POST, 'case_id', FILTER_VALIDATE_INT) ?: null;
        $targetClientId = $isStaff ? (filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT) ?: 0) : $clientId;

        $allowedPriorities = ['low', 'normal', 'high', 'urgent'];
        if (!in_array($priority, $allowedPriorities, true)) {
            $priority = 'normal';
        }

        if ($subject === '' || $description === '' || $targetClientId < 1) {
            $error = 'Preencha o assunto, a descrição e selecione o cliente correspondente.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO tickets (client_id, case_id, opened_by, subject, description, category, priority, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'open', NOW(), NOW())
                ");
                $stmt->execute([$targetClientId, $caseId, $userId, $subject, $description, $category, $priority]);
                $ticketId = (int)$pdo->lastInsertId();

                flash_set('ok', 'Chamado #' . $ticketId . ' aberto com sucesso.');
                header('Location: chamado-detalhe.php?id=' . $ticketId);
                exit;

            } catch (Throwable $e) {
                error_log('Erro ao criar chamado: ' . $e->getMessage());
                $error = 'Não foi possível registrar o chamado neste momento.';
            }
        }
    }
}

// --- 2. CONSULTA DOS CHAMADOS E PROCESSO DO CLIENTE ---
$tickets    = [];
$casesList  = [];
$clientList = [];

try {
    if ($isStaff) {
        $clientList = $pdo->query("SELECT id, name FROM clients WHERE active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        
        // Incluído c.client_id na consulta para permitir o filtro dinâmico
        $casesList  = $pdo->query("SELECT id, client_id, case_number, title FROM cases WHERE active = 1 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("
            SELECT t.*, c.name AS client_name, u.name AS creator_name, cs.case_number 
            FROM tickets t 
            LEFT JOIN clients c ON c.id = t.client_id 
            LEFT JOIN users u ON u.id = t.opened_by 
            LEFT JOIN cases cs ON cs.id = t.case_id 
            ORDER BY t.updated_at DESC, t.created_at DESC 
            LIMIT 200
        ");
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $queryData = get_authorized_cases_query($pdo, $userId);
        $stmtCases = $pdo->prepare("SELECT c.id, c.client_id, c.case_number, c.title FROM cases c WHERE {$queryData['where']} AND c.active = 1");
        $stmtCases->execute($queryData['params']);
        $casesList = $stmtCases->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT t.*, u.name AS creator_name, cs.case_number 
            FROM tickets t 
            LEFT JOIN users u ON u.id = t.opened_by 
            LEFT JOIN cases cs ON cs.id = t.case_id 
            WHERE t.client_id = ? 
            ORDER BY t.updated_at DESC, t.created_at DESC 
            LIMIT 100
        ");
        $stmt->execute([$clientId]);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('Erro ao listar chamados: ' . $e->getMessage());
    $error = 'Não foi possível carregar a lista de chamados.';
}

require_once __DIR__ . '/portal-layout.php';
portal_header('Chamados', 'chamados');
?>

<section class="panel">
    <span>PORTAL TSP</span>
    <h2>Central de Atendimento e Chamados</h2>
    <p><?= $isStaff ? 'Responda e gerencie os chamados e dúvidas enviadas pelos clientes.' : 'Abra solicitações de atendimento e acompanhe o histórico de mensagens.' ?></p>
</section>

<?php if ($error): ?>
<section class="panel" style="margin-top:20px;color:#8b2525">
    <p role="alert"><?= tk_h($error) ?></p>
</section>
<?php endif; ?>

<!-- FORMULÁRIO DE ABERTURA DE CHAMADO -->
<section class="panel" style="margin-top:20px">
    <h2>Abrir novo chamado</h2>
    <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px">
        <input type="hidden" name="csrf" value="<?= tk_h($csrf) ?>">
        <input type="hidden" name="action" value="create">

        <?php if ($isStaff): ?>
        <label>Cliente *
            <select name="client_id" id="client_id_select" required style="display:block;width:100%;padding:12px;margin-top:6px">
                <option value="">Selecione o cliente</option>
                <?php foreach ($clientList as $cl): ?>
                    <option value="<?= (int)$cl['id'] ?>"><?= tk_h($cl['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>

        <label>Categoria *
            <select name="category" required style="display:block;width:100%;padding:12px;margin-top:6px">
                <option value="Dúvida Processual">Dúvida Processual</option>
                <option value="Envio de Documento">Envio de Documento</option>
                <option value="Solicitação Financeira">Solicitação Financeira</option>
                <option value="Agendamento / Reunião">Agendamento / Reunião</option>
                <option value="Outros">Outros</option>
            </select>
        </label>

        <label>Prioridade
            <select name="priority" style="display:block;width:100%;padding:12px;margin-top:6px">
                <option value="low">Baixa</option>
                <option value="normal" selected>Normal</option>
                <option value="high">Alta</option>
                <option value="urgent">Urgente</option>
            </select>
        </label>

        <label>Vincular a um processo (Opcional)
            <select name="case_id" id="case_id_select" style="display:block;width:100%;padding:12px;margin-top:6px">
                <option value="">Nenhum processo específico</option>
                <?php foreach ($casesList as $cs): ?>
                    <option value="<?= (int)$cs['id'] ?>" data-client-id="<?= (int)($cs['client_id'] ?? 0) ?>">
                        #<?= tk_h($cs['case_number']) ?> — <?= tk_h($cs['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label style="grid-column:1/-1">Assunto / Título *
            <input type="text" name="subject" required maxlength="255" placeholder="Ex: Solicitação de cópia de contrato" style="display:block;width:100%;padding:12px;margin-top:6px">
        </label>

        <label style="grid-column:1/-1">Descrição da Solicitação *
            <textarea name="description" rows="4" required placeholder="Descreva detalhadamente sua dúvida ou solicitação..." style="display:block;width:100%;padding:12px;margin-top:6px"></textarea>
        </label>

        <div style="grid-column:1/-1">
            <button type="submit" style="padding:12px 22px;background:#183049;color:#fff;border:0;border-radius:8px;cursor:pointer;font-weight:600">
                Enviar Chamado
            </button>
        </div>
    </form>
</section>

<!-- TABELA DE CHAMADOS -->
<section class="panel" style="margin-top:20px">
    <h2><?= $isStaff ? 'Todos os Chamados' : 'Seus Chamados' ?></h2>

    <?php if (empty($tickets)): ?>
        <p style="color:#64748b;margin-top:10px">Nenhum chamado aberto até o momento.</p>
    <?php else: ?>
        <div style="overflow-x:auto;margin-top:16px">
            <table style="width:100%;border-collapse:collapse;text-align:left;font-size:14px">
                <thead>
                    <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0">
                        <th style="padding:12px">Protocolo / Assunto</th>
                        <?php if ($isStaff): ?><th style="padding:12px">Cliente</th><?php endif; ?>
                        <th style="padding:12px">Categoria / Processo</th>
                        <th style="padding:12px">Prioridade</th>
                        <th style="padding:12px">Status</th>
                        <th style="padding:12px">Última Atualização</th>
                        <th style="padding:12px;text-align:center">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                    <?php 
                        $statusClass = match ($t['status']) {
                            'resolved'       => 'background:#d1e7dd;color:#0f5132',
                            'in_progress'    => 'background:#cff4fc;color:#055160',
                            'waiting_client' => 'background:#fff3cd;color:#664d03',
                            'closed'         => 'background:#e2e8f0;color:#475569',
                            default          => 'background:#fee2e2;color:#991b1b'
                        };
                        $statusLabel = match ($t['status']) {
                            'resolved'       => 'Resolvido',
                            'in_progress'    => 'Em Atendimento',
                            'waiting_client' => 'Aguardando Cliente',
                            'closed'         => 'Fechado',
                            default          => 'Aberto'
                        };

                        $prioLabel = match ($t['priority']) {
                            'low'    => 'Baixa',
                            'high'   => 'Alta',
                            'urgent' => 'Urgente',
                            default  => 'Normal'
                        };
                    ?>
                    <tr style="border-top:1px solid #e2e8f0">
                        <td style="padding:12px;vertical-align:top">
                            <span style="font-size:11px;background:#f1f5f9;color:#475569;padding:2px 6px;border-radius:4px;font-weight:600">
                                #<?= (int)$t['id'] ?>
                            </span>
                            <strong style="display:block;margin-top:4px;color:#0f172a"><?= tk_h($t['subject']) ?></strong>
                            <small style="color:#64748b">Por: <?= tk_h($t['creator_name'] ?? 'Usuário') ?></small>
                        </td>

                        <?php if ($isStaff): ?>
                            <td style="padding:12px;vertical-align:top">
                                <strong><?= tk_h($t['client_name'] ?? 'Cliente') ?></strong>
                            </td>
                        <?php endif; ?>

                        <td style="padding:12px;vertical-align:top">
                            <span><?= tk_h($t['category'] ?? 'Geral') ?></span>
                            <?php if (!empty($t['case_number'])): ?>
                                <small style="display:block;color:#64748b;margin-top:2px">Proc: #<?= tk_h($t['case_number']) ?></small>
                            <?php endif; ?>
                        </td>

                        <td style="padding:12px;vertical-align:top">
                            <span style="font-size:12px;font-weight:600;color:<?= $t['priority'] === 'urgent' ? '#b91c1c' : '#334155' ?>">
                                <?= tk_h($prioLabel) ?>
                            </span>
                        </td>

                        <td style="padding:12px;vertical-align:top">
                            <span style="padding:4px 8px;border-radius:6px;font-size:12px;font-weight:bold;<?= $statusClass ?>">
                                <?= tk_h($statusLabel) ?>
                            </span>
                        </td>

                        <td style="padding:12px;vertical-align:top;color:#64748b">
                            <?= date('d/m/Y H:i', strtotime((string)$t['updated_at'])) ?>
                        </td>

                        <td style="padding:12px;vertical-align:top;text-align:center">
                            <a href="chamado-detalhe.php?id=<?= (int)$t['id'] ?>" 
                               style="padding:6px 14px;background:#183049;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:13px;display:inline-block">
                                Ver Chamado →
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- SCRIPT DE FILTRAGEM DINÂMICA DE PROCESSOS POR CLIENTE -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const clientSelect = document.getElementById('client_id_select');
    const caseSelect = document.getElementById('case_id_select');

    if (clientSelect && caseSelect) {
        const caseOptions = Array.from(caseSelect.querySelectorAll('option[data-client-id]'));

        function filterCasesByClient() {
            const selectedClientId = clientSelect.value;
            
            // Reseta a seleção do processo ao trocar de cliente
            caseSelect.value = '';

            caseOptions.forEach(option => {
                const optionClientId = option.getAttribute('data-client-id');

                if (!selectedClientId || optionClientId === selectedClientId) {
                    option.style.display = '';
                    option.disabled = false;
                } else {
                    option.style.display = 'none';
                    option.disabled = true;
                }
            });
        }

        clientSelect.addEventListener('change', filterCasesByClient);
        filterCasesByClient(); // Executa na carga inicial
    }
});
</script>

<?php portal_footer(); ?>