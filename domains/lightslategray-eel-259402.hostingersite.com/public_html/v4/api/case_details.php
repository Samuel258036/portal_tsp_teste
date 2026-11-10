// 1. Inclua o helper de acesso no topo do ficheiro
require_once __DIR__ . '/access_control.php'; 

// 2. Obtenha o ID do processo da URL e o ID do utilizador logado
$caseId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$user = current_user();
$userId = (int)$user['id'];

if (!$caseId) {
    http_response_code(404);
    exit('Processo não encontrado.');
}

// 3. Aplica a barreira de segurança (Bloqueia e regista na auditoria se não tiver acesso)
require_case_access($pdo, $userId, $caseId, 'view');

// Daqui para baixo, o seu código original de carregar os andamentos, documentos, etc. continua normalmente...