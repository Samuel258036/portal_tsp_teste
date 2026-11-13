<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/access_control.php';
//require_once __DIR__ . '/../api/audit.php';

require_login();

$user    = current_user();
$userId  = (int)($user['id'] ?? 0);
$isStaff = is_staff();

$docId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$docId) {
    http_response_code(400);
    exit('Requisição inválida.');
}

try {
    // Busca o documento e valida o estado do processo
    $stmt = $pdo->prepare("
        SELECT d.*, c.client_id, c.is_confidential AS case_confidential 
        FROM case_documents d 
        INNER JOIN cases c ON c.id = d.case_id 
        WHERE d.id = ? AND c.active = 1 
        LIMIT 1
    ");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        http_response_code(404);
        exit('Documento não encontrado.');
    }

    // Validação estrita de autorização (RBAC + Isolamento por Cliente)
    $authorized = false;
    if ($isStaff) {
        $authorized = true;
    } else {
        $queryData = get_authorized_cases_query($pdo, $userId);
        
        $chk = $pdo->prepare("
            SELECT 1 FROM cases c 
            WHERE c.id = :case_id 
              AND {$queryData['where']} 
              AND c.active = 1 
            LIMIT 1
        ");
        $params = array_merge([':case_id' => $doc['case_id']], $queryData['params']);
        $chk->execute($params);

        if ($chk->fetchColumn() && (int)$doc['visible_to_client'] === 1 && (int)$doc['is_confidential'] === 0 && (int)$doc['case_confidential'] === 0) {
            $authorized = true;
        }
    }

    if (!$authorized) {
        //log_action('document_download_denied', 'case_documents', $docId, "Tentativa de acesso não autorizada pelo utilizador #{$userId}");
        http_response_code(403);
        exit('Acesso negado. Você não possui permissão para visualizar este documento.');
    }

    $filePath = $doc['file_path'];
    if (!file_exists($filePath)) {
        http_response_code(404);
        exit('Arquivo físico não localizado no servidor.');
    }

    // Registra o acesso bem-sucedido na auditoria
    //log_action('document_download_success', 'case_documents', $docId, "Download do ficheiro: {$doc['original_name']}");

    // Limpa buffers e envia os cabeçalhos de transferência segura
    if (ob_get_level()) ob_end_clean();

    header('Content-Description: File Transfer');
    header('Content-Type: ' . ($doc['mime_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . rawurlencode((string)$doc['original_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

    readfile($filePath);
    exit;

} catch (Throwable $e) {
    error_log('Erro ao baixar documento: ' . $e->getMessage());
    http_response_code(500);
    exit('Erro interno no processamento do download.');
}