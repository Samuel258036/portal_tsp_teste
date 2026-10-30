<?php

declare(strict_types=1);
require_once __DIR__ . '/portal-layout.php';
require_roles(['admin']);

$ok = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $type = $_POST['type'] ?? 'PJ';
        $name = trim($_POST['name'] ?? '');
        $document = preg_replace('/\s+/', '', trim($_POST['document'] ?? ''));
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($name === '') $error = 'Informe o nome do cliente.';
        else {
            try {
                $stmt = $pdo->prepare("INSERT INTO clients(type,name,document,email,phone,notes) VALUES(?,?,?,?,?,?)");
                $stmt->execute([$type, $name, $document ?: null, $email ?: null, $phone ?: null, $notes ?: null]);
                $id = (int)$pdo->lastInsertId();
                audit($pdo, 'create', 'client', $id, 'Cliente cadastrado pelo Portal TSP');
                $ok = 'Cliente cadastrado com sucesso.';
            } catch (PDOException $e) {
                $error = $e->getCode() === '23000' ? 'Já existe cliente com esse CPF/CNPJ.' : 'Não foi possível cadastrar o cliente.';
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE clients SET status=IF(status='active','inactive','active') WHERE id=?");
        $stmt->execute([$id]);
        audit($pdo, 'toggle_status', 'client', $id, 'Status do cliente alterado');
        $ok = 'Status atualizado.';
    }
}

$clients = $pdo->query("SELECT c.*,
    (SELECT COUNT(*) FROM users u WHERE u.client_id=c.id AND u.role='cliente') AS access_count,
    (SELECT COUNT(*) FROM cases cs WHERE cs.client_id=c.id AND cs.active=1) AS case_count
    FROM clients c ORDER BY c.created_at DESC")->fetchAll();

portal_header('Clientes', 'clientes');
?>
<header class="pagehead">
    <div><span class="kicker">ADMINISTRAÇÃO</span>
        <h1>Clientes</h1>
        <p>Cadastre e gerencie os clientes diretamente pelo Portal TSP.</p>
    </div><button type="button" onclick="clientModal.showModal()">+ Novo cliente</button>
</header>
<?php if ($ok): ?><div class="notice-ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice-error"><?= e($error) ?></div><?php endif; ?>
<section class="card">
    <?php if (!$clients): ?><p>Nenhum cliente cadastrado ainda.</p><?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>CLIENTE</th>
                    <th>DOCUMENTO</th>
                    <th>CONTATO</th>
                    <th>ACESSOS</th>
                    <th>PROCESSOS</th>
                    <th>STATUS</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $c): ?><tr>
                        <td><b><?= e($c['name']) ?></b><br><small><?= e($c['type']) ?></small></td>
                        <td><?= e($c['document'] ?: '—') ?></td>
                        <td><?= e($c['email'] ?: '—') ?><br><small><?= e($c['phone'] ?: '') ?></small></td>
                        <td><?= $c['access_count'] ?></td>
                        <td><?= $c['case_count'] ?></td>
                        <td><?= e($c['status'] === 'active' ? 'Ativo' : 'Inativo') ?></td>
                        <td>
                            <form method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="ghost"><?= $c['status'] === 'active' ? 'Desativar' : 'Ativar' ?></button></form>
                        </td>
                    </tr><?php endforeach; ?></tbody>
        </table>
    <?php endif; ?>
</section>
<dialog id="clientModal">
    <form method="post" class="modal"><input type="hidden" name="action" value="create">
        <div class="sectionhead">
            <div><span class="kicker">NOVO CADASTRO</span>
                <h2>Cadastrar cliente</h2>
            </div><button type="button" class="x" onclick="clientModal.close()">×</button>
        </div>
        <div class="form-grid">
            <label>Tipo<select name="type">
                    <option value="PJ">Pessoa jurídica</option>
                    <option value="PF">Pessoa física</option>
                </select></label>
            <label>Nome / Razão social<input name="name" required></label>
            <label>CPF / CNPJ<input name="document"></label><label>E-mail<input type="email" name="email"></label>
            <label>Telefone<input name="phone"></label><label class="full">Observações<textarea name="notes"></textarea></label>
        </div><button>Cadastrar cliente</button>
    </form>
</dialog>
<?php portal_footer(); ?>