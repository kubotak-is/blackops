<?php

declare(strict_types=1);

namespace App\Tests\Board;

use App\Domain\Notification\Notification;
use App\Infrastructure\Persistence\DoctrineNotificationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class NotificationRepositoryTest extends TestCase
{
    private const string ALICE = '019b1000-0000-7000-8000-000000000001';

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
        $this->connection->executeStatement('TRUNCATE public.board_notifications');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('TRUNCATE public.board_notifications');
        $this->connection->close();
    }

    public function testRecipientLatestFirstAndChildReplayAreIdempotent(): void
    {
        $repository = new DoctrineNotificationRepository($this->connection);
        $first = $this->notification('019b6000-0000-7000-8000-000000000001', '019b7000-0000-7000-8000-000000000001');
        $second = $this->notification('019b6000-0000-7000-8000-000000000002', '019b7000-0000-7000-8000-000000000002');
        self::assertTrue($repository->saveIfAbsent($first));
        self::assertFalse($repository->saveIfAbsent($first));
        self::assertTrue($repository->saveIfAbsent($second));
        self::assertSame(
            [$second->id, $first->id],
            array_map(static fn(Notification $item): string => $item->id, $repository->listForRecipient(self::ALICE)),
        );
        self::assertSame([], $repository->listForRecipient('019b1000-0000-7000-8000-000000000099'));
    }

    private function notification(string $id, string $operation): Notification
    {
        return new Notification(
            $id,
            self::ALICE,
            '019b2000-0000-7000-8000-000000000001',
            '019b3000-0000-7000-8000-000000000001',
            $operation,
            new DateTimeImmutable(
                $operation === '019b7000-0000-7000-8000-000000000001' ? '2026-07-24T00:00:00Z' : '2026-07-24T00:01:00Z',
            ),
        );
    }
}
