<?php
declare(strict_types=1);

/*
 * TSP Portal - conexão MySQL
 * Preencha SOMENTE a senha abaixo com a senha do usuário MySQL.
 */
$dbHost = 'localhost';
$dbName = 'u872285189_tsp_portal';
$dbUser = 'u872285189_tsp_portal';
$dbPass = 'Tgirotsp2026*';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('TSP Portal - erro DB: '.$e->getMessage());
    http_response_code(500);
    exit('Não foi possível conectar ao banco de dados.');
}
