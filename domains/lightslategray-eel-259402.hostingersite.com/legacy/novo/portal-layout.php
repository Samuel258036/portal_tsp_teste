<?php
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function portal_header(string $title,string $active=''):void{
$u=current_user();$items=is_admin()?[
'dashboard'=>['dashboard.php','Visão geral'],'clientes'=>['clientes.php','Clientes'],'usuarios'=>['usuarios.php','Usuários e acessos'],
'processos'=>['processos.php','Processos'],'atualizacoes'=>['atualizacoes.php','Atualizações'],'financeiro'=>['financeiro.php','Financeiro'],'chamados'=>['chamados.php','Chamados'],'integracoes'=>['integracoes.php','Integrações']
]:['dashboard'=>['dashboard.php','Visão geral'],'processos'=>['processos.php','Processos'],'atualizacoes'=>['atualizacoes.php','Atualizações'],'financeiro'=>['financeiro.php','Financeiro'],'chamados'=>['chamados.php','Chamados']];
echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' · TSP</title><link rel="stylesheet" href="assets/portal.css?v=4.2"></head><body><aside class="sidebar"><div class="brand"><b>T</b><div><strong>TSP</strong><small>SOCIEDADE DE ADVOGADOS</small></div></div><div class="role">'.(is_admin()?'ADMIN':'CLIENTE').'</div><nav>';
foreach($items as $k=>$i)echo '<a class="'.($active===$k?'active':'').'" href="'.$i[0].'">'.$i[1].'</a>';
echo '</nav><a class="logout" href="logout.php">Sair</a></aside><main><header><div><span>PORTAL TSP</span><h1>'.e($title).'</h1></div><em>'.e($u['name']??'Usuário').'</em></header>';
if($f=flash_get())echo '<div class="flash '.$f['type'].'">'.e($f['message']).'</div>';
}
function portal_footer():void{echo '</main></body></html>';}
?>