<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/integracoes-controller.php';
require_once __DIR__ . '/portal-layout.php';
portal_header('Integrações', 'integracoes');


require_role(['admin', 'gestao']);
?>
<section class="panel"><span>PORTAL TSP</span>
    <h2>Integrações</h2>
    <p>Conecte sistemas externos ao portal de forma segura.</p>
</section>
<section class="panel" style="margin-top:20px">
    <h2>Juridiq</h2>
    <p>Consulte pessoas e importe individualmente, após conferir os dados. Nenhuma movimentação ou processo será
        importado nesta etapa.</p>
    <?php if (isset($result) && is_array($result) && !empty($result['message'])): ?>
    <p role="status"
        style="padding:12px;border-radius:8px;background:<?= $result['ok'] ? '#e8f5ec' : '#fff0ed' ?>;color:#192b36">
        <?= juridiq_escape($result['message']) ?></p>
    <?php endif; ?>
    <form method="post" style="display:flex;gap:12px;flex-wrap:wrap">
        <input type="hidden" name="csrf" value="<?= juridiq_escape($csrf) ?>">
        <button type="submit" name="action" value="test_juridiq"
            style="padding:12px 18px;border:0;border-radius:8px;background:#183049;color:#fff;cursor:pointer">Testar
            conexão Juridiq</button>
        <button type="submit" name="action" value="preview_juridiq"
            style="padding:12px 18px;border:0;border-radius:8px;background:#9a772f;color:#fff;cursor:pointer">Consultar
            10 clientes</button>
    </form>
    <br>
    <form method="POST" action="">
        <input type="hidden" name="csrf" value="<?= juridiq_escape($csrf); ?>">
        <input type="hidden" name="action" value="sync_lawsuits">

        <!-- ID do cliente local que possui o juridiq_person_id -->
        <input type="hidden" name="client_id" value="6">

        <button type="submit" class="btn btn-primary">
            Sincronizar Processos do Cliente
        </button>
    </form>
    <div class="card mb-4">
        <div class="card-header">
            <h5>Importar Processo (Juridiq)</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf" value="<?= juridiq_escape($csrf); ?>">
                <input type="hidden" name="action" value="preview_lawsuit">

                <div class="mb-3">
                    <label class="form-label"><strong>Número CNJ:</strong></label>
                    <div class="input-group">
                        <input type="text" name="juridiq_lawsuit_by_cnj" class="form-control"
                            placeholder="Digite o CNJ (ex: 0007092-25.2025.8.26.0496) ou o ID do Juridiq"
                            value="<?= juridiq_escape($_POST['juridiq_lawsuit_by_cnj'] ?? ''); ?>" required>
                        <button class="btn btn-primary" type="submit">Buscar Processo</button>
                    </div>
                </div>
            </form>

            <?php if (isset($result['type']) && $result['type'] === 'lawsuit_preview' && !empty($result['data'])): ?>
            <?php 
        $lawsuit  = $result['data'];
        $client   = $result['matched_client'] ?? null;
        $cnj      = $lawsuit['processNumber'] ?? $lawsuit['code'] ?? 'Não informado';
        $court    = $lawsuit['instances'][0]['court'] ?? 'Não informado';
        $instance = $lawsuit['currentInstance'] ?? 1;
        $status   = $lawsuit['status'] ?? 'active';
    ?>

            <div class="border rounded p-3 bg-light mt-3">
                <h6 class="border-bottom pb-2">Pré-visualização do Processo</h6>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Número CNJ:</strong> <?= juridiq_escape($cnj); ?></p>
                        <p><strong>Tribunal:</strong> <?= juridiq_escape($court); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Instância:</strong> <?= juridiq_escape((string)$instance); ?>ª Instância</p>
                        <p><strong>Situação:</strong> <span class="badge bg-info"><?= juridiq_escape($status); ?></span>
                        </p>
                    </div>
                </div>

                <!-- FORMULÁRIO ÚNICO DE IMPORTAÇÃO -->
                <form method="POST" action="">
                    <input type="hidden" name="csrf" value="<?= juridiq_escape($csrf); ?>">
                    <input type="hidden" name="action" value="confirm_import_lawsuit">
                    <input type="hidden" name="juridiq_lawsuit_id" value="<?= juridiq_escape($lawsuit['id']); ?>">

                    <!-- CENÁRIO 1: CLIENTE ENCONTRADO NO BANCO LOCAL -->
                    <?php if ($client): ?>
                    <input type="hidden" name="client_id" value="<?= (int)$client['id']; ?>">

                    <div class="alert alert-success d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <strong>Cliente Identificado no Portal:</strong> <?= juridiq_escape($client['name']); ?>
                            <span class="badge bg-dark ms-1">ID Local: #<?= (int)$client['id']; ?></span>
                        </div>
                    </div>

                    <!-- CENÁRIO 2: CLIENTE NÃO LOCALIZADO LOCALMENTE -->
                    <?php else: ?>
                    <div class="alert alert-warning mb-3">
                        <strong>Atenção:</strong> Nenhum cliente do portal corresponde automaticamente a este processo.
                    </div>

                    <!-- Se houver pessoas retornadas do Juridiq, permite importar em 1 clique -->
                    <?php if (!empty($result['unmatched_persons'])): ?>
                    <div class="card mb-3 border-warning">
                        <div class="card-header bg-warning text-dark">
                            <strong>Pessoas Encontradas no Juridiq para este Processo:</strong>
                        </div>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($result['unmatched_persons'] as $unmatched): ?>
                            <li
                                class="list-group-item d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <strong><?= juridiq_escape($unmatched['name']); ?></strong>
                                    <span class="badge bg-secondary"><?= juridiq_escape($unmatched['origin']); ?></span>
                                    <br>
                                    <small class="text-muted">ID Juridiq:
                                        <code><?= juridiq_escape($unmatched['id']); ?></code></small>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Seleção manual de cliente local caso não queira importar um novo -->
                    <div class="mb-3">
                        <label class="form-label"><strong>Selecione um Cliente Existente no Portal:</strong></label>
                        <select name="client_id" class="form-select" required>
                            <option value="">-- Escolha um cliente --</option>
                            <?php
                            $stmtAll = $pdo->query("SELECT id, name FROM clients WHERE active = 1 ORDER BY name ASC");
                            while ($c = $stmtAll->fetch()) {
                                echo '<option value="' . (int)$c['id'] . '">' . juridiq_escape($c['name']) . '</option>';
                            }
                        ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- BOTÃO DE SUBMISSÃO: SEMPRE VISÍVEL -->
                    <button type="submit" class="btn btn-success w-100">
                        Confirmar e Importar Processo
                    </button>
                </form>
            </div>
            <?php endif; ?>
            <!-- Seção: Importar / Vincular Cliente -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Importar Cliente (Juridiq)</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($result) && is_array($result) && !empty($result['message'])): ?>
                    <div class="alert alert-<?= !empty($result['ok']) ? 'success' : 'danger'; ?> alert-dismissible fade show mb-4"
                        role="alert">
                        <?= juridiq_escape((string)$result['message']); ?>
                    </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf" value="<?= juridiq_escape($csrf); ?>">
                        <input type="hidden" name="action" value="preview_person">

                        <div class="mb-3">
                            <label class="form-label"><strong>ID da Pessoa (Juridiq UUID):</strong></label>
                            <div class="input-group">
                                <input type="text" name="juridiq_person_id" class="form-control"
                                    placeholder="Cole o ID da pessoa (ex: 9611937a-be66-4307-af6b-388c63e80dca)"
                                    value="<?= juridiq_escape($_POST['juridiq_person_id'] ?? ''); ?>" required>
                                <button class="btn btn-primary" type="submit">Buscar Cliente</button>
                            </div>
                        </div>
                    </form>

                    <?php if (isset($result['type']) && $result['type'] === 'person_preview' && !empty($result['data'])): ?>
                    <?php 
                    $person   = $result['data'];
                    $existing = $result['existing_local'] ?? null;
                ?>
                    <div class="border rounded p-3 bg-light mt-3">
                        <h6 class="border-bottom pb-2">Cliente Encontrado no Juridiq</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>ID Juridiq:</strong> <code><?= juridiq_escape($person['id'] ?? ''); ?></code>
                                </p>
                                <p><strong>Nome:</strong> <?= juridiq_escape($person['name'] ?? 'Não informado'); ?></p>
                                <p><strong>Documento (CPF/CNPJ):</strong>
                                    <?= juridiq_escape($person['document'] ?? 'Não informado'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>E-mail:</strong> <?= juridiq_escape($person['email'] ?? 'Não informado'); ?>
                                </p>
                                <p><strong>Telefone:</strong>
                                    <?= juridiq_escape($person['phone'] ?? 'Não informado'); ?></p>
                            </div>
                        </div>

                        <?php if ($existing): ?>
                        <div class="alert alert-warning mt-2">
                            <strong>Atenção:</strong> Esta pessoa já se encontra associada ao cliente local
                            <strong>#<?= (int)$existing['id']; ?> - <?= juridiq_escape($existing['name']); ?></strong>.
                            Ao confirmar, os dados cadastrais locais serão atualizados.
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info mt-2">
                            Este registo será importado e cadastrado como um novo cliente na base de dados local.
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="mt-3">
                            <input type="hidden" name="csrf" value="<?= juridiq_escape($csrf); ?>">
                            <input type="hidden" name="action" value="confirm_import_person">
                            <input type="hidden" name="juridiq_person_id"
                                value="<?= juridiq_escape($person['id'] ?? ''); ?>">

                            <button type="submit" class="btn btn-success">
                                Confirmar Importação do Cliente
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <p style="margin-top:14px;font-size:14px">A chave permanece no servidor. Senhas e documentos não são
                importados.</p>
            <?php if ($preview): ?>
            <div style="overflow-x:auto;margin-top:20px">
                <table style="width:100%;border-collapse:collapse;text-align:left">
                    <thead>
                        <tr>
                            <th style="padding:10px">ID Juridiq</th>
                            <th style="padding:10px">Nome</th>
                            <th style="padding:10px">E-mail</th>
                            <th style="padding:10px">Telefone</th>
                            <th style="padding:10px">Importação</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($preview as $p): ?><tr style="border-top:1px solid #ddd">
                            <td style="padding:10px"><?= juridiq_escape($p['id']) ?></td>
                            <td style="padding:10px"><?= juridiq_escape($p['name']) ?></td>
                            <td style="padding:10px"><?= juridiq_escape($p['email']) ?></td>
                            <td style="padding:10px"><?= juridiq_escape($p['phone']) ?></td>
                            <td style="padding:10px">
                                <form method="post"
                                    onsubmit="return confirm('Importar este cliente para o Portal TSP? Confira se já não existe cadastro manual.');">
                                    <input type="hidden" name="csrf" value="<?= juridiq_escape($csrf) ?>"><input
                                        type="hidden" name="juridiq_id" value="<?= juridiq_escape($p['id']) ?>">
                                    <select name="client_type" required style="padding:8px">
                                        <option value="">PF ou PJ?</option>
                                        <option value="PF">PF</option>
                                        <option value="PJ">PJ</option>
                                    </select>
                                    <button type="submit" name="action" value="import_juridiq"
                                        style="padding:9px 12px;border:0;border-radius:8px;background:#183049;color:#fff;cursor:pointer">Importar</button>
                                </form>
                            </td>
                        </tr><?php endforeach; ?></tbody>
                </table>
            </div>
            <?php endif; ?>
</section>
<?php portal_footer(); ?>