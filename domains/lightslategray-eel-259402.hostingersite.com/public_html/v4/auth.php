<?php

declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/database.php';
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}
function require_login(): void
{
    if (!current_user()) {
        header('Location: index.php');
        exit;
    }
}
function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        header('Location: dashboard.php');
        exit;
    }
}
function flash_set(string $t, string $m): void
{
    $_SESSION['flash'] = ['type' => $t, 'message' => $m];
}
function flash_get(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
