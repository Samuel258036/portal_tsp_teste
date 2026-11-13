<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/auth.php';
require_login();
require_once __DIR__ . '/../api/access_control.php';
require_once __DIR__ . '/portal-layout.php';

// Helper de auditoria opcional (se o ficheiro existir no projeto)
if (file_exists(__DIR__ . '/../api/audit.php')) {
    require_once __DIR__ . '/../api/audit.php';
}

function doc_h($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$user     = current_user();
$userId   = (int)($user['id'] ?? 0);
$isStaff  = is_staff();
$error    = null;

// Diretório de armazenamento seguro
$uploadDir = __DIR__ . '/../storage/documents/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (empty($_SESSION['docs_csrf'])) {
    $_SESSION['docs_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['docs_csrf'];

// --- PROCESSAMENTO DE UPLOAD (EQUIPA INTERNA) ---
if ($isStaff && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Sessão expirada. Atualize a página.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'upload') {
            $caseId          = filter_input(INPUT_POST, 'case_id', FILTER_VALIDATE_INT);
            $description     = trim((string)($_POST['description'] ?? ''));
            $visibleToClient = isset($_POST['visible_to_client']) ? 1 : 0;
            $isConfidential  = isset($_POST['is_confidential']) ? 1 : 0;
            $file            = $_FILES['document'] ?? null;

            $allowedExtensions = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'zip'];
            $maxFileSize       = 20 * 1024 * 1024; // 20 MB

            if (!$caseId || !$file || $file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Selecione um processo e um arquivo válido.';
            } elseif ($file['size'] > $maxFileSize) {
                $error = 'O arquivo excede o tamanho máximo permitido de 20MB.';
            } else {
                $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions, true)) {
                    $error = 'Tipo de arquivo não permitido. Extensões aceitas: ' . implode(', ', $allowedExtensions);
                } else {
                    $safeFilename = bin2hex(random_bytes(16)) . '.' . $ext;
                    $targetPath   = $uploadDir . $safeFilename;

                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $stmt = $pdo->prepare("
                            INSERT INTO case_documents (case_id, uploaded_by_user_id, filename, original_name, file_path, mime_type, file_size, visible_to_client, is_confidential, description, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $caseId,
                            $userId,
                            $safeFilename,
                            (string)$file['name'],
                            $targetPath,
                            (string)$file['type'],
                            (int)$file['size'],
                            $visibleToClient,
                            $isConfidential,
                            $description !== '' ? $description : null
                        ]);

                        $newId = (int)$pdo->lastInsertId();
                        if (function_exists('log_action')) {
                            log_action('document_upload', 'case_documents', $newId, "Upload do arquivo: {$file['name']} no processo #{$caseId}");
                        }

                        flash_set('ok', 'Documento enviado com sucesso.');
                        header('Location: documentos.php');
                        exit;
                    } else {
                        $error = 'Falha ao salvar o arquivo no servidor.';
                    }
                }
            }
        }
    }
}

// --- CONSULTA DE DOCUMENTOS E AGRUPAMENTO POR PROCESSO ---
$groupedDocuments = [];
$casesList = [];

try {
    if ($isStaff) {
        $casesList = $pdo->query("SELECT id, case_number, title FROM cases WHERE active = 1 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("
            SELECT d.*, c.case_number, c.title AS case_title, u.name AS uploader_name 
            FROM case_documents d 
            INNER JOIN cases c ON c.id = d.case_id 
            LEFT JOIN users u ON u.id = d.uploaded_by_user_id 
            WHERE c.active = 1 
            ORDER BY c.created_at DESC, d.created_at DESC 
            LIMIT 300
        ");
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $queryData = get_authorized_cases_query($pdo, $userId);

        $stmt = $pdo->prepare("
            SELECT d.id, d.case_id, d.original_name, d.file_size, d.mime_type, d.description, d.created_at, c.case_number, c.title AS case_title 
            FROM case_documents d 
            INNER JOIN cases c ON c.id = d.case_id 
            WHERE {$queryData['where']} 
              AND c.active = 1 
              AND (c.is_confidential = 0 OR c.is_confidential IS NULL) 
              AND d.visible_to_client = 1 
              AND d.is_confidential = 0 
            ORDER BY c.created_at DESC, d.created_at DESC 
            LIMIT 200
        ");
        $stmt->execute($queryData['params']);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Agrupa os documentos por processo no PHP
    foreach ($documents as $d) {
        $cId = (int)$d['case_id'];
        if (!isset($groupedDocuments[$cId])) {
            $groupedDocuments[$cId] = [
                'case_number' => $d['case_number'],
                'case_title'  => $d['case_title'],
                'items'       => []
            ];
        }
        $groupedDocuments[$cId]['items'][] = $d;
    }

} catch (Throwable $e) {
    error_log('Erro ao listar documentos: ' . $e->getMessage());
    $error = 'Não foi possível carregar os documentos.';
}

portal_header('Documentos', 'documentos');
?>

<section class="panel">
    <span>PORTAL TSP</span>
    <h2>Central de Documentos</h2>
    <p><?= $isStaff ? 'Gerencie o envio e as permissões de acesso aos documentos dos processos.' : 'Acesse os documentos disponibilizados para os seus processos.' ?></p>
</section>

<?php if ($error): ?>
<section class="panel" style="margin-top:20px;color:#8b2525">
    <p role="alert"><?= doc_h($error) ?></p>
</section>
<?php endif; ?>

<?php if ($isStaff): ?>
<!-- FORMULÁRIO DE UPLOAD DA EQUIPE INTERNA -->
<section class="panel" style="margin-top:20px">
    <h2>Enviar Documento</h2>
    <form method="post" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px">
        <input type="hidden" name="csrf" value="<?= doc_h($csrf) ?>">
        <input type="hidden" name="action" value="upload">

        <label>Processo *
            <select name="case_id" required style="display:block;width:100%;padding:12px;margin-top:6px">
                <option value="">Selecione um processo</option>
                <?php foreach ($casesList as $cs): ?>
                    <option value="<?= (int)$cs['id'] ?>">#<?= doc_h($cs['case_number']) ?> — <?= doc_h($cs['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Arquivo * (PDF, DOCX, PNG, JPG, ZIP — máx 20MB)
            <input type="file" name="document" required style="display:block;width:100%;padding:10px;margin-top:6px">
        </label>

        <label style="grid-column:1/-1">Descrição / Observação
            <input type="text" name="description" maxlength="255" placeholder="Ex: Cópia da Petição Inicial" style="display:block;width:100%;padding:12px;margin-top:6px">
        </label>

        <div style="grid-column:1/-1;display:flex;gap:20px;align-items:center">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="visible_to_client" value="1"> Visível ao Cliente
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:#c2410c">
                <input type="checkbox" name="is_confidential" value="1"> Documento Sigiloso
            </label>
        </div>

        <div style="grid-column:1/-1">
            <button type="submit" style="padding:12px 22px;background:#183049;color:#fff;border:0;border-radius:8px;cursor:pointer;font-weight:600">
                Fazer Upload
            </button>
        </div>
    </form>
</section>
<?php endif; ?>

<!-- DOCUMENTOS AGRUPADOS POR PROCESSO -->
<section class="panel" style="margin-top:20px">
    <h2>Documentos Disponíveis</h2>

    <?php if (empty($groupedDocuments)): ?>
        <p style="color:#64748b;margin-top:10px">Nenhum documento disponível no momento.</p>
    <?php else: ?>
        <div style="display:grid;gap:20px;margin-top:18px">
            <?php foreach ($groupedDocuments as $caseId => $group): ?>
            <div style="border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:16px">
                
                <!-- CABEÇALHO DO PROCESSO -->
                <div style="margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                    <div>
                        <span style="font-size:12px;background:#183049;color:#fff;padding:2px 8px;border-radius:4px;font-weight:600">
                            CNJ: <?= doc_h($group['case_number']) ?>
                        </span>
                        <h3 style="font-size:16px;color:#0f172a;margin-top:6px"><?= doc_h($group['case_title']) ?></h3>
                    </div>
                    <a href="processo-detalhe.php?id=<?= (int)$caseId ?>" style="font-size:13px;color:#183049;font-weight:bold;text-decoration:none">
                        Ver Ficha do Processo →
                    </a>
                </div>

                <!-- TABELA DE DOCUMENTOS DO PROCESSO -->
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;text-align:left;font-size:14px">
                        <thead>
                            <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0">
                                <th style="padding:10px">Documento</th>
                                <th style="padding:10px">Tamanho</th>
                                <th style="padding:10px">Data de Envio</th>
                                <?php if ($isStaff): ?><th style="padding:10px">Visibilidade</th><?php endif; ?>
                                <th style="padding:10px;text-align:center">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($group['items'] as $d): ?>
                            <tr style="border-top:1px solid #f1f5f9">
                                <td style="padding:10px;vertical-align:top">
                                    <strong><?= doc_h($d['original_name']) ?></strong>
                                    <?php if (!empty($d['description'])): ?>
                                        <small style="display:block;color:#64748b;margin-top:2px"><?= doc_h($d['description']) ?></small>
                                    <?php endif; ?>
                                </td>

                                <td style="padding:10px;vertical-align:top;color:#64748b">
                                    <?= number_format((float)($d['file_size'] / 1024 / 1024), 2, ',', '.') ?> MB
                                </td>

                                <td style="padding:10px;vertical-align:top;color:#64748b">
                                    <?= date('d/m/Y H:i', strtotime((string)$d['created_at'])) ?>
                                </td>

                                <?php if ($isStaff): ?>
                                    <td style="padding:10px;vertical-align:top">
                                        <?php if (!empty($d['is_confidential'])): ?>
                                            <span style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:bold">SIGILOSO</span>
                                        <?php elseif (!empty($d['visible_to_client'])): ?>
                                            <span style="background:#d1e7dd;color:#0f5132;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:bold">CLIENTE</span>
                                        <?php else: ?>
                                            <span style="background:#f1f5f9;color:#475569;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:bold">INTERNO</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>

                                <td style="padding:10px;vertical-align:top;text-align:center">
                                    <a href="download-documento.php?id=<?= (int)$d['id'] ?>" 
                                       style="padding:6px 14px;background:#183049;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:13px;display:inline-block">
                                        Download
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php portal_footer(); ?>