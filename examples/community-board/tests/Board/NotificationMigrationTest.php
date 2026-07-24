<?php

declare(strict_types=1);

namespace App\Tests\Board;

use App\Migrations\Version20260724000100;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Query\Query;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class NotificationMigrationTest extends TestCase
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

    public function testNotificationSchemaHasDeliveryUniquenessAndLatestRecipientIndexWithoutSourceForeignKeys(): void
    {
        $constraints = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT conname, contype, pg_get_constraintdef(oid) AS definition
            FROM pg_constraint
            WHERE conrelid = 'public.board_notifications'::regclass
            ORDER BY conname
            SQL);
        $definitions = implode("\n", array_column($constraints, 'definition'));
        self::assertStringContainsString('delivery_operation_id', $definitions);
        self::assertSame(1, count(array_filter($constraints, static fn(array $row): bool => $row['contype'] === 'u')));
        $foreignKeys = implode("\n", array_column(
            array_filter($constraints, static fn(array $row): bool => $row['contype'] === 'f'),
            'definition',
        ));
        self::assertStringNotContainsString('source_post_id', $foreignKeys);
        self::assertStringNotContainsString('source_comment_id', $foreignKeys);

        $indexes = $this->connection->fetchFirstColumn(
            "SELECT indexname FROM pg_indexes WHERE schemaname = 'public' AND tablename = 'board_notifications'",
        );
        self::assertContains('board_notifications_recipient_created_index', $indexes);
    }

    public function testMigrationDownRemovesOnlyNotificationTableAndIndex(): void
    {
        require_once dirname(__DIR__, 2) . '/migrations/Version20260724000100.php';
        $up = new Version20260724000100($this->connection, new NullLogger());
        $up->up(new Schema());
        $upStatements = $this->statements($up);
        self::assertCount(2, $upStatements);
        self::assertStringContainsString('CREATE TABLE public.board_notifications', $upStatements[0]);
        self::assertStringContainsString('board_notifications_delivery_operation_unique', $upStatements[0]);
        self::assertSame(
            'CREATE INDEX board_notifications_recipient_created_index ON public.board_notifications (recipient_user_id, created_at DESC, id DESC)',
            $upStatements[1],
        );

        $down = new Version20260724000100($this->connection, new NullLogger());
        $down->down(new Schema());
        self::assertSame(
            [
                'DROP INDEX public.board_notifications_recipient_created_index',
                'DROP TABLE public.board_notifications',
            ],
            $this->statements($down),
        );
    }

    /** @return list<string> */
    private function statements(Version20260724000100 $migration): array
    {
        return array_map(static fn(Query $query): string => $query->getStatement(), $migration->getSql());
    }
}
