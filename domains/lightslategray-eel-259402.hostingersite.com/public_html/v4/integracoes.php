<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_admin();
require_once __DIR__ . '/portal-layout.php';

if (empty($_SESSION['juridiq_csrf'])) $_SESSION['juridiq_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['juridiq_csrf'];
$result = null;
$preview = [];
$limit = 10;

function juridiq_escape(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function juridiq_request(string $apiKey, int $limit): array {
    $url = 'https://api.juridiq.com.br/person/?' . http_build_query(['page' => 1, 'limit' => $limit]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['x-juridiq-api-key: ' . $apiKey, 'Accept: application/json'],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $status < 200 || $status >= 300) {
        error_log('Juridiq API failure: HTTP ' . $status . '; cURL: ' . $error);
        throw new RuntimeException('Falha na consulta ao Juridiq.');
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data)) throw new RuntimeException('Resposta inesperada do Juridiq.');
    return $data;
}
function juridiq_key(): string {
    $file = dirname(__DIR__, 2) . '/tsp-juridiq-secret.php';
    if (!is_file($file)) return '';
    $config = require $file;
    return is_array($config) ? trim((string)($config['api_key'] ?? '')) : '';
}
function juridiq_people(string $key, int $limit): array {
    $response = juridiq_request($key, $limit);
    $items = $response['data'] ?? null;
    if (!is_array($items) || !array_is_list($items)) throw new RuntimeException('Lista de pessoas inválida.');
    $people = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $id = trim((string)($item['id'] ?? ''));
        $name = trim((string)($item['name'] ?? ''));
        if ($id === '' || $name === '' || strlen($id) > 100) continue;
        $people[$id] = [
            'id' => $id,
            'name' => mb_substr($name, 0, 180),
            'email' => mb_substr(trim((string)($item['email'] ?? '')), 0, 180),
            'phone' => mb_substr(trim((string)($item['phone'] ?? '')), 0, 30),
        ];
    }
    return $people;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $result = ['ok' => false, 'message' => 'Sessão inválida. Atualize a página.'];
    } else {
        $action = (string)($_POST['action'] ?? '');
        $key = juridiq_key();
        if ($key === '') {
            $result = ['ok' => false, 'message' => 'Chave Juridiq não configurada.'];
        } elseif (!function_exists('curl_init')) {
            $result = ['ok' => false, 'message' => 'Extensão cURL indisponível.'];
        } else {
            try {
                if ($action === 'test_juridiq') {
                    juridiq_request($key, 1);
                    $result = ['ok' => true, 'message' => 'Conexão validada. Nenhum dado importado.'];
                } elseif ($action === 'preview_juridiq') {
                    $preview = juridiq_people($key, $limit);
                    $result = ['ok' => true, 'message' => 'Prévia de ' . count($preview) . ' pessoa(s). Selecione apenas um cliente para o primeiro teste.'];
                } elseif ($action === 'import_juridiq') {
                    $id = trim((string)($_POST['juridiq_id'] ?? ''));
                    if ($id === '' || strlen($id) > 100) throw new RuntimeException('Seleção inválida.');
                    // Reconsulta no servidor: nunca confiar em nome ou e-mail enviados pelo navegador.
                    $people = juridiq_people($key, $limit);
                    if (!isset($people[$id])) throw new RuntimeException('Pessoa não encontrada na consulta atual.');
                    $person = $people[$id];
                    $check = $pdo->prepare('SELECT id FROM clients WHERE juridiq_person_id = ? LIMIT 1');
                    $check->execute([$id]);
                    if ($check->fetch()) {
                        $result = ['ok' => false, 'message' => 'Este cliente já está vinculado ao Juridiq. Nenhum registro foi alterado.'];
                    } else {
                        $email = filter_var($person['email'], FILTER_VALIDATE_EMAIL) ? $person['email'] : null;
                        $phone = $person['phone'] !== '' ? $person['phone'] : null;
                        // PF/PJ não é informado de forma confiável neste endpoint: não inferir.
                        // A importação é bloqueada até que o administrador escolha o tipo.
                        $type = (string)($_POST['client_type'] ?? '');
                        if (!in_array($type, ['PF', 'PJ'], true)) throw new RuntimeException('Escolha PF ou PJ antes de importar.');
                        $pdo->beginTransaction();
                        try {
                            $insert = $pdo->prepare("INSERT INTO clients (type, name, document, email, phone, status, notes, active, juridiq_person_id, created_at, updated_at) VALUES (?, ?, NULL, ?, ?, 'active', NULL, 1, ?, NOW(), NOW())");
                            $insert->execute([$type, $person['name'], $email, $phone, $id]);
                            $pdo->commit();
                            $result = ['ok' => true, 'message' => 'Cliente importado com sucesso. Confira o cadastro em Clientes antes de prosseguir.'];
                        } catch (Throwable $e) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            throw $e;
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log('Juridiq integration: ' . $e->getMessage());
                $result = ['ok' => false, 'message' => 'Não foi possível concluir a operação. Verifique se o cliente já existe, se o tipo foi escolhido e consulte o log do servidor.'];
            }
        }
    }
}
portal_header('Integrações', 'integracoes');
?>
<section class="panel"><span>PORTAL TSP</span><h2>Integrações</h2><p>Conecte sistemas externos ao portal de forma segura.</p></section>
<section class="panel" style="margin-top:20px">
  <h2>Juridiq</h2>
  <p>Consulte pessoas e importe individualmente, após conferir os dados. Nenhuma movimentação ou processo será importado nesta etapa.</p>
  <?php if ($result): ?>
    <p role="status" style="padding:12px;border-radius:8px;background:<?= $result['ok'] ? '#e8f5ec' : '#fff0ed' ?>;color:#192b36"><?= juridiq_escape($result['message']) ?></p>
  <?php endif; ?>
  <form method="post" style="display:flex;gap:12px;flex-wrap:wrap">
    <input type="hidden" name="csrf" value="<?= juridiq_escape($csrf) ?>">
    <button type="submit" name="action" value="test_juridiq" style="padding:12px 18px;border:0;border-radius:8px;background:#183049;color:#fff;cursor:pointer">Testar conexão Juridiq</button>
    <button type="submit" name="action" value="preview_juridiq" style="padding:12px 18px;border:0;border-radius:8px;background:#9a772f;color:#fff;cursor:pointer">Consultar 10 clientes</button>
  </form>
  <p style="margin-top:14px;font-size:14px">A chave permanece no servidor. Senhas e documentos não são importados.</p>
  <?php if ($preview): ?>
    <div style="overflow-x:auto;margin-top:20px"><table style="width:100%;border-collapse:collapse;text-align:left">
      <thead><tr><th style="padding:10px">ID Juridiq</th><th style="padding:10px">Nome</th><th style="padding:10px">E-mail</th><th style="padding:10px">Telefone</th><th style="padding:10px">Importação</th></tr></thead>
      <tbody><?php foreach ($preview as $p): ?><tr style="border-top:1px solid #ddd">
        <td style="padding:10px"><?= juridiq_escape($p['id']) ?></td><td style="padding:10px"><?= juridiq_escape($p['name']) ?></td><td style="padding:10px"><?= juridiq_escape($p['email']) ?></td><td style="padding:10px"><?= juridiq_escape($p['phone']) ?></td>
        <td style="padding:10px"><form method="post" onsubmit="return confirm('Importar este cliente para o Portal TSP? Confira se já não existe cadastro manual.');">
          <input type="hidden" name="csrf" value="<?= juridiq_escape($csrf) ?>"><input type="hidden" name="juridiq_id" value="<?= juridiq_escape($p['id']) ?>">
          <select name="client_type" required style="padding:8px"><option value="">PF ou PJ?</option><option value="PF">PF</option><option value="PJ">PJ</option></select>
          <button type="submit" name="action" value="import_juridiq" style="padding:9px 12px;border:0;border-radius:8px;background:#183049;color:#fff;cursor:pointer">Importar</button>
        </form></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php portal_footer(); ?>
