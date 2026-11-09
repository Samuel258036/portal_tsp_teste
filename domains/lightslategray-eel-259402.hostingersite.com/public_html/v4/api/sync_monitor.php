<?php

declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_admin();
require_once __DIR__ . '/cron_sync.php';

function sync_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (empty($_SESSION['sync_csrf'])) $_SESSION['sync_csrf'] = bin2hex(random_bytes(32));
$csrf = (string)$_SESSION['sync_csrf'];

$resultMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $resultMsg = ['ok' => false, 'message' => 'Sessão expirada. Atualize a página.'];
    } else {
        $action = (string)($_POST['action'] ?? '');
        $caseId = filter_var($_POST['case_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

        if ($action === 'sync_manual') {
            $res = execute_juridiq_sync($pdo, $caseId);
            $resultMsg = ['ok' => $res['ok'], 'message' => $res['message']];
        }
    }
}

$integrations = [];
$syncLogs = [];
$stats = ['total' => 0, 'success' => 0, 'error' => 0];

try {
    $integrations = $pdo->query("
        SELECT ci.case_id, ci.external_case_id, ci.sync_status, ci.last_sync_at, ci.last_success_at, ci.last_error,
               c.case_number, c.title AS case_title, cl.name AS client_name
        FROM case_integrations ci
        INNER JOIN cases c ON c.id = ci.case_id
        INNER JOIN clients cl ON cl.id = c.client_id
        WHERE c.active = 1
        ORDER BY FIELD(ci.sync_status, 'error', 'pending', 'success'), ci.last_sync_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($integrations as $item) {
        $stats['total']++;
        if ($item['sync_status'] === 'error') $stats['error']++;
        if ($item['sync_status'] === 'success') $stats['success']++;
    }

    // Leitura adaptada para a estrutura real da tabela sync_logs
    $syncLogs = $pdo->query("
        SELECT l.id, l.case_id, l.started_at, l.finished_at, l.status, l.items_found, l.message, l.technical_details, c.case_number
        FROM sync_logs l
        LEFT JOIN cases c ON c.id = l.case_id
        ORDER BY l.started_at DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Sync monitor read error: ' . $e->getMessage());
}