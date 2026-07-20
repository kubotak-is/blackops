<?php

declare(strict_types=1);

namespace App\Tests\Board;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class DigestMigrationTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $_ENV['POSTGRES_HOST'] ?? 'postgres',
            'port' => (int) ($_ENV['POSTGRES_PORT'] ?? '5432'),
            'dbname' => $_ENV['POSTGRES_DB'] ?? 'community_board',
            'user' => $_ENV['POSTGRES_USER'] ?? 'blackops',
            'password' => $_ENV['POSTGRES_PASSWORD'] ?? 'blackops',
        ]);
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    public function testDigestSchemaHasOwnershipChecksIndexesAndNoUserWeekUniqueness(): void
    {
        $constraints = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT conname, contype, pg_get_constraintdef(oid) AS definition
            FROM pg_constraint
            WHERE conrelid = 'public.board_digests'::regclass
            ORDER BY conname
            SQL);
        $definitions = implode("\n", array_column($constraints, 'definition'));
        self::assertStringContainsString('^[0-9]{4}-W(0[1-9]|[1-4][0-9]|5[0-3])$', $definitions);
        self::assertStringContainsString('char_length((content)::text) >= 1', $definitions);
        self::assertStringContainsString('char_length((content)::text) <= 255', $definitions);
        self::assertStringContainsString('post_count >= 0', $definitions);
        self::assertStringContainsString('comment_count >= 0', $definitions);
        self::assertStringContainsString('ON DELETE RESTRICT', $definitions);
        self::assertNotContains('u', array_column($constraints, 'contype'));

        $indexes = $this->connection->fetchFirstColumn(<<<'SQL'
            SELECT indexname FROM pg_indexes
            WHERE schemaname = 'public'
              AND tablename IN ('board_digests', 'board_comments')
            SQL);
        self::assertContains('board_digests_requested_user_created_index', $indexes);
        self::assertContains('board_comments_created_at_index', $indexes);
    }
}
