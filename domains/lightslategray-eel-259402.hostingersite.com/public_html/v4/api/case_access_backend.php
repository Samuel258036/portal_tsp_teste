<?php

declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/access_control.php'; // Ajuste o caminho se necessário
require_admin();

function access_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$user = current_user();
$adminId = (int)$user['id'];

if (empty($_SESSION['access_csrf'])) {
    $_SESSION['access_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['access_csrf'];
$resultMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $resultMsg = ['ok' => false, 'message' => 'Sessão expirada. Atualize a página.'];
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'grant_access') {
            $caseId = (int)($_POST['case_id'] ?? 0);
            $targetUserId = (int)($_POST['user_id'] ?? 0);
            $reason = trim((string)($_POST['authorization_reason'] ?? ''));

            if ($caseId <= 0 || $targetUserId <= 0 || $reason === '') {
                $resultMsg = ['ok' => false, 'message' => 'Preencha todos os campos obrigatórios.'];
            } else {
                $stmtCheck = $pdo->prepare("SELECT id FROM case_permissions WHERE case_id = ? AND user_id = ? AND status = 'active'");
                $stmtCheck->execute([$caseId, $targetUserId]);
                
                if ($stmtCheck->fetch()) {
                    $resultMsg = ['ok' => false, 'message' => 'Este utilizador já possui permissão ativa para este processo.'];
                } else {
                    $stmtGrant = $pdo->prepare("
                        INSERT INTO case_permissions (case_id, user_id, granted_by_user_id, authorization_reason, status, granted_at)
                        VALUES (?, ?, ?, ?, 'active', NOW())
                    ");
                    $stmtGrant->execute([$caseId, $targetUserId, $adminId, $reason]);
                    
                    // Log na auditoria
                    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
                    $desc = "Concedeu acesso ao processo ID $caseId para o utilizador ID $targetUserId. Motivo: $reason";

                    $pdo->prepare("
                        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address, user_agent) 
                        VALUES (?, 'granted_case_access', 'case_permissions', ?, ?, ?, ?)
                    ")->execute([$adminId, $pdo->lastInsertId(), $desc, $ip, $ua]);

                    $resultMsg = ['ok' => true, 'message' => 'Acesso concedido com sucesso.'];
                }
            }
        }

        if ($action === 'revoke_access') {
            $permId = (int)($_POST['permission_id'] ?? 0);
            if ($permId > 0) {
                $stmtRevoke = $pdo->prepare("
                    UPDATE case_permissions 
                    SET status = 'revoked', revoked_at = NOW(), revoked_by_user_id = ?, revocation_reason = 'Revogado manualmente' 
                    WHERE id = ? AND status = 'active'
                ");
                $stmtRevoke->execute([$adminId, $permId]);
                
                // Log na auditoria
                $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
                
                $pdo->prepare("
                    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address, user_agent) 
                    VALUES (?, 'revoked_case_access', 'case_permissions', ?, 'Acesso revogado manualmente pelo painel', ?, ?)
                ")->execute([$adminId, $permId, $ip, $ua]);

                $resultMsg = ['ok' => true, 'message' => 'Permissão revogada. O utilizador já não tem acesso.'];
            }
        }
    }
}

// Consultas para popular os selects e a tabela de histórico
$cases = $pdo->query("SELECT c.id, c.case_number, c.title, cl.name AS client_name FROM cases c INNER JOIN clients cl ON cl.id = c.client_id WHERE c.active = 1 ORDER BY c.id DESC")->fetchAll(PDO::FETCH_ASSOC);
$users = $pdo->query("SELECT id, name, email FROM users WHERE role != 'admin' OR role IS NULL OR role = '' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$permissionsHistory = $pdo->query("
    SELECT cp.id, cp.case_id, cp.authorization_reason, cp.status, cp.granted_at, cp.revoked_at,
           c.case_number, u_target.name AS target_user_name, u_target.email AS target_user_email,
           u_grantor.name AS grantor_name, u_revoker.name AS revoker_name
    FROM case_permissions cp
    INNER JOIN cases c ON c.id = cp.case_id
    INNER JOIN users u_target ON u_target.id = cp.user_id
    INNER JOIN users u_grantor ON u_grantor.id = cp.granted_by_user_id
    LEFT JOIN users u_revoker ON u_revoker.id = cp.revoked_by_user_id
    ORDER BY cp.granted_at DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);