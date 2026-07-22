<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Auth\Session;

use BlackOps\Auth\Session\InvalidSessionException;
use BlackOps\Auth\Session\RawSessionToken;
use BlackOps\Auth\Session\SessionConfiguration;
use BlackOps\Auth\Session\SessionIdentityProvider;
use BlackOps\Core\ActorRef;
use BlackOps\Database\DatabaseManager;
use BlackOps\Internal\Auth\Session\CryptographicSessionTokenGenerator;
use BlackOps\Internal\Auth\Session\DefaultSessionManager;
use BlackOps\Internal\Auth\Session\PostgreSqlSessionStore;
use BlackOps\Internal\Auth\Session\SessionClock;
use BlackOps\Internal\Auth\Session\SessionTokenGenerator;
use BlackOps\Internal\Auth\Session\SymfonySessionIdentifierGenerator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;

/**
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class PostgreSqlSessionIntegrationTest extends TestCase
{
    private const string SCHEMA = 'blackops_session_auth';
    private const string TABLE = self::SCHEMA . '.blackops_sessions';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->connection->executeStatement('CREATE SCHEMA ' . self::SCHEMA);
        $this->createTable($this->connection);
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->connection->close();
    }

    public function testIssuePersistsOnlyHashAndAbsoluteLifetime(): void
    {
        $clock = new MutableSessionClock('2026-07-22T00:00:00Z');
        $manager = $this->manager($this->connection, $clock, new ByteSequenceTokenGenerator(["\0"]));

        $issued = $manager->issue('opaque-identity');
        $row = $this->connection->fetchAssociative('SELECT * FROM ' . self::TABLE);

        self::assertIsArray($row);
        self::assertSame('opaque-identity', $row['identity_id']);
        self::assertSame(hash('sha256', $issued->token()->reveal()), $row['token_hash']);
        self::assertStringNotContainsString($issued->token()->reveal(), implode('|', array_map(
            static fn(mixed $value): string => is_scalar($value) ? (string) $value : '',
            $row,
        )));
        self::assertSame('2026-07-22 00:00:00+00', $row['issued_at']);
        self::assertSame('2026-07-22 08:00:00+00', $row['expires_at']);
        self::assertArrayNotHasKey('actor_type', $row);
        self::assertArrayNotHasKey('password', $row);
        self::assertArrayNotHasKey('role', $row);
    }

    public function testAuthenticationTouchesOnlyAtIntervalAndNeverExtendsExpiry(): void
    {
        $clock = new MutableSessionClock('2026-07-22T00:00:00Z');
        $manager = $this->manager($this->connection, $clock, new ByteSequenceTokenGenerator(["\0"]));
        $issued = $manager->issue('opaque-identity');
        $raw = $issued->token()->reveal();

        $clock->set('2026-07-22T00:04:59Z');
        self::assertSame('opaque-identity', $manager->authenticate($raw)?->id());
        self::assertSame('2026-07-22 00:00:00+00', $this->lastUsedAt());

        $clock->set('2026-07-22T00:05:00Z');
        self::assertSame('opaque-identity', $manager->authenticate($raw)?->id());
        self::assertSame('2026-07-22 00:05:00+00', $this->lastUsedAt());
        self::assertSame('2026-07-22 08:00:00+00', $this->expiresAt());

        $clock->set('2026-07-22T08:00:00Z');
        self::assertNull($manager->authenticate($raw));
        self::assertSame('2026-07-22 08:00:00+00', $this->expiresAt());
    }

    public function testConcurrentAuthenticationTouchesOnceWithoutDeadlock(): void
    {
        self::assertTrue(function_exists('pcntl_fork'));
        $clock = new MutableSessionClock('2026-07-22T00:00:00Z');
        $raw = $this
            ->manager($this->connection, $clock, new CryptographicSessionTokenGenerator())
            ->issue('concurrent-touch-identity')
            ->token()
            ->reveal();
        $results = [$this->resultPath(), $this->resultPath()];
        $start = $this->resultPath();
        $this->connection->close();
        $pids = [
            $this->forkAuthentication($raw, $results[0], $start),
            $this->forkAuthentication($raw, $results[1], $start),
        ];
        file_put_contents(filename: $start, data: 'go');

        try {
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
                self::assertSame(0, pcntl_wexitstatus($status));
            }

            $this->connection = $this->connection();
            self::assertSame('success', trim((string) file_get_contents($results[0])));
            self::assertSame('success', trim((string) file_get_contents($results[1])));
            self::assertSame('2026-07-22 00:05:00+00', $this->lastUsedAt());
        } finally {
            $this->removeResultFiles([...$results, $start]);
        }
    }

    public function testRotationRevokesOldTokenAndRevokeIsIdempotent(): void
    {
        $clock = new MutableSessionClock('2026-07-22T00:00:00Z');
        $manager = $this->manager($this->connection, $clock, new ByteSequenceTokenGenerator(["\0", "\1"]));
        $original = $manager->issue('opaque-identity')->token()->reveal();
        $clock->set('2026-07-22T01:00:00Z');

        $successor = $manager->rotate($original)->token()->reveal();

        self::assertNull($manager->authenticate($original));
        self::assertSame('opaque-identity', $manager->authenticate($successor)?->id());
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::TABLE));
        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT count(*) FROM ' . self::TABLE . ' WHERE revoked_at IS NOT NULL AND rotated_to_id IS NOT NULL',
            ),
        );

        try {
            $manager->rotate($original);
            self::fail('Expected old token rotation to fail.');
        } catch (InvalidSessionException $exception) {
            self::assertSame('Session is invalid.', $exception->getMessage());
        }

        $manager->revoke($successor);
        $manager->revoke($successor);
        self::assertNull($manager->authenticate($successor));
        self::assertSame(
            2,
            (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::TABLE . ' WHERE revoked_at IS NOT NULL'),
        );
    }

    public function testCleanupDeletesOnlyExpiredOrRevokedRowsOlderThanCutoff(): void
    {
        $clock = new MutableSessionClock('2026-07-25T00:00:00Z');
        $manager = $this->manager($this->connection, $clock, new CryptographicSessionTokenGenerator());
        $this->insertRow('00000000-0000-7000-8000-000000000001', 'a', '1', '2026-07-20', '2026-07-21', null);
        $this->insertRow('00000000-0000-7000-8000-000000000002', 'b', '2', '2026-07-20', '2026-07-30', '2026-07-21');
        $this->insertRow('00000000-0000-7000-8000-000000000003', 'c', '3', '2026-07-20', '2026-07-30', null);
        $this->insertRow('00000000-0000-7000-8000-000000000004', 'd', '4', '2026-07-20', '2026-07-30', '2026-07-24');

        $deleted = $manager->cleanup(new DateTimeImmutable('2026-07-23T00:00:00Z'));

        self::assertSame(2, $deleted);
        self::assertSame(
            ['c', 'd'],
            $this->connection->fetchFirstColumn('SELECT identity_id FROM ' . self::TABLE . ' ORDER BY identity_id'),
        );
    }

    public function testConcurrentRotationAllowsExactlyOneSuccessor(): void
    {
        self::assertTrue(function_exists('pcntl_fork'));
        $clock = new MutableSessionClock('2026-07-22T00:00:00Z');
        $raw = $this
            ->manager($this->connection, $clock, new CryptographicSessionTokenGenerator())
            ->issue('concurrent-identity')
            ->token()
            ->reveal();
        $results = [$this->resultPath(), $this->resultPath()];
        $start = $this->resultPath();
        $this->connection->close();
        $pids = [
            $this->forkRotation($raw, $results[0], $start),
            $this->forkRotation($raw, $results[1], $start),
        ];
        file_put_contents(filename: $start, data: 'go');

        try {
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
                self::assertSame(0, pcntl_wexitstatus($status));
            }

            $this->connection = $this->connection();
            $outcomes = [trim((string) file_get_contents($results[0])), trim((string) file_get_contents($results[1]))];
            sort($outcomes);
            self::assertSame(['invalid', 'success'], $outcomes);
            self::assertSame(
                1,
                (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::TABLE . ' WHERE revoked_at IS NULL'),
            );
            self::assertSame(
                1,
                (int) $this->connection->fetchOne(
                    'SELECT count(*) FROM ' . self::TABLE . ' WHERE rotated_to_id IS NOT NULL',
                ),
            );
        } finally {
            $this->removeResultFiles([...$results, $start]);
        }
    }

    public function testConcurrentRevokeAndRotateNeverCreateTwoActiveSuccessors(): void
    {
        self::assertTrue(function_exists('pcntl_fork'));
        $clock = new MutableSessionClock('2026-07-22T00:00:00Z');
        $raw = $this
            ->manager($this->connection, $clock, new CryptographicSessionTokenGenerator())
            ->issue('concurrent-identity')
            ->token()
            ->reveal();
        $hash = hash('sha256', $raw);
        $rotateResult = $this->resultPath();
        $revokeResult = $this->resultPath();
        $start = $this->resultPath();
        $this->connection->close();
        $pids = [
            $this->forkRotation($raw, $rotateResult, $start),
            $this->forkRevocation($raw, $revokeResult, $start),
        ];
        file_put_contents(filename: $start, data: 'go');

        try {
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
                self::assertSame(0, pcntl_wexitstatus($status));
            }

            $this->connection = $this->connection();
            self::assertContains(trim((string) file_get_contents($rotateResult)), ['invalid', 'success']);
            self::assertSame('success', trim((string) file_get_contents($revokeResult)));
            self::assertLessThanOrEqual(
                1,
                (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::TABLE . ' WHERE revoked_at IS NULL'),
            );
            self::assertSame(
                1,
                (int) $this->connection->fetchOne('SELECT count(*) FROM '
                . self::TABLE
                . ' WHERE token_hash = :hash AND revoked_at IS NOT NULL', ['hash' => $hash]),
            );
        } finally {
            $this->removeResultFiles([$rotateResult, $revokeResult, $start]);
        }
    }

    public function testMigrationTemplateOwnsExpectedSchemaAndNoApplicationIdentityColumns(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../../resources/stubs/auth-session-migration.php.stub');

        self::assertIsString($template);
        foreach ([
            'token_hash CHAR(64)',
            'rotated_to_id UUID',
            'ON DELETE SET NULL',
            'identity_id VARCHAR(255)',
        ] as $shape) {
            self::assertStringContainsString($shape, $template);
        }
        foreach (['actor_type', 'password', 'permission', 'cookie_value'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $template);
        }
        self::assertSame(
            1,
            (int) $this->connection->fetchOne('SELECT count(*) FROM information_schema.referential_constraints
             WHERE constraint_schema = :schema AND delete_rule = :rule', [
                'schema' => self::SCHEMA,
                'rule' => 'SET NULL',
            ]),
        );
    }

    private function manager(
        Connection $connection,
        SessionClock $clock,
        SessionTokenGenerator $tokens,
    ): DefaultSessionManager {
        return new DefaultSessionManager(
            new SessionConfiguration(),
            new IntegrationIdentityProvider(),
            new PostgreSqlSessionStore(new SingleConnectionDatabaseManager($connection), self::TABLE),
            $tokens,
            new SymfonySessionIdentifierGenerator(),
            $clock,
        );
    }

    private function forkRotation(#[SensitiveParameter] string $rawToken, string $result, string $start): int
    {
        $pid = pcntl_fork();
        self::assertGreaterThanOrEqual(0, $pid);

        if ($pid !== 0) {
            return $pid;
        }

        $this->awaitStart($start);
        $connection = $this->connection();

        try {
            $manager = $this->manager(
                $connection,
                new MutableSessionClock('2026-07-22T00:10:00Z'),
                new CryptographicSessionTokenGenerator(),
            );

            try {
                $manager->rotate($rawToken);
                file_put_contents(filename: $result, data: 'success');
            } catch (InvalidSessionException) {
                file_put_contents(filename: $result, data: 'invalid');
            }
        } finally {
            $connection->close();
        }

        exit(0);
    }

    private function forkAuthentication(#[SensitiveParameter] string $rawToken, string $result, string $start): int
    {
        $pid = pcntl_fork();
        self::assertGreaterThanOrEqual(0, $pid);

        if ($pid !== 0) {
            return $pid;
        }

        $this->awaitStart($start);
        $connection = $this->connection();

        try {
            $connection->executeStatement("SET lock_timeout TO '5s'");
            $actor = $this->manager(
                $connection,
                new MutableSessionClock('2026-07-22T00:05:00Z'),
                new CryptographicSessionTokenGenerator(),
            )->authenticate($rawToken);
            file_put_contents(
                filename: $result,
                data: $actor?->id() === 'concurrent-touch-identity' ? 'success' : 'invalid',
            );
        } finally {
            $connection->close();
        }

        exit(0);
    }

    private function forkRevocation(#[SensitiveParameter] string $rawToken, string $result, string $start): int
    {
        $pid = pcntl_fork();
        self::assertGreaterThanOrEqual(0, $pid);

        if ($pid !== 0) {
            return $pid;
        }

        $this->awaitStart($start);
        $connection = $this->connection();

        try {
            $this->manager(
                $connection,
                new MutableSessionClock('2026-07-22T00:10:00Z'),
                new CryptographicSessionTokenGenerator(),
            )->revoke($rawToken);
            file_put_contents(filename: $result, data: 'success');
        } finally {
            $connection->close();
        }

        exit(0);
    }

    private function resultPath(): string
    {
        return sys_get_temp_dir() . '/blackops-session-concurrency-' . bin2hex(random_bytes(8));
    }

    private function awaitStart(string $start): void
    {
        while (!is_file($start)) {
            usleep(1_000);
        }
    }

    /** @param list<string> $results */
    private function removeResultFiles(array $results): void
    {
        foreach ($results as $result) {
            if (!is_file($result)) {
                continue;
            }

            unlink($result);
        }
    }

    /** @mago-expect lint:excessive-parameter-list */
    private function insertRow(
        string $id,
        string $identity,
        string $hashSeed,
        string $issuedAt,
        string $expiresAt,
        ?string $revokedAt,
    ): void {
        $this->connection->executeStatement('INSERT INTO ' . self::TABLE . ' (
                id, identity_id, token_hash, issued_at, expires_at, last_used_at, revoked_at
             ) VALUES (
                :id, :identity, :hash, :issued_at, :expires_at, :issued_at, :revoked_at
             )', [
            'id' => $id,
            'identity' => $identity,
            'hash' => hash('sha256', $hashSeed),
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'revoked_at' => $revokedAt,
        ]);
    }

    private function lastUsedAt(): string
    {
        return (string) $this->connection->fetchOne('SELECT last_used_at FROM ' . self::TABLE);
    }

    private function expiresAt(): string
    {
        return (string) $this->connection->fetchOne('SELECT expires_at FROM ' . self::TABLE);
    }

    private function createTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE ' . self::TABLE . ' (
            id UUID PRIMARY KEY,
            identity_id VARCHAR(255) NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE CHECK (token_hash ~ \'^[0-9a-f]{64}$\'),
            issued_at TIMESTAMPTZ NOT NULL,
            expires_at TIMESTAMPTZ NOT NULL CHECK (expires_at > issued_at),
            last_used_at TIMESTAMPTZ NOT NULL CHECK (last_used_at >= issued_at AND last_used_at <= expires_at),
            revoked_at TIMESTAMPTZ NULL,
            rotated_to_id UUID NULL UNIQUE,
            CONSTRAINT blackops_sessions_rotated_to_id_fkey
                FOREIGN KEY (rotated_to_id)
                REFERENCES ' . self::TABLE . ' (id)
                ON DELETE SET NULL
        )',
        );
        $connection->executeStatement(
            'CREATE INDEX blackops_sessions_identity_idx ON ' . self::TABLE . ' (identity_id, expires_at)',
        );
        $connection->executeStatement(
            'CREATE INDEX blackops_sessions_expiry_cleanup_idx ON ' . self::TABLE . ' (expires_at, id)',
        );
        $connection->executeStatement(
            'CREATE INDEX blackops_sessions_revocation_cleanup_idx ON '
            . self::TABLE
            . ' (revoked_at, id) WHERE revoked_at IS NOT NULL',
        );
    }

    private function connection(): Connection
    {
        $port = getenv('POSTGRES_PORT');
        $database = getenv('POSTGRES_DB');
        $user = getenv('POSTGRES_USER');
        $password = getenv('POSTGRES_PASSWORD');

        self::assertIsString($port);
        self::assertIsString($database);
        self::assertIsString($user);
        self::assertIsString($password);

        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => 'postgres',
            'port' => (int) $port,
            'dbname' => $database,
            'user' => $user,
            'password' => $password,
        ]);
    }
}

/** @mago-expect lint:single-class-per-file */
final class MutableSessionClock implements SessionClock
{
    private DateTimeImmutable $now;

    public function __construct(string $now)
    {
        $this->set($now);
    }

    public function set(string $now): void
    {
        $this->now = new DateTimeImmutable($now);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}

/** @mago-expect lint:single-class-per-file */
final class ByteSequenceTokenGenerator implements SessionTokenGenerator
{
    /** @var list<string> */
    private array $bytes;

    /** @param list<string> $bytes */
    public function __construct(array $bytes)
    {
        $this->bytes = $bytes;
    }

    public function generate(): RawSessionToken
    {
        $byte = array_shift($this->bytes) ?? "\0";

        return RawSessionToken::fromRandomBytes(str_repeat(string: $byte, times: 32));
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class SingleConnectionDatabaseManager implements DatabaseManager
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function connection(?string $name = null): Connection
    {
        return $this->connection;
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class IntegrationIdentityProvider implements SessionIdentityProvider
{
    public function resolve(string $identityId): ?ActorRef
    {
        return new ActorRef($identityId, 'user');
    }
}
