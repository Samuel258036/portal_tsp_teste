<?php

declare(strict_types=1);
require_once __DIR__ . '/../api/auth.php';
require_login();
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

if (empty($_SESSION['cases_csrf'])) $_SESSION['cases_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['cases_csrf'];
$admin = is_admin();
$user = current_user();
$clientId = (int)($user['client_id'] ?? 0);
$error = null;
$clients = [];
$cases = [];

if ($admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Sessão expirada. Atualize a página e tente novamente.';
    } elseif (($_POST['action'] ?? '') === 'create') {
        $cid = (int)($_POST['client_id'] ?? 0);
        $number = trim((string)($_POST['case_number'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'Em andamento'));
        $state = strtoupper(trim((string)($_POST['state'] ?? '')));
        $date = trim((string)($_POST['next_step_date'] ?? ''));
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

try {
    if ($admin) {
        $clients = $pdo->query('SELECT id, name FROM clients WHERE active = 1 ORDER BY name')->fetchAll();
        $cases = $pdo->query('SELECT ca.id, ca.client_id, ca.case_number, ca.title, ca.legal_area, ca.court, ca.status, ca.summary, ca.next_step, ca.next_step_date, ca.created_at, cl.name AS client_name FROM cases ca INNER JOIN clients cl ON cl.id = ca.client_id WHERE ca.active = 1 ORDER BY ca.created_at DESC, ca.id DESC LIMIT 200')->fetchAll();
    } else {
        // Se não for admin, carrega processos onde o utilizador é o TITULAR ou TERCEIRO AUTORIZADO
        $userId = (int)$user['id'];
        
        $sql = "SELECT ca.id, ca.client_id, ca.case_number, ca.title, ca.legal_area, ca.court, ca.status, ca.summary, ca.next_step, ca.next_step_date, ca.created_at 
                FROM cases ca 
                INNER JOIN clients cl ON cl.id = ca.client_id 
                WHERE ca.active = 1 
                AND cl.active = 1 
                AND (
                    ca.client_id = :client_id 
                    OR ca.id IN (SELECT cp.case_id FROM case_permissions cp WHERE cp.user_id = :user_id AND cp.status = 'active')
                ) 
                ORDER BY ca.created_at DESC, ca.id DESC LIMIT 200";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':client_id' => $clientId,
            ':user_id'   => $userId
        ]);
        $cases = $stmt->fetchAll();
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
    <p><?= $admin ? 'Cadastre processos e vincule cada registro ao cliente correto.' : 'Acompanhe os processos vinculados ao seu cadastro.' ?></p>
</section>
<?php if ($error): ?><section class="panel" style="margin-top:20px;color:#8b2525">
        <p role="alert"><?= case_h($error) ?></p>
    </section><?php endif; ?>
<?php if ($admin): ?>
    <section class="panel" style="margin-top:20px">
        <h2>Novo processo</h2>
        <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px">
            <input type="hidden" name="csrf" value="<?= case_h($csrf) ?>">
            <input type="hidden" name="action" value="create">
            <label>Cliente *<select name="client_id" required style="display:block;width:100%;padding:12px;margin-top:6px">
                    <option value="">Selecione</option><?php foreach ($clients as $cl): ?><option value="<?= (int)$cl['id'] ?>"><?= case_h($cl['name']) ?></option><?php endforeach; ?>
                </select></label>
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
            <div style="grid-column:1/-1"><button type="submit" style="padding:13px 22px;background:#183049;color:white;border:0;border-radius:9px;cursor:pointer">Cadastrar processo</button></div>
        </form>
        <p style="font-size:13px;margin-top:16px">Cadastro manual nesta etapa. A sincronização com o Juridiq será habilitada após validação dos vínculos e das regras de sigilo.</p>
    </section>
<?php endif; ?>
<section class="panel" style="margin-top:20px">
    <h2><?= $admin ? 'Processos cadastrados' : 'Meus processos' ?></h2>
    <?php if (!$cases): ?><p>Nenhum processo disponível no momento.</p><?php else: ?>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;text-align:left">
                <thead>
                    <tr><?php if ($admin): ?><th style="padding:12px">Cliente</th><?php endif; ?><th style="padding:12px">Processo</th>
                        <th style="padding:12px">Situação</th>
                        <th style="padding:12px">Resumo / próxima etapa</th>
                    </tr>
                </thead>
                <tbody><?php foreach ($cases as $ca): ?><tr style="border-top:1px solid #ddd">
                            <?php if ($admin): ?><td style="padding:12px"><?= case_h($ca['client_name']) ?></td><?php endif; ?>
                            <td style="padding:12px"><strong><?= case_h($ca['case_number']) ?></strong>
                                <div><?= case_h($ca['title']) ?></div><small><?= case_h($ca['court']) ?></small>
                            </td>
                            <td style="padding:12px"><?= case_h($ca['status']) ?></td>
                            <td style="padding:12px"><?= case_h($ca['summary']) ?><?php if (!empty($ca['next_step'])): ?><p><strong>Próxima etapa:</strong> <?= case_h($ca['next_step']) ?></p><?php endif; ?><?php if (!empty($ca['next_step_date'])): ?><small>Previsão: <?= case_h($ca['next_step_date']) ?></small><?php endif; ?></td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php portal_footer(); ?>