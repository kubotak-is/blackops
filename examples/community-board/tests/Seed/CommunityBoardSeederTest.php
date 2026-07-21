<?php

declare(strict_types=1);

namespace App\Tests\Seed;

use App\Http\DatabaseConnectionFactory;
use App\Identity\DoctrineIdentityRepository;
use App\Identity\IdentityService;
use App\Identity\PasswordHasher;
use App\Identity\SessionSettings;
use App\Identity\SessionToken;
use App\Infrastructure\Seed\CommunityBoardSeedDataset;
use App\Infrastructure\Seed\CommunityBoardSeeder;
use App\Infrastructure\Seed\FixedSeedClock;
use App\Infrastructure\Seed\FixedSeedIdentifierGenerator;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class CommunityBoardSeederTest extends TestCase
{
    private const NON_SEED_USER = '019b1000-0009-7000-8000-000000000999';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DatabaseConnectionFactory::fromEnvironment($this->environment())->create();
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
        $identity = new IdentityService(
            new DoctrineIdentityRepository($this->connection),
            new PasswordHasher(),
            new SessionToken(),
            new FixedSeedClock(new DateTimeImmutable('2026-07-21T00:00:00Z', new DateTimeZone('UTC'))),
            new FixedSeedIdentifierGenerator(self::NON_SEED_USER),
            new SessionSettings(28_800),
        );
        $identity->provisionUser('outside@example.com', 'Outside User', 'OutsideUserPassword!2026');

        $sessionsBefore = $this->rowCount('public.board_sessions');
        $operationsBefore = $this->rowCount('blackops.operations');
        $journalBefore = $this->rowCount('blackops.journal');
        $seeder = new CommunityBoardSeeder($this->connection);

        $first = $seeder->seed();
        $second = $seeder->seed();

        self::assertSame([3, 3, 4], [$first->users, $first->posts, $first->comments]);
        self::assertEquals($first, $second);
        self::assertSame(3, $this->seedCount('board_users'));
        self::assertSame(3, $this->seedCount('board_posts'));
        self::assertSame(4, $this->seedCount('board_comments'));
        self::assertSame($sessionsBefore, $this->rowCount('public.board_sessions'));
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

    /** @return array<string, string> */
    private function environment(): array
    {
        $environment = [];
        foreach ($_ENV as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $environment[$name] = $value;
            }
        }

        return $environment;
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
