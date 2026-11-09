<?php
require_once __DIR__ . '/../api/sync_monitor.php';
require_admin();
require_once __DIR__.'/portal-layout.php';
portal_header('Monitoramento de Integrações', 'monitoramento'); ?>

<section class="panel"><span>PORTAL TSP</span>
    <h2>Monitoramento e Sincronização Automática</h2>
    <p>Acompanhe a saúde das integrações com o Juridiq e execute sincronizações manuais com relatório de erros em tempo real.</p>
</section>

<?php if ($resultMsg): ?>
<section class="panel" style="margin-top:18px">
    <p role="status" style="padding:12px;border-radius:8px;background:<?= $resultMsg['ok'] ? '#e8f5ec' : '#fff0ed' ?>;color:#192b36">
        <?= sync_h($resultMsg['message']) ?>
    </p>
</section>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-top:20px">
    <div class="panel" style="text-align:center">
        <h3 style="margin:0;font-size:28px"><?= $stats['total'] ?></h3>
        <small>Processos Monitorados</small>
    </div>
    <div class="panel" style="text-align:center;border-left:4px solid #198754">
        <h3 style="margin:0;font-size:28px;color:#198754"><?= $stats['success'] ?></h3>
        <small>Sincronizados com Sucesso</small>
    </div>
    <div class="panel" style="text-align:center;border-left:4px solid #dc3545">
        <h3 style="margin:0;font-size:28px;color:#dc3545"><?= $stats['error'] ?></h3>
        <small>Com Erro de Integração</small>
    </div>
</div>

<section class="panel" style="margin-top:20px">
    <h2>Sincronização Manual (Admin)</h2>
    <p>Dispare a verificação manual para todos os processos ou para um processo específico.</p>
    <form method="post" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="csrf" value="<?= sync_h($csrf) ?>">
        <input type="hidden" name="action" value="sync_manual">
        
        <select name="case_id" style="padding:10px;border-radius:6px;border:1px solid #ccc;min-width:300px">
            <option value="">Todos os processos monitorados</option>
            <?php foreach ($integrations as $item): ?>
                <option value="<?= (int)$item['case_id'] ?>">
                    #<?= (int)$item['case_id'] ?> — <?= sync_h($item['case_number'] . ' (' . $item['client_name'] . ')') ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" style="padding:10px 18px;border:0;border-radius:8px;background:#183049;color:#fff;cursor:pointer">
            Executar Sincronização Agora
        </button>
    </form>
</section>

<section class="panel" style="margin-top:20px">
    <h2>Status por Processo</h2>
    <div style="overflow-x:auto;margin-top:14px">
        <table style="width:100%;border-collapse:collapse;text-align:left">
            <thead>
                <tr style="border-bottom:2px solid #ddd">
                    <th style="padding:10px">Processo</th>
                    <th style="padding:10px">Cliente</th>
                    <th style="padding:10px">Status Sinc.</th>
                    <th style="padding:10px">Último Sucesso</th>
                    <th style="padding:10px">Último Erro</th>
                    <th style="padding:10px">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($integrations as $item): ?>
                <tr style="border-bottom:1px solid #eee">
                    <td style="padding:10px"><strong><?= sync_h($item['case_number']) ?></strong><br><small><?= sync_h($item['case_title']) ?></small></td>
                    <td style="padding:10px"><?= sync_h($item['client_name']) ?></td>
                    <td style="padding:10px">
                        <span style="padding:4px 8px;border-radius:4px;font-size:12px;background:<?= $item['sync_status'] === 'success' ? '#e8f5ec;color:#146c43' : ($item['sync_status'] === 'error' ? '#fff0ed;color:#b02a37' : '#f8f9fa;color:#6c757d') ?>">
                            <?= sync_h(strtoupper($item['sync_status'])) ?>
                        </span>
                    </td>
                    <td style="padding:10px"><?= sync_h($item['last_success_at'] ?: '—') ?></td>
                    <td style="padding:10px;color:#dc3545;font-size:12px"><?= sync_h($item['last_error'] ?: '—') ?></td>
                    <td style="padding:10px">
                        <form method="post" style="margin:0">
                            <input type="hidden" name="csrf" value="<?= sync_h($csrf) ?>">
                            <input type="hidden" name="action" value="sync_manual">
                            <input type="hidden" name="case_id" value="<?= (int)$item['case_id'] ?>">
                            <button type="submit" style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;cursor:pointer;font-size:12px">Re-sincronizar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel" style="margin-top:20px">
    <h2>Histórico de Execuções (`sync_logs`)</h2>
    <div style="overflow-x:auto;margin-top:14px">
        <table style="width:100%;border-collapse:collapse;text-align:left;font-size:13px">
            <thead>
                <tr style="border-bottom:2px solid #ddd">
                    <th style="padding:8px">Início</th>
                    <th style="padding:8px">Fim</th>
                    <th style="padding:8px">Escopo</th>
                    <th style="padding:8px">Status</th>
                    <th style="padding:8px">Itens Encontrados</th>
                    <th style="padding:8px">Mensagem / Detalhes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($syncLogs as $log): ?>
                <tr style="border-bottom:1px solid #eee">
                    <td style="padding:8px"><?= sync_h($log['started_at']) ?></td>
                    <td style="padding:8px"><?= sync_h($log['finished_at'] ?: 'Executando...') ?></td>
                    <td style="padding:8px"><?= $log['case_id'] ? 'Processo #' . (int)$log['case_id'] . ' (' . sync_h($log['case_number']) . ')' : 'Todos os processos' ?></td>
                    <td style="padding:8px">
                        <span style="font-weight:bold;color:<?= match($log['status']) { 'success' => '#146c43', 'error' => '#dc3545', 'no_change' => '#6c757d', default => '#d9534f' } ?>">
                            <?= sync_h(strtoupper($log['status'])) ?>
                        </span>
                    </td>
                    <td style="padding:8px"><?= (int)$log['items_found'] ?></td>
                    <td style="padding:8px">
                        <?= sync_h($log['message'] ?? '') ?>
                        <?php if (!empty($log['technical_details'])): ?>
                            <br><small style="color:#dc3545"><?= sync_h($log['technical_details']) ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php portal_footer(); ?>