<?php

declare(strict_types=1);

if (!function_exists('juridiq_key')) {
    function juridiq_key(): string
    {
        $envPath = __DIR__ . '/../../../.env';
        if (function_exists('loadEnv')) {
            $config = loadEnv($envPath);
            return trim((string)($config['JURIDIQ_API_KEY'] ?? ''));
        }
        return getenv('JURIDIQ_API_KEY') ?: '';
    }
}

if (!function_exists('juridiq_request')) {
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
            CURLOPT_TIMEOUT        => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno    = curl_errno($ch);
        $curlError    = curl_error($ch);
        // curl_close($ch);

        if ($responseBody === false) {
            if ($curlErrno === CURLE_OPERATION_TIMEDOUT) {
                error_log("Juridiq Timeout [{$timeoutSeconds}s] na URL: {$url}");
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
}

if (!function_exists('sync_juridiq_movements')) {
    function sync_juridiq_movements(PDO $pdo, int $caseId, array $movements, ?int $authorUserId = null): int
    {
        if (empty($movements)) {
            return 0;
        }

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
}