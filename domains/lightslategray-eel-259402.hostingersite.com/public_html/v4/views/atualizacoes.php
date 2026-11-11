<?php

declare(strict_types=1);
require_once __DIR__ . '/../api/auth.php';
require_login();
require_once __DIR__ . '/../api/atualizacoes-controller.php';
require_once __DIR__ . '/portal-layout.php';

// Identificação de papéis e permissões (RBAC)
$isStaff    = is_staff();            // admin, gestao, adv
$canApprove = is_admin_or_gestao();  // admin, gestao
$user       = current_user();
$userRole   = $user['role'] ?? 'cliente';

portal_header('Atualizações', 'atualizacoes');
?>

<section class="panel"><span>PORTAL TSP</span>
    <h2>Atualizações processuais</h2>
    <p><?= $isStaff ? 'Registre, revise e publique as movimentações de cada processo.' : 'Acompanhe as movimentações e orientações publicadas pela TSP.' ?>
    </p>
</section>

<?php if (!empty($flash)): ?>
<section class="panel" style="margin-top:18px">
    <p role="status" style="color:<?= ($flash['type'] ?? '') === 'ok' ? '#146c43' : '#9f2525' ?>">
        <?= upd_h($flash['message'] ?? '') ?></p>
</section>
<?php endif; ?>

<?php if (!empty($error)): ?>
<section class="panel" style="margin-top:18px">
    <p role="alert"><?= upd_h($error) ?></p>
</section>
<?php endif; ?>

<?php if ($isStaff && empty($error)): ?>
<!-- FORMULÁRIO DE NOVA ATUALIZAÇÃO (EQUIPE INTERNA: ADMIN, GESTÃO, ADV) -->
<section class="panel" style="margin-top:20px">
    <h2>Nova atualização manual</h2>
    <form method="post" style="display:grid;gap:14px;max-width:900px">
        <input type="hidden" name="csrf" value="<?= upd_h($csrf) ?>">
        <input type="hidden" name="action" value="create">

        <label>Processo *
            <select name="case_id" required style="display:block;width:100%;padding:12px;margin-top:5px">
                <option value="">Selecione o processo</option>
                <?php foreach ($cases as $case): ?>
                <option value="<?= (int)$case['id'] ?>">
                    <?= upd_h($case['case_number'] . ' — ' . $case['client_name'] . ' — ' . $case['title']) ?>
                    <?= !empty($case['is_confidential']) ? '[SIGILOSO]' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Título *
            <input name="title" maxlength="255" required style="display:block;width:100%;padding:12px;margin-top:5px" placeholder="Ex: Apresentação de defesa / Agendamento de audiência">
        </label>

        <label>Data da movimentação (opcional)
            <input name="event_date" type="date" style="display:block;padding:12px;margin-top:5px">
        </label>

        <label>Mensagem para o cliente
            <textarea name="client_message" rows="3" maxlength="10000" style="display:block;width:100%;padding:12px;margin-top:5px" placeholder="Escreva uma explicação clara e sem jargões para o cliente..."></textarea>
        </label>

        <label>Nota interna (apenas equipe)
            <textarea name="internal_note" rows="2" maxlength="10000" style="display:block;width:100%;padding:12px;margin-top:5px" placeholder="Observações estratégicas e termos técnicos para uso interno..."></textarea>
        </label>

        <label style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="client_action_required" value="1"> Exige ação do cliente
        </label>

        <div style="display:flex;gap:12px;margin-top:5px">
            <button name="mode" value="draft" type="submit"
                style="padding:12px 18px;border:0;border-radius:8px;background:#6c757d;color:#fff;cursor:pointer">
                Salvar rascunho
            </button>

            <?php if ($canApprove): ?>
                <button name="mode" value="publish" type="submit"
                    style="padding:12px 18px;border:0;border-radius:8px;background:#9a772f;color:#fff;cursor:pointer">
                    Publicar para o cliente
                </button>
            <?php else: ?>
                <button name="mode" value="pending" type="submit"
                    style="padding:12px 18px;border:0;border-radius:8px;background:#183049;color:#fff;cursor:pointer">
                    Enviar para revisão da gestão
                </button>
            <?php endif; ?>
        </div>
    </form>
</section>
<?php endif; ?>

