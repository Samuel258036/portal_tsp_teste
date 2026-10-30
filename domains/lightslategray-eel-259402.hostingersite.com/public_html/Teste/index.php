<?php
require_once __DIR__ . '/config.php';
if (logged_in()) { header('Location: inicio.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpf = only_digits($_POST['cpf'] ?? '');
    $senha = (string)($_POST['senha'] ?? '');
    if ($cpf === DEMO_CPF && hash_equals(DEMO_PASSWORD, $senha)) {
        session_regenerate_id(true);
        $_SESSION['cliente'] = $cliente;
        header('Location: inicio.php');
        exit;
    }
    $error = 'CPF ou senha inválidos.';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Área do Cliente | TSP Advogados</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
<div class="login-wrap">
  <section class="login-hero">
    <div class="hero-brand"><div class="brand-mark light">TSP</div><span>TSP Advogados</span></div>
    <div>
      <p class="eyebrow light-text">ÁREA EXCLUSIVA</p>
      <h1>Seu processo,<br>mais perto de você.</h1>
      <p>Acompanhe movimentações, financeiro e fale com nossa equipe em um único lugar.</p>
    </div>
    <small>Ambiente seguro • Acesso individual</small>
  </section>
  <section class="login-card">
    <div class="login-card-inner">
      <p class="eyebrow">BEM-VINDO</p>
      <h2>Área do Cliente</h2>
      <p class="muted">Entre com seus dados para acessar suas informações.</p>
      <form method="post">
        <label>CPF<input name="cpf" placeholder="000.000.000-00" autocomplete="username" required></label>
        <label>Senha<input name="senha" type="password" autocomplete="current-password" required></label>
        <?php if ($error): ?><div class="alert bad"><?= e($error) ?></div><?php endif; ?>
        <button class="btn primary full">Entrar</button>
      </form>
      <div class="demo-box"><b>Acesso para teste</b><span>CPF: 123.456.789-00</span><span>Senha: demonstracao</span></div>
      <p class="security">🔒 Seus dados são tratados de forma confidencial.</p>
    </div>
  </section>
</div>
</body></html>