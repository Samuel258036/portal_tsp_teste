<?php

declare(strict_types=1);
require_once __DIR__ . '/../api/auth.php';
require_login();
require_once __DIR__ . '/../api/access_control.php';
require_once __DIR__ . '/portal-layout.php';

function case_h($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function case_text($v, int $max): ?string
{
    $s = trim((string)($v ?? ''));
    return $s === '' ? null : mb_substr($s, 0, $max);
}

if (empty($_SESSION['cases_csrf'])) {
    $_SESSION['cases_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['cases_csrf'];

$user      = current_user();
$userId    = (int)($user['id'] ?? 0);
$clientId  = (int)($user['client_id'] ?? 0);
$isStaff   = is_staff();
$canCreate = is_admin_or_gestao();

$error = null;
$clients = [];
$cases = [];

// --- CRIAÇÃO DE NOVO PROCESSO (ADMIN E GESTÃO) ---
if ($canCreate && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Sessão expirada. Atualize a página e tente novamente.';
    } elseif (($_POST['action'] ?? '') === 'create') {
        $cid    = (int)($_POST['client_id'] ?? 0);
        $number = trim((string)($_POST['case_number'] ?? ''));
        $title  = trim((string)($_POST['title'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'Em andamento'));
        $state  = strtoupper(trim((string)($_POST['state'] ?? '')));
        $date   = trim((string)($_POST['next_step_date'] ?? ''));

        if ($cid < 1 || $number === '' || $title === '' || strlen($number) > 100 || mb_strlen($title) > 255 || mb_strlen($status) > 100 || ($state !== '' && !preg_match('/^[A-Z]{2}$/', $state)) || ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))) {
            $error = 'Confira cliente, número, título, situação, UF e data.';
        } else {
            try {
                $valid = $pdo->prepare('SELECT id FROM clients WHERE id = ? AND active = 1 LIMIT 1');
                $valid->execute([$cid]);
                if (!$valid->fetchColumn()) {
                    $error = 'Selecione um cliente ativo.';
                } else {
                    $sql = 'INSERT INTO cases (client_id, case_number, title, legal_area, court, court_unit, instance, state, status, summary, next_step, next_step_date, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $cid,
                        $number,
                        $title,
                        case_text($_POST['legal_area'] ?? '', 120),
                        case_text($_POST['court'] ?? '', 180),
                        case_text($_POST['court_unit'] ?? '', 180),
                        case_text($_POST['instance'] ?? '', 80),
                        $state === '' ? null : $state,
                        $status,
                        case_text($_POST['summary'] ?? '', 10000),
                        case_text($_POST['next_step'] ?? '', 10000),
                        $date === '' ? null : $date,
                    ]);
                    flash_set('ok', 'Processo cadastrado e vinculado ao cliente com sucesso.');
                    header('Location: processos.php');
                    exit;
                }
            } catch (Throwable $e) {
                error_log('TSP cases create: ' . $e->getMessage());
                $error = 'Não foi possível cadastrar o processo. Confira se ele já existe e tente novamente.';
            }
        }
    }
}

