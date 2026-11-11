<?php
declare(strict_types=1);

// Ajuste o caminho do db.php de acordo com a localização do ficheiro no seu projeto
require_once __DIR__ . '/../../../config/database.php';

$rawToken = filter_input(INPUT_GET, 'token', FILTER_DEFAULT) ?? ($_POST['token'] ?? '');
$error = '';
$success = false;
$resetRecord = null;

if (empty($rawToken)) {
    $error = 'Token de recuperação não fornecido ou inválido.';
} else {
    $tokenHash = hash('sha256', (string)$rawToken);

    // Valida se o token existe, é válido, não expirou e não foi utilizado
    $stmt = $pdo->prepare("
        SELECT r.id AS reset_id, r.user_id, u.email 
        FROM password_resets r
        INNER JOIN users u ON u.id = r.user_id
        WHERE r.token_hash = ? 
          AND r.expires_at > NOW() 
          AND r.used_at IS NULL 
          AND u.active = 1
          AND u.status = 'active'
    ");
    $stmt->execute([$tokenHash]);
    $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetRecord) {
        $error = 'Este link de recuperação é inválido ou já expirou.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resetRecord) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $error = 'A nova senha deve ter pelo menos 8 caracteres.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'As senhas não coincidem.';
    } else {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $pdo->beginTransaction();
        try {
            // 1. Atualiza a coluna password_hash do utilizador
            $stmtUser = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmtUser->execute([$newHash, $resetRecord['user_id']]);

            // 2. Marca o token como utilizado
            $stmtReset = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
            $stmtReset->execute([$resetRecord['reset_id']]);

            $pdo->commit();
            $success = true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Erro ao redefinir senha: ' . $e->getMessage());
            $error = 'Erro ao atualizar a senha. Tente novamente.';
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
    <title>Redefinir Senha — Portal TSP</title>
</head>

<body class="login">
    <section>
        <span>PORTAL TSP</span>
        <h1>Redefinir Senha</h1>
        <p>Crie uma nova senha de acesso à sua conta.</p>

        <?php if ($error): ?>
            <div class="flash error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="flash ok">Senha redefinida com sucesso!</div>
            <div style="margin-top: 20px; text-align: center;">
                <a href="../../index.php" style="display: block; width: 100%; padding: 12px; background: #183049; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 600; box-sizing: border-box;">
                    Ir para o Login
                </a>
            </div>
        <?php elseif ($resetRecord): ?>
            <form method="post">
                <input type="hidden" name="token" value="<?= htmlspecialchars((string)$rawToken) ?>">
                
                <label>Nova Senha (mín. 8 caracteres)
                    <input type="password" name="password" required minlength="8" placeholder="••••••••">
                </label>

                <label style="margin-top: 10px;">Confirmar Nova Senha
                    <input type="password" name="confirm_password" required minlength="8" placeholder="••••••••">
                </label>

                <button style="margin-top: 15px;">Atualizar Senha</button>
            </form>
        <?php else: ?>
            <div style="text-align: center; margin-top: 20px;">
                <a href="../../index.php"
                   style="color: #64748b; font-size: 13px; text-decoration: none; font-family: sans-serif; transition: color 0.2s;"
                   onmouseover="this.style.color='#183049'; this.style.textDecoration='underline';"
                   onmouseout="this.style.color='#64748b'; this.style.textDecoration='none';">
                    Voltar para o login
                </a>
            </div>
        <?php endif; ?>
    </section>
</body>

</html>