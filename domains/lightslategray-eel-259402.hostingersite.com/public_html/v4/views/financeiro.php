<?php
require_once __DIR__ . '/../api/auth.php';
require_login();
require_once __DIR__ . '/portal-layout.php';
portal_header('Financeiro', 'financeiro'); ?><section class="panel"><span>PORTAL TSP</span>
    <h2>Financeiro</h2>
    <p>Módulo estabilizado no novo layout. A funcionalidade completa será ativada na próxima etapa.</p>
</section><?php portal_footer(); ?>