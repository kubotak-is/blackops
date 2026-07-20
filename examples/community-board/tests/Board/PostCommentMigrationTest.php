<?php

declare(strict_types=1);

namespace App\Tests\Board;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class PostCommentMigrationTest extends TestCase
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

    public function testSchemaHasLengthChecksIndexesAndDeletionRules(): void
    {
        $definitions = $this->connection->fetchFirstColumn(<<<'SQL'
            SELECT pg_get_constraintdef(oid)
            FROM pg_constraint
            WHERE conrelid IN ('public.board_posts'::regclass, 'public.board_comments'::regclass)
            ORDER BY conname
            SQL);
        $joined = implode("\n", array_map(static fn(mixed $value): string => (string) $value, $definitions));
        self::assertStringContainsString('char_length((title)::text) >= 1', $joined);
        self::assertStringContainsString('char_length((title)::text) <= 120', $joined);
        self::assertStringContainsString('char_length(body) >= 1', $joined);
        self::assertStringContainsString('char_length(body) <= 10000', $joined);
        self::assertStringContainsString('char_length(body) <= 2000', $joined);

        self::assertSame('CASCADE', $this->deleteRule('board_comments_post_foreign'));
        self::assertSame('RESTRICT', $this->deleteRule('board_comments_author_foreign'));
        self::assertSame('RESTRICT', $this->deleteRule('board_posts_author_foreign'));

        $indexes = $this->connection->fetchFirstColumn(<<<'SQL'
            SELECT indexname
            FROM pg_indexes
            WHERE schemaname = 'public'
              AND tablename IN ('board_posts', 'board_comments')
            ORDER BY indexname
            SQL);
        foreach ([
            'board_posts_feed_index',
            'board_posts_author_index',
            'board_comments_detail_index',
            'board_comments_author_index',
        ] as $index) {
            self::assertContains($index, $indexes);
        }
    }

    private function deleteRule(string $constraint): string
    {
        $rule = $this->connection->fetchOne(
            <<<'SQL'
                SELECT delete_rule
                FROM information_schema.referential_constraints
                WHERE constraint_schema = 'public'
                  AND constraint_name = :constraint
                SQL,
            ['constraint' => $constraint],
        );
        self::assertIsString($rule);

        return $rule;
    }
}
