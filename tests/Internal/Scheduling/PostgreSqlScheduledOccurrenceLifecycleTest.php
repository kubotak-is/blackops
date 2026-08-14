<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Scheduling;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Scheduling\PostgreSqlScheduledOccurrenceLifecycle;
use BlackOps\Internal\Scheduling\PostgreSqlScheduleStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use LogicException;
use PHPUnit\Framework\TestCase;

final class PostgreSqlScheduledOccurrenceLifecycleTest extends TestCase
{
    private const string SCHEMA = 'scheduled_lifecycle_test';
    private const string OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687701';

    private Connection $connection;
    private PostgreSqlScheduledOccurrenceLifecycle $lifecycle;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => (int) (getenv('POSTGRES_PORT') ?: 5432),
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS "' . self::SCHEMA . '" CASCADE');
        new PostgreSqlScheduleStore($this->connection, self::SCHEMA)->migrate();
        $this->lifecycle = new PostgreSqlScheduledOccurrenceLifecycle($this->connection, self::SCHEMA);
        $this->connection->executeStatement(
            'INSERT INTO "'
            . self::SCHEMA
            . '"."schedule_states" (schedule_name, operation_type, cursor_at, created_at, updated_at) VALUES (\'reports.daily\', \'reports.daily.run\', \'2026-07-22 09:00:00+00\', \'2026-07-23 00:00:00+00\', \'2026-07-23 00:00:00+00\')',
        );
        $this->insert('claimed');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS "' . self::SCHEMA . '" CASCADE');
    }

    public function testClaimedTransitionsToAcceptedWithAcknowledgementInstant(): void
    {
        $at = new DateTimeImmutable('2026-07-23T00:00:00.654321+09:00');
        $this->lifecycle->transition($this->id(), 'claimed', 'accepted', null, $at);

        $row = $this->row();
        self::assertSame('accepted', $row['state']);
        self::assertNull($row['category']);
        self::assertSame('2026-07-22 15:00:00.654321+00', $row['accepted_at']);
    }

    public function testExpectedStateGuardsAndRejectsUnsafeCategory(): void
    {
        $this->expectException(LogicException::class);
        $this->lifecycle->transition($this->id(), 'accepted', 'failed', 'Raw SQL; leaked', new DateTimeImmutable());
    }

    public function testZeroRowTransitionIsNotSuccessfulAndTerminalCannotReopen(): void
    {
        $this->lifecycle->transition(
            $this->id(),
            'claimed',
            'rejected',
            'authorization.denied',
            new DateTimeImmutable('2026-07-23T00:00:00.000001Z'),
        );

        try {
            $this->lifecycle->transition(
                $this->id(),
                'claimed',
                'completed',
                null,
                new DateTimeImmutable('2026-07-23T00:00:00.000002Z'),
            );
            self::fail('Expected guarded transition failure.');
        } catch (LogicException $exception) {
            self::assertSame(
                'Scheduled occurrence transition did not update exactly one row.',
                $exception->getMessage(),
            );
        }
        self::assertSame('rejected', $this->row()['state']);
    }

    public function testOuterTransactionRollbackRestoresClaimedOccurrence(): void
    {
        try {
            $this->connection->transactional(function (): void {
                $this->lifecycle->transition(
                    $this->id(),
                    'claimed',
                    'completed',
                    null,
                    new DateTimeImmutable('2026-07-23T00:00:00.000001Z'),
                );
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $exception) {
            self::assertSame('rollback', $exception->getMessage());
        }

        self::assertSame('claimed', $this->row()['state']);
    }

    public function testAcceptedOccurrenceSupportsRetryAndEveryDeferredTerminalState(): void
    {
        $this->lifecycle->transition(
            $this->id(),
            'claimed',
            'accepted',
            null,
            new DateTimeImmutable('2026-07-23T00:00:00.000001Z'),
        );
        $this->lifecycle->transition(
            $this->id(),
            'accepted',
            'accepted',
            null,
            new DateTimeImmutable('2026-07-23T00:00:00.000002Z'),
        );
        self::assertSame('accepted', $this->row()['state']);

        foreach (['completed', 'rejected', 'failed', 'dead_lettered'] as $target) {
            $this->connection->executeStatement('UPDATE "'
            . self::SCHEMA
            . '"."schedule_occurrences" SET state = \'accepted\', category = NULL WHERE operation_id = :operation', [
                'operation' => self::OPERATION_ID,
            ]);
            $this->lifecycle->transition(
                $this->id(),
                'accepted',
                $target,
                in_array($target, ['rejected', 'failed', 'dead_lettered'], true) ? 'worker.safe_failure' : null,
                new DateTimeImmutable('2026-07-23T00:00:00.000003Z'),
            );
            self::assertSame($target, $this->row()['state']);
        }
    }

    private function id(): OperationId
    {
        return OperationId::fromString(self::OPERATION_ID);
    }

    private function insert(string $state): void
    {
        $this->connection->executeStatement(
            'INSERT INTO "'
            . self::SCHEMA
            . '"."schedule_occurrences" (schedule_name, scheduled_at, evaluated_at, state, category, operation_id, accepted_at, created_at, updated_at) VALUES (:name, :scheduled, :evaluated, :state, NULL, :operation, NULL, :created, :updated)',
            [
                'name' => 'reports.daily',
                'scheduled' => '2026-07-22 09:00:00+00',
                'evaluated' => '2026-07-23 00:00:00.123456+00',
                'state' => $state,
                'operation' => self::OPERATION_ID,
                'created' => '2026-07-23 00:00:00.123456+00',
                'updated' => '2026-07-23 00:00:00.123456+00',
            ],
        );
    }

    /** @return array<string, mixed> */
    private function row(): array
    {
        $row = $this->connection->fetchAssociative('SELECT state, category, accepted_at::text AS accepted_at FROM "'
        . self::SCHEMA
        . '"."schedule_occurrences" WHERE operation_id = :operation', ['operation' => self::OPERATION_ID]);
        self::assertIsArray($row);
        return $row;
    }
}
