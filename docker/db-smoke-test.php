<?php

declare(strict_types=1);

$host = getenv('POSTGRES_HOST') ?: 'postgres';
$port = getenv('POSTGRES_PORT') ?: '5432';
$db = getenv('POSTGRES_DB') ?: 'blackops';
$user = getenv('POSTGRES_USER') ?: 'blackops';
$password = getenv('POSTGRES_PASSWORD') ?: 'blackops';

$dsn = "pgsql:host={$host};port={$port};dbname={$db}";

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $version = $pdo->query('SHOW server_version')->fetchColumn();
    echo "DB_CONNECTION_OK server_version={$version}\n";
    exit(0);
} catch (PDOException $e) {
    echo "DB_CONNECTION_FAIL " . $e->getMessage() . "\n";
    exit(1);
}