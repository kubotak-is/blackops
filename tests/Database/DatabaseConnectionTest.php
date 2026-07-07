<?php

declare(strict_types=1);

namespace BlackOps\Tests\Database;

use PHPUnit\Framework\TestCase;

final class DatabaseConnectionTest extends TestCase
{
    public function testConnection(): void
    {
        $host = (string) (getenv('POSTGRES_HOST') ?: 'postgres');
        $port = (string) (getenv('POSTGRES_PORT') ?: '5432');
        $db = (string) (getenv('POSTGRES_DB') ?: 'blackops');
        $user = (string) (getenv('POSTGRES_USER') ?: 'blackops');
        $password = (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops');

        $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
        $pdo = new \PDO($dsn, $user, $password, [
            \PDO::ATTR_TIMEOUT => 5,
        ]);

        $version = $pdo->query('SHOW server_version')->fetchColumn();

        self::assertNotEmpty($version);
    }
}
