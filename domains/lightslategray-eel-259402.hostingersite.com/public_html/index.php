<?php
declare(strict_types=1);
require_once __DIR__ . '/v4/api/auth.php';
require_once __DIR__ . '/v4/views/portal-layout.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Se já existir a sessão 'user', redireciona para o dashboard
if (current_user()) {
    header('Location: v4/views/dashboard.php'); // (ajuste o caminho se a view estiver noutra pasta)
    exit;
}

$error = '';

if (($_GET['reason'] ?? '') === 'timeout') {
    $error = 'Sua sessão expirou por inatividade. Por favor, faça login novamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if (!$email || empty($password)) {
        $error = 'Por favor, preencha o e-mail e a senha.';
    } 
    // 1. Verificação de Rate Limiting (5 tentativas falhadas / 15 min)
    elseif (is_ip_or_email_blocked($pdo, $ip, (string)$email)) {
        $error = 'Muitas tentativas falhadas. Sua conta/IP foi temporariamente bloqueada por 15 minutos por razões de segurança.';
    } 
    else {
        // 2. Consulta adaptada às colunas exatas da tabela 'users'
        $stmt = $pdo->prepare("
            SELECT id, client_id, name, email, password_hash, role, status, active 
            FROM users 
            WHERE email = ? 
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 3. Validação de credenciais usando 'password_hash'
        if ($user && password_verify($password, $user['password_hash'])) {
            
            // 4. Validação dos campos 'active' e 'status'
            if ((int)$user['active'] !== 1 || $user['status'] !== 'active') {
                if ($user['status'] === 'blocked') {
                    $error = 'Sua conta está bloqueada por um administrador.';
                } else {
                    $error = 'Sua conta está inativa. Entre em contato com o suporte.';
                }
            } else {
                // 5. Limpa histórico de rate limiting
                clear_login_attempts($pdo, $ip, (string)$email);

                // 6. Atualiza a coluna 'last_login_at'
                $updateLoginStmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
                $updateLoginStmt->execute([$user['id']]);

                // 7. Regeneração de sessão segura contra Session Fixation
                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'id'        => (int)$user['id'],
                    'client_id' => $user['client_id'] ? (int)$user['client_id'] : null,
                    'name'      => $user['name'],
                    'email'     => $user['email'],
                    'role'      => $user['role'],
                    'status'    => $user['status'],
                    'active'    => (int)$user['active']
                ];

                $_SESSION['last_activity'] = time();

                header('Location: v4/views/dashboard.php');
                exit;
            }
        } else {
            // 8. Falha de autenticação: regista tentativa
            record_login_attempt($pdo, $ip, (string)$email);
            $error = 'E-mail ou senha incorretos.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="assets/portal.css?v=4.2">
    <title>Portal TSP</title>
</head>

<body class="login">
    <section><span>PORTAL TSP</span>
        <h1>Portal do Cliente</h1>
        <p>Acesse seu ambiente TSP.</p><?php if ($error): ?><div class="flash error"><?= $error ?></div><?php endif; ?>
        <form method="post"><label>E-mail<input type="email" name="email" required></label><label>Senha<input
                    type="password" name="password" required></label>
            <div style="text-align: right; margin-top: 6px;">
                <a href="v4/views/esqueci-senha.php"
                    style="color: #64748b; font-size: 13px; text-decoration: none; font-family: sans-serif; transition: color 0.2s;"
                    onmouseover="this.style.color='#183049'; this.style.textDecoration='underline';"
                    onmouseout="this.style.color='#64748b'; this.style.textDecoration='none';">
                    Esqueceu a senha?
                </a>
            </div><br>
            <button>Entrar</button>
        </form>
    </section>
</body>

</html>