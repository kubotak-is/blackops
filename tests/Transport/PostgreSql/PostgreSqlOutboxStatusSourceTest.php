<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\OutboxRecordId;
use BlackOps\Core\Outbox\OutboxStatusFailure;
use BlackOps\Core\Outbox\OutboxStatusSnapshot;
use BlackOps\Core\Outbox\OutboxStatusSourceException;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStatusSource;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PostgreSqlOutboxStatusSourceTest extends TestCase
{
    private const string OBSERVED_AT = '2026-08-27T15:00:00.000000Z';

    public function testOneAggregateRowProjectsEveryCountAgeAndAnomalyColumn(): void
    {
        $connection = $this->connectionMock($this->row([
            'pendingReady' => '1',
            'pendingDelayed' => (string) OutboxStatusSnapshot::MAX_SAFE_INTEGER,
            'retryReady' => '1',
            'retryDelayed' => '1',
            'leasedActive' => '1',
            'leasedExpired' => '1',
            'deadLettered' => '1',
            'oldestReady' => '2026-08-27T14:59:58.750000Z',
            'oldestLeasedExpired' => '2026-08-27T14:59:59.000000Z',
            'oldestDeadLettered' => '2026-08-27T14:59:55.000000Z',
        ]));

        $snapshot = new PostgreSqlOutboxStatusSource(
            $connection,
            'outbox_status_test',
        )->read(new DateTimeImmutable(self::OBSERVED_AT));

        self::assertSame(1, $snapshot->pendingReady);
        self::assertSame(OutboxStatusSnapshot::MAX_SAFE_INTEGER, $snapshot->pendingDelayed);
        self::assertSame(1, $snapshot->retryReady);
        self::assertSame(1, $snapshot->retryDelayed);
        self::assertSame(1, $snapshot->leasedActive);
        self::assertSame(1, $snapshot->leasedExpired);
        self::assertSame(1, $snapshot->deadLettered);
        self::assertSame(1, $snapshot->oldestReady);
        self::assertSame(1, $snapshot->oldestLeasedExpired);
        self::assertSame(5, $snapshot->oldestDeadLettered);
    }

    public function testEmptyAggregateReturnsNullAgesAndUsesOneFixedFetch(): void
    {
        $connection = $this->connectionMock($this->row());

        $snapshot = new PostgreSqlOutboxStatusSource(
            $connection,
            'outbox_status_test',
        )->read(new DateTimeImmutable(self::OBSERVED_AT));

        self::assertSame(0, $snapshot->pendingReady);
        self::assertSame(0, $snapshot->pendingDelayed);
        self::assertSame(0, $snapshot->retryReady);
        self::assertSame(0, $snapshot->retryDelayed);
        self::assertSame(0, $snapshot->leasedActive);
        self::assertSame(0, $snapshot->leasedExpired);
        self::assertSame(0, $snapshot->deadLettered);
        self::assertNull($snapshot->oldestReady);
        self::assertNull($snapshot->oldestLeasedExpired);
        self::assertNull($snapshot->oldestDeadLettered);
    }

    public function testEqualityIsReadyOrExpiredAndUsesFloorSeconds(): void
    {
        $snapshot = new PostgreSqlOutboxStatusSource(
            $this->connectionMock($this->row([
                'pendingReady' => '1',
                'leasedExpired' => '1',
                'deadLettered' => '1',
                'oldestReady' => self::OBSERVED_AT,
                'oldestLeasedExpired' => self::OBSERVED_AT,
                'oldestDeadLettered' => self::OBSERVED_AT,
            ])),
            'outbox_status_test',
        )->read(new DateTimeImmutable(self::OBSERVED_AT));

        self::assertSame(1, $snapshot->pendingReady);
        self::assertSame(1, $snapshot->leasedExpired);
        self::assertSame(0, $snapshot->oldestReady);
        self::assertSame(0, $snapshot->oldestLeasedExpired);
        self::assertSame(0, $snapshot->oldestDeadLettered);
    }

    public function testAgeBoundUsesFlooredSecondsAtMaximumAndRejectsCapPlusOne(): void
    {
        $observed = DateTimeImmutable::createFromFormat(
            'U.u',
            (string) OutboxStatusSnapshot::MAX_SAFE_INTEGER . '.000000',
            new \DateTimeZone('UTC'),
        );
        self::assertInstanceOf(DateTimeImmutable::class, $observed);

        foreach (['1969-12-31T23:59:59.500000Z', '1969-12-31T23:59:59.499999Z'] as $timestamp) {
            $snapshot = new PostgreSqlOutboxStatusSource(
                $this->connectionMock($this->row([
                    'pendingReady' => '1',
                    'oldestReady' => $timestamp,
                ]), $observed),
                'outbox_status_test',
            )->read($observed);
            self::assertSame(OutboxStatusSnapshot::MAX_SAFE_INTEGER, $snapshot->oldestReady);
        }

        try {
            new PostgreSqlOutboxStatusSource(
                $this->connectionMock($this->row([
                    'pendingReady' => '1',
                    'oldestReady' => '1969-12-31T23:59:59.000000Z',
                ]), $observed),
                'outbox_status_test',
            )->read($observed);
            self::fail('Expected cap-plus-one age to fail.');
        } catch (OutboxStatusSourceException $exception) {
            self::assertSame(OutboxStatusFailure::Integrity, $exception->failure);
        }
    }

    public function testMalformedRowsAndAnomaliesFailAsIntegrity(): void
    {
        $cases = [];
        $missing = $this->row();
        unset($missing['pendingReady']);
        $cases['missing alias'] = $missing;
        $cases['extra alias'] = $this->row(['unexpected' => 'private-row']);
        foreach (['', '01', '-1', '+1', '1.0', ' 1', '1 ', '１'] as $value) {
            $cases['invalid integer ' . $value] = $this->row(['pendingReady' => $value]);
        }
        $cases['integer type'] = $this->row(['pendingReady' => 1.5]);
        $cases['integer boolean'] = $this->row(['pendingReady' => true]);
        $cases['integer overflow'] = $this->row(['pendingReady' => '9007199254740992']);
        $cases['ready age missing'] = $this->row([
            'pendingReady' => '1',
            'oldestReady' => null,
        ]);
        $cases['ready age without count'] = $this->row(['oldestReady' => self::OBSERVED_AT]);
        $cases['leased age missing'] = $this->row([
            'leasedExpired' => '1',
            'oldestLeasedExpired' => null,
        ]);
        $cases['dead age without count'] = $this->row(['oldestDeadLettered' => self::OBSERVED_AT]);
        foreach ([
            '2026-08-27T14:59:59+00:00',
            '2026-08-27T14:59:59.000000 UTC',
            '2026-08-27T14:59:59Z',
            '2026-08-27T14:59:59.000Z',
            ' 2026-08-27T14:59:59.000000Z',
            '2026-08-27T14:59:59.000000Z ',
            '2026-02-30T14:59:59.000000Z',
        ] as $value) {
            $cases['invalid timestamp ' . $value] = $this->row([
                'pendingReady' => '1',
                'oldestReady' => $value,
            ]);
        }
        $cases['timestamp type'] = $this->row([
            'pendingReady' => '1',
            'oldestReady' => new DateTimeImmutable(self::OBSERVED_AT),
        ]);
        $cases['future timestamp'] = $this->row([
            'pendingReady' => '1',
            'oldestReady' => '2026-08-27T15:00:00.000001Z',
        ]);
        $cases['retry null next attempt anomaly'] = $this->row(['retryNullNextAttemptCount' => '1']);
        $cases['leased null lease anomaly'] = $this->row(['leasedNullLeaseCount' => '1']);
        $cases['dead timestamp anomaly'] = $this->row(['deadLetterInvalidTimestampCount' => '1']);

        foreach ($cases as $label => $row) {
            try {
                new PostgreSqlOutboxStatusSource(
                    $this->connectionMock($row),
                    'outbox_status_test',
                )->read(new DateTimeImmutable(self::OBSERVED_AT));
                self::fail("Expected integrity failure for {$label}.");
            } catch (OutboxStatusSourceException $exception) {
                self::assertSame(OutboxStatusFailure::Integrity, $exception->failure, $label);
                self::assertSame('Outbox status source failed.', $exception->getMessage(), $label);
            }
        }
    }

    public function testTimestampAtTheExactContractLengthMustBeCanonicalUtc(): void
    {
        $cases = [
            'non-string timestamp' => ['oldestReady' => 1],
            'null timestamp with count' => ['pendingReady' => '1', 'oldestReady' => null],
        ];
        foreach ($cases as $label => $overrides) {
            try {
                new PostgreSqlOutboxStatusSource(
                    $this->connectionMock($this->row($overrides)),
                    'outbox_status_test',
                )->read(new DateTimeImmutable(self::OBSERVED_AT));
                self::fail("Expected integrity failure for {$label}.");
            } catch (OutboxStatusSourceException $exception) {
                self::assertSame(OutboxStatusFailure::Integrity, $exception->failure, $label);
            }
        }
    }

    public function testMissingRowAndConnectionFailureAreStorageFailures(): void
    {
        try {
            new PostgreSqlOutboxStatusSource(
                $this->connectionMock(false),
                'outbox_status_test',
            )->read(new DateTimeImmutable(self::OBSERVED_AT));
            self::fail('Expected missing aggregate row to fail.');
        } catch (OutboxStatusSourceException $exception) {
            self::assertSame(OutboxStatusFailure::Storage, $exception->failure);
        }

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->willThrowException(new RuntimeException('dsn=password-secret sql=private-row'));
        try {
            new PostgreSqlOutboxStatusSource(
                $connection,
                'outbox_status_test',
            )->read(new DateTimeImmutable(self::OBSERVED_AT));
            self::fail('Expected connection failure.');
        } catch (OutboxStatusSourceException $exception) {
            self::assertSame(OutboxStatusFailure::Storage, $exception->failure);
            self::assertSame('Outbox status source failed.', $exception->getMessage());
            self::assertStringNotContainsString('password-secret', $exception->getMessage());
            self::assertStringNotContainsString('private-row', $exception->getMessage());
        }
    }

    public function testMissingSchemaIsStorageFailureWithoutLeakingDatabaseDetails(): void
    {
        $schema = 'blackops_outbox_status_missing';
        $connection = $this->connection();
        $connection->executeStatement('DROP SCHEMA IF EXISTS "' . $schema . '" CASCADE');
        $connection->executeStatement('CREATE SCHEMA "' . $schema . '"');

        try {
            new PostgreSqlOutboxStatusSource($connection, $schema)->read(new DateTimeImmutable(self::OBSERVED_AT));
            self::fail('Expected missing schema to fail closed.');
        } catch (OutboxStatusSourceException $exception) {
            self::assertSame(OutboxStatusFailure::Storage, $exception->failure);
            self::assertSame('Outbox status source failed.', $exception->getMessage());
            self::assertStringNotContainsString($schema, $exception->getMessage());
            self::assertStringNotContainsString('outbox_records', $exception->getMessage());
            self::assertStringNotContainsString('SELECT', $exception->getMessage());
        } finally {
            $connection->close();
        }
    }

    public function testInvalidSchemaIsConfigurationFailureWithoutARead(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('fetchAssociative');

        try {
            new PostgreSqlOutboxStatusSource($connection, 'outbox status secret');
            self::fail('Expected invalid schema to fail.');
        } catch (OutboxStatusSourceException $exception) {
            self::assertSame(OutboxStatusFailure::Configuration, $exception->failure);
            self::assertSame('Outbox status source failed.', $exception->getMessage());
        }
    }

    public function testAggregateQueryIsOneBoundedOutboxRecordsReadWithoutForbiddenColumns(): void
    {
        new PostgreSqlOutboxStatusSource(
            $this->connectionMock($this->row()),
            'outbox_status_test',
        )->read(new DateTimeImmutable(self::OBSERVED_AT));
    }

    public function testPostgreSqlAggregateCountsMultipleTenantsAndExcludesSentRows(): void
    {
        $schema = 'blackops_outbox_status_source';
        $connection = $this->connection();
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . $schema . ' CASCADE');

        try {
            $store = new PostgreSqlOutboxStore(
                $connection,
                PostgreSqlTestStorageProtection::codec(),
                new ReflectionJsonOperationCodec(),
                $schema,
            );
            $store->migrate();
            $records = [
                ['019f70ab-7000-7000-8000-000000000001', '2026-08-27T15:00:00Z', 'tenant-a'],
                ['019f70ab-7000-7000-8000-000000000002', '2026-08-27T14:59:00Z', 'tenant-b'],
                ['019f70ab-7000-7000-8000-000000000003', '2026-08-27T14:58:00Z', 'tenant-b'],
                ['019f70ab-7000-7000-8000-000000000004', '2026-08-27T15:02:00Z', 'tenant-a'],
                ['019f70ab-7000-7000-8000-000000000005', '2026-08-27T15:00:00Z', 'tenant-a'],
                ['019f70ab-7000-7000-8000-000000000006', '2026-08-27T15:00:00Z', 'tenant-b'],
                ['019f70ab-7000-7000-8000-000000000007', '2026-08-27T15:00:00Z', 'tenant-b'],
                ['019f70ab-7000-7000-8000-000000000008', '2026-08-27T15:00:00Z', 'tenant-a'],
            ];
            foreach ($records as $index => [$id, $availableAt, $tenantId]) {
                $store->insert($this->record(
                    $id,
                    '019f70ac-7000-7000-8000-' . str_pad((string) ($index + 1), 12, '0', STR_PAD_LEFT),
                    new DateTimeImmutable($availableAt),
                    new TenantRef('customer', $tenantId),
                ));
            }

            $table = '"' . $schema . '"."outbox_records"';
            $connection->executeStatement("UPDATE {$table} SET next_attempt_at='2026-08-27T15:01:00Z'
                 WHERE record_id='019f70ab-7000-7000-8000-000000000002'");
            $connection->executeStatement(
                "UPDATE {$table} SET state='retry_scheduled', next_attempt_at='2026-08-27T14:58:00Z'
                 WHERE record_id='019f70ab-7000-7000-8000-000000000003'",
            );
            $connection->executeStatement(
                "UPDATE {$table} SET state='retry_scheduled', next_attempt_at='2026-08-27T15:02:00Z'
                 WHERE record_id='019f70ab-7000-7000-8000-000000000004'",
            );
            $connection->executeStatement("UPDATE {$table} SET state='leased', lease_expires_at='2026-08-27T15:01:00Z'
                 WHERE record_id='019f70ab-7000-7000-8000-000000000005'");
            $connection->executeStatement("UPDATE {$table} SET state='leased', lease_expires_at='2026-08-27T15:00:00Z'
                 WHERE record_id='019f70ab-7000-7000-8000-000000000006'");
            $connection->executeStatement(
                "UPDATE {$table} SET state='dead_lettered', dead_lettered_at='2026-08-27T14:59:55Z'
                 WHERE record_id='019f70ab-7000-7000-8000-000000000007'",
            );
            $connection->executeStatement(
                "UPDATE {$table} SET state='sent' WHERE record_id='019f70ab-7000-7000-8000-000000000008'",
            );

            $statusSource = new PostgreSqlOutboxStatusSource($connection, $schema);
            $snapshot = $statusSource->read(new DateTimeImmutable(self::OBSERVED_AT));

            self::assertSame(1, $snapshot->pendingReady);
            self::assertSame(1, $snapshot->pendingDelayed);
            self::assertSame(1, $snapshot->retryReady);
            self::assertSame(1, $snapshot->retryDelayed);
            self::assertSame(1, $snapshot->leasedActive);
            self::assertSame(1, $snapshot->leasedExpired);
            self::assertSame(1, $snapshot->deadLettered);
            self::assertSame(120, $snapshot->oldestReady);
            self::assertSame(0, $snapshot->oldestLeasedExpired);
            self::assertSame(5, $snapshot->oldestDeadLettered);

            $connection->executeStatement("UPDATE {$table} SET state='sent'");
            $empty = $statusSource->read(new DateTimeImmutable(self::OBSERVED_AT));
            self::assertSame(0, $empty->pendingReady);
            self::assertSame(0, $empty->pendingDelayed);
            self::assertSame(0, $empty->retryReady);
            self::assertSame(0, $empty->retryDelayed);
            self::assertSame(0, $empty->leasedActive);
            self::assertSame(0, $empty->leasedExpired);
            self::assertSame(0, $empty->deadLettered);
            self::assertNull($empty->oldestReady);
            self::assertNull($empty->oldestLeasedExpired);
            self::assertNull($empty->oldestDeadLettered);
        } finally {
            $connection->executeStatement('DROP SCHEMA IF EXISTS ' . $schema . ' CASCADE');
            $connection->close();
        }
    }

    public function testPostgreSqlAnomalyRowsFailClosedWithTypedIntegrity(): void
    {
        $schema = 'blackops_outbox_status_anomalies';
        $connection = $this->connection();
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . $schema . ' CASCADE');

        try {
            $store = new PostgreSqlOutboxStore(
                $connection,
                PostgreSqlTestStorageProtection::codec(),
                new ReflectionJsonOperationCodec(),
                $schema,
            );
            $store->migrate();
            $table = '"' . $schema . '"."outbox_records"';
            $source = new PostgreSqlOutboxStatusSource($connection, $schema);
            $observed = new DateTimeImmutable(self::OBSERVED_AT);
            $cases = [
                'retry null next attempt' => static function (Connection $database, string $qualifiedTable): void {
                    $database->executeStatement(
                        "UPDATE {$qualifiedTable} SET state='retry_scheduled', next_attempt_at=NULL",
                    );
                },
                'leased null lease' => static function (Connection $database, string $qualifiedTable): void {
                    $database->executeStatement("UPDATE {$qualifiedTable} SET state='leased', lease_expires_at=NULL");
                },
                'dead null timestamp' => static function (Connection $database, string $qualifiedTable): void {
                    $database->executeStatement(
                        "UPDATE {$qualifiedTable} SET state='dead_lettered', dead_lettered_at=NULL",
                    );
                },
                'dead future timestamp' => static function (Connection $database, string $qualifiedTable): void {
                    $database->executeStatement(
                        "UPDATE {$qualifiedTable} SET state='dead_lettered', dead_lettered_at='2026-08-27T15:00:00.000001Z'",
                    );
                },
            ];

            foreach ($cases as $label => $mutate) {
                $connection->executeStatement("TRUNCATE {$table}");
                $store->insert($this->record(
                    '019f70ab-7000-7000-8000-000000000009',
                    '019f70ac-7000-7000-8000-000000000009',
                    $observed,
                    new TenantRef('customer', 'tenant-anomaly'),
                ));
                $mutate($connection, $table);

                try {
                    $source->read($observed);
                    self::fail("Expected {$label} to fail closed.");
                } catch (OutboxStatusSourceException $exception) {
                    self::assertSame(OutboxStatusFailure::Integrity, $exception->failure, $label);
                    self::assertSame('Outbox status source failed.', $exception->getMessage(), $label);
                    self::assertStringNotContainsString($schema, $exception->getMessage(), $label);
                    self::assertStringNotContainsString('tenant-anomaly', $exception->getMessage(), $label);
                }
            }
        } finally {
            $connection->executeStatement('DROP SCHEMA IF EXISTS ' . $schema . ' CASCADE');
            $connection->close();
        }
    }

    /** @param array<string, mixed>|false $row */
    private function connectionMock(array|false $row, ?DateTimeImmutable $observedAt = null): Connection
    {
        $observedAt ??= new DateTimeImmutable(self::OBSERVED_AT);
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->with(
                self::callback(function (string $sql): bool {
                    self::assertStringContainsString('FROM "outbox_status_test"."outbox_records"', $sql);
                    self::assertStringContainsString('COALESCE(next_attempt_at, available_at)', $sql);
                    self::assertStringContainsString("AT TIME ZONE 'UTC'", $sql);
                    foreach ([
                        'pendingReady',
                        'pendingDelayed',
                        'retryReady',
                        'retryDelayed',
                        'leasedActive',
                        'leasedExpired',
                        'deadLettered',
                        'oldestReady',
                        'oldestLeasedExpired',
                        'oldestDeadLettered',
                        'retryNullNextAttemptCount',
                        'leasedNullLeaseCount',
                        'deadLetterInvalidTimestampCount',
                    ] as $alias) {
                        self::assertStringContainsString('"' . $alias . '"', $sql);
                    }
                    foreach ([
                        'operations',
                        'dead_letters',
                        'outbox_dead_letter_retry_audits',
                        'operation_id',
                        'record_id',
                        'tenant_id',
                        'encoded_payload',
                        'encoded_context',
                        'connection_name',
                        'attempt_count',
                        'state_version',
                        'leased_at',
                        'sent_at',
                        'failure_fingerprint',
                        'relay_id',
                    ] as $forbidden) {
                        self::assertStringNotContainsString($forbidden, $sql);
                    }
                    self::assertStringNotContainsString('fetchAll', $sql);

                    return true;
                }),
                [
                    'observed_at' => $observedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP'),
                ],
            )
            ->willReturn($row);

        return $connection;
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function row(array $overrides = []): array
    {
        return array_replace([
            'pendingReady' => '0',
            'pendingDelayed' => '0',
            'retryReady' => '0',
            'retryDelayed' => '0',
            'leasedActive' => '0',
            'leasedExpired' => '0',
            'deadLettered' => '0',
            'oldestReady' => null,
            'oldestLeasedExpired' => null,
            'oldestDeadLettered' => null,
            'retryNullNextAttemptCount' => '0',
            'leasedNullLeaseCount' => '0',
            'deadLetterInvalidTimestampCount' => '0',
        ], $overrides);
    }

    private function record(
        string $recordId,
        string $operationId,
        DateTimeImmutable $availableAt,
        TenantRef $tenant,
    ): PostgreSqlOutboxRecord {
        return new PostgreSqlOutboxRecord(
            OutboxRecordId::fromString($recordId),
            OperationId::fromString($operationId),
            'outbox.status.fixture',
            1,
            '{"opaque":"payload"}',
            '{"opaque":"context"}',
            $availableAt,
            $availableAt,
            'app',
            tenant: $tenant,
        );
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
