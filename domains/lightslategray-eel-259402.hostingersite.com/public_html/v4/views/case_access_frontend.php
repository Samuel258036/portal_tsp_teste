<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/case_access_backend.php';
require_login();
require_once __DIR__ . '/portal-layout.php';

// Apenas Administradores e Gestão acedem a estas configurações
require_role(['admin', 'gestao']);

// ... Renderize a tabela com $meusProcessos ...
portal_header('Acessos a Processos', 'case_access'); ?><section class="panel"><span>PORTAL TSP</span>
    <h2>Gestão de Acessos e Representantes Autorizados</h2>
    <p>Conceda permissão de visualização a parentes ou representantes legais sem alterar o titular principal do
        processo.</p>
</section>

<?php if (!empty($resultMsg)): ?>
    <section class="panel" style="margin-top:18px">
    <p role="status"
        style="padding:12px;border-radius:8px;background:<?= $resultMsg['ok'] ? '#e8f5ec' : '#fff0ed' ?>;color:#192b36">
        <?= access_h($resultMsg['message']) ?>
    </p>
</section>
<?php endif; ?>

<!-- Formulário de Concessão -->
<section class="panel" style="margin-top:20px">
    <h2>Conceder Nova Autorização</h2>
    <form method="post"
        style="display:grid;grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));gap:16px;margin-top:14px">
        <input type="hidden" name="csrf" value="<?= access_h($csrf) ?>">
        <input type="hidden" name="action" value="grant_access">

        <div>
            <label style="display:block;margin-bottom:6px;font-weight:bold;font-size:13px">Processo Alvo:</label>
            <select name="case_id" required
                style="width:100%;padding:10px;border-radius:6px;border:1px solid #ccc;font-size:13px">
                <option value="">Selecione o processo...</option>
                <?php foreach ($cases as $c): ?>
                <option value="<?= (int)$c['id'] ?>">
                    #<?= (int)$c['id'] ?> — <?= access_h($c['case_number']) ?> (Titular:
                    <?= access_h($c['client_name']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="display:block;margin-bottom:6px;font-weight:bold;font-size:13px">Utilizador Autorizado
                (Terceiro):</label>
            <select name="user_id" required
                style="width:100%;padding:10px;border-radius:6px;border:1px solid #ccc;font-size:13px">
                <option value="">Selecione o utilizador...</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>">
                    <?= access_h($u['name']) ?> (<?= access_h($u['email']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="display:block;margin-bottom:6px;font-weight:bold;font-size:13px">Fundamento da
                Autorização:</label>
            <input type="text" name="authorization_reason" placeholder="Ex: Procuração / Filho do titular" required
                style="width:100%;padding:10px;border-radius:6px;border:1px solid #ccc;font-size:13px">
        </div>

        <div style="grid-column: 1 / -1">
            <button type="submit"
                style="padding:10px 20px;border:0;border-radius:6px;background:#183049;color:#fff;cursor:pointer;font-weight:bold">
                Conceder Permissão
            </button>
        </div>
    </form>
</section>

<!-- Histórico e Gestão -->
<section class="panel" style="margin-top:20px">
    <h2>Histórico de Acessos Concedidos</h2>
    <div style="overflow-x:auto;margin-top:14px">
        <table style="width:100%;border-collapse:collapse;text-align:left;font-size:13px">
            <thead>
                <tr style="border-bottom:2px solid #ddd;background:#f8f9fa">
                    <th style="padding:10px">Processo</th>
                    <th style="padding:10px">Utilizador Autorizado</th>
                    <th style="padding:10px">Fundamento</th>
                    <th style="padding:10px">Data Concessão</th>
                    <th style="padding:10px">Status</th>
                    <th style="padding:10px">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permissionsHistory as $p): ?>
                <tr style="border-bottom:1px solid #eee">
                    <td style="padding:10px"><strong><?= access_h($p['case_number']) ?></strong></td>
                    <td style="padding:10px"><?= access_h($p['target_user_name']) ?><br><small
                            style="color:#666"><?= access_h($p['target_user_email']) ?></small></td>
                    <td style="padding:10px"><?= access_h($p['authorization_reason']) ?></td>
                    <td style="padding:10px"><?= date('d/m/Y H:i', strtotime($p['granted_at'])) ?><br><small
                            style="color:#666">Por: <?= access_h($p['grantor_name']) ?></small></td>
                    <td style="padding:10px">
                        <span
                            style="padding:4px 8px;border-radius:4px;color:#fff;font-size:11px;font-weight:bold;background:<?= $p['status'] === 'active' ? '#198754' : '#dc3545' ?>">
                            <?= access_h(strtoupper($p['status'])) ?>
                        </span>
                    </td>
                    <td style="padding:10px">
                        <?php if ($p['status'] === 'active'): ?>
                        <form method="post" style="margin:0"
                            onsubmit="return confirm('Tem a certeza que deseja revogar o acesso imediatamente?');">
                            <input type="hidden" name="csrf" value="<?= access_h($csrf) ?>">
                            <input type="hidden" name="action" value="revoke_access">
                            <input type="hidden" name="permission_id" value="<?= (int)$p['id'] ?>">
                            <button type="submit"
                                style="padding:6px 12px;border:1px solid #dc3545;border-radius:4px;background:#fff;color:#dc3545;cursor:pointer;font-size:12px;font-weight:bold">
                                Revogar
                            </button>
                        </form>
                        <?php else: ?>
                        <small style="color:#888">Revogado a <?= date('d/m/Y', strtotime($p['revoked_at'])) ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section><?php portal_footer(); ?>