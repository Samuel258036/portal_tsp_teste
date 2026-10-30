<?php

declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/portal-layout.php';

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

$admin = is_admin();
$user = current_user();
$userId = (int)($user['id'] ?? 0);
$clientId = (int)($user['client_id'] ?? 0);
if (empty($_SESSION['updates_csrf'])) $_SESSION['updates_csrf'] = bin2hex(random_bytes(32));
$csrf = (string)$_SESSION['updates_csrf'];

if ($admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) upd_flash('error', 'Sessão expirada. Atualize a página.');
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'create') {
            $caseId = filter_var($_POST['case_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $title = trim((string)($_POST['title'] ?? ''));
            $message = trim((string)($_POST['client_message'] ?? ''));
            $note = trim((string)($_POST['internal_note'] ?? ''));
            $date = trim((string)($_POST['event_date'] ?? ''));
            $actionRequired = isset($_POST['client_action_required']) ? 1 : 0;
            $mode = (string)($_POST['mode'] ?? 'draft');
            if (!$caseId || $title === '' || mb_strlen($title) > 255 || mb_strlen($message) > 10000 || mb_strlen($note) > 10000) upd_flash('error', 'Revise o processo, o título e o tamanho dos textos.');
            if ($mode === 'publish' && $message === '') upd_flash('error', 'Informe a mensagem ao cliente antes de publicar.');
            $dateValue = null;
            if ($date !== '') {
                $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if (!$parsed || $parsed->format('Y-m-d') !== $date) upd_flash('error', 'Data inválida.');
                $dateValue = $date . ' 00:00:00';
            }
            $stmt = $pdo->prepare('SELECT id FROM cases WHERE id = ? AND active = 1 LIMIT 1');
            $stmt->execute([$caseId]);
            if (!$stmt->fetchColumn()) upd_flash('error', 'Processo não encontrado ou inativo.');
            $published = $mode === 'publish';
            $stmt = $pdo->prepare("INSERT INTO case_updates (case_id, author_user_id, update_type, title, client_message, internal_note, visible_to_client, client_action_required, status, source, event_date, published_at, created_at, updated_at) VALUES (?, ?, 'tsp', ?, ?, ?, ?, ?, ?, 'manual', ?, ?, NOW(), NOW())");
            $stmt->execute([$caseId, $userId, $title, $message !== '' ? $message : null, $note !== '' ? $note : null, $published ? 1 : 0, $actionRequired, $published ? 'published' : 'draft', $dateValue, $published ? date('Y-m-d H:i:s') : null]);
            upd_flash('ok', $published ? 'Atualização publicada com sucesso.' : 'Rascunho salvo com sucesso.');
        }
        if ($action === 'publish') {
            $id = filter_var($_POST['update_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) upd_flash('error', 'Atualização inválida.');
            $stmt = $pdo->prepare("UPDATE case_updates u INNER JOIN cases c ON c.id = u.case_id SET u.status = 'published', u.visible_to_client = 1, u.published_at = NOW() WHERE u.id = ? AND u.status IN ('draft', 'pending_review') AND c.active = 1 AND u.client_message IS NOT NULL AND TRIM(u.client_message) <> ''");
            $stmt->execute([$id]);
            upd_flash($stmt->rowCount() ? 'ok' : 'error', $stmt->rowCount() ? 'Atualização publicada.' : 'Não foi possível publicar. Confira a mensagem e a situação.');
        }
        if ($action === 'hide') {
            $id = filter_var($_POST['update_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) upd_flash('error', 'Atualização inválida.');
            $stmt = $pdo->prepare("UPDATE case_updates SET visible_to_client = 0, status = 'pending_review', published_at = NULL WHERE id = ? AND status = 'published'");
            $stmt->execute([$id]);
            upd_flash($stmt->rowCount() ? 'ok' : 'error', $stmt->rowCount() ? 'Publicação retirada da área do cliente.' : 'Atualização não encontrada.');
        }
        upd_flash('error', 'Ação desconhecida.');
    } catch (Throwable $e) {
        error_log('TSP case updates error: ' . $e->getMessage());
        upd_flash('error', 'Não foi possível concluir a operação. Consulte o log do servidor.');
    }
}

$cases = [];
$updates = [];
$error = null;
try {
    if ($admin) {
        $cases = $pdo->query('SELECT c.id, c.case_number, c.title, cl.name AS client_name FROM cases c INNER JOIN clients cl ON cl.id = c.client_id WHERE c.active = 1 ORDER BY c.created_at DESC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
        $updates = $pdo->query('SELECT u.id, u.case_id, u.title, u.client_message, u.internal_note, u.update_type, u.status, u.source, u.visible_to_client, u.client_action_required, u.event_date, u.published_at, u.created_at, c.case_number, c.title AS case_title, cl.name AS client_name FROM case_updates u INNER JOIN cases c ON c.id = u.case_id INNER JOIN clients cl ON cl.id = c.client_id ORDER BY COALESCE(u.event_date, u.created_at) DESC, u.id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($clientId > 0) {
        $stmt = $pdo->prepare("SELECT u.id, u.title, u.client_message, u.client_action_required, u.event_date, u.published_at, c.case_number, c.title AS case_title FROM case_updates u INNER JOIN cases c ON c.id = u.case_id WHERE c.client_id = ? AND c.active = 1 AND u.status = 'published' AND u.visible_to_client = 1 AND u.published_at IS NOT NULL ORDER BY COALESCE(u.event_date, u.published_at) DESC, u.id DESC LIMIT 200");
        $stmt->execute([$clientId]);
        $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('TSP case updates read error: ' . $e->getMessage());
    $error = 'Não foi possível carregar as atualizações. Consulte o log do servidor.';
}
portal_header('Atualizações', 'atualizacoes');
$flash = flash_get();
?>
<section class="panel"><span>PORTAL TSP</span>
    <h2>Atualizações processuais</h2>
    <p><?= $admin ? 'Registre, revise e publique as movimentações de cada processo.' : 'Acompanhe as movimentações e orientações publicadas pela TSP.' ?></p>
</section>
<?php if ($flash): ?><section class="panel" style="margin-top:18px">
        <p role="status" style="color:<?= ($flash['type'] ?? '') === 'ok' ? '#146c43' : '#9f2525' ?>"><?= upd_h($flash['message'] ?? '') ?></p>
    </section><?php endif; ?>
<?php if ($error): ?><section class="panel" style="margin-top:18px">
        <p role="alert"><?= upd_h($error) ?></p>
    </section><?php endif; ?>
<?php if ($admin && !$error): ?>
    <section class="panel" style="margin-top:20px">
        <h2>Nova atualização</h2>
        <p>O rascunho não aparece para o cliente. A publicação exige uma mensagem destinada ao cliente.</p>
        <form method="post" style="display:grid;gap:14px;max-width:900px">
            <input type="hidden" name="csrf" value="<?= upd_h($csrf) ?>"><input type="hidden" name="action" value="create">
            <label>Processo *<select name="case_id" required style="display:block;width:100%;padding:12px;margin-top:5px">
                    <option value="">Selecione o processo</option><?php foreach ($cases as $case): ?><option value="<?= (int)$case['id'] ?>"><?= upd_h($case['case_number'] . ' — ' . $case['client_name'] . ' — ' . $case['title']) ?></option><?php endforeach; ?>
                </select></label>
            <label>Título *<input name="title" maxlength="255" required style="display:block;width:100%;padding:12px;margin-top:5px"></label>
            <label>Data da movimentação (opcional)<input name="event_date" type="date" style="display:block;padding:12px;margin-top:5px"></label>
            <label>Mensagem para o cliente<textarea name="client_message" rows="4" maxlength="10000" style="display:block;width:100%;padding:12px;margin-top:5px"></textarea></label>
            <label>Nota interna — nunca exibida ao cliente<textarea name="internal_note" rows="3" maxlength="10000" style="display:block;width:100%;padding:12px;margin-top:5px"></textarea></label>
            <label><input type="checkbox" name="client_action_required" value="1"> Cliente precisa realizar uma ação</label>
            <div style="display:flex;gap:12px;flex-wrap:wrap"><button name="mode" value="draft" type="submit" style="padding:12px 18px;border:0;border-radius:8px;background:#183049;color:#fff;cursor:pointer">Salvar rascunho</button><button name="mode" value="publish" type="submit" style="padding:12px 18px;border:0;border-radius:8px;background:#9a772f;color:#fff;cursor:pointer">Publicar para o cliente</button></div>
        </form>
    </section>
<?php endif; ?>
<section class="panel" style="margin-top:20px">
    <h2><?= $admin ? 'Histórico e revisão' : 'Minhas atualizações' ?></h2>
    <?php if (!$updates): ?><p><?= $error ? 'Indisponível no momento.' : 'Nenhuma atualização disponível no momento.' ?></p><?php else: ?>
        <div style="display:grid;gap:14px;margin-top:18px">
            <?php foreach ($updates as $item): ?>
                <article style="padding:18px;border:1px solid #e4dfd6;border-radius:12px">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                        <div><strong><?= upd_h($item['title']) ?></strong>
                            <p style="margin:5px 0"><?= upd_h($item['case_number'] . ' — ' . $item['case_title']) ?><?= $admin ? ' · ' . upd_h($item['client_name']) : '' ?></p>
                        </div><small><?= upd_h(upd_date($item['event_date'] ?: ($item['published_at'] ?? $item['created_at'] ?? null))) ?></small>
                    </div>
                    <?php if ($admin): ?><p><strong>Situação:</strong> <?= upd_h($item['status']) ?> · <strong>Origem:</strong> <?= upd_h($item['source'] ?: $item['update_type']) ?> · <strong>Visível:</strong> <?= (int)$item['visible_to_client'] ? 'Sim' : 'Não' ?></p><?php endif; ?>
                    <?php if (trim((string)($item['client_message'] ?? '')) !== ''): ?><p style="white-space:pre-wrap"><?= upd_h($item['client_message']) ?></p><?php endif; ?>
                    <?php if (!empty($item['client_action_required'])): ?><p style="color:#9a772f"><strong>Ação necessária do cliente</strong></p><?php endif; ?>
                    <?php if ($admin): ?>
                        <?php if (trim((string)($item['internal_note'] ?? '')) !== ''): ?><p style="padding:12px;background:#f4f1ea;border-radius:8px;white-space:pre-wrap"><strong>Nota interna:</strong> <?= upd_h($item['internal_note']) ?></p><?php endif; ?>
                        <?php if (in_array($item['status'], ['draft', 'pending_review'], true) && trim((string)($item['client_message'] ?? '')) !== ''): ?><form method="post" style="display:inline-block;margin-right:8px"><input type="hidden" name="csrf" value="<?= upd_h($csrf) ?>"><input type="hidden" name="action" value="publish"><input type="hidden" name="update_id" value="<?= (int)$item['id'] ?>"><button type="submit" style="padding:9px 14px;border:0;border-radius:7px;background:#9a772f;color:#fff;cursor:pointer">Aprovar e publicar</button></form><?php endif; ?>
                        <?php if ($item['status'] === 'published' && (int)$item['visible_to_client'] === 1): ?><form method="post" style="display:inline-block"><input type="hidden" name="csrf" value="<?= upd_h($csrf) ?>"><input type="hidden" name="action" value="hide"><input type="hidden" name="update_id" value="<?= (int)$item['id'] ?>"><button type="submit" style="padding:9px 14px;border:1px solid #ddd;border-radius:7px;background:white;cursor:pointer">Retirar publicação</button></form><?php endif; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php portal_footer(); ?>