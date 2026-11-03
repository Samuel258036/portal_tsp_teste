<?php
require_once __DIR__ . '/config.php';

function page_header(string $title, string $active = ''): void {
    $name = $_SESSION['cliente']['nome'] ?? 'Cliente';
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e($title) ?> | TSP</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand"><div class="brand-mark">TSP</div><div><strong>TSP Advogados</strong><small>Área do Cliente</small></div></div>
    <nav>
      <a class="<?= $active==='inicio'?'active':'' ?>" href="inicio.php">⌂ <span>Início</span></a>
      <a class="<?= $active==='processos'?'active':'' ?>" href="processos.php">▣ <span>Processos</span></a>
      <a class="<?= $active==='financeiro'?'active':'' ?>" href="financeiro.php">◫ <span>Financeiro</span></a>
      <a class="<?= $active==='chamados'?'active':'' ?>" href="chamados.php">◌ <span>Chamados</span></a>
    </nav>
    <div class="sidebar-bottom">
      <div class="user-mini"><div class="avatar"><?= e(strtoupper(substr($name,0,1))) ?></div><div><b><?= e($name) ?></b><small>Cliente</small></div></div>
      <a class="logout" href="logout.php">Sair</a>
    </div>
  </aside>
  <main class="content">
    <div class="mobile-top"><div class="brand-mark">TSP</div><b>Área do Cliente</b></div>
<?php
}

function page_footer(): void { ?>
  </main>
</div>
<script src="assets/app.js"></script>
</body>
</html>
<?php } ?>