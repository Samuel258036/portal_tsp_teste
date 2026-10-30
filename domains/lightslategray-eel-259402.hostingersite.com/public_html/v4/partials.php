<?php require_once 'config.php';
function label_role($r)
{
    return ['admin' => 'Admin', 'gestao' => 'Gestão TSP', 'adv' => 'Adv TSP', 'cliente' => 'Cliente'][$r] ?? $r;
}
function header_page($title, $active = '')
{
    require_login();
    $u = $_SESSION['user'];
    $r = role(); ?>
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
        <aside class="side"><a class="logo" href="dashboard.php"><span class="brandmark">T</span><span class="brandtext"><b>TSP</b><small>SOCIEDADE DE ADVOGADOS</small></span></a>
            <div class="role"><span class="dot"></span><?= e(label_role($r)) ?></div>
            <nav>
                <a class="<?= $active === 'dash' ? 'on' : '' ?>" href="dashboard.php"><span>⌂</span> Visão geral</a>
                <?php if (in_array($r, ['admin', 'gestao'])): ?><a class="<?= $active === 'clients' ? 'on' : '' ?>" href="clientes.php"><span>◇</span> Clientes</a><?php endif; ?>
                <?php if ($r === 'admin'): ?><a class="<?= $active === 'users' ? 'on' : '' ?>" href="usuarios.php"><span>◎</span> Usuários e acessos</a><?php endif; ?>
                <a class="<?= $active === 'cases' ? 'on' : '' ?>" href="processos.php"><span>▱</span> <?= $r === 'adv' ? 'Minha carteira' : 'Processos' ?></a>
                <?php if (in_array($r, ['admin', 'gestao', 'cliente'])): ?><a class="<?= $active === 'fin' ? 'on' : '' ?>" href="financeiro.php"><span>◫</span> Financeiro</a><?php endif; ?>
                <a class="<?= $active === 'tickets' ? 'on' : '' ?>" href="chamados.php"><span>◌</span> Chamados</a>
                <?php if (in_array($r, ['admin', 'gestao', 'adv'])): ?><a class="<?= $active === 'updates' ? 'on' : '' ?>" href="movimentacoes.php"><span>↗</span> Movimentações</a><?php endif; ?>
                <?php if ($r === 'admin'): ?><a class="<?= $active === 'integrations' ? 'on' : '' ?>" href="integracoes.php"><span>⌁</span> Integrações</a><?php endif; ?>

            </nav>
            <div class="sidebottom"><b><?= e($u['name']) ?></b><small><?= e($u['email']) ?></small><a href="logout.php">Sair</a></div>
        </aside>
        <main>
            <div class="top-mobile"><span class="monogram">TSP</span><b><?= e(label_role($r)) ?></b></div>
        <?php }
    function footer_page()
    { ?><footer>Portal TSP · Ambiente demonstrativo V2</footer>
        </main>
        <script src="assets/app.js"></script>
    </body>

    </html><?php } ?>