<!-- LISTAGEM E TIMELINE DE ATUALIZAÇÕES -->
<section class="panel" style="margin-top:20px">
    <h2><?= $isStaff ? 'Fila de Revisão e Histórico' : 'Minhas atualizações' ?></h2>

    <?php if ($isStaff): ?>
        <!-- BARRA DE PESQUISA E FILTROS RÁPIDOS PARA A EQUIPE INTERNA -->
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px;align-items:center;justify-content:space-between">
            <form method="GET" action="atualizacoes.php" style="display:flex;gap:10px;flex-grow:1;max-width:500px">
                <input type="text" name="search" placeholder="Pesquisar por número do processo ou título..."
                    value="<?= upd_h($_GET['search'] ?? '') ?>"
                    style="flex-grow:1;padding:10px 14px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px">
                <?php if (!empty($_GET['view'])): ?>
                    <input type="hidden" name="view" value="<?= upd_h($_GET['view']) ?>">
                <?php endif; ?>
                <button type="submit"
                    style="padding:10px 18px;background:#183049;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px">
                    Pesquisar
                </button>
                <?php if (!empty($_GET['search'])): ?>
                    <a href="atualizacoes.php<?= !empty($_GET['view']) ? '?view=' . urlencode($_GET['view']) : '' ?>"
                        style="padding:10px 14px;background:#e2e8f0;color:#334155;text-decoration:none;border-radius:6px;font-size:14px">Limpar</a>
                <?php endif; ?>
            </form>

            <!-- FILTROS POR STATUS DE REVISÃO -->
            <div style="display:flex;gap:6px">
                <?php 
                    $currentView = $_GET['view'] ?? 'all';
                    $searchParam = !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '';
                ?>
                <a href="atualizacoes.php?view=all<?= $searchParam ?>"
                   style="padding:8px 12px;border-radius:6px;font-size:13px;text-decoration:none;<?= $currentView === 'all' ? 'background:#183049;color:#fff;font-weight:bold' : 'background:#e2e8f0;color:#334155' ?>">
                    Todos
                </a>
                <a href="atualizacoes.php?view=pending<?= $searchParam ?>"
                   style="padding:8px 12px;border-radius:6px;font-size:13px;text-decoration:none;<?= $currentView === 'pending' ? 'background:#f0ad4e;color:#fff;font-weight:bold' : 'background:#e2e8f0;color:#334155' ?>">
                    Pendentes de Aprovação
                </a>
                <a href="atualizacoes.php?view=published<?= $searchParam ?>"
                   style="padding:8px 12px;border-radius:6px;font-size:13px;text-decoration:none;<?= $currentView === 'published' ? 'background:#198754;color:#fff;font-weight:bold' : 'background:#e2e8f0;color:#334155' ?>">
                    Visíveis ao Cliente
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- ABAS DE NAVEGAÇÃO POR PROCESSO PARA O CLIENTE -->
        <?php if (!empty($userCases) && is_array($userCases)): ?>
        <div class="case-tabs" style="display:flex;gap:10px;margin-bottom:20px;overflow-x:auto;padding-bottom:5px">
            <a href="atualizacoes.php"
                style="padding:8px 16px;border-radius:6px;background:<?= empty($_GET['case_id']) ? '#183049' : '#e2e8f0' ?>;color:<?= empty($_GET['case_id']) ? '#fff' : '#334155' ?>;text-decoration:none;font-weight:bold;font-size:13px">
                Todos os Processos
            </a>

            <?php foreach ($userCases as $c): 
                $isActive = (isset($_GET['case_id']) && (int)$_GET['case_id'] === (int)$c['id']);
            ?>
            <a href="atualizacoes.php?case_id=<?= (int)$c['id'] ?>"
                style="padding:8px 16px;border-radius:6px;background:<?= $isActive ? '#183049' : '#e2e8f0' ?>;color:<?= $isActive ? '#fff' : '#334155' ?>;text-decoration:none;font-size:13px;white-space:nowrap">
                #<?= upd_h($c['case_number']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (empty($updates)): ?>
        <p style="color:#64748b;padding:15px 0">Nenhuma atualização disponível no momento.</p>
    <?php else: ?>
        <div style="display:grid;gap:14px;margin-top:18px">
            <?php foreach ($updates as $item): ?>
            <article style="padding:18px;border:1px solid <?= ($item['status'] ?? '') === 'pending_review' ? '#f0ad4e' : '#e4dfd6' ?>;border-radius:12px;background:<?= ($item['status'] ?? '') === 'pending_review' ? '#fffdf8' : '#fff' ?>">

                <?php if ($isStaff && !empty($item['is_confidential'])): ?>
                    <span style="background:#d9534f;color:#fff;padding:2px 8px;border-radius:4px;font-size:12px;float:right">PROCESSO SIGILOSO</span>
                <?php endif; ?>

                <?php if (!$isStaff && !empty($item['client_action_required'])): ?>
                    <span style="background:#d97706;color:#fff;padding:2px 8px;border-radius:4px;font-size:12px;float:right;font-weight:bold">AÇÃO NECESSÁRIA</span>
                <?php endif; ?>

                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                    <div>
                        <strong><?= upd_h($item['title']) ?></strong>
                        <p style="margin:5px 0">
                            <?= upd_h($item['case_number'] . ' — ' . $item['case_title']) ?><?= $isStaff ? ' · ' . upd_h($item['client_name']) : '' ?>
                        </p>
                    </div>
                    <small><?= upd_h(upd_date($item['event_date'] ?: ($item['published_at'] ?? $item['created_at'] ?? null))) ?></small>
                </div>

                <?php if ($isStaff): ?>
                <p style="font-size:13px;color:#555;margin:8px 0 12px 0">
                    <strong>Situação:</strong> <?= upd_h($item['status']) ?> ·
                    <strong>Origem:</strong> <?= upd_h($item['source'] ?? $item['update_type'] ?? 'Manual') ?> ·
                    <strong>Visível ao cliente:</strong> <?= !empty($item['visible_to_client']) ? 'Sim' : 'Não' ?>
                    <?php if (!empty($item['approver_name'])): ?> · <strong>Aprovado por:</strong> <?= upd_h($item['approver_name']) ?> em <?= upd_date($item['published_at']) ?><?php endif; ?>
                </p>
                <?php endif; ?>

                <!-- MODAL / FORMULÁRIO DE REVISÃO E EDIÇÃO PARA A EQUIPE INTERNA -->
                <?php if ($isStaff && in_array($item['status'], ['pending_review', 'draft'], true)): ?>
                <form method="post" style="display:grid;gap:10px;margin-top:12px;padding:12px;background:#f9f9f9;border-radius:8px">
                    <input type="hidden" name="csrf" value="<?= upd_h($csrf) ?>">
                    <input type="hidden" name="action" value="update_pending">
                    <input type="hidden" name="update_id" value="<?= (int)$item['id'] ?>">

                    <label style="font-size:13px"><strong>Título para o Cliente:</strong>
                        <input name="title" value="<?= upd_h($item['title']) ?>" required style="display:block;width:100%;padding:8px;margin-top:4px">
                    </label>
                    <label style="font-size:13px"><strong>Redigir Mensagem ao Cliente:</strong>
                        <textarea name="client_message" rows="3" style="display:block;width:100%;padding:8px;margin-top:4px" placeholder="Traduza o andamento jurídico em linguagem clara para o cliente..."><?= upd_h($item['client_message'] ?? '') ?></textarea>
                    </label>
                    <label style="font-size:13px"><strong>Nota Interna (Original/Equipe):</strong>
                        <textarea name="internal_note" rows="2" style="display:block;width:100%;padding:8px;margin-top:4px"><?= upd_h($item['internal_note'] ?? '') ?></textarea>
                    </label>
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px">
                        <input type="checkbox" name="client_action_required" value="1" <?= !empty($item['client_action_required']) ? 'checked' : '' ?>> Exige ação do cliente
                    </label>

                    <div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap">
                        <button name="mode" value="draft" type="submit" style="padding:8px 12px;border:0;border-radius:6px;background:#6c757d;color:#fff;cursor:pointer">Salvar Rascunho</button>
                        
                        <?php if ($canApprove): ?>
                            <button name="mode" value="publish" type="submit" style="padding:8px 12px;border:0;border-radius:6px;background:#198754;color:#fff;cursor:pointer">Aprovar e Publicar</button>
                            <button type="submit" form="reject-form-<?= (int)$item['id'] ?>" style="padding:8px 12px;border:0;border-radius:6px;background:#dc3545;color:#fff;cursor:pointer">Rejeitar</button>
                        <?php else: ?>
                            <button name="mode" value="pending" type="submit" style="padding:8px 12px;border:0;border-radius:6px;background:#183049;color:#fff;cursor:pointer">Enviar p/ Revisão</button>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($canApprove): ?>
                <form id="reject-form-<?= (int)$item['id'] ?>" method="post" style="display:none">
                    <input type="hidden" name="csrf" value="<?= upd_h($csrf) ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="update_id" value="<?= (int)$item['id'] ?>">
                </form>
                <?php endif; ?>

                <?php else: ?>

                <!-- EXIBIÇÃO DO CONTEÚDO PARA CLIENTE OU REGISTRO JÁ PUBLICADO -->
                <?php if (trim((string)($item['client_message'] ?? '')) !== ''): ?>
                    <p style="white-space:pre-wrap;margin-top:10px;line-height:1.5;color:#334155"><?= upd_h($item['client_message']) ?></p>
                <?php endif; ?>

                <?php if ($isStaff && !empty($item['internal_note'])): ?>
                    <div style="margin-top:10px;padding:10px;background:#f1f5f9;border-left:4px solid #183049;border-radius:4px;font-size:13px">
                        <strong>Nota Interna:</strong> <?= upd_h($item['internal_note']) ?>
                    </div>
                <?php endif; ?>

                <?php if ($canApprove && ($item['status'] ?? '') === 'published'): ?>
                    <form method="post" style="display:inline-block;margin-top:12px">
                        <input type="hidden" name="csrf" value="<?= upd_h($csrf) ?>">
                        <input type="hidden" name="action" value="hide">
                        <input type="hidden" name="update_id" value="<?= (int)$item['id'] ?>">
                        <button type="submit" style="padding:6px 12px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;cursor:pointer;font-size:12px;color:#475569">Retirar Publicação</button>
                    </form>
                <?php endif; ?>

                <?php endif; ?>

            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php portal_footer(); ?>