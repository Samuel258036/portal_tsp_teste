<?php
declare(strict_types=1);

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function portal_header(string $title, string $active = ''): void
{
    $u = current_user();
    $role = $u['role'] ?? 'cliente';

    $roleLabel = match ($role) {
        'admin'   => 'ADMIN',
        'gestao'  => 'GESTÃO',
        'adv'     => 'ADVOGADO',
        'cliente' => 'CLIENTE',
        default   => strtoupper((string)$role)
    };

    // Construção Dinâmica do Menu (Simplificado para 1 única linha do tempo)
    $items = [];
    $items['dashboard'] = ['dashboard.php', 'Visão geral'];

    if (is_admin_or_gestao()) {
        $items['clientes'] = ['clientes.php', 'Clientes'];
        $items['usuarios'] = ['usuarios.php', 'Usuários e acessos'];
    }

    $items['processos']    = ['processos.php', 'Processos'];
    $items['atualizacoes'] = ['atualizacoes.php', 'Atualizações']; // Módulo Único
    $items['financeiro']   = ['financeiro.php', 'Financeiro'];

    if (is_admin_or_gestao()) {
        $items['sync_monitor'] = ['sync_monitor.php', 'Monitor de Sincronização'];
        $items['case_access']  = ['case_access_frontend.php', 'Acessos a processos'];
    }

    $items['chamados'] = ['chamados.php', 'Chamados'];

    if (is_admin_or_gestao()) {
        $items['integracoes'] = ['integracoes.php', 'Integrações'];
    }

    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . ' · TSP</title><link rel="stylesheet" href="../../assets/portal.css?v=4.2"></head><body><aside class="sidebar"><div class="brand"><b>T</b><div><strong>TSP</strong><small>SOCIEDADE DE ADVOGADOS</small></div></div><div class="role">' . e($roleLabel) . '</div><nav>';
    
    foreach ($items as $k => $i) {
        echo '<a class="' . ($active === $k ? 'active' : '') . '" href="' . $i[0] . '">' . $i[1] . '</a>';
    }
    
    echo '</nav><a class="logout" href="../api/logout.php">Sair</a></aside><main><header><div><span>PORTAL TSP</span><h1>' . e($title) . '</h1></div><em>' . e($u['name'] ?? 'Usuário') . ' (' . e($roleLabel) . ')</em></header>';
    
    if ($f = flash_get()) {
        echo '<div class="flash ' . $f['type'] . '">' . e($f['message']) . '</div>';
    }
}

function portal_footer(): void
{
    echo '</main></body></html>';
}