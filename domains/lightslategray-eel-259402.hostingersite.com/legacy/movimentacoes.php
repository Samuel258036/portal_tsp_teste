<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/portal-layout.php';
portal_header('Atualizações', 'movimentacoes');

require_role(['admin', 'gestao', 'adv']);

if (function_exists('flash')) {
    flash();
} 

?>
<section class="panel"><span class="eyebrow">PORTAL TSP</span>
    <h2>Atualizações</h2>
    <p>Central de movimentações processuais. O módulo será conectado à tabela case_updates na próxima etapa.</p>
    <div class="empty">Módulo preparado para a próxima etapa da integração com MySQL.</div>
</section>
<?php portal_footer(); ?>