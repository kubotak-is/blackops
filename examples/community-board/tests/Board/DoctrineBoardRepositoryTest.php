<?php

declare(strict_types=1);

namespace App\Tests\Board;

use App\Infrastructure\Persistence\DoctrineBoardRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\TestCase;

final class DoctrineBoardRepositoryTest extends TestCase
{
    private const string ALICE = '019b5000-0000-7000-8000-000000000001';
    private const string BOB = '019b5000-0000-7000-8000-000000000002';
    private const string FIRST_POST = '019b6000-0000-7000-8000-000000000001';
    private const string SECOND_POST = '019b6000-0000-7000-8000-000000000002';
    private const string FIRST_COMMENT = '019b7000-0000-7000-8000-000000000001';
    private const string SECOND_COMMENT = '019b7000-0000-7000-8000-000000000002';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->connect();
        $this->clearDatabase();
        $this->createUser(self::ALICE, 'Alice');
        $this->createUser(self::BOB, 'Bob');
    }

    protected function tearDown(): void
    {
        $this->clearDatabase();
        $this->connection->close();
    }

    public function testFeedPaginationCountPreviewAndDetailOrderingAreDeterministic(): void
    {
        $repository = new DoctrineBoardRepository($this->connection);
        $time = new DateTimeImmutable('2026-07-20T12:00:00.123456Z');
        $repository->createPost(self::FIRST_POST, self::ALICE, 'First', str_repeat('界', 241), $time);
        $repository->createPost(self::SECOND_POST, self::BOB, 'Second', 'Second body', $time);
        $repository->createComment(self::SECOND_COMMENT, self::SECOND_POST, self::BOB, 'Second comment', $time);
        $repository->createComment(self::FIRST_COMMENT, self::SECOND_POST, self::ALICE, 'First comment', $time);

        $firstPage = $repository->listPosts(1, 0);
        $secondPage = $repository->listPosts(1, 1);
        $emptyPage = $repository->listPosts(1, 2);

        self::assertSame(2, $firstPage->total);
        self::assertSame(self::SECOND_POST, $firstPage->posts[0]->id);
        self::assertSame(2, $firstPage->posts[0]->commentCount);
        self::assertSame(self::FIRST_POST, $secondPage->posts[0]->id);
        self::assertSame(240, preg_match_all('/./us', $secondPage->posts[0]->bodyPreview));
        self::assertSame([], $emptyPage->posts);
        self::assertSame(2, $emptyPage->total);

        $thread = $repository->findPost(self::SECOND_POST);
        self::assertNotNull($thread);
        self::assertSame('Bob', $thread->post->authorDisplayName);
        self::assertSame([self::FIRST_COMMENT, self::SECOND_COMMENT], array_column($thread->comments, 'id'));
        self::assertSame('2026-07-20T12:00:00.123456Z', $thread->post->createdAt->format('Y-m-d\TH:i:s.u\Z'));
    }

    public function testCreateUpdateLockRollbackAndDeleteCascadeUseTheSameConnection(): void
    {
        $repository = new DoctrineBoardRepository($this->connection);
        $createdAt = new DateTimeImmutable('2026-07-20T00:00:00Z');
        $repository->createPost(self::FIRST_POST, self::ALICE, 'Original', 'Original body', $createdAt);
        $repository->createComment(self::FIRST_COMMENT, self::FIRST_POST, self::BOB, 'Comment', $createdAt);

        $this->connection->beginTransaction();
        self::assertSame(self::ALICE, $repository->lockPostAuthorId(self::FIRST_POST));
        $repository->updatePost(
            self::FIRST_POST,
            'Changed',
            'Changed body',
            new DateTimeImmutable('2026-07-21T00:00:00Z'),
        );
        $this->connection->rollBack();

        $afterRollback = $repository->findPost(self::FIRST_POST);
        self::assertNotNull($afterRollback);
        self::assertSame('Original', $afterRollback->post->title);
        self::assertCount(1, $afterRollback->comments);

        $this->connection->beginTransaction();
        self::assertSame(self::ALICE, $repository->lockPostAuthorId(self::FIRST_POST));
        $repository->deletePost(self::FIRST_POST);
        $this->connection->commit();

        self::assertNull($repository->findPost(self::FIRST_POST));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM public.board_comments'));
    }

    public function testDeleteAndAddCommentRaceCannotCreateAnOrphan(): void
    {
        $repository = new DoctrineBoardRepository($this->connection);
        $repository->createPost(
            self::FIRST_POST,
            self::ALICE,
            'Race',
            'Race body',
            new DateTimeImmutable('2026-07-20T00:00:00Z'),
        );

        $competing = $this->connect();
        $competingRepository = new DoctrineBoardRepository($competing);
        try {
            $this->connection->beginTransaction();
            self::assertSame(self::ALICE, $repository->lockPostAuthorId(self::FIRST_POST));
            $repository->deletePost(self::FIRST_POST);

            $competing->beginTransaction();
            $competing->executeStatement("SET LOCAL lock_timeout = '100ms'");
            try {
                $competingRepository->lockPostAuthorId(self::FIRST_POST);
                self::fail('Expected the competing comment path to wait for the delete lock.');
            } catch (Exception) {
                $competing->rollBack();
            }

            $this->connection->commit();
            self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM public.board_posts'));
            self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM public.board_comments'));
        } finally {
            if ($competing->isTransactionActive()) {
                $competing->rollBack();
            }
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $competing->close();
        }
    }

    private function connect(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $_ENV['POSTGRES_HOST'] ?? 'postgres',
            'port' => (int) ($_ENV['POSTGRES_PORT'] ?? '5432'),
            'dbname' => $_ENV['POSTGRES_DB'] ?? 'community_board',
            'user' => $_ENV['POSTGRES_USER'] ?? 'blackops',
            'password' => $_ENV['POSTGRES_PASSWORD'] ?? 'blackops',
        ]);
    }

    private function clearDatabase(): void
    {
        $this->connection->executeStatement(
            'TRUNCATE public.board_comments, public.board_posts, public.blackops_sessions, public.board_users CASCADE',
        );
    }

    private function createUser(string $id, string $displayName): void
    {
        $this->connection->insert('public.board_users', [
            'id' => $id,
            'email_canonical' => strtolower($displayName) . '@example.test',
            'email_display' => strtolower($displayName) . '@example.test',
            'display_name' => $displayName,
            'password_hash' => 'not-used-by-board-repository-test',
            'created_at' => '2026-07-20 00:00:00+00:00',
            'updated_at' => '2026-07-20 00:00:00+00:00',
        ]);
    }
}
