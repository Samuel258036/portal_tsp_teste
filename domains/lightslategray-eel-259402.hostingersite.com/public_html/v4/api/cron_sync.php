<?php

declare(strict_types=1);

$cronToken = getenv('CRON_SECRET_TOKEN') ?: 'TSP_SECURE_CRON_TOKEN_2026';
$isCli = (php_sapi_name() === 'cli');

// 1. Carregamento condicional de dependências (evita bloqueio por sessão em modo CLI)
if ($isCli) {
    require_once __DIR__ . '/../../../config/database.php';
    require_once __DIR__ . '/functions_juridiq.php';
} else {
    // $providedToken = $_GET['token'] ?? '';
    // if (!hash_equals($cronToken, (string)$providedToken)) {
    //     http_response_code(403);
    //     exit('Acesso negado: Token de cron inválido.');
    // }
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/functions_juridiq.php';
}

/**
 * Executa a rotina de sincronização de processos e andamentos.
 */
function execute_juridiq_sync(PDO $pdo, ?int $targetCaseId = null): array
{
    // Trava para evitar execuções concorrentes em paralelo
    $lockFile = sys_get_temp_dir() . '/tsp_juridiq_sync.lock';
    $lockHandle = @fopen($lockFile, 'c+');

    if ($lockHandle && !flock($lockHandle, LOCK_EX | LOCK_NB)) {
        return ['ok' => false, 'message' => 'Uma sincronização já está em andamento. Aguarde o término.'];
    }

    $apiKey = juridiq_key();
    if (trim($apiKey) === '') {
        if ($lockHandle) flock($lockHandle, LOCK_UN);
        return ['ok' => false, 'message' => 'Chave de API Juridiq não configurada no .env.'];
    }

    // Registra início do log na tabela sync_logs
    $stmtInitLog = $pdo->prepare("
        INSERT INTO sync_logs (integration_id, case_id, started_at, status, items_found) 
        VALUES (1, ?, NOW(), 'running', 0)
    ");
    $stmtInitLog->execute([$targetCaseId]);
    $logId = (int)$pdo->lastInsertId();

    $sql = "SELECT ci.case_id, ci.external_case_id, ci.integration_id 
            FROM case_integrations ci 
            INNER JOIN cases c ON c.id = ci.case_id 
            WHERE c.active = 1 AND ci.monitoring_enabled = 1";
    
    $params = [];
    if ($targetCaseId !== null) {
        $sql .= " AND ci.case_id = ?";
        $params[] = $targetCaseId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $integrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalCases = count($integrations);
    $syncedCases = 0;
    $totalMovements = 0;
    $errors = [];

    foreach ($integrations as $item) {
        $caseId = (int)$item['case_id'];
        $externalId = trim((string)$item['external_case_id']);

        if ($externalId === '') continue;

        try {
            $movements = [];
            $attempts = 0;
            $maxAttempts = 3;

            while ($attempts < $maxAttempts) {
                $attempts++;
                try {
                    $urlMov = 'https://api.juridiq.com.br/lawSuit/movements/' . urlencode($externalId) . '?limit=50';
                    $movData = juridiq_request($apiKey, $urlMov, 30);
                    $movements = $movData['data'] ?? $movData ?? [];
                    break;
                } catch (Throwable $eReq) {
                    if ($attempts >= $maxAttempts) throw $eReq;
                    usleep(500000); // Aguarda 500ms entre tentativas
                }
            }

            $movCount = sync_juridiq_movements($pdo, $caseId, $movements);

            $stmtUpd = $pdo->prepare("
                UPDATE case_integrations 
                SET last_sync_at = NOW(), last_success_at = NOW(), sync_status = 'success', last_error = NULL 
                WHERE case_id = ?
            ");
            $stmtUpd->execute([$caseId]);

            $syncedCases++;
            $totalMovements += $movCount;

            usleep(200000); // 200ms rate limit

        } catch (Throwable $e) {
            $errorMsg = $e->getMessage();
            $errors[] = "Processo #{$caseId}: " . $errorMsg;

            $stmtErr = $pdo->prepare("
                UPDATE case_integrations 
                SET last_sync_at = NOW(), sync_status = 'error', last_error = ? 
                WHERE case_id = ?
            ");
            $stmtErr->execute([mb_substr($errorMsg, 0, 500), $caseId]);
        }
    }

    // Define status final para a tabela sync_logs
    if (!empty($errors)) {
        $finalStatus = 'error';
    } elseif ($totalMovements === 0) {
        $finalStatus = 'no_change';
    } else {
        $finalStatus = 'success';
    }

    $summaryMessage = "Sincronização concluída: {$syncedCases}/{$totalCases} processos processados. {$totalMovements} novos andamentos encontrados.";
    $techDetails = !empty($errors) ? implode("\n", $errors) : null;

    // Atualiza o log de execução
    $stmtDoneLog = $pdo->prepare("
        UPDATE sync_logs 
        SET finished_at = NOW(), status = ?, items_found = ?, message = ?, technical_details = ? 
        WHERE id = ?
    ");
    $stmtDoneLog->execute([$finalStatus, $totalMovements, $summaryMessage, $techDetails, $logId]);

    if ($lockHandle) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }

    return [
        'ok' => empty($errors),
        'message' => $summaryMessage,
        'errors' => $errors
    ];
}

// 2. Gatilho de execução automática (descomentado)
if ($isCli || isset($_GET['token'])) {
    $result = execute_juridiq_sync($pdo);
    echo "[" . date('Y-m-d H:i:s') . "] " . $result['message'] . PHP_EOL;
    if (!empty($result['errors'])) {
        echo "Erros:" . PHP_EOL . implode(PHP_EOL, $result['errors']) . PHP_EOL;
    }
}