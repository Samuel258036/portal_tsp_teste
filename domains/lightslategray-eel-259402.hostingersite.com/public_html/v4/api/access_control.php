<?php

declare(strict_types=1);

function user_can_access_case(PDO $pdo, int $userId, int $caseId): bool
{
    // 1. Administrador ou equipa interna tem acesso amplo
    $stmtUser = $pdo->prepare("SELECT role, client_id FROM users WHERE id = ? AND active = 1");
    $stmtUser->execute([$userId]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        return false;
    }

    if (in_array($userData['role'], ['admin', 'staff'], true)) {
        return true;
    }

    $userClientId = (int)($userData['client_id'] ?? 0);

    // 2. Verifica se o utilizador é o titular direto
    $stmtCase = $pdo->prepare("SELECT client_id FROM cases WHERE id = ? AND active = 1");
    $stmtCase->execute([$caseId]);
    $caseData = $stmtCase->fetch(PDO::FETCH_ASSOC);

    if ($caseData && $userClientId > 0 && (int)$caseData['client_id'] === $userClientId) {
        return true;
    }

    // 3. Verifica se existe permissão de terceiro ativa
    $stmtPerm = $pdo->prepare("
        SELECT id 
        FROM case_permissions 
        WHERE case_id = ? AND user_id = ? AND status = 'active'
        LIMIT 1
    ");
    $stmtPerm->execute([$caseId, $userId]);
    
    return (bool)$stmtPerm->fetchColumn();
}

function require_case_access(PDO $pdo, int $userId, int $caseId, string $accessType = 'view'): void
{
    $hasAccess = user_can_access_case($pdo, $userId, $caseId);
    
    // Regista tentativa no audit_logs existente
    $action = $hasAccess ? 'case_access_granted' : 'case_access_denied';
    $desc = "Tentativa de acesso via: $accessType";
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmtLog = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address, user_agent)
        VALUES (?, ?, 'cases', ?, ?, ?, ?)
    ");
    $stmtLog->execute([$userId, $action, $caseId, $desc, $ip, $ua]);

    if (!$hasAccess) {
        http_response_code(403);
        exit('Acesso Negado: Não tem permissão para visualizar os detalhes deste processo.');
    }
}

function get_authorized_cases_query(PDO $pdo, int $userId): array
{
    $stmtUser = $pdo->prepare("SELECT role, client_id FROM users WHERE id = ? AND active = 1");
    $stmtUser->execute([$userId]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        return ['where' => '1=0', 'params' => []];
    }

    if (in_array($userData['role'], ['admin', 'staff'], true)) {
        return ['where' => 'c.active = 1', 'params' => []];
    }

    $userClientId = (int)($userData['client_id'] ?? 0);

    $where = "(c.client_id = :client_id OR c.id IN (
        SELECT cp.case_id FROM case_permissions cp WHERE cp.user_id = :user_id AND cp.status = 'active'
    )) AND c.active = 1";

    return [
        'where' => $where,
        'params' => [
            ':client_id' => $userClientId,
            ':user_id'   => $userId
        ]
    ];
}