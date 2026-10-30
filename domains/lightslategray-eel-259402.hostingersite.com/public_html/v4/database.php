<?php
declare(strict_types=1);

$envPath = __DIR__ . '/../../.env';

if (!file_exists($envPath)) {
    http_response_code(500);
    exit('Arquivo de configuração (.env) não encontrado.');
}

// Função personalizada para ler .env com suporte a caracteres especiais
function loadEnv(string $filePath): array {
    $env = [];
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'"); // Remove espaços e aspas externas
            $env[$key] = $value;
        }
    }
    return $env;
}

$env = loadEnv($envPath);

$dbHost = $env['DB_HOST'] ?? '127.0.0.1';
$dbName = $env['DB_NAME'] ?? '';
$dbUser = $env['DB_USER'] ?? '';
$dbPass = $env['DB_PASS'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    // Conexão bem-sucedida!
} catch (PDOException $e) {
    error_log('TSP Portal - Erro DB: ' . $e->getMessage());
    http_response_code(500);
    exit('Erro na conexão com o banco de dados: ' . $e->getMessage());
}