// --- CONSULTA DE PROCESSOS POR PERFIL (RBAC) ---
try {
    $search = trim((string)($_GET['search'] ?? ''));

    if ($isStaff) {
        $clients = $pdo->query('SELECT id, name FROM clients WHERE active = 1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        
        $whereSql = "WHERE c.active = 1";
        $params = [];

        if ($search !== '') {
            $whereSql .= " AND (c.case_number LIKE ? OR c.title LIKE ? OR cl.name LIKE ?)";
            $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
        }

        $sql = "SELECT c.id, c.client_id, c.case_number, c.title, c.legal_area, c.court, c.status, c.summary, c.next_step, c.next_step_date, c.created_at, c.is_confidential, cl.name AS client_name 
                FROM cases c 
                INNER JOIN clients cl ON cl.id = c.client_id 
                {$whereSql} 
                ORDER BY c.created_at DESC, c.id DESC LIMIT 200";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Consulta restrita ao Cliente / Terceiro Autorizado via access_control.php (usa alias `c`)
        $queryData = get_authorized_cases_query($pdo, $userId);

        $searchSql = "";
        if ($search !== '') {
            $searchSql = " AND (c.case_number LIKE :search OR c.title LIKE :search)";
            $queryData['params'][':search'] = "%{$search}%";
        }

        $sql = "SELECT c.id, c.client_id, c.case_number, c.title, c.legal_area, c.court, c.status, c.summary, c.next_step, c.next_step_date, c.created_at 
                FROM cases c 
                INNER JOIN clients cl ON cl.id = c.client_id 
                WHERE {$queryData['where']} 
                  {$searchSql} 
                  AND c.active = 1 
                  AND cl.active = 1 
                  AND (c.is_confidential = 0 OR c.is_confidential IS NULL)
                ORDER BY c.created_at DESC, c.id DESC LIMIT 200";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($queryData['params']);
        $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('TSP cases listing: ' . $e->getMessage());
    $error = 'Não foi possível carregar os processos neste momento.';
}

portal_header('Processos', 'processos');
?>

<section class="panel">
    <span>PORTAL TSP</span>
    <h2>Processos</h2>
    <p><?= $isStaff ? 'Gerencie os processos do escritório e vincule cada registro ao cliente correto.' : 'Acompanhe os processos vinculados ao seu cadastro.' ?></p>
</section>

<?php if ($error): ?>
<section class="panel" style="margin-top:20px;color:#8b2525">
    <p role="alert"><?= case_h($error) ?></p>
</section>
<?php endif; ?>

<?php if ($canCreate): ?>
<!-- FORMULÁRIO DE CADASTRO (APENAS ADMIN E GESTÃO) -->
<section class="panel" style="margin-top:20px">
    <h2>Novo processo</h2>
    <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px">
        <input type="hidden" name="csrf" value="<?= case_h($csrf) ?>">
        <input type="hidden" name="action" value="create">
        
        <label>Cliente *
            <select name="client_id" required style="display:block;width:100%;padding:12px;margin-top:6px">
                <option value="">Selecione</option>
                <?php foreach ($clients as $cl): ?>
                    <option value="<?= (int)$cl['id'] ?>"><?= case_h($cl['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        
        <label>Número do processo *<input name="case_number" maxlength="100" required style="display:block;width:100%;padding:12px;margin-top:6px"></label>
        <label>Título / assunto *<input name="title" maxlength="255" required style="display:block;width:100%;padding:12px;margin-top:6px"></label>
        <label>Situação *<input name="status" value="Em andamento" maxlength="100" required style="display:block;width:100%;padding:12px;margin-top:6px"></label>
        <label>Área jurídica<input name="legal_area" maxlength="120" style="display:block;width:100%;padding:12px;margin-top:6px"></label>
        <label>Tribunal<input name="court" maxlength="180" style="display:block;width:100%;padding:12px;margin-top:6px"></label>
        <label>Vara / unidade<input name="court_unit" maxlength="180" style="display:block;width:100%;padding:12px;margin-top:6px"></label>
        <label>Instância<input name="instance" maxlength="80" style="display:block;width:100%;padding:12px;margin-top:6px"></label>
        <label>UF<input name="state" maxlength="2" placeholder="SP" style="display:block;width:100%;padding:12px;margin-top:6px"></label>
        <label>Data da próxima etapa<input type="date" name="next_step_date" style="display:block;width:100%;padding:12px;margin-top:6px"></label>
        
        <label style="grid-column:1/-1">Resumo para o cliente<textarea name="summary" rows="3" style="display:block;width:100%;padding:12px;margin-top:6px"></textarea></label>
        <label style="grid-column:1/-1">Próxima etapa<textarea name="next_step" rows="2" style="display:block;width:100%;padding:12px;margin-top:6px"></textarea></label>
        
        <div style="grid-column:1/-1">
            <button type="submit" style="padding:13px 22px;background:#183049;color:white;border:0;border-radius:9px;cursor:pointer">Cadastrar processo</button>
        </div>
    </form>
    <p style="font-size:13px;margin-top:16px;color:#64748b">Cadastro manual nesta etapa. A sincronização com o Juridiq será habilitada após validação dos vínculos e das regras de sigilo.</p>
</section>
<?php endif; ?>

<!-- LISTAGEM COM PESQUISA E LINK DE DETALHAMENTO -->
<section class="panel" style="margin-top:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px">
        <h2><?= $isStaff ? 'Processos cadastrados' : 'Meus processos' ?></h2>

        <!-- BARRA DE PESQUISA -->
        <form method="GET" action="processos.php" style="display:flex;gap:8px;align-items:center">
            <input type="text" name="search" placeholder="Pesquisar por CNJ ou título..." value="<?= case_h($_GET['search'] ?? '') ?>" style="padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px">
            <button type="submit" style="padding:8px 14px;background:#183049;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px">Pesquisar</button>
            <?php if (!empty($_GET['search'])): ?>
                <a href="processos.php" style="padding:8px 12px;background:#e2e8f0;color:#334155;text-decoration:none;border-radius:6px;font-size:14px">Limpar</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!$cases): ?>
        <p style="color:#64748b;padding:10px 0">Nenhum processo encontrado.</p>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;text-align:left;font-size:14px">
                <thead>
                    <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0">
                        <?php if ($isStaff): ?><th style="padding:12px">Cliente</th><?php endif; ?>
                        <th style="padding:12px">Processo</th>
                        <th style="padding:12px">Situação</th>
                        <th style="padding:12px">Resumo / Próxima etapa</th>
                        <th style="padding:12px;text-align:center">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cases as $ca): ?>
                    <tr style="border-top:1px solid #e2e8f0">
                        <?php if ($isStaff): ?>
                            <td style="padding:12px;vertical-align:top">
                                <strong><?= case_h($ca['client_name']) ?></strong>
                            </td>
                        <?php endif; ?>

                        <td style="padding:12px;vertical-align:top">
                            <span style="font-size:11px;background:#f1f5f9;color:#475569;padding:2px 6px;border-radius:4px;font-weight:600">
                                CNJ: <?= case_h($ca['case_number']) ?>
                            </span>
                            <?php if ($isStaff && !empty($ca['is_confidential'])): ?>
                                <span style="background:#d9534f;color:#fff;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:bold;margin-left:4px">SIGILOSO</span>
                            <?php endif; ?>
                            <div style="font-weight:bold;margin-top:4px;color:#0f172a"><?= case_h($ca['title']) ?></div>
                            <small style="color:#64748b"><?= case_h($ca['court']) ?></small>
                        </td>

                        <td style="padding:12px;vertical-align:top">
                            <span style="padding:4px 10px;border-radius:12px;background:#e2e8f0;color:#334155;font-weight:600;font-size:12px;display:inline-block">
                                <?= case_h($ca['status']) ?>
                            </span>
                        </td>

                        <td style="padding:12px;vertical-align:top">
                            <div><?= case_h($ca['summary']) ?></div>
                            <?php if (!empty($ca['next_step'])): ?>
                                <p style="margin-top:4px;font-size:13px;color:#334155"><strong>Próxima etapa:</strong> <?= case_h($ca['next_step']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($ca['next_step_date'])): ?>
                                <small style="color:#64748b">Previsão: <?= case_h(date('d/m/Y', strtotime((string)$ca['next_step_date']))) ?></small>
                            <?php endif; ?>
                        </td>

                        <td style="padding:12px;vertical-align:top;text-align:center;white-space:nowrap">
                            <a href="processo-detalhe.php?id=<?= (int)$ca['id'] ?>" 
                               style="display:inline-block;padding:8px 14px;background:#183049;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:13px">
                                Detalhes →
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php portal_footer(); ?>