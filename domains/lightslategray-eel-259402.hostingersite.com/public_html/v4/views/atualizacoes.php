<?php

declare(strict_types=1);
require_once __DIR__ . '/../api/auth.php';
require_login();
require_once __DIR__ . '/../api/atualizacoes-controller.php';
require_once __DIR__ . '/portal-layout.php';
portal_header('Atualizações', 'atualizacoes');
?>

<section class="panel"><span>PORTAL TSP</span>
    <h2>Atualizações processuais</h2>
    <p><?= $admin ? 'Registre, revise e publique as movimentações de cada processo.' : 'Acompanhe as movimentações e orientações publicadas pela TSP.' ?>
    </p>
</section>

<?php if ($flash): ?><section class="panel" style="margin-top:18px">
    <p role="status" style="color:<?= ($flash['type'] ?? '') === 'ok' ? '#146c43' : '#9f2525' ?>">
        <?= upd_h($flash['message'] ?? '') ?></p>
</section><?php endif; ?>

<?php if ($error): ?><section class="panel" style="margin-top:18px">
    <p role="alert"><?= upd_h($error) ?></p>
</section><?php endif; ?>

<?php if ($admin && !$error): ?>
<section class="panel" style="margin-top:20px">
    <h2>Nova atualização manual</h2>
    <form method="post" style="display:grid;gap:14px;max-width:900px">
        <input type="hidden" name="csrf" value="<?= upd_h($csrf) ?>">
        <input type="hidden" name="action" value="create">
        <label>Processo *<select name="case_id" required style="display:block;width:100%;padding:12px;margin-top:5px">
                <option value="">Selecione o processo</option>
                <?php foreach ($cases as $case): ?>
                <option value="<?= (int)$case['id'] ?>">
                    <?= upd_h($case['case_number'] . ' — ' . $case['client_name'] . ' — ' . $case['title']) ?>
                    <?= !empty($case['is_confidential']) ? '[SIGILOSO]' : '' ?>
                </option>
                <?php endforeach; ?>
            </select></label>
        <label>Título *<input name="title" maxlength="255" required
                style="display:block;width:100%;padding:12px;margin-top:5px"></label>
        <label>Data da movimentação (opcional)<input name="event_date" type="date"
                style="display:block;padding:12px;margin-top:5px"></label>
        <label>Mensagem para o cliente<textarea name="client_message" rows="3" maxlength="10000"
                style="display:block;width:100%;padding:12px;margin-top:5px"></textarea></label>
        <label>Nota interna<textarea name="internal_note" rows="2" maxlength="10000"
                style="display:block;width:100%;padding:12px;margin-top:5px"></textarea></label>
        <label><input type="checkbox" name="client_action_required" value="1"> Cliente precisa realizar uma ação</label>
        <div style="display:flex;gap:12px">
            <button name="mode" value="draft" type="submit"
                style="padding:12px 18px;border:0;border-radius:8px;background:#183049;color:#fff;cursor:pointer">Salvar
                rascunho</button>
            <button name="mode" value="publish" type="submit"
                style="padding:12px 18px;border:0;border-radius:8px;background:#9a772f;color:#fff;cursor:pointer">Publicar
                para o cliente</button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="panel" style="margin-top:20px">
    <h2><?= $admin ? 'Fila de Revisão e Histórico' : 'Minhas atualizações' ?></h2>
    <?php if (!$updates): ?><p>Nenhuma atualização disponível no momento.</p><?php else: ?>
    <div style="display:grid;gap:14px;margin-top:18px">
        <?php foreach ($updates as $item): ?>
        <article
            style="padding:18px;border:1px solid <?= ($item['status'] ?? '') === 'pending_review' ? '#f0ad4e' : '#e4dfd6' ?>;border-radius:12px;background:<?= ($item['status'] ?? '') === 'pending_review' ? '#fffdf8' : '#fff' ?>">

            <?php if ($admin && !empty($item['is_confidential'])): ?>
            <span
                style="background:#d9534f;color:#fff;padding:2px 8px;border-radius:4px;font-size:12px;float:right">PROCESSO
                SIGILOSO</span>
            <?php endif; ?>

            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                <div>
                    <strong><?= upd_h($item['title']) ?></strong>
                    <p style="margin:5px 0">
                        <?= upd_h($item['case_number'] . ' — ' . $item['case_title']) ?><?= $admin ? ' · ' . upd_h($item['client_name']) : '' ?>
                    </p>
                </div>
                <small><?= upd_h(upd_date($item['event_date'] ?: ($item['published_at'] ?? $item['created_at'] ?? null))) ?></small>
            </div>

            <?php if ($admin): ?>
            <p style="font-size:13px;color:#555">
                <strong>Situação:</strong> <?= upd_h($item['status']) ?> ·
                <strong>Origem:</strong> <?= upd_h($item['source'] ?: $item['update_type']) ?> ·
                <strong>Visável ao cliente:</strong> <?= (int)$item['visible_to_client'] ? 'Sim' : 'Não' ?>
                <?php if (!empty($item['approver_name'])): ?> · <strong>Aprovado por:</strong>
                <?= upd_h($item['approver_name']) ?> em <?= upd_date($item['published_at']) ?><?php endif; ?>
            </p>
            <?php endif; ?>

            <!-- MODO EDIÇÃO / REVISÃO PARA ADMIN -->
            <?php if ($admin && in_array($item['status'], ['pending_review', 'draft'], true)): ?>
            <form method="post"
                style="display:grid;gap:10px;margin-top:12px;padding:12px;background:#f9f9f9;border-radius:8px">
                <input type="hidden" name="csrf" value="<?= upd_h($csrf) ?>">
                <input type="hidden" name="action" value="update_pending">
                <input type="hidden" name="update_id" value="<?= (int)$item['id'] ?>">

                <label style="font-size:13px"><strong>Título para o Cliente:</strong>
                    <input name="title" value="<?= upd_h($item['title']) ?>" required
                        style="display:block;width:100%;padding:8px;margin-top:4px">
                </label>
                <label style="font-size:13px"><strong>Redigir Mensagem ao Cliente:</strong>
                    <textarea name="client_message" rows="3" style="display:block;width:100%;padding:8px;margin-top:4px"
                        placeholder="Traduza o andamento jurídico em linguagem clara para o cliente..."><?= upd_h($item['client_message'] ?? '') ?></textarea>
                </label>
                <label style="font-size:13px"><strong>Nota Interna (Original/Equipe):</strong>
                    <textarea name="internal_note" rows="2"
                        style="display:block;width:100%;padding:8px;margin-top:4px"><?= upd_h($item['internal_note'] ?? '') ?></textarea>
                </label>
                <label style="font-size:13px">
                    <input type="checkbox" name="client_action_required" value="1"
                        <?= !empty($item['client_action_required']) ? 'checked' : '' ?>> Exige ação do cliente
                </label>

                <div style="display:flex;gap:8px;margin-top:6px">
                    <button name="mode" value="draft" type="submit"
                        style="padding:8px 12px;border:0;border-radius:6px;background:#6c757d;color:#fff;cursor:pointer">Salvar
                        Rascunho</button>
                    <button name="mode" value="publish" type="submit"
                        style="padding:8px 12px;border:0;border-radius:6px;background:#198754;color:#fff;cursor:pointer">Aprovar
                        e Publicar</button>
                    <button type="submit" form="reject-form-<?= (int)$item['id'] ?>"
                        style="padding:8px 12px;border:0;border-radius:6px;background:#dc3545;color:#fff;cursor:pointer">Rejeitar</button>
                </div>
            </form>

            <form id="reject-form-<?= (int)$item['id'] ?>" method="post" style="display:none">
                <input type="hidden" name="csrf" value="<?= upd_h($csrf) ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="update_id" value="<?= (int)$item['id'] ?>">
            </form>
            <?php else: ?>

            <?php if (trim((string)($item['client_message'] ?? '')) !== ''): ?>
            <p style="white-space:pre-wrap"><?= upd_h($item['client_message']) ?></p>
            <?php endif; ?>

            <?php if ($admin && $item['status'] === 'published'): ?>
            <form method="post" style="display:inline-block;margin-top:8px">
                <input type="hidden" name="csrf" value="<?= upd_h($csrf) ?>">
                <input type="hidden" name="action" value="hide">
                <input type="hidden" name="update_id" value="<?= (int)$item['id'] ?>">
                <button type="submit"
                    style="padding:6px 12px;border:1px solid #ccc;border-radius:6px;background:#fff;cursor:pointer">Retirar
                    Publicação</button>
            </form>
            <?php endif; ?>

            <?php endif; ?>

        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php portal_footer(); ?>