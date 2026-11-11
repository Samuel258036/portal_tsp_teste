<?php

declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../../config/database.php';

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        header('Location: v4/views/dashboard.php');
        exit;
    }
}
function flash_set(string $t, string $m): void
{
    $_SESSION['flash'] = ['type' => $t, 'message' => $m];
}
function flash_get(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/**
 * Verifica se o IP ou e-mail ultrapassou o limite de 5 tentativas nos últimos 15 minutos
 */
function is_ip_or_email_blocked(PDO $pdo, string $ip, string $email): bool {
    $sql = "SELECT COUNT(*) FROM login_attempts 
            WHERE (ip_address = ? OR email = ?) 
              AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ip, $email]);
    return (int)$stmt->fetchColumn() >= 5;
}

/**
 * Regista uma tentativa falhada de login
 */
function record_login_attempt(PDO $pdo, string $ip, string $email): void {
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, email) VALUES (?, ?)");
    $stmt->execute([$ip, $email]);
}

/**
 * Limpa o histórico de tentativas falhadas após login com sucesso
 */
function clear_login_attempts(PDO $pdo, string $ip, string $email): void {
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR email = ?");
    $stmt->execute([$ip, $email]);
}

/**
 * Verifica se a sessão expirou por inatividade (padrão: 30 minutos / 1800 segundos)
 */
function check_session_timeout(int $maxInactivitySeconds = 1800): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['last_activity'])) {
        $inactiveTime = time() - $_SESSION['last_activity'];
        if ($inactiveTime >= $maxInactivitySeconds) {
            // Destrói a sessão por inatividade
            session_unset();
            session_destroy();

            // Redireciona para o login informando o tempo esgotado
            header('Location: ../../index.php?reason=timeout');
            exit;
        }
    }
    // Atualiza a estampa de tempo da última atividade realizada
    $_SESSION['last_activity'] = time();
}

/**
 * Função de middleware para proteger páginas privadas
 */
function require_login(): void {
    if (!current_user()) {
        header('Location: ../../index.php');
        exit;
    }
    
    // Executa a checagem de inatividade a cada requisição
    check_session_timeout(1500); // 25 minutos
}

/**
 * Verifica se o utilizador atual possui uma das roles permitidas
 */
function has_role(array $allowedRoles): bool {
    $user = current_user();
    if (!$user || !isset($user['role'])) {
        return false;
    }
    return in_array($user['role'], $allowedRoles, true);
}

/**
 * Exige um conjunto de roles para aceder à página (retorna HTTP 403 se não tiver permissão)
 */
function require_role(array $allowedRoles): void {
    require_login();
    if (!has_role($allowedRoles)) {
        http_response_code(403);
        die('Acesso Negado: Não possui permissão para aceder a esta secção.');
    }
}

/**
 * Verifica se o utilizador pertence à equipa interna (Admin, Gestão ou Advogado)
 */
function is_staff(): bool {
    return has_role(['admin', 'gestao', 'adv']);
}

/**
 * Verifica se o utilizador tem privilégios de gestão ou admin
 */
function is_admin_or_gestao(): bool {
    return has_role(['admin', 'gestao']);
}