<?php

declare(strict_types=1);
require_once __DIR__ . '/../api/auth.php';
require_admin();

$adminUserId = (int)(
    $_SESSION['user_id'] 
    ?? $_SESSION['admin_id'] 
    ?? $_SESSION['id'] 
    ?? $_SESSION['usuario_id'] 
    ?? $_SESSION['user']['id'] 
    ?? 0
);

// Converte 0 para null explicitamente
$authorUserId = $adminUserId > 0 ? $adminUserId : null;

if (empty($_SESSION['juridiq_csrf'])) $_SESSION['juridiq_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['juridiq_csrf'];
$result = null;
$preview = [];
$limit = 10;

function sync_juridiq_movements(PDO $pdo, int $caseId, array $movements, ?int $authorUserId = null): int
{
    if (empty($movements)) {
        return 0;
    }

    // O FORÇAMENTO DE ATUALIZAÇÃO OCORRE NO ON DUPLICATE KEY UPDATE
    $stmt = $pdo->prepare("
        INSERT INTO case_updates (
            case_id, author_user_id, update_type, title, client_message,
            internal_note, visible_to_client, client_action_required, status,
            source, external_reference, event_date, published_at, created_at, updated_at
        ) VALUES (
            ?, ?, 'judicial', ?, ?,
            'Importado via API Juridiq', 0, 0, 'pending_review',
            'juridiq', ?, ?, NULL, NOW(), NOW()
        )
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            client_message = VALUES(client_message),
            event_date = VALUES(event_date),
            author_user_id = COALESCE(VALUES(author_user_id), author_user_id),
            updated_at = NOW()
            ");

    $synced = 0;

    foreach ($movements as $mov) {
        if (!is_array($mov)) continue;

        $externalRef = trim((string)($mov['id'] ?? ''));
        if ($externalRef === '') continue;

        $conteudo = trim((string)($mov['conteudo'] ?? ''));
        $tagName  = $mov['tagsDetails'][0]['name'] ?? null;
        $rawTitle = $tagName ?: ($conteudo !== '' ? mb_substr($conteudo, 0, 100) : 'Andamento Judicial');
        $title    = mb_substr($rawTitle, 0, 255);

        $rawDate   = $mov['data'] ?? null;
        $eventDate = date('Y-m-d H:i:s');
        if ($rawDate) {
            $dt = DateTime::createFromFormat(DateTimeInterface::ISO8601, $rawDate) ?: new DateTime($rawDate);
            if ($dt) {
                $eventDate = $dt->format('Y-m-d H:i:s');
            }
        }

        $stmt->execute([
            $caseId,
            $authorUserId ?: null,
            $title,
            $conteudo !== '' ? $conteudo : null,
            $externalRef,
            $eventDate
        ]);

        $synced++;
    }

    return $synced;
}

function juridiq_escape(?string $s): string 
{
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Executa requisições HTTP para a API do Juridiq com tratamento completo de erros.
 *
 * @throws RuntimeException
 */
function juridiq_request(string $apiKey, string $url, int $timeoutSeconds = 30): array
{
    if (trim($apiKey) === '') {
        throw new RuntimeException('Chave de API do Juridiq não configurada.');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'x-juridiq-api-key: ' . $apiKey,
            'Accept: application/json',
            'User-Agent: TSP-Portal/4.0'
        ],
        CURLOPT_TIMEOUT        => $timeoutSeconds, // Timeout dinâmico
        CURLOPT_CONNECTTIMEOUT => 30,              // Limite de 30s para conectar ao servidor
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $responseBody = curl_exec($ch);
    $httpCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno    = curl_errno($ch);
    $curlError    = curl_error($ch);
    curl_close($ch);

    // TRATAMENTO DE TIMEOUT (cURL Error 28) OU QUEDA DE REDE
    if ($responseBody === false) {
        if ($curlErrno === CURLE_OPERATION_TIMEDOUT) {
            error_log("Juridiq Timeout [{$timeoutSeconds}s] na URL: {$url}");
            // Retorna array vazio para indicar que o registro não foi localizado no tempo limite
            return [];
        }
        error_log("Juridiq cURL Error [{$curlErrno}]: {$curlError} - URL: {$url}");
        throw new RuntimeException('Falha de comunicação com o servidor do Juridiq.');
    }

    $data = json_decode((string)$responseBody, true);

    if ($httpCode === 404) {
        return [];
    }

    if ($httpCode >= 400) {
        $apiMessage = $data['message'] ?? $data['error'] ?? 'Erro na API Juridiq.';
        throw new RuntimeException("Erro na API Juridiq (HTTP {$httpCode}): {$apiMessage}");
    }

    return is_array($data) ? $data : [];
}

function is_valid_cnj_search(string $term): bool
{
    $digitsOnly = preg_replace('/\D/', '', $term);
    return strlen($digitsOnly) >= 15;
}

function juridiq_key(): string
{
    $envPath = __DIR__ . '/../../../.env'; // Ajuste para .env no ambiente final

    $config = loadEnv($envPath);

    return trim((string)($config['JURIDIQ_API_KEY'] ?? ''));
}

function juridiq_people(string $key, int $limit): array
{
    $url = 'https://api.juridiq.com.br/person/?' . http_build_query(['page' => 1, 'limit' => $limit]);
    $response = juridiq_request($key, $url);

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

// 1. Consultar os processos vinculados a um ID de pessoa/cliente
function juridiq_lawsuits_by_person(string $key, string $personId): array
{
    $url = 'https://api.juridiq.com.br/lawSuit/?' . http_build_query([
        'personId' => $personId,
        'limit'    => 50
    ]);
    return juridiq_request($key, $url);
}

/**
 * Consulta os detalhes de um processo específico pelo ID do Juridiq.
 */
function juridiq_lawsuit_by_id(string $key, string $lawsuitId): array
{
    $lawsuitId = trim($lawsuitId);
    if ($lawsuitId === '') {
        return [];
    }

    $url = 'https://api.juridiq.com.br/lawSuit/' . urlencode($lawsuitId);
    // Executa com timeout de 30 segundos
    $response = juridiq_request($key, $url, 30);

    $lawsuit = $response['data'] ?? $response ?? [];
    if (isset($lawsuit[0]) && is_array($lawsuit[0])) {
        $lawsuit = $lawsuit[0];
    }

    return (is_array($lawsuit) && !empty($lawsuit['id'])) ? $lawsuit : [];
}

// 2. Detalhar um processo específico pelo ID do Juridiq
function juridiq_lawsuit_detail(string $key, string $lawSuitId): array
{
    $url = 'https://api.juridiq.com.br/lawSuit/' . urlencode($lawSuitId);
    return juridiq_request($key, $url);
}

// 3. Obter as movimentações/andamentos de um processo
function juridiq_movements(string $key, string $lawSuitId, int $page = 1): array
{
    $url = 'https://api.juridiq.com.br/lawSuit/movements/' . urlencode($lawSuitId) . '?' . http_build_query(['page' => $page, 'limit' => 50]);
    return juridiq_request($key, $url);
}

/**
 * Busca um processo na API do Juridiq pelo número CNJ.
 */
function juridiq_lawsuit_by_cnj(string $key, string $cnj): array
{
    $cnjRaw = trim($cnj);
    if ($cnjRaw === '') {
        return [];
    }

    // Consulta direta via processNumber com 30s de timeout
    $url = 'https://api.juridiq.com.br/lawSuit/?' . http_build_query([
        'processNumber' => $cnjRaw,
        'limit'         => 5
    ]);
    
    $response = juridiq_request($key, $url, 30);
    $items    = $response['data'] ?? $response ?? [];

    if (!is_array($items) || empty($items)) {
        return [];
    }

    $digitsInput = preg_replace('/\D/', '', $cnjRaw);

    foreach ($items as $item) {
        if (!is_array($item)) continue;

        $itemCnj    = (string)($item['processNumber'] ?? $item['code'] ?? '');
        $digitsItem = preg_replace('/\D/', '', $itemCnj);

        if ($itemCnj === $cnjRaw || ($digitsInput !== '' && $digitsInput === $digitsItem)) {
            return $item;
        }
    }

    return $items[0] ?? [];
}

function upsert_juridiq_case(PDO $pdo, int $clientId, array $juridiqData, int $integrationId = 1): int
{
    $juridiqId = trim((string)($juridiqData['id'] ?? ''));
    if ($juridiqId === '') {
        throw new InvalidArgumentException('Impossível gravar: ID do Juridiq (external_id) ausente no payload.');
    }

    // TRATAMENTO DE DADOS INCOMPLETOS (Fallbacks inteligentes)
    
    // 1. Número CNJ
    $caseNumber = trim((string)(
        $juridiqData['processNumber'] 
        ?? $juridiqData['code'] 
        ?? $juridiqData['cnjNumber'] 
        ?? "JUR-{$juridiqId}" // Fallback caso o CNJ venha ausente
    ));

    // 2. Área Jurídica
    $tagName   = $juridiqData['tags'][0]['name'] ?? $juridiqData['area'] ?? null;
    $legalArea = $tagName ? mb_substr(trim((string)$tagName), 0, 120) : 'Não informada';

    // 3. Título do Processo
    $rawTitle = $juridiqData['title'] ?? $juridiqData['name'] ?? null;
    if (!empty($rawTitle)) {
        $title = mb_substr(trim((string)$rawTitle), 0, 255);
    } else {
        $title = mb_substr("Processo {$legalArea} - {$caseNumber}", 0, 255);
    }

    // 4. Tribunal e Vara
    $courtRaw = $juridiqData['instances'][0]['court'] ?? $juridiqData['court'] ?? null;
    $court    = $courtRaw ? mb_substr(trim((string)$courtRaw), 0, 180) : 'Tribunal não informado';

    // 5. Instância e Estado
    $instanceStr = isset($juridiqData['currentInstance']) ? (string)$juridiqData['currentInstance'] : '1';
    $state       = !empty($juridiqData['state']) 
        ? strtoupper(mb_substr((string)$juridiqData['state'], 0, 2)) 
        : ((str_contains((string)$court, 'São Paulo') || str_contains($caseNumber, '.8.26.')) ? 'SP' : null);

    // 6. Status
    $rawStatus = strtolower(trim((string)($juridiqData['status'] ?? 'active')));
    $status    = match ($rawStatus) {
        'active', 'cadastrado' => 'Em andamento',
        'archived', 'arquivado' => 'Arquivado',
        'suspended', 'suspenso' => 'Suspenso',
        default => mb_substr($rawStatus ?: 'Em andamento', 0, 100)
    };

    // 7. Data da última atualização
    $lastMovementRaw = $juridiqData['lastMovementDate'] ?? $juridiqData['updatedAt'] ?? null;
    $lastUpdate      = date('Y-m-d H:i:s');
    if ($lastMovementRaw) {
        try {
            $dt = DateTime::createFromFormat(DateTimeInterface::ISO8601, $lastMovementRaw) ?: new DateTime($lastMovementRaw);
            if ($dt) {
                $lastUpdate = $dt->format('Y-m-d H:i:s');
            }
        } catch (Throwable $eDate) {
            // Mantém a data/hora atual se a string de data da API for inválida
        }
    }

    $pdo->beginTransaction();

    try {
        $stmtCase = $pdo->prepare("
            INSERT INTO cases (
                client_id, external_id, case_number, title, legal_area, 
                court, court_unit, instance, state, status, 
                last_judicial_update_at, active, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, NULL, ?, ?, ?, 
                ?, 1, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                client_id = VALUES(client_id),
                external_id = VALUES(external_id),
                title = VALUES(title),
                legal_area = VALUES(legal_area),
                court = VALUES(court),
                instance = VALUES(instance),
                state = VALUES(state),
                status = VALUES(status),
                last_judicial_update_at = VALUES(last_judicial_update_at),
                updated_at = NOW()
        ");

        $stmtCase->execute([
            $clientId, $juridiqId, $caseNumber, $title, $legalArea,
            $court, $instanceStr, $state, $status, $lastUpdate
        ]);

        $caseId = (int)$pdo->lastInsertId();
        if ($caseId === 0) {
            $stmtFind = $pdo->prepare("SELECT id FROM cases WHERE external_id = ? OR case_number = ? LIMIT 1");
            $stmtFind->execute([$juridiqId, $caseNumber]);
            $caseId = (int)$stmtFind->fetchColumn();
        }

        $stmtIntegration = $pdo->prepare("
            INSERT INTO case_integrations (
                case_id, integration_id, external_case_id, monitoring_enabled, 
                last_sync_at, last_success_at, sync_status, last_error, created_at, updated_at
            ) VALUES (
                ?, ?, ?, 1, 
                NOW(), NOW(), 'success', NULL, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                external_case_id = VALUES(external_case_id),
                last_sync_at = NOW(),
                last_success_at = NOW(),
                sync_status = 'success',
                last_error = NULL,
                updated_at = NOW()
        ");

        $stmtIntegration->execute([$caseId, $integrationId, $juridiqId]);

        $pdo->commit();
        return $caseId;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Erro na upsert_juridiq_case: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Consulta uma pessoa específica no Juridiq pelo seu UUID.
 */
function juridiq_person_by_id(string $key, string $personId): array
{
    $personId = trim($personId);
    if ($personId === '') {
        return [];
    }

    $url = 'https://api.juridiq.com.br/person/' . urlencode($personId);
    $response = juridiq_request($key, $url);

    // Extrai o payload caso a API devolva os dados aninhados no nó 'data'
    $person = $response['data'] ?? $response ?? [];
    
    // Caso a API devolva uma lista com um elemento
    if (isset($person[0]) && is_array($person[0])) {
        $person = $person[0];
    }

    return is_array($person) ? $person : [];
}

/**
 * Insere ou atualiza um cliente local a partir dos dados retornados da API do Juridiq.
 */
function upsert_juridiq_client(PDO $pdo, array $personData): int
{
    $juridiqPersonId = trim((string)($personData['id'] ?? ''));
    if ($juridiqPersonId === '') {
        throw new InvalidArgumentException('ID da pessoa (Juridiq) ausente no payload.');
    }

    $name     = mb_substr(trim((string)($personData['name'] ?? 'Cliente Juridiq')), 0, 255);
    $document = !empty($personData['document']) ? preg_replace('/\D/', '', (string)$personData['document']) : null;
    $email    = !empty($personData['email']) ? mb_substr(trim((string)$personData['email']), 0, 180) : null;
    $phone    = !empty($personData['phone']) ? mb_substr(trim((string)$personData['phone']), 0, 50) : null;

    $pdo->beginTransaction();

    try {
        // 1. Verifica se o cliente já existe pelo juridiq_person_id ou pelo documento (CPF/CNPJ)
        $stmtCheck = $pdo->prepare("
            SELECT id FROM clients 
            WHERE juridiq_person_id = ? 
               OR (document IS NOT NULL AND document = ? AND document != '')
            LIMIT 1
        ");
        $stmtCheck->execute([$juridiqPersonId, $document]);
        $existingId = (int)$stmtCheck->fetchColumn();

        if ($existingId > 0) {
            // Atualiza o vínculo e os dados cadastrais
            $stmtUpdate = $pdo->prepare("
                UPDATE clients SET 
                    juridiq_person_id = ?,
                    name = IF(name IS NULL OR name = '', ?, name),
                    email = COALESCE(?, email),
                    phone = COALESCE(?, phone),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([$juridiqPersonId, $name, $email, $phone, $existingId]);
            $clientId = $existingId;
        } else {
            // Cria um novo registo na tabela clients
            $stmtInsert = $pdo->prepare("
                INSERT INTO clients (
                    juridiq_person_id, name, document, email, phone, active, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, 1, NOW(), NOW()
                )
            ");
            $stmtInsert->execute([$juridiqPersonId, $name, $document, $email, $phone]);
            $clientId = (int)$pdo->lastInsertId();
        }

        $pdo->commit();
        return $clientId;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
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
                    juridiq_request($key, 'https://api.juridiq.com.br/person/?' . http_build_query(['page' => 1, 'limit' => 1]));
                    $result = ['ok' => true, 'message' => 'Conexão validada. Nenhum dado importado.'];
                
                } elseif ($action === 'preview_juridiq') {
                    $preview = juridiq_people($key, $limit);
                    $result = ['ok' => true, 'message' => 'Prévia de ' . count($preview) . ' pessoa(s).'];

                } elseif ($action === 'import_juridiq') {
                    // ... Lógica de importar pessoa existente ...

                } elseif ($action === 'sync_lawsuits') {
                    // ... Lógica de sincronizar processos ...

                } elseif ($action === 'preview_lawsuit') {
                    $searchInput = trim((string)($_POST['juridiq_lawsuit_by_cnj'] ?? ''));

                    if ($searchInput === '') {
                        $result = ['ok' => false, 'message' => 'Informe o número CNJ'];
                    } else {
                        // 1. VALIDAÇÃO FAIL-FAST: Bloqueia se o CNJ estiver incompleto
                        if (!is_valid_cnj_search($searchInput)) {
                            $result = [
                                'ok'      => false,
                                'message' => 'Número CNJ incompleto. Digite o número CNJ completo (ex: 0007092-25.2025.8.26.0496).'
                            ];
                            return;
                        }

                        // 2. Busca estritamente por número CNJ
                        $lawsuit = juridiq_lawsuit_by_cnj($key, $searchInput);
                    }

                        if (empty($lawsuit['id'])) {
                                $result = [
                                    'ok'      => false, 
                                    'message' => "Processo não encontrado na API do Juridiq para '{$searchInput}'."
                                ];
                        } else {
                            $matchedClient = null;
                            $persons = $lawsuit['persons'] ?? [];
                            $unmatchedPersons = [];
                    
                            if (!empty($persons) && is_array($persons)) {
                                $juridiqPersonIds = array_filter(array_column($persons, 'id'));
                                
                                if (!empty($juridiqPersonIds)) {
                                    // Tenta localizar cliente já existente no banco local
                                    $inClause = implode(',', array_fill(0, count($juridiqPersonIds), '?'));
                                    $stmt = $pdo->prepare("SELECT id, name, document, juridiq_person_id FROM clients WHERE juridiq_person_id IN ($inClause) LIMIT 1");
                                    $stmt->execute(array_values($juridiqPersonIds));
                                    $matchedClient = $stmt->fetch() ?: null;
                                }
                    
                                // Se não encontrou vínculo local, separa as pessoas do payload para exibição
                                if (!$matchedClient) {
                                    foreach ($persons as $p) {
                                        if (!is_array($p) || empty($p['id'])) continue;
                                        $unmatchedPersons[] = [
                                            'id'     => $p['id'],
                                            'name'   => $p['name'] ?? 'Nome não informado',
                                            'origin' => $p['personOrigin'] ?? $p['origin'] ?? 'Parte'
                                        ];
                                    }
                                }
                            }
                    
                            $result = [
                                'ok'                => true,
                                'type'              => 'lawsuit_preview',
                                'data'              => $lawsuit,
                                'matched_client'    => $matchedClient,
                                'unmatched_persons' => $unmatchedPersons
                            ];
                        }
                } elseif ($action === 'confirm_import_lawsuit') {
                    $lawsuitId = trim((string)($_POST['juridiq_lawsuit_id'] ?? ''));
                    $clientId  = (int)($_POST['client_id'] ?? 0);
                    $adminUserId = (int)(
                        $_SESSION['user_id'] 
                        ?? $_SESSION['admin_id'] 
                        ?? $_SESSION['id'] 
                        ?? $_SESSION['usuario_id'] 
                        ?? $_SESSION['user']['id'] 
                        ?? 0
                    );
                    
                    // Converte 0 para null explicitamente
                    $authorUserId = $adminUserId > 0 ? $adminUserId : null;

                    if ($lawsuitId === '') {
                        $result = ['ok' => false, 'message' => 'ID do processo inválido para importação.'];
                    } elseif ($clientId <= 0) {
                        $result = ['ok' => false, 'message' => 'Selecione um cliente válido para vincular ao processo.'];
                    } else {
                        $lawsuit = juridiq_lawsuit_by_id($key, $lawsuitId);

                        // TRATAMENTO: Processo não encontrado na releitura de confirmação
                        if (empty($lawsuit['id'])) {
                            $result = ['ok' => false, 'message' => 'Não foi possível recuperar os dados atualizados do processo para gravação.'];
                        } else {
                            try {
                                $caseId = upsert_juridiq_case($pdo, $clientId, $lawsuit);

                                // Tenta importar as movimentações iniciais sem interromper a gravação do processo em caso de falha pontual
                                $movCount = 0;
                                try {
                                    $urlMov = 'https://api.juridiq.com.br/lawSuit/movements/' . urlencode($lawsuitId) . '?limit=50';
                                    $movData = juridiq_request($key, $urlMov);
                                    $movements = $movData['data'] ?? $movData ?? [];
                                    if (is_array($movements) && !empty($movements)) {
                                        $movCount = sync_juridiq_movements($pdo, $caseId, $movements, $authorUserId);
                                    }
                                } catch (Throwable $eMov) {
                                    error_log('Aviso: Movimentações não importadas na gravação inicial: ' . $eMov->getMessage());
                                }

                                $result = [
                                    'ok'      => true,
                                    'message' => "Processo #{$caseId} importado e vinculado com sucesso! ({$movCount} movimentação(ões) sincronizada(s))."
                                ];
                            } catch (Throwable $e) {
                                $result = ['ok' => false, 'message' => $e->getMessage()];
                            }
                        }
                    }
                } elseif ($action === 'preview_person') {
                    $personId = trim((string)($_POST['juridiq_person_id'] ?? ''));
                    if ($personId === '') {
                        throw new RuntimeException('Informe o ID (UUID) da pessoa no Juridiq.');
                    }

                    $person = juridiq_person_by_id($key, $personId);

                    if (empty($person['id'])) {
                        throw new RuntimeException("Pessoa não encontrada na API do Juridiq para o ID informado.");
                    }

                    // Tenta localizar cliente local pelo UUID do Juridiq ou pelo Documento (CPF/CNPJ)
                    $docClean = !empty($person['document']) ? preg_replace('/\D/', '', (string)$person['document']) : '';

                    $stmtCheck = $pdo->prepare("
                        SELECT id, name, document, juridiq_person_id 
                        FROM clients 
                        WHERE juridiq_person_id = ? 
                           OR (document IS NOT NULL AND document != '' AND document = ?)
                        LIMIT 1
                    ");
                    $stmtCheck->execute([$person['id'], $docClean]);
                    $existingLocalClient = $stmtCheck->fetch() ?: null;

                    $result = [
                        'ok'             => true,
                        'type'           => 'person_preview',
                        'data'           => $person,
                        'existing_local' => $existingLocalClient
                    ];

                } elseif ($action === 'confirm_import_person') {
                    $personId = trim((string)($_POST['juridiq_person_id'] ?? ''));
                    if ($personId === '') {
                        throw new RuntimeException('ID da pessoa inválido.');
                    }

                    $person = juridiq_person_by_id($key, $personId);
                    if (empty($person['id'])) {
                        throw new RuntimeException('Não foi possível recuperar os dados da pessoa para confirmação.');
                    }

                    $clientId = upsert_juridiq_client($pdo, $person);

                    $result = [
                        'ok'      => true,
                        'message' => "Cliente '{$person['name']}' importado/vinculado com sucesso! (ID Local: #{$clientId})"
                    ];
                } else {
                    // Captura qualquer ação não mapeada para evitar que $result fique indefinido
                    $result = ['ok' => false, 'message' => "Ação não reconhecida: '{$action}'."];
                }

            } catch (Throwable $e) {
                error_log('Juridiq integration: ' . $e->getMessage());
                $result = ['ok' => false, 'message' => $e->getMessage()];
            } catch (InvalidArgumentException $eAttr) {
                // TRATAMENTO: Dados insuficientes para gravação no banco
                $result = ['ok' => false, 'message' => 'Falha de validação nos dados do processo: ' . $eAttr->getMessage()];
            } catch (Throwable $eGlobal) {
                $result = ['ok' => false, 'message' => 'Erro interno ao gravar o processo no banco de dados.'];
            }
        }
    }
}