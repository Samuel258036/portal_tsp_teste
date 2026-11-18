<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/integracoes-controller.php';
require_once __DIR__ . '/portal-layout.php';

require_role(['admin', 'gestao']);

portal_header('Integrações', 'integracoes');
?>

<!-- CABEÇALHO DO MÓDULO -->
<section class="panel">
    <span>PORTAL TSP</span>
    <h2>Central de Integrações</h2>
    <p style="color:#64748b;margin-top:4px">Conecte sistemas externos ao portal com segurança e gerencie a sincronização de dados.</p>
</section>

<!-- MENSAGEM GLOBAL DE RETORNO DA API -->
<?php if (isset($result) && is_array($result) && !empty($result['message'])): ?>
    <div role="status" style="margin-top:20px;padding:14px 18px;border-radius:8px;background:<?= !empty($result['ok']) ? '#d1e7dd' : '#f8d7da' ?>;color:<?= !empty($result['ok']) ? '#0f5132' : '#842029' ?>;border:1px solid <?= !empty($result['ok']) ? '#badbcc' : '#f5c2c7' ?>;font-weight:600;display:flex;align-items:center;gap:10px">
        <span><?= !empty($result['ok']) ? '' : '' ?></span>
        <div><?= juridiq_escape((string)$result['message']) ?></div>
    </div>
<?php endif; ?>

<!-- 1. AÇÕES RÁPIDAS E DIAGNÓSTICO JURIDIQ -->
<section class="panel" style="margin-top:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
        <div>
            <h3 style="margin:0;font-size:18px;color:#0f172a">API Juridiq — Ações Rápidas</h3>
            <p style="margin-top:4px;font-size:13px;color:#64748b">Teste a conectividade ou consulte os registros disponíveis no servidor remoto.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= juridiq_escape($csrf) ?>">
                <button type="submit" name="action" value="test_juridiq" style="padding:10px 16px;border:0;border-radius:6px;background:#183049;color:#fff;font-weight:600;cursor:pointer;font-size:13px;transition:opacity 0.2s">
                     Testar Conexão
                </button>
                <button type="submit" name="action" value="preview_juridiq" style="padding:10px 16px;border:0;border-radius:6px;background:#9a772f;color:#fff;font-weight:600;cursor:pointer;font-size:13px">
                     Consultar 10 Clientes
                </button>
            </form>

            <form method="POST" style="display:inline">
                <input type="hidden" name="csrf" value="<?= juridiq_escape($csrf); ?>">
                <input type="hidden" name="action" value="sync_lawsuits">
                <input type="hidden" name="client_id" value="6">
                <button type="submit" style="padding:10px 16px;border:1px solid #183049;border-radius:6px;background:#f8fafc;color:#183049;font-weight:600;cursor:pointer;font-size:13px">
                     Sincronizar Processos
                </button>
            </form>
        </div>
    </div>
</section>

<!-- 2. GRID DE CONSULTAS INDIVIDUAIS (PROCESSO E CLIENTE) -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:20px;margin-top:20px">
    
    <!-- CARD: CONSULTAR PROCESSO POR CNJ -->
    <section class="panel" style="margin:0">
        <h3 style="font-size:16px;color:#0f172a;margin-bottom:6px">Importar Processo por CNJ</h3>
        <p style="font-size:13px;color:#64748b;margin-bottom:14px">Busque um processo específico na API para pré-visualizar antes de importar.</p>
        
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= juridiq_escape($csrf); ?>">
            <input type="hidden" name="action" value="preview_lawsuit">

            <div style="display:flex;gap:8px">
                <input type="text" name="juridiq_lawsuit_by_cnj" placeholder="Ex: 0007092-25.2025.8.26.0496" value="<?= juridiq_escape($_POST['juridiq_lawsuit_by_cnj'] ?? ''); ?>" required style="flex:1;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">
                <button type="submit" style="padding:10px 16px;background:#183049;color:#fff;border:0;border-radius:6px;font-weight:600;cursor:pointer;font-size:13px">
                    Buscar
                </button>
            </div>
        </form>
    </section>

    <!-- CARD: CONSULTAR CLIENTE POR ID JURIDIQ -->
    <section class="panel" style="margin:0">
        <h3 style="font-size:16px;color:#0f172a;margin-bottom:6px">Importar Cliente por UUID</h3>
        <p style="font-size:13px;color:#64748b;margin-bottom:14px">Informe o ID único (UUID) da pessoa cadastrada no Juridiq.</p>
        
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= juridiq_escape($csrf); ?>">
            <input type="hidden" name="action" value="preview_person">

            <div style="display:flex;gap:8px">
                <input type="text" name="juridiq_person_id" placeholder="Ex: 9611937a-be66-4307-af6b-388c63e80dca" value="<?= juridiq_escape($_POST['juridiq_person_id'] ?? ''); ?>" required style="flex:1;padding:10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">
                <button type="submit" style="padding:10px 16px;background:#183049;color:#fff;border:0;border-radius:6px;font-weight:600;cursor:pointer;font-size:13px">
                    Buscar
                </button>
            </div>
        </form>
    </section>
