<?php
require_once __DIR__ . '/../api/auth.php';
require_admin();
require_once __DIR__ . '/portal-layout.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $n = trim($_POST['name'] ?? '');
    $doc = preg_replace('/\D+/', '', $_POST['document'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    try {
        $s = $pdo->prepare("INSERT INTO clients(name,document,email,phone,active,created_at,updated_at)VALUES(?,?,?,?,1,NOW(),NOW())");
        $s->execute([$n, $doc, $email, $phone]);
        flash_set('ok', 'Cliente cadastrado com sucesso.');
    } catch (Throwable $e) {
        flash_set('error', 'Não foi possível cadastrar.');
    }
    header('Location: clientes.php');
    exit;
}
$rows = $pdo->query("SELECT * FROM clients ORDER BY id DESC")->fetchAll();
portal_header('Clientes', 'clientes'); ?><section class="panel"><span>CADASTRO</span>
    <h2>Novo cliente</h2>
    <form method="post" class="grid"><label>Nome / Razão social<input name="name" required></label><label>CPF/CNPJ<input name="document"></label><label>E-mail<input type="email" name="email"></label><label>Telefone<input name="phone"></label>
        <div><button>Cadastrar cliente</button></div>
    </form>
</section>
<section class="panel">
    <h2>Clientes cadastrados</h2>
    <table>
        <tr>
            <th>Cliente</th>
            <th>Documento</th>
            <th>E-mail</th>
            <th>Status</th>
        </tr><?php foreach ($rows as $r): ?><tr>
                <td><?= e($r['name']) ?></td>
                <td><?= e($r['document'] ?? '') ?></td>
                <td><?= e($r['email'] ?? '') ?></td>
                <td><?= ((int)($r['active'] ?? 1) === 1) ? 'Ativo' : 'Inativo' ?></td>
            </tr><?php endforeach; ?>
    </table>
</section><?php portal_footer(); ?>