<?php

declare(strict_types=1);

namespace App\Tests\Board;

use App\Domain\Board\GeneratedDigest;
use App\Domain\Board\IsoWeek;
use App\Infrastructure\Persistence\DoctrineBoardRepository;
use App\Infrastructure\Persistence\DoctrineDigestRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class DoctrineDigestRepositoryTest extends TestCase
{
    private const string ALICE = '019b1000-0000-7000-8000-000000000001';
    private const string BOB = '019b1000-0000-7000-8000-000000000002';
    private const string POST = '019b2000-0000-7000-8000-000000000001';
    private const string COMMENT = '019b3000-0000-7000-8000-000000000001';
    private const string FIRST = '019b5000-0000-7000-8000-000000000001';
    private const string SECOND = '019b5000-0000-7000-8000-000000000002';

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
        $this->connection->executeStatement(
            'TRUNCATE public.board_digests, public.board_comments, public.board_posts, public.board_sessions, public.board_users CASCADE',
        );
        foreach ([[self::ALICE, 'alice'], [self::BOB, 'bob']] as [$id, $name]) {
            $this->connection->insert('public.board_users', [
                'id' => $id,
                'email_canonical' => $name . '@example.test',
                'email_display' => $name . '@example.test',
                'display_name' => ucfirst($name),
                'password_hash' => 'not-used',
                'created_at' => '2026-07-20 00:00:00+00:00',
                'updated_at' => '2026-07-20 00:00:00+00:00',
            ]);
        }
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        $this->connection->executeStatement(
            'TRUNCATE public.board_digests, public.board_comments, public.board_posts, public.board_sessions, public.board_users CASCADE',
        );
        $this->connection->close();
    }

    public function testSnapshotUsesUtcHalfOpenBoundariesAndExcludesHardDeletes(): void
    {
        $board = new DoctrineBoardRepository($this->connection);
        $digests = new DoctrineDigestRepository($this->connection);
        $week = IsoWeek::fromString('2026-W30');
        $board->createPost(self::POST, self::ALICE, 'Boundary', 'Body', $week->startsAt());
        $board->createComment(
            self::COMMENT,
            self::POST,
            self::BOB,
            'Comment',
            $week->endsAt()->modify('-1 microsecond'),
        );
        $this->connection->insert('public.board_posts', [
            'id' => '019b2000-0000-7000-8000-000000000002',
            'author_id' => self::ALICE,
            'title' => 'Outside',
            'body' => 'Outside',
            'created_at' => $week->endsAt()->format('Y-m-d H:i:sP'),
            'updated_at' => $week->endsAt()->format('Y-m-d H:i:sP'),
        ]);

        self::assertSame([1, 1], array_values((array) $digests->snapshot($week)));
        $board->deletePost(self::POST);
        self::assertSame([0, 0], array_values((array) $digests->snapshot($week)));
    }

    public function testOwnerMultipleRowsAndRollbackAreConcealedAndAtomic(): void
    {
        $repository = new DoctrineDigestRepository($this->connection);
        $first = $this->digest(self::FIRST);
        $second = $this->digest(self::SECOND);
        $repository->save($first);
        $repository->save($second);

        self::assertSame(self::FIRST, $repository->findOwned(self::FIRST, self::ALICE)?->id);
        self::assertNull($repository->findOwned(self::FIRST, self::BOB));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM public.board_digests'));

        $this->connection->beginTransaction();
        $repository->save($this->digest('019b5000-0000-7000-8000-000000000003'));
        $this->connection->rollBack();
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM public.board_digests'));
    }

    private function digest(string $id): GeneratedDigest
    {
        return new GeneratedDigest(
            $id,
            self::ALICE,
            '2026-W30',
            'Weekly digest for 2026-W30: 0 posts and 0 comments.',
            0,
            0,
            new DateTimeImmutable('2026-07-21T00:00:00.123456Z'),
        );
    }
}