</div>

<!-- 3. PRÉ-VISUALIZAÇÃO DE PROCESSO (SE CONSULTADO) -->
<?php if (isset($result['type']) && $result['type'] === 'lawsuit_preview' && !empty($result['data'])): ?>
    <?php 
        $lawsuit  = $result['data'];
        $client   = $result['matched_client'] ?? null;
        $cnj      = $lawsuit['processNumber'] ?? $lawsuit['code'] ?? 'Não informado';
        $court    = $lawsuit['instances'][0]['court'] ?? 'Não informado';
        $instance = $lawsuit['currentInstance'] ?? 1;
        $status   = $lawsuit['status'] ?? 'active';
    ?>
    <section class="panel" style="margin-top:20px;border-left:4px solid #183049">
        <h3 style="margin-bottom:14px;color:#183049;font-size:18px">Pré-visualização do Processo</h3>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;background:#f8fafc;padding:16px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:16px">
            <div>
                <small style="color:#64748b;font-weight:bold;display:block">NÚMERO CNJ</small>
                <strong style="color:#0f172a;font-size:15px"><?= juridiq_escape($cnj); ?></strong>
            </div>
            <div>
                <small style="color:#64748b;font-weight:bold;display:block">TRIBUNAL</small>
                <span style="color:#334155"><?= juridiq_escape($court); ?></span>
            </div>
            <div>
                <small style="color:#64748b;font-weight:bold;display:block">INSTÂNCIA</small>
                <span style="color:#334155"><?= juridiq_escape((string)$instance); ?>ª Instância</span>
            </div>
            <div>
                <small style="color:#64748b;font-weight:bold;display:block">SITUAÇÃO</small>
                <span style="display:inline-block;padding:2px 8px;border-radius:4px;background:#e2e8f0;color:#334155;font-weight:bold;font-size:12px">
                    <?= juridiq_escape($status); ?>
                </span>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf" value="<?= juridiq_escape($csrf); ?>">
            <input type="hidden" name="action" value="confirm_import_lawsuit">
            <input type="hidden" name="juridiq_lawsuit_id" value="<?= juridiq_escape($lawsuit['id']); ?>">

            <?php if ($client): ?>
                <input type="hidden" name="client_id" value="<?= (int)$client['id']; ?>">
                <div style="padding:12px 16px;background:#d1e7dd;color:#0f5132;border-radius:6px;margin-bottom:16px;font-size:14px">
                    <strong>✓ Cliente Vinculado Encontrado:</strong> <?= juridiq_escape($client['name']); ?> (ID Local: #<?= (int)$client['id']; ?>)
                </div>
            <?php else: ?>
                <div style="padding:12px 16px;background:#fff3cd;color:#664d03;border-radius:6px;margin-bottom:16px;font-size:14px">
                    <strong>Atenção:</strong> Nenhum cliente local corresponde automaticamente a este processo. Selecione um cliente existente abaixo:
                </div>

                <?php if (!empty($result['unmatched_persons'])): ?>
                    <div style="margin-bottom:16px;padding:12px;background:#f1f5f9;border-radius:6px">
                        <strong style="font-size:13px;color:#334155;display:block;margin-bottom:8px">Pessoas relacionadas no Juridiq:</strong>
                        <ul style="margin:0;padding-left:20px;font-size:13px;color:#475569">
                            <?php foreach ($result['unmatched_persons'] as $unmatched): ?>
                                <li>
                                    <strong><?= juridiq_escape($unmatched['name']); ?></strong> (<?= juridiq_escape($unmatched['origin']); ?>) — <code><?= juridiq_escape($unmatched['id']); ?></code>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div style="margin-bottom:16px">
                    <label style="display:block;font-weight:bold;font-size:13px;margin-bottom:6px">Vincular a um Cliente do Portal: *</label>
                    <select name="client_id" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px">
                        <option value="">-- Selecione o cliente --</option>
                        <?php
                            $stmtAll = $pdo->query("SELECT id, name FROM clients WHERE active = 1 ORDER BY name ASC");
                            while ($c = $stmtAll->fetch()) {
                                echo '<option value="' . (int)$c['id'] . '">' . juridiq_escape($c['name']) . '</option>';
                            }
                        ?>
                    </select>
                </div>
            <?php endif; ?>

            <button type="submit" style="padding:12px 20px;background:#183049;color:#fff;border:0;border-radius:6px;font-weight:600;cursor:pointer;width:100%">
                Confirmar e Importar Processo
            </button>
        </form>
    </section>
<?php endif; ?>

<!-- 4. PRÉ-VISUALIZAÇÃO DE CLIENTE (SE CONSULTADO) -->
<?php if (isset($result['type']) && $result['type'] === 'person_preview' && !empty($result['data'])): ?>
    <?php 
        $person   = $result['data'];
        $existing = $result['existing_local'] ?? null;
    ?>
    <section class="panel" style="margin-top:20px;border-left:4px solid #9a772f">
        <h3 style="margin-bottom:14px;color:#9a772f;font-size:18px">👤 Cliente Encontrado no Juridiq</h3>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;background:#f8fafc;padding:16px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:16px">
            <div>
                <small style="color:#64748b;font-weight:bold;display:block">NOME</small>
                <strong style="color:#0f172a;font-size:15px"><?= juridiq_escape($person['name'] ?? 'Não informado'); ?></strong>
            </div>
            <div>
                <small style="color:#64748b;font-weight:bold;display:block">DOCUMENTO (CPF/CNPJ)</small>
                <span style="color:#334155"><?= juridiq_escape($person['document'] ?? 'Não informado'); ?></span>
            </div>
            <div>
                <small style="color:#64748b;font-weight:bold;display:block">E-MAIL</small>
                <span style="color:#334155"><?= juridiq_escape($person['email'] ?? 'Não informado'); ?></span>
            </div>
            <div>
                <small style="color:#64748b;font-weight:bold;display:block">TELEFONE</small>
                <span style="color:#334155"><?= juridiq_escape($person['phone'] ?? 'Não informado'); ?></span>
            </div>
        </div>

        <?php if ($existing): ?>
            <div style="padding:12px 16px;background:#fff3cd;color:#664d03;border-radius:6px;margin-bottom:16px;font-size:14px">
                <strong>Atenção:</strong> Esta pessoa já está associada ao cliente local <strong>#<?= (int)$existing['id']; ?> - <?= juridiq_escape($existing['name']); ?></strong>. Ao confirmar, os dados serão atualizados.
            </div>
        <?php else: ?>
            <div style="padding:12px 16px;background:#e0e7ff;color:#3730a3;border-radius:6px;margin-bottom:16px;font-size:14px">
                Este registro será cadastrado como um <strong>novo cliente</strong> na base de dados do Portal TSP.
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf" value="<?= juridiq_escape($csrf); ?>">
            <input type="hidden" name="action" value="confirm_import_person">
            <input type="hidden" name="juridiq_person_id" value="<?= juridiq_escape($person['id'] ?? ''); ?>">

            <button type="submit" style="padding:12px 20px;background:#9a772f;color:#fff;border:0;border-radius:6px;font-weight:600;cursor:pointer">
                Confirmar Importação do Cliente
            </button>
        </form>
    </section>
<?php endif; ?>

<!-- 5. TABELA DE CONSULTA DE CLIENTES DISPONÍVEIS (SE SOLICITADO VIA CONSULTAR 10 CLIENTES) -->
<?php if (!empty($preview) && is_array($preview)): ?>
    <section class="panel" style="margin-top:20px">
        <h3 style="margin-bottom:6px;font-size:18px;color:#0f172a">Clientes Disponíveis para Importação Rápida</h3>
        <p style="font-size:13px;color:#64748b;margin-bottom:16px">Selecione o tipo de pessoa (PF ou PJ) para importar o cliente selecionado diretamente do Juridiq.</p>

        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;text-align:left;font-size:14px">
                <thead>
                    <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0">
                        <th style="padding:12px">ID Juridiq</th>
                        <th style="padding:12px">Nome</th>
                        <th style="padding:12px">E-mail</th>
                        <th style="padding:12px">Telefone</th>
                        <th style="padding:12px;text-align:right">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview as $p): ?>
                        <tr style="border-top:1px solid #e2e8f0">
                            <td style="padding:12px"><code><?= juridiq_escape($p['id']) ?></code></td>
                            <td style="padding:12px"><strong><?= juridiq_escape($p['name']) ?></strong></td>
                            <td style="padding:12px"><?= juridiq_escape($p['email']) ?></td>
                            <td style="padding:12px"><?= juridiq_escape($p['phone']) ?></td>
                            <td style="padding:12px;text-align:right">
                                <form method="post" style="display:inline-flex;gap:8px;align-items:center" onsubmit="return confirm('Importar este cliente para o Portal TSP?');">
                                    <input type="hidden" name="csrf" value="<?= juridiq_escape($csrf) ?>">
                                    <input type="hidden" name="juridiq_id" value="<?= juridiq_escape($p['id']) ?>">
                                    
                                    <select name="client_type" required style="padding:7px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">
                                        <option value="">Tipo...</option>
                                        <option value="PF">Pessoa Física (PF)</option>
                                        <option value="PJ">Pessoa Jurídica (PJ)</option>
                                    </select>
                                    
                                    <button type="submit" name="action" value="import_juridiq" style="padding:7px 14px;border:0;border-radius:6px;background:#183049;color:#fff;font-weight:600;cursor:pointer;font-size:13px">
                                        Importar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<p style="margin-top:20px;font-size:13px;color:#94a3b8;text-align:center">
    As chaves e tokens de comunicação permanecem protegidos no servidor. Documentos e senhas sigilosas não são importados automaticamente.
</p>

<?php portal_footer(); ?>