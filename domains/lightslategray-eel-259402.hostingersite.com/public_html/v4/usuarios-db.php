<?php
declare(strict_types=1);
require_once __DIR__ . '/portal-layout.php';
require_roles(['admin']);

$ok=''; $error='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action=$_POST['action'] ?? 'create';

    if ($action==='create') {
        $name=trim($_POST['name']??'');
        $email=strtolower(trim($_POST['email']??''));
        $password=$_POST['password']??'';
        $role=$_POST['role']??'cliente';
        $clientId=($_POST['client_id']??'')!=='' ? (int)$_POST['client_id'] : null;
        $oab=trim($_POST['oab']??'');
        $phone=trim($_POST['phone']??'');

        if($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Preencha nome e e-mail válido.';
        elseif(strlen($password)<8) $error='A senha inicial precisa ter pelo menos 8 caracteres.';
        elseif(!in_array($role,['admin','gestao','adv','cliente'],true)) $error='Perfil inválido.';
        elseif($role==='cliente' && !$clientId) $error='Para perfil Cliente, selecione o cliente vinculado.';
        else {
            if($role!=='cliente') $clientId=null;
            try {
                $stmt=$pdo->prepare("INSERT INTO users(client_id,name,email,password_hash,role,oab,phone,status) VALUES(?,?,?,?,?,?,?,'active')");
                $stmt->execute([$clientId,$name,$email,password_hash($password,PASSWORD_DEFAULT),$role,$oab?:null,$phone?:null]);
                $id=(int)$pdo->lastInsertId();
                audit($pdo,'create','user',$id,'Usuário criado pelo painel administrativo');
                $ok='Usuário e acesso criados com sucesso.';
            } catch(PDOException $e) {
                $error=$e->getCode()==='23000' ? 'Já existe um acesso com esse e-mail.' : 'Não foi possível criar o usuário.';
            }
        }
    }

    if ($action==='toggle') {
        $id=(int)($_POST['id']??0);
        if($id === (int)current_user()['id']) $error='Você não pode bloquear o próprio acesso.';
        else {
            $pdo->prepare("UPDATE users SET status=IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
            audit($pdo,'toggle_status','user',$id,'Status do usuário alterado');
            $ok='Acesso atualizado.';
        }
    }
}

$clients=$pdo->query("SELECT id,name FROM clients WHERE status='active' ORDER BY name")->fetchAll();
$users=$pdo->query("SELECT u.*,c.name AS client_name FROM users u LEFT JOIN clients c ON c.id=u.client_id ORDER BY u.created_at DESC")->fetchAll();

portal_header('Usuários e acessos','usuarios');
?>
<header class="pagehead"><div><span class="kicker">CONTROLE DE ACESSO</span><h1>Usuários e acessos</h1><p>Crie Admin, Gestão TSP, Adv TSP e Cliente sem acessar o banco de dados.</p></div><button type="button" onclick="userModal.showModal()">+ Novo acesso</button></header>
<?php if($ok):?><div class="notice-ok"><?=e($ok)?></div><?php endif;?>
<?php if($error):?><div class="notice-error"><?=e($error)?></div><?php endif;?>
<section class="card"><table class="admin-table"><thead><tr><th>USUÁRIO</th><th>PERFIL</th><th>CLIENTE VINCULADO</th><th>OAB</th><th>ÚLTIMO LOGIN</th><th>STATUS</th><th></th></tr></thead><tbody>
<?php foreach($users as $u):?><tr><td><b><?=e($u['name'])?></b><br><small><?=e($u['email'])?></small></td><td><?=e(role_label($u['role']))?></td><td><?=e($u['client_name']?:'—')?></td><td><?=e($u['oab']?:'—')?></td><td><?=e($u['last_login_at']?:'Nunca')?></td><td><?=e($u['status']==='active'?'Ativo':($u['status']==='blocked'?'Bloqueado':'Inativo'))?></td><td>
<?php if((int)$u['id']!==(int)current_user()['id']):?><form method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$u['id']?>"><button class="ghost"><?=$u['status']==='active'?'Desativar':'Ativar'?></button></form><?php endif;?>
</td></tr><?php endforeach;?></tbody></table></section>
<dialog id="userModal"><form method="post" class="modal"><input type="hidden" name="action" value="create">
<div class="sectionhead"><div><span class="kicker">NOVO ACESSO</span><h2>Cadastrar usuário</h2></div><button type="button" class="x" onclick="userModal.close()">×</button></div>
<div class="form-grid">
<label>Nome<input name="name" required></label><label>E-mail<input type="email" name="email" required></label>
<label>Perfil<select name="role" id="role" onchange="roleChanged()"><option value="cliente">Cliente</option><option value="adv">Adv TSP</option><option value="gestao">Gestão TSP</option><option value="admin">Admin</option></select></label>
<label id="clientWrap">Cliente vinculado<select name="client_id"><option value="">Selecione</option><?php foreach($clients as $c):?><option value="<?=$c['id']?>"><?=e($c['name'])?></option><?php endforeach;?></select></label>
<label>OAB (quando aplicável)<input name="oab"></label><label>Telefone<input name="phone"></label>
<label class="full">Senha inicial<input type="password" name="password" minlength="8" required><small>O usuário utilizará essa senha para o primeiro acesso.</small></label>
</div><button>Criar acesso</button></form></dialog>
<script>function roleChanged(){document.getElementById('clientWrap').style.display=document.getElementById('role').value==='cliente'?'block':'none';}roleChanged();</script>
<?php portal_footer(); ?>
