<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/portal-layout.php';

// Bloqueia Advogados e Clientes
require_role(['admin', 'gestao']);


// --- PROCESSAMENTO DE AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    // 1. Cadastrar Novo Cliente
    if ($action === 'create') {
        $n     = trim($_POST['name'] ?? '');
        $doc   = preg_replace('/\D+/', '', $_POST['document'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');

        if (empty($n)) {
            flash_set('error', 'O campo Nome / Razão social é obrigatório.');
        } else {
            try {
                $s = $pdo->prepare("INSERT INTO clients (name, document, email, phone, active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())");
                $s->execute([$n, $doc, $email, $phone]);
                flash_set('ok', 'Cliente cadastrado com sucesso.');
            } catch (Throwable $e) {
                error_log('Erro ao cadastrar cliente: ' . $e->getMessage());
                flash_set('error', 'Não foi possível cadastrar o cliente.');
            }
        }
    } 
    // 2. Alternar Status (Ativo / Inativo)
    elseif ($action === 'toggle_active') {
        $clientId   = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
        $currActive = (int)($_POST['current_active'] ?? 1);
        $newActive  = $currActive === 1 ? 0 : 1;
        $newStatus  = $newActive === 1 ? 'active' : 'inactive';

        if ($clientId) {
            try {
                $pdo->beginTransaction();

                // 1. Atualiza o status da empresa na tabela 'clients'
                $stmt = $pdo->prepare("UPDATE clients SET active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newActive, $clientId]);

                // 2. Propaga em cascata para todos os utilizadores vinculados a este client_id na tabela 'users'
                $stmtUsers = $pdo->prepare("UPDATE users SET active = ?, status = ?, updated_at = NOW() WHERE client_id = ?");
                $stmtUsers->execute([$newActive, $newStatus, $clientId]);

                $pdo->commit();
                flash_set('ok', 'Status do cliente e das contas de utilizador vinculadas atualizado com sucesso.');
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('Erro ao alterar status do cliente: ' . $e->getMessage());
                flash_set('error', 'Não foi possível alterar o status do cliente.');
            }
        }
    }

    header('Location: clientes.php');
    exit;
}

// --- CONSULTA COM CONTAGEM DE UTILIZADORES VINCULADOS ---
$rows = $pdo->query("
    SELECT c.*, COUNT(u.id) AS linked_users 
    FROM clients c 
    LEFT JOIN users u ON u.client_id = c.id 
    GROUP BY c.id 
    ORDER BY c.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

portal_header('Clientes', 'clientes'); 
?>

<section class="panel">
    <span>CADASTRO</span>
    <h2>Novo cliente</h2>
    <form method="post" class="grid">
        <input type="hidden" name="action" value="create">
        
        <label>
            Nome / Razão social *
            <input name="name" required placeholder="Ex: Empresa XYZ Ltda">
        </label>
        <label>
            CPF/CNPJ
            <input name="document" placeholder="Apenas números">
        </label>
        <label>
            E-mail
            <input type="email" name="email" placeholder="cliente@empresa.com">
        </label>
        <label>
            Telefone
            <input name="phone" placeholder="(11) 99999-9999">
        </label>
        <div>
            <button type="submit">Cadastrar cliente</button>
        </div>
    </form>
</section>

<section class="panel">
    <h2>Clientes cadastrados</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Documento</th>
                <th>E-mail</th>
                <th>Telefone</th>
                <th>Usuários Vinculados</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: #64748b; padding: 20px;">
                        Nenhum cliente cadastrado até o momento.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r): 
                    $isActive = ((int)($r['active'] ?? 1) === 1);
                ?>
                    <tr>
                        <td>#<?= (int)$r['id'] ?></td>
                        <td><strong><?= e($r['name']) ?></strong></td>
                        <td><?= e($r['document'] ?: '—') ?></td>
                        <td><?= e($r['email'] ?: '—') ?></td>
                        <td><?= e($r['phone'] ?: '—') ?></td>
                        <td>
                            <span style="background: #e2e8f0; color: #334155; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                <?= (int)$r['linked_users'] ?> conta(s)
                            </span>
                        </td>
                        <td>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; background: <?= $isActive ? '#dcfce7' : '#fee2e2' ?>; color: <?= $isActive ? '#166534' : '#991b1b' ?>;">
                                <?= $isActive ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="client_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="current_active" value="<?= (int)($r['active'] ?? 1) ?>">
                                <button type="submit" style="padding: 5px 10px; font-size: 12px; cursor: pointer; border-radius: 4px; border: 1px solid #cbd5e1;">
                                    <?= $isActive ? 'Desativar' : 'Ativar' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<?php portal_footer(); ?>