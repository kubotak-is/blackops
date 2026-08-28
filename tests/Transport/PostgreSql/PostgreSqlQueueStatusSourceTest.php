<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Queue\QueueStatusFailure;
use BlackOps\Core\Queue\QueueStatusSourceException;
use BlackOps\Core\TenantRef;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlQueueStatusSource;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PostgreSqlQueueStatusSourceTest extends TestCase
{
    private const string OBSERVED_AT = '2026-08-27T15:00:00.000000Z';

    public function testOneAggregateRowProjectsTheExactEightCountsAndFourAges(): void
    {
        $connection = $this->connectionMock($this->row([
            'acceptedReady' => '1',
            'acceptedDelayed' => '1',
            'retryReady' => '1',
            'retryDelayed' => '1',
            'runningActive' => '1',
            'runningExpired' => '1',
            'supervising' => '1',
            'deadLettered' => '1',
            'oldestReady' => '2026-08-27 14:59:58.750000+00',
            'oldestRunningExpired' => '2026-08-27 14:59:59.000000+00',
            'oldestSupervising' => '2026-08-27 14:59:55.000000+00',
            'oldestDeadLettered' => '2026-08-27 14:59:54.000000+00',
        ]));
        $source = new PostgreSqlQueueStatusSource($connection, 'queue_status_test');

        $snapshot = $source->read(new DateTimeImmutable(self::OBSERVED_AT));

        self::assertSame(1, $snapshot->acceptedReady);
        self::assertSame(1, $snapshot->acceptedDelayed);
        self::assertSame(1, $snapshot->retryReady);
        self::assertSame(1, $snapshot->retryDelayed);
        self::assertSame(1, $snapshot->runningActive);
        self::assertSame(1, $snapshot->runningExpired);
        self::assertSame(1, $snapshot->supervising);
        self::assertSame(1, $snapshot->deadLettered);
        self::assertSame(1, $snapshot->oldestReady);
        self::assertSame(1, $snapshot->oldestRunningExpired);
        self::assertSame(5, $snapshot->oldestSupervising);
        self::assertSame(6, $snapshot->oldestDeadLettered);
    }

    public function testEmptyAggregateReturnsNullAgesAndUsesOneFixedFetch(): void
    {
        $connection = $this->connectionMock($this->row());

        $snapshot = new PostgreSqlQueueStatusSource(
            $connection,
            'queue_status_test',
        )->read(new DateTimeImmutable(self::OBSERVED_AT));

        self::assertSame(0, $snapshot->acceptedReady);
        self::assertSame(0, $snapshot->acceptedDelayed);
        self::assertSame(0, $snapshot->retryReady);
        self::assertSame(0, $snapshot->retryDelayed);
        self::assertSame(0, $snapshot->runningActive);
        self::assertSame(0, $snapshot->runningExpired);
        self::assertSame(0, $snapshot->supervising);
        self::assertSame(0, $snapshot->deadLettered);
        self::assertNull($snapshot->oldestReady);
        self::assertNull($snapshot->oldestRunningExpired);
        self::assertNull($snapshot->oldestSupervising);
        self::assertNull($snapshot->oldestDeadLettered);
    }

    public function testPostgreSqlAggregateCountsBucketsAgesAndExcludesTerminalRows(): void
    {
        $schema = 'blackops_queue_status_source';
        $connection = $this->connection();
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . $schema . ' CASCADE');

        try {
            $sender = new PostgreSqlDeferredOperationSender(
                $connection,
                PostgreSqlTestStorageProtection::codec(),
                $schema,
                new DateTimeImmutable('2026-08-27T00:00:00.000000Z'),
            );
            $sender->migrate();

            $states = [
                [
                    '019f70ab-5000-7000-8000-000000000001',
                    'accepted',
                    '2026-08-27T15:00:00Z',
                    null,
                    '2026-08-27T15:00:00Z',
                    'tenant-a',
                ],
                [
                    '019f70ab-5000-7000-8000-000000000002',
                    'accepted',
                    '2026-08-27T15:01:00Z',
                    null,
                    '2026-08-27T15:01:00Z',
                    'tenant-a',
                ],
                [
                    '019f70ab-5000-7000-8000-000000000003',
                    'retry_scheduled',
                    '2026-08-27T14:58:00Z',
                    null,
                    '2026-08-27T14:58:00Z',
                    'tenant-b',
                ],
                [
                    '019f70ab-5000-7000-8000-000000000004',
                    'retry_scheduled',
                    '2026-08-27T15:02:00Z',
                    null,
                    '2026-08-27T15:02:00Z',
                    'tenant-a',
                ],
                [
                    '019f70ab-5000-7000-8000-000000000005',
                    'running',
                    '2026-08-27T15:00:00Z',
                    '2026-08-27T15:01:00Z',
                    '2026-08-27T15:00:00Z',
                    'tenant-a',
                ],
                [
                    '019f70ab-5000-7000-8000-000000000006',
                    'running',
                    '2026-08-27T15:00:00Z',
                    '2026-08-27T15:00:00Z',
                    '2026-08-27T15:00:00Z',
                    'tenant-a',
                ],
                [
                    '019f70ab-5000-7000-8000-000000000007',
                    'supervising',
                    '2026-08-27T15:00:00Z',
                    null,
                    '2026-08-27T14:55:00Z',
                    'tenant-a',
                ],
                [
                    '019f70ab-5000-7000-8000-000000000008',
                    'dead_lettered',
                    '2026-08-27T15:00:00Z',
                    null,
                    '2026-08-27T14:54:00Z',
                    'tenant-b',
                ],
                [
                    '019f70ab-5000-7000-8000-000000000009',
                    'completed',
                    '2026-08-27T15:00:00Z',
                    null,
                    '2026-08-27T14:53:00Z',
                    'tenant-a',
                ],
                [
                    '019f70ab-5000-7000-8000-00000000000a',
                    'rejected',
                    '2026-08-27T15:00:00Z',
                    null,
                    '2026-08-27T14:52:00Z',
                    'tenant-a',
                ],
                [
                    '019f70ab-5000-7000-8000-00000000000b',
                    'failed',
                    '2026-08-27T15:00:00Z',
                    null,
                    '2026-08-27T14:51:00Z',
                    'tenant-a',
                ],
            ];
            foreach ($states as [$id, $state, $availableAt, $leaseExpiresAt, $updatedAt, $tenantId]) {
                $sender->enqueue(
                    new DeferredOperationMessage(
                        OperationId::fromString($id),
                        'queue.status.fixture',
                        1,
                        '{"private":"payload-secret"}',
                        '{"private":"context-secret"}',
                        new DateTimeImmutable($availableAt),
                        tenant: new TenantRef('customer', $tenantId),
                    ),
                );
                if (in_array($state, ['completed', 'rejected', 'failed', 'dead_lettered'], true)) {
                    $connection->executeStatement(
                        'UPDATE ' . $schema . '.operations
                        SET state = :state, lease_expires_at = NULL, updated_at = :updated_at,
                            encoded_payload = NULL, encoded_context = NULL, payload_purged_at = :purged_at
                        WHERE operation_id = :operation_id',
                        [
                            'state' => $state,
                            'updated_at' => $updatedAt,
                            'purged_at' => '2026-08-27T15:00:00Z',
                            'operation_id' => $id,
                        ],
                    );
                } else {
                    $connection->executeStatement(
                        'UPDATE ' . $schema . '.operations
                        SET state = :state, lease_expires_at = :lease_expires_at, updated_at = :updated_at
                        WHERE operation_id = :operation_id',
                        [
                            'state' => $state,
                            'lease_expires_at' => $leaseExpiresAt,
                            'updated_at' => $updatedAt,
                            'operation_id' => $id,
                        ],
                    );
                }
            }

            self::assertSame(
                9,
                (int) $connection->fetchOne(
                    'SELECT count(*) FROM '
                    . $schema
                    . '.operations
                    WHERE tenant_type = :tenant_type AND tenant_id = :tenant_id',
                    ['tenant_type' => 'customer', 'tenant_id' => 'tenant-a'],
                ),
            );
            self::assertSame(
                2,
                (int) $connection->fetchOne(
                    'SELECT count(*) FROM '
                    . $schema
                    . '.operations
                    WHERE tenant_type = :tenant_type AND tenant_id = :tenant_id',
                    ['tenant_type' => 'customer', 'tenant_id' => 'tenant-b'],
                ),
            );

            $snapshot = new PostgreSqlQueueStatusSource(
                $connection,
                $schema,
            )->read(new DateTimeImmutable(self::OBSERVED_AT));

            self::assertSame(1, $snapshot->acceptedReady);
            self::assertSame(1, $snapshot->acceptedDelayed);
            self::assertSame(1, $snapshot->retryReady);
            self::assertSame(1, $snapshot->retryDelayed);
            self::assertSame(1, $snapshot->runningActive);
            self::assertSame(1, $snapshot->runningExpired);
            self::assertSame(1, $snapshot->supervising);
            self::assertSame(1, $snapshot->deadLettered);
            self::assertSame(120, $snapshot->oldestReady);
            self::assertSame(0, $snapshot->oldestRunningExpired);
            self::assertSame(300, $snapshot->oldestSupervising);
            self::assertSame(360, $snapshot->oldestDeadLettered);
        } finally {
            $connection->executeStatement('DROP SCHEMA IF EXISTS ' . $schema . ' CASCADE');
            $connection->close();
        }
    }

    public function testPostgreSqlRunningNullLeaseIsIntegrityFailure(): void
    {
        $schema = 'blackops_queue_status_null_lease';
        $connection = $this->connection();
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . $schema . ' CASCADE');

        try {
            $sender = new PostgreSqlDeferredOperationSender(
                $connection,
                PostgreSqlTestStorageProtection::codec(),
                $schema,
                new DateTimeImmutable('2026-08-27T00:00:00.000000Z'),
            );
            $sender->migrate();
            $id = '019f70ab-6000-7000-8000-000000000001';
            $sender->enqueue(
                new DeferredOperationMessage(
                    OperationId::fromString($id),
                    'queue.status.fixture',
                    1,
                    '{"private":"payload-secret"}',
                    '{"private":"context-secret"}',
                    new DateTimeImmutable('2026-08-27T15:00:00Z'),
                ),
            );
            $connection->executeStatement('UPDATE '
            . $schema
            . '.operations SET state = \'running\', lease_expires_at = NULL WHERE operation_id = :operation_id', [
                'operation_id' => $id,
            ]);

            try {
                new PostgreSqlQueueStatusSource($connection, $schema)->read(new DateTimeImmutable(self::OBSERVED_AT));
                self::fail('Expected null lease integrity failure.');
            } catch (QueueStatusSourceException $exception) {
                self::assertSame(QueueStatusFailure::Integrity, $exception->failure);
            }
        } finally {
            $connection->executeStatement('DROP SCHEMA IF EXISTS ' . $schema . ' CASCADE');
            $connection->close();
        }
    }

    public function testAggregateQueryUsesOnlyAllowedColumnsAndNeverOutboxOrRowPayload(): void
    {
        $connection = $this->connectionMock($this->row());
        new PostgreSqlQueueStatusSource(
            $connection,
            'queue_status_test',
        )->read(new DateTimeImmutable(self::OBSERVED_AT));

        $source = file_get_contents(dirname(__DIR__, 3) . '/src/Transport/PostgreSql/PostgreSqlQueueStatusSource.php');
        self::assertIsString($source);
        self::assertStringContainsString('fetchAssociative', $source);
        self::assertStringNotContainsString('fetchAll', $source);
        self::assertStringNotContainsString('outbox_records', $source);
        self::assertStringNotContainsString('tenant_', $source);
        self::assertStringNotContainsString('operation_id', $source);
        self::assertStringNotContainsString('encoded_payload', $source);
        self::assertStringNotContainsString('encoded_context', $source);
        self::assertStringNotContainsString('lease_owner', $source);
        self::assertStringNotContainsString('attempt_number', $source);
    }

    public function testConfigurationAndStorageFailuresHaveTypedSafeMessages(): void
    {
        $configurationConnection = $this->createMock(Connection::class);
        $configurationConnection->expects(self::never())->method('fetchAssociative');
        try {
            new PostgreSqlQueueStatusSource($configurationConnection, 'queue status secret');
            self::fail('Expected configuration failure.');
        } catch (QueueStatusSourceException $exception) {
            self::assertSame(QueueStatusFailure::Configuration, $exception->failure);
            self::assertSame('Queue status source failed.', $exception->getMessage());
        }

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->willThrowException(new RuntimeException('dsn=password-secret sql=private'));
        try {
            new PostgreSqlQueueStatusSource(
                $connection,
                'queue_status_test',
            )->read(new DateTimeImmutable(self::OBSERVED_AT));
            self::fail('Expected storage failure.');
        } catch (QueueStatusSourceException $exception) {
            self::assertSame(QueueStatusFailure::Storage, $exception->failure);
            self::assertSame('Queue status source failed.', $exception->getMessage());
            self::assertStringNotContainsString('password-secret', $exception->getMessage());
        }
    }

    public function testMalformedAggregateRowsAreIntegrityFailures(): void
    {
        $cases = [];
        $missing = $this->row();
        unset($missing['supervising']);
        $cases['missing alias'] = $missing;

        $extra = $this->row(['sentinel' => 'not-a-check']);
        $cases['extra alias'] = $extra;
        $cases['nondecimal'] = $this->row(['acceptedReady' => '01']);
        $cases['cap overflow'] = $this->row(['acceptedReady' => '9007199254740992']);
        $cases['nullability mismatch'] = $this->row([
            'acceptedReady' => '1',
            'oldestReady' => null,
        ]);
        $cases['running null lease'] = $this->row(['runningNullLeaseCount' => '1']);
        $cases['future timestamp'] = $this->row([
            'acceptedReady' => '1',
            'oldestReady' => '2026-08-27 15:00:00.000001+00',
        ]);

        foreach ($cases as $label => $row) {
            $connection = $this->connectionMock($row);
            try {
                new PostgreSqlQueueStatusSource(
                    $connection,
                    'queue_status_test',
                )->read(new DateTimeImmutable(self::OBSERVED_AT));
                self::fail("Expected integrity failure for {$label}.");
            } catch (QueueStatusSourceException $exception) {
                self::assertSame(QueueStatusFailure::Integrity, $exception->failure, $label);
                self::assertSame('Queue status source failed.', $exception->getMessage(), $label);
            }
        }
    }

    public function testMissingAggregateRowIsStorageFailure(): void
    {
        $connection = $this->connectionMock(false);

        try {
            new PostgreSqlQueueStatusSource(
                $connection,
                'queue_status_test',
            )->read(new DateTimeImmutable(self::OBSERVED_AT));
            self::fail('Expected storage failure.');
        } catch (QueueStatusSourceException $exception) {
            self::assertSame(QueueStatusFailure::Storage, $exception->failure);
        }
    }

    /** @param array<string, mixed>|false $row */
    private function connectionMock(array|false $row): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->with(
                self::callback(function (string $sql): bool {
                    self::assertStringContainsString('FROM "queue_status_test"."operations"', $sql);
                    self::assertStringContainsString('runningNullLeaseCount', $sql);
                    self::assertStringContainsString('available_at <= :observed_at', $sql);
                    self::assertStringContainsString('lease_expires_at <= :observed_at', $sql);

                    return true;
                }),
                ['observed_at' => '2026-08-27 15:00:00.000000+00:00'],
            )
            ->willReturn($row);

        return $connection;
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function row(array $overrides = []): array
    {
        return array_replace([
            'acceptedReady' => '0',
            'acceptedDelayed' => '0',
            'retryReady' => '0',
            'retryDelayed' => '0',
            'runningActive' => '0',
            'runningExpired' => '0',
            'supervising' => '0',
            'deadLettered' => '0',
            'oldestReady' => null,
            'oldestRunningExpired' => null,
            'oldestSupervising' => null,
            'oldestDeadLettered' => null,
            'runningNullLeaseCount' => '0',
        ], $overrides);
    }

    private function connection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
    }
}
