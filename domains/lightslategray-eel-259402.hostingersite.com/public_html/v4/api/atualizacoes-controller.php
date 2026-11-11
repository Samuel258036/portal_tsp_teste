<?php

declare(strict_types=1);
require_once __DIR__ . '/../api/auth.php';
require_login();

function upd_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function upd_date($value): string
{
    if (!$value) return '—';
    try {
        return (new DateTimeImmutable((string)$value))->format('d/m/Y H:i');
    } catch (Throwable $e) {
        return '—';
    }
}

function upd_flash(string $type, string $message): void
{
    flash_set($type, $message);
    header('Location: atualizacoes.php');
    exit;
}

// RBAC: Identificação de papéis e permissões
$isStaff    = is_staff();            // admin, gestao, adv
$canApprove = is_admin_or_gestao();  // admin, gestao
$user       = current_user();
$userId     = (int)($user['id'] ?? 0);
$clientId   = (int)($user['client_id'] ?? 0);

if (empty($_SESSION['updates_csrf'])) {
    $_SESSION['updates_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['updates_csrf'];

// --- PROCESSAMENTO DE AÇÕES (POST) ---
if ($isStaff && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        upd_flash('error', 'Sessão expirada. Atualize a página.');
    }
    
    $action = (string)($_POST['action'] ?? '');
    
    try {
        // 1. CRIAR NOVA ATUALIZAÇÃO
        if ($action === 'create') {
            $caseId         = filter_var($_POST['case_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $title          = trim((string)($_POST['title'] ?? ''));
            $message        = trim((string)($_POST['client_message'] ?? ''));
            $note           = trim((string)($_POST['internal_note'] ?? ''));
            $date           = trim((string)($_POST['event_date'] ?? ''));
            $actionRequired = isset($_POST['client_action_required']) ? 1 : 0;
            $mode           = (string)($_POST['mode'] ?? 'draft');

            if (!$caseId || $title === '' || mb_strlen($title) > 255 || mb_strlen($message) > 10000 || mb_strlen($note) > 10000) {
                upd_flash('error', 'Revise o processo, o título e o tamanho dos textos.');
            }

            // Definição de status e permissão de publicação baseada na role
            $isPublishing = ($mode === 'publish' && $canApprove);
            if ($mode === 'publish' && !$canApprove) {
                $mode = 'pending'; // Advogados direcionam para revisão da gestão
            }

            if ($isPublishing && $message === '') {
                upd_flash('error', 'Informe a mensagem ao cliente antes de publicar.');
            }

            $dateValue = null;
            if ($date !== '') {
                $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                    upd_flash('error', 'Data inválida.');
                }
                $dateValue = $date . ' 00:00:00';
            }

            $stmt = $pdo->prepare('SELECT id FROM cases WHERE id = ? AND active = 1 LIMIT 1');
            $stmt->execute([$caseId]);
            if (!$stmt->fetchColumn()) {
                upd_flash('error', 'Processo não encontrado ou inativo.');
            }

            $status = match ($mode) {
                'publish' => 'published',
                'pending' => 'pending_review',
                default   => 'draft'
            };

            $visible = ($status === 'published') ? 1 : 0;

            $stmt = $pdo->prepare("
                INSERT INTO case_updates (
                    case_id, author_user_id, approved_by_user_id, update_type, 
                    title, client_message, internal_note, visible_to_client, 
                    client_action_required, status, source, event_date, published_at, created_at, updated_at
                ) VALUES (?, ?, ?, 'tsp', ?, ?, ?, ?, ?, ?, 'manual', ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $caseId,
                $userId,
                $isPublishing ? $userId : null,
                $title,
                $message !== '' ? $message : null,
                $note !== '' ? $note : null,
                $visible,
                $actionRequired,
                $status,
                $dateValue,
                $isPublishing ? date('Y-m-d H:i:s') : null
            ]);

            $msgRetorno = match ($status) {
                'published'      => 'Atualização publicada com sucesso.',
                'pending_review' => 'Atualização enviada para revisão da gestão.',
                default          => 'Rascunho salvo com sucesso.'
            };

            upd_flash('ok', $msgRetorno);
        }

        // 2. ATUALIZAR REGISTRO EM RASCUNHO / PENDENTE
        if ($action === 'update_pending') {
            $id             = filter_var($_POST['update_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $title          = trim((string)($_POST['title'] ?? ''));
            $message        = trim((string)($_POST['client_message'] ?? ''));
            $note           = trim((string)($_POST['internal_note'] ?? ''));
            $actionRequired = isset($_POST['client_action_required']) ? 1 : 0;
            $mode           = (string)($_POST['mode'] ?? 'draft');

            if (!$id || $title === '') {
                upd_flash('error', 'Dados inválidos para atualização.');
            }

            $isPublishing = ($mode === 'publish' && $canApprove);
            if ($mode === 'publish' && !$canApprove) {
                $mode = 'pending';
            }

            if ($isPublishing && $message === '') {
                upd_flash('error', 'Informe a mensagem ao cliente antes de publicar.');
            }

            $status = match ($mode) {
                'publish' => 'published',
                'pending' => 'pending_review',
                default   => 'draft'
            };

            $visible = ($status === 'published') ? 1 : 0;

            $stmt = $pdo->prepare("
                UPDATE case_updates 
                SET title = ?, 
                    client_message = ?, 
                    internal_note = ?, 
                    client_action_required = ?, 
                    status = ?, 
                    visible_to_client = ?, 
                    approved_by_user_id = IF(?, ?, approved_by_user_id), 
                    published_at = IF(?, NOW(), published_at), 
                    updated_at = NOW() 
                WHERE id = ? AND status IN ('draft', 'pending_review')
            ");
            
            $stmt->execute([
                $title,
                $message !== '' ? $message : null,
                $note !== '' ? $note : null,
                $actionRequired,
                $status,
                $visible,
                $isPublishing ? 1 : 0,
                $userId,
                $isPublishing ? 1 : 0,
                $id
            ]);

            $msgRetorno = match ($status) {
                'published'      => 'Andamento revisado e publicado!',
                'pending_review' => 'Alterações enviadas para revisão da gestão.',
                default          => 'Alterações salvas em rascunho.'
            };

            upd_flash('ok', $msgRetorno);
        }

        // 3. APROVAR E PUBLICAR (RESTRITO A ADMIN / GESTÃO)
        if ($action === 'publish') {
            if (!$canApprove) {
                upd_flash('error', 'Apenas a gestão ou administração pode aprovar publicações.');
            }

            $id = filter_var($_POST['update_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) {
                upd_flash('error', 'Atualização inválida.');
            }
            
            $stmt = $pdo->prepare("
                UPDATE case_updates u 
                INNER JOIN cases c ON c.id = u.case_id 
                SET u.status = 'published', 
                    u.visible_to_client = 1, 
                    u.approved_by_user_id = ?, 
                    u.published_at = NOW() 
                WHERE u.id = ? 
                  AND u.status IN ('draft', 'pending_review') 
                  AND c.active = 1 
                  AND u.client_message IS NOT NULL 
                  AND TRIM(u.client_message) <> ''
            ");
            $stmt->execute([$userId, $id]);
            
            upd_flash($stmt->rowCount() ? 'ok' : 'error', $stmt->rowCount() ? 'Atualização aprovada e publicada.' : 'Não foi possível publicar. Preencha a mensagem ao cliente.');
        }

        // 4. REJEITAR MOVIMENTAÇÃO (RESTRITO A ADMIN / GESTÃO)
        if ($action === 'reject') {
            if (!$canApprove) {
                upd_flash('error', 'Permissão negada.');
            }

            $id = filter_var($_POST['update_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) {
                upd_flash('error', 'Atualização inválida.');
            }
            
            $stmt = $pdo->prepare("
                UPDATE case_updates 
                SET status = 'rejected', 
                    visible_to_client = 0, 
                    published_at = NULL, 
                    approved_by_user_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([$userId, $id]);
            upd_flash('ok', 'Movimentação rejeitada.');
        }

        // 5. OCULTAR / RETIRAR PUBLICAÇÃO (RESTRITO A ADMIN / GESTÃO)
        if ($action === 'hide') {
            if (!$canApprove) {
                upd_flash('error', 'Permissão negada.');
            }

            $id = filter_var($_POST['update_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) {
                upd_flash('error', 'Atualização inválida.');
            }
            
            $stmt = $pdo->prepare("
                UPDATE case_updates 
                SET visible_to_client = 0, 
                    status = 'pending_review', 
                    published_at = NULL 
                WHERE id = ? AND status = 'published'
            ");
            $stmt->execute([$id]);
            upd_flash($stmt->rowCount() ? 'ok' : 'error', $stmt->rowCount() ? 'Publicação retirada da área do cliente.' : 'Atualização não encontrada.');
        }

        upd_flash('error', 'Ação desconhecida.');
    } catch (Throwable $e) {
        error_log('TSP case updates error: ' . $e->getMessage());
        upd_flash('error', 'Não foi possível concluir a operação. Consulte o log do servidor.');
    }
}

// --- CONSULTA E LEITURA DE DADOS (GET) ---
$cases = [];
$updates = [];
$userCases = [];
$error = null;

try {
    if ($isStaff) {
        // --- 1. CONSULTA PARA EQUIPE INTERNA (ADMIN, GESTÃO, ADVOGADOS) ---
        $cases = $pdo->query('
            SELECT c.id, c.case_number, c.title, c.is_confidential, cl.name AS client_name 
            FROM cases c 
            INNER JOIN clients cl ON cl.id = c.client_id 
            WHERE c.active = 1 
            ORDER BY c.created_at DESC 
            LIMIT 500
        ')->fetchAll(PDO::FETCH_ASSOC);
        
        $searchTerm = trim((string)($_GET['search'] ?? ''));
        $view       = trim((string)($_GET['view'] ?? 'all'));
        
        $whereConditions = ["1=1"];
        $params          = [];
        
        // Filtro por termo de busca
        if ($searchTerm !== '') {
            $whereConditions[] = "(c.case_number LIKE ? OR c.title LIKE ? OR u.title LIKE ?)";
            $params[] = '%' . $searchTerm . '%';
            $params[] = '%' . $searchTerm . '%';
            $params[] = '%' . $searchTerm . '%';
        }
        
        // Filtro estrito por abas de visualização (view)
        if ($view === 'pending') {
            $whereConditions[] = "u.status IN ('pending_review', 'draft')";
        } elseif ($view === 'published') {
            $whereConditions[] = "u.status = 'published' AND u.visible_to_client = 1";
        }

        $whereSql = implode(' AND ', $whereConditions);

        $sql = "
            SELECT u.id, u.case_id, u.title, u.client_message, u.internal_note, u.update_type, 
                   u.status, u.source, u.visible_to_client, u.client_action_required, u.event_date, 
                   u.published_at, u.created_at, u.approved_by_user_id, c.case_number, 
                   c.title AS case_title, c.is_confidential, cl.name AS client_name, usr.name AS approver_name 
            FROM case_updates u 
            INNER JOIN cases c ON c.id = u.case_id 
            INNER JOIN clients cl ON cl.id = c.client_id 
            LEFT JOIN users usr ON usr.id = u.approved_by_user_id
            WHERE {$whereSql}
            ORDER BY FIELD(u.status, 'pending_review', 'draft', 'published', 'rejected'), 
                     COALESCE(u.event_date, u.created_at) DESC 
            LIMIT 200
        ";
            
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // --- 2. CONSULTA RESTRITA PARA CLIENTES E TERCEIROS ---
        require_once __DIR__ . '/../api/access_control.php';
        
        $queryData = get_authorized_cases_query($pdo, $userId);
        
        $filterSql = "";
        $filterCaseId = filter_input(INPUT_GET, 'case_id', FILTER_VALIDATE_INT);
        
        if ($filterCaseId) {
            $filterSql = " AND c.id = :filter_case_id ";
            $queryData['params'][':filter_case_id'] = $filterCaseId;
        }
        
        // Carrega os processos autorizados para montar as abas do cliente
        if ($clientId > 0 || $userId > 0) {
            $casesSql = "
                SELECT c.id, c.case_number, c.title 
                FROM cases c 
                WHERE {$queryData['where']} 
                  AND c.active = 1 
                ORDER BY c.created_at DESC
            ";
            $stmtCases = $pdo->prepare($casesSql);
            $authOnlyParams = array_diff_key($queryData['params'], [':filter_case_id' => 1]);
            $stmtCases->execute($authOnlyParams);
            $userCases = $stmtCases->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Consulta de atualizações visíveis para o cliente
        $sql = "
            SELECT u.id, u.title, u.client_message, u.client_action_required, u.event_date, 
                   u.published_at, c.case_number, c.title AS case_title 
            FROM case_updates u 
            INNER JOIN cases c ON c.id = u.case_id 
            WHERE {$queryData['where']} 
              {$filterSql} 
              AND (c.is_confidential = 0 OR c.is_confidential IS NULL) 
              AND u.status = 'published' 
              AND u.visible_to_client = 1 
              AND u.published_at IS NOT NULL 
            ORDER BY COALESCE(u.event_date, u.published_at) DESC, u.id DESC 
            LIMIT 200
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($queryData['params']);
        $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('TSP case updates read error: ' . $e->getMessage());
    $error = 'Não foi possível carregar as atualizações. Consulte o log do servidor.';
}

$flash = flash_get();