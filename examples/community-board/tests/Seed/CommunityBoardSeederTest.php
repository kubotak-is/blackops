<?php

declare(strict_types=1);

namespace App\Tests\Seed;

use App\Domain\Identity\PasswordHasher;
use App\Domain\Identity\User;
use App\Infrastructure\Identity\DoctrineUserRepository;
use App\Infrastructure\Seed\CommunityBoardSeedDataset;
use App\Infrastructure\Seed\CommunityBoardSeeder;
use BlackOps\Database\DatabaseManager;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class CommunityBoardSeederTest extends TestCase
{
    private const NON_SEED_USER = '019b1000-0009-7000-8000-000000000999';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $this->environment('POSTGRES_HOST', 'postgres'),
            'port' => (int) $this->environment('POSTGRES_PORT', '5432'),
            'dbname' => $this->environment('POSTGRES_DB', 'community_board'),
            'user' => $this->environment('POSTGRES_USER', 'blackops'),
            'password' => $this->environment('POSTGRES_PASSWORD', 'blackops'),
        ]);
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        $this->connection->close();
    }

    public function testSeedIsIdempotentAndPreservesNonSeedDataAndRuntimeBoundaries(): void
    {
        $passwords = new PasswordHasher();
        $users = new DoctrineUserRepository(new TestDatabaseManager($this->connection));
        $now = new DateTimeImmutable('2026-07-21T00:00:00+00:00');
        $users->save(
            new User(
                self::NON_SEED_USER,
                'outside@example.com',
                'outside@example.com',
                'Outside User',
                $passwords->hash('OutsideUserPassword!2026'),
                $now,
                $now,
            ),
        );

        $sessionsBefore = $this->rowCount('public.blackops_sessions');
        $operationsBefore = $this->rowCount('blackops.operations');
        $journalBefore = $this->rowCount('blackops.journal');
        $seeder = new CommunityBoardSeeder($this->connection, $users, $passwords);

        $first = $seeder->seed();
        $second = $seeder->seed();

        self::assertSame([3, 3, 4], [$first->users, $first->posts, $first->comments]);
        self::assertEquals($first, $second);
        self::assertSame(3, $this->seedCount('board_users'));
        self::assertSame(3, $this->seedCount('board_posts'));
        self::assertSame(4, $this->seedCount('board_comments'));
        self::assertSame($sessionsBefore, $this->rowCount('public.blackops_sessions'));
        self::assertSame($operationsBefore, $this->rowCount('blackops.operations'));
        self::assertSame($journalBefore, $this->rowCount('blackops.journal'));
        self::assertSame('Outside User', $this->connection->fetchOne('SELECT display_name FROM public.board_users WHERE id = :id', [
            'id' => self::NON_SEED_USER,
        ]));

        $hash = $this->connection->fetchOne('SELECT password_hash FROM public.board_users WHERE email_canonical = :email', [
            'email' => CommunityBoardSeedDataset::DEMO_EMAIL,
        ]);
        self::assertIsString($hash);
        self::assertTrue(password_verify(CommunityBoardSeedDataset::DEMO_PASSWORD, $hash));
    }

    private function environment(string $name, string $default): string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function rowCount(string $table): int
    {
        return (int) $this->connection->fetchOne("SELECT count(*) FROM {$table}");
    }

    private function seedCount(string $table): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT count(*) FROM public.{$table} WHERE id::text LIKE '019b1000-%' AND id <> :non_seed_user",
            ['non_seed_user' => self::NON_SEED_USER],
        );
    }
}

final readonly class TestDatabaseManager implements DatabaseManager
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function connection(?string $name = null): Connection
    {
        return $this->connection;
    }
}
