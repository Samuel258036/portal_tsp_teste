<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/auth.php';
require_login();
require_role(['admin', 'gestao']);

if (empty($_SESSION['users_csrf'])) {
    $_SESSION['users_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['users_csrf'];

// --- PROCESSAMENTO DE CRIAÇÃO DE USUÁRIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        flash_set('error', 'Sessão expirada. Atualize a página e tente novamente.');
    } else {
        $name  = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pw    = (string)($_POST['password'] ?? '');
        $role  = (string)($_POST['role'] ?? 'cliente');
        
        $allowedRoles = ['admin', 'gestao', 'adv', 'cliente'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'cliente';
        }

        $isStaffRole = in_array($role, ['admin', 'gestao', 'adv'], true);
        $cid         = $isStaffRole ? null : (filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT) ?: null);

        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 8 || (!$isStaffRole && !$cid)) {
            flash_set('error', 'Revise os campos. Para o perfil Cliente, é obrigatório selecionar a empresa correspondente.');
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (client_id, name, email, password_hash, role, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
                ");
                $stmt->execute([$cid, $name, $email, password_hash($pw, PASSWORD_DEFAULT), $role]);
                flash_set('ok', 'Acesso criado com sucesso.');

            } catch (PDOException $e) {
                error_log('Erro de banco ao criar usuário: ' . $e->getMessage());

                // Tratamento específico para erro de e-mail duplicado (SQLSTATE 23000 / Error 1062)
                if ((string)$e->getCode() === '23000' || str_contains($e->getMessage(), '1062')) {
                    flash_set('error', 'Este endereço de e-mail já está cadastrado no sistema. Por favor, informe outro e-mail.');
                } else {
                    flash_set('error', 'Ocorreu um erro no banco de dados ao tentar registrar o acesso.');
                }
            } catch (Throwable $e) {
                error_log('Erro geral ao criar usuário: ' . $e->getMessage());
                flash_set('error', 'Não foi possível concluir o cadastro neste momento.');
            }
        }
    }
    header('Location: usuarios.php');
    exit;
}

// --- CONSULTA DE CLIENTES E USUÁRIOS ---
$clients = $pdo->query("SELECT id, name FROM clients WHERE active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$users   = $pdo->query("SELECT u.*, c.name AS client_name FROM users u LEFT JOIN clients c ON c.id = u.client_id ORDER BY u.id DESC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/portal-layout.php';
portal_header('Usuários e acessos', 'usuarios');
?>

<section class="panel">
    <span>ACESSOS</span>
    <h2>Criar novo usuário</h2>
    <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

        <label>Nome *
            <input name="name" required style="display:block;width:100%;padding:10px;margin-top:4px">
        </label>

        <label>E-mail *
            <input type="email" name="email" required style="display:block;width:100%;padding:10px;margin-top:4px">
        </label>

        <label>Senha temporária *
            <input type="password" name="password" minlength="8" required style="display:block;width:100%;padding:10px;margin-top:4px">
        </label>

        <label>Perfil *
            <select name="role" id="user_role_select" required style="display:block;width:100%;padding:10px;margin-top:4px">
                <option value="cliente">Cliente</option>
                <option value="adv">Advogado</option>
                <option value="gestao">Gestão</option>
                <option value="admin">Administrador</option>
            </select>
        </label>

        <label id="client_id_group">Vincular ao cliente *
            <select name="client_id" id="user_client_id" style="display:block;width:100%;padding:10px;margin-top:4px">
                <option value="">Selecione o cliente</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <div style="grid-column:1/-1;margin-top:8px">
            <button type="submit" style="padding:10px 20px;background:#183049;color:#fff;border:0;border-radius:6px;cursor:pointer;font-weight:600">
                Criar acesso
            </button>
        </div>
    </form>
</section>

<section class="panel" style="margin-top:20px">
    <h2>Usuários cadastrados</h2>
    <div style="overflow-x:auto;margin-top:12px">
        <table style="width:100%;border-collapse:collapse;text-align:left;font-size:14px">
            <thead>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0">
                    <th style="padding:10px">Nome</th>
                    <th style="padding:10px">E-mail</th>
                    <th style="padding:10px">Perfil (Role)</th>
                    <th style="padding:10px">Cliente / Empresa</th>
                    <th style="padding:10px">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <?php 
                    $roleClass = match ($u['role']) {
                        'admin'   => 'background:#fee2e2;color:#991b1b',
                        'gestao'  => 'background:#e0e7ff;color:#3730a3',
                        'adv'     => 'background:#dbeafe;color:#1e40af',
                        default   => 'background:#f1f5f9;color:#334155'
                    };
                    $roleLabel = match ($u['role']) {
                        'admin'   => 'ADMIN',
                        'gestao'  => 'GESTÃO',
                        'adv'     => 'ADVOGADO',
                        'cliente' => 'CLIENTE',
                        default   => strtoupper((string)$u['role'])
                    };
                    $userStatus = $u['status'] ?? ($u['active'] ?? 'active');
                    $isActive   = in_array((string)$userStatus, ['1', 'active'], true);
                ?>
                <tr style="border-top:1px solid #e2e8f0">
                    <td style="padding:10px"><strong><?= e($u['name']) ?></strong></td>
                    <td style="padding:10px"><?= e($u['email']) ?></td>
                    <td style="padding:10px">
                        <span style="font-size:11px;font-weight:bold;padding:3px 8px;border-radius:4px;<?= $roleClass ?>">
                            <?= e($roleLabel) ?>
                        </span>
                    </td>
                    <td style="padding:10px"><?= e($u['client_name'] ?? 'TSP (Equipe Interna)') ?></td>
                    <td style="padding:10px">
                        <?= $isActive ? '<span style="color:#0f5132;font-weight:bold">Ativo</span>' : '<span style="color:#8b2525">Inativo</span>' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('user_role_select');
    const clientGroup = document.getElementById('client_id_group');
    const clientSelect = document.getElementById('user_client_id');

    function toggleClientSelect() {
        const role = roleSelect.value;
        if (role === 'cliente') {
            clientGroup.style.display = 'block';
            clientSelect.required = true;
        } else {
            clientGroup.style.display = 'none';
            clientSelect.required = false;
            clientSelect.value = '';
        }
    }

    roleSelect.addEventListener('change', toggleClientSelect);
    toggleClientSelect();
});
</script>

<?php portal_footer(); ?>