<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/database.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        $error = 'Por favor, insira um e-mail válido.';
    } else {
        // 1. Verifica se o utilizador existe e está ativo
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // 2. Gera o token único e o seu hash SHA-256
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            // 3. Invalida tokens anteriores não utilizados
            $stmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");
            $stmt->execute([$user['id']]);

            // 4. Regista o novo token de utilização única
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $tokenHash, $expiresAt]);

            // 5. Constrói o link de redefinição
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/v4/redefinir-senha.php?token=" . $rawToken;

            // Simulação de envio por e-mail (substituir pela função mail() ou PHPMailer do projeto)
            // mail($email, "Recuperação de Senha", "Aceda ao link para redefinir a sua senha: " . $resetLink);

            $message = 'Se o e-mail estiver registado no sistema, receberá as instruções de redefinição em instantes.';
        } else {
            // Resposta genérica para evitar enumeração de e-mails
            $message = 'Se o e-mail estiver registado no sistema, receberá as instruções de redefinição em instantes.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="../../assets/portal.css?v=4.2">
    <title>Recuperar Senha — Portal TSP</title>
</head>

<body class="login">
    <section>
        <span>PORTAL TSP</span>
        <h1>Recuperar Senha</h1>
        <p>Informe seu e-mail para receber as instruções.</p>

        <?php if ($message): ?>
            <div class="flash ok"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="flash error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <label>E-mail
                <input type="email" name="email" required autofocus placeholder="seu.email@exemplo.com">
            </label>

            <button style="margin-top: 15px;">Enviar link de recuperação</button>

            <div style="text-align: center; margin-top: 15px;">
                <a href="../../index.php"
                   style="color: #64748b; font-size: 13px; text-decoration: none; font-family: sans-serif; transition: color 0.2s;"
                   onmouseover="this.style.color='#183049'; this.style.textDecoration='underline';"
                   onmouseout="this.style.color='#64748b'; this.style.textDecoration='none';">
                    Voltar para o login
                </a>
            </div>
        </form>
    </section>
</body>

</html>