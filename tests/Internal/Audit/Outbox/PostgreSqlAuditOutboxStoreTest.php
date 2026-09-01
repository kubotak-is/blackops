<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Outbox;

use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditRecordAction;
use BlackOps\Core\Audit\AuditRecordActor;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditRecordEvent;
use BlackOps\Core\Audit\AuditRecordEvidence;
use BlackOps\Core\Audit\AuditRecordOutcome;
use BlackOps\Core\Audit\AuditRecordPolicy;
use BlackOps\Core\Audit\AuditRecordResource;
use BlackOps\Core\Audit\AuditRecordSource;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Outbox\AuditOutboxLookupResult;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreException;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreFailure;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxSchema;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordStamp;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\StorageProtection\StorageKey;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class PostgreSqlAuditOutboxStoreTest extends TestCase
{
    private const string REAL_SCHEMA = 'blackops_audit_outbox_store_real';

    private const string SYSTEM_PARTITION = 'system:global';

    private const string NOW = '2026-09-01T03:00:00.123456Z';

    private const string FIRST_RECORD_ID = '01941f29-7c00-7000-8000-000000000001';

    private const string SECOND_RECORD_ID = '01941f29-7c00-7000-8000-000000000002';

    private const string THIRD_RECORD_ID = '01941f29-7c00-7000-8000-000000000003';

    private const string TOKEN = '01941f29-7c00-7000-8000-000000000009';

    private const string NEXT_TOKEN = '01941f29-7c00-7000-8000-00000000000a';

    private const string RACE_PARENT_RECORD_ID = '01941f29-7c00-7000-8000-000000000011';

    private const string RACE_CHILD_RECORD_ID = '01941f29-7c00-7000-8000-000000000012';

    private const string RACE_RETRY_RECORD_ID = '01941f29-7c00-7000-8000-000000000013';

    private const string RACE_TENANT_RECORD_ID = '01941f29-7c00-7000-8000-000000000014';

    private const string CLAIM_PARENT_TOKEN = '01941f29-7c00-7000-8000-000000000015';

    private const string CLAIM_CHILD_TOKEN = '01941f29-7c00-7000-8000-000000000016';

    private const string LOCK_EXISTING_RECORD_ID = '01941f29-7c00-7000-8000-000000000017';

    private const string LOCK_BLOCKED_RECORD_ID = '01941f29-7c00-7000-8000-000000000018';

    private const string LOCK_RETRY_RECORD_ID = '01941f29-7c00-7000-8000-000000000019';

    private const string LOCK_TENANT_RECORD_ID = '01941f29-7c00-7000-8000-00000000001a';

    private const string LOCK_PARTITION_RECORD_ID = '01941f29-7c00-7000-8000-00000000001b';

    public function testAutoCommitFalseIsTheFirstFailureForEveryPublicOperation(): void
    {
        $operations = [
            fn(PostgreSqlAuditOutboxStore $store): mixed => $store->appendDurable($this->durableDraft(), 'key'),
            fn(PostgreSqlAuditOutboxStore $store): mixed => $store->find(
                self::FIRST_RECORD_ID,
                null,
                self::SYSTEM_PARTITION,
            ),
            fn(PostgreSqlAuditOutboxStore $store): mixed => $store->claimNext(),
            fn(PostgreSqlAuditOutboxStore $store): mixed => $store->recoverNextExpired(),
            fn(PostgreSqlAuditOutboxStore $store): mixed => $store->retry(
                self::FIRST_RECORD_ID,
                null,
                self::SYSTEM_PARTITION,
                1,
                self::TOKEN,
            ),
            fn(PostgreSqlAuditOutboxStore $store): mixed => $store->relay(
                self::FIRST_RECORD_ID,
                null,
                self::SYSTEM_PARTITION,
                1,
                self::TOKEN,
            ),
            fn(PostgreSqlAuditOutboxStore $store): mixed => $store->poison(
                self::FIRST_RECORD_ID,
                null,
                self::SYSTEM_PARTITION,
                1,
                self::TOKEN,
            ),
        ];

        foreach ($operations as $operation) {
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(false);
            $this->expectNoConnectionUse($connection, ['isAutoCommit']);
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $operation($this->store($connection, $clock, $uuids));
                self::fail('auto-commit false was accepted');
            } catch (AuditOutboxStoreException $exception) {
                self::assertSame(AuditOutboxStoreFailure::StorageUnavailable, $exception->failure());
                self::assertSame('Audit outbox persistence failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testInvalidTransactionStatesAreRejectedBeforeSqlOrResources(): void
    {
        foreach ([
            [false, 1],
            [true, 0],
            [true, 2],
        ] as [$active, $level]) {
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn($active);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn($level);
            $this->expectNoConnectionUse($connection, [
                'isAutoCommit',
                'isTransactionActive',
                'getTransactionNestingLevel',
            ]);
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids)->appendDurable($this->durableDraft(), 'key');
                self::fail('invalid mutation transaction state was accepted');
            } catch (AuditOutboxStoreException $exception) {
                self::assertSame(AuditOutboxStoreFailure::StorageUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }

        foreach ([
            [false, 1],
            [true, 0],
            [true, 2],
        ] as [$active, $level]) {
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn($active);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn($level);
            $this->expectNoConnectionUse($connection, [
                'isAutoCommit',
                'isTransactionActive',
                'getTransactionNestingLevel',
            ]);
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids)->find(self::FIRST_RECORD_ID, null, self::SYSTEM_PARTITION);
                self::fail('invalid read transaction state was accepted');
            } catch (AuditOutboxStoreException $exception) {
                self::assertSame(AuditOutboxStoreFailure::StorageUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testOwnedAndCallerLevelOneBootstrapAppendHaveExactOrderAndNoLeak(): void
    {
        foreach ([false, true] as $callerOwned) {
            $events = [];
            $connection = $this->appendConnection(
                $callerOwned,
                $events,
                state: false,
                aggregate: $this->aggregate(0, null, null, 0),
                orphan: null,
                idempotency: [],
                writeResult: '1',
            );
            $clock = new FixtureClock(static function () use (&$events): void {
                $events[] = 'clock';
            }, new \DateTimeImmutable(self::NOW));
            $uuids = new SequenceUuidGenerator([
                self::FIRST_RECORD_ID,
            ], static function () use (&$events): void {
                $events[] = 'uuid';
            });

            $result = $this->store($connection, $clock, $uuids, onUse: static function (string $event) use (
                &$events,
            ): void {
                $events[] = $event;
            })->appendDurable($this->durableDraft(), 'bootstrap-key');

            self::assertTrue($result->inserted());
            self::assertSame(1, $result->record()->toArray()['ingressOrdinal']);
            self::assertSame(self::NOW, $result->record()->toArray()['availableAt']);
            self::assertSame(
                [
                    'partition-lock',
                    'state',
                    'aggregate',
                    'orphan',
                    'idempotency',
                    'clock',
                    'uuid',
                    'record-lock',
                    'record-probe',
                    'key',
                    'nonce',
                    'write',
                ],
                $events,
            );
        }
    }

    public function testCallerOwnedCountFenceDoesNotTakeTransactionControl(): void
    {
        $events = [];
        $connection = $this->appendConnection(
            callerOwned: true,
            events: $events,
            state: false,
            aggregate: $this->aggregate(0, null, null, 0),
            orphan: null,
            idempotency: [],
            writeResult: '0',
        );
        $clock = new FixtureClock(null, new \DateTimeImmutable(self::NOW));
        $uuids = new SequenceUuidGenerator([self::FIRST_RECORD_ID]);

        try {
            $this->store($connection, $clock, $uuids)->appendDurable($this->durableDraft(), 'fence-key');
            self::fail('count fence failure was swallowed');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('fence', $exception->getMessage());
        }
    }

    public function testBootstrapAndExistingAppendUseSeparateDependencyOrderedCtes(): void
    {
        $cases = [
            'bootstrap' => [
                'state' => false,
                'aggregate' => $this->aggregate(0, null, null, 0),
                'key' => 'cte-bootstrap-key',
                'recordId' => self::FIRST_RECORD_ID,
                'opposite' => 'advanced_state AS',
                'expected' => 'inserted_state AS',
            ],
            'existing' => [
                'state' => $this->state(null, self::NOW, 1),
                'aggregate' => $this->aggregate(1, 1, 1, 0),
                'key' => 'cte-existing-key',
                'recordId' => self::SECOND_RECORD_ID,
                'opposite' => 'inserted_state AS',
                'expected' => 'advanced_state AS',
            ],
        ];

        foreach ($cases as $label => $case) {
            $events = [];
            $queries = [];
            $connection = $this->appendConnection(
                callerOwned: false,
                events: $events,
                state: $case['state'],
                aggregate: $case['aggregate'],
                orphan: null,
                idempotency: [],
                writeResult: '1',
                queries: $queries,
            );
            $result = $this->store(
                $connection,
                new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                new SequenceUuidGenerator([$case['recordId']]),
            )->appendDurable($this->durableDraft(), $case['key']);

            self::assertTrue($result->inserted(), $label);
            $writes = array_values(array_filter($queries, static fn(array $query): bool => str_contains(
                $query['sql'],
                'audit_outbox_abort',
            )));
            self::assertCount(1, $writes, $label);
            $sql = $writes[0]['sql'];
            self::assertStringContainsString('WITH ' . $case['expected'], $sql, $label);
            self::assertStringNotContainsString($case['opposite'], $sql, $label);
            self::assertStringContainsString('RETURNING partition_id', $sql, $label);
            self::assertStringContainsString('SELECT committed FROM fence', $sql, $label);
            self::assertStringContainsString("ELSE 'audit_outbox_abort' END AS bigint", $sql, $label);
            if ($label === 'bootstrap') {
                self::assertStringContainsString('FROM inserted_state', $sql);
                self::assertStringNotContainsString('UPDATE ', $sql);
            } else {
                self::assertStringContainsString('FROM advanced_state', $sql);
                self::assertStringContainsString('UPDATE ', $sql);
                self::assertStringNotContainsString('INSERT INTO "blackops"."audit_outbox_partition_state"', $sql);
            }
        }
    }

    public function testSameKeyRetryReturnsOriginalRowWithZeroNewResourceWork(): void
    {
        $draft = $this->durableDraft();
        $key = 'same-key';
        $row = $this->outboxRow($draft, self::FIRST_RECORD_ID, self::NOW, 1, $key);
        $events = [];
        $connection = $this->appendConnection(
            callerOwned: false,
            events: $events,
            state: $this->state(null, self::NOW, 1),
            aggregate: $this->aggregate(1, 1, 1, 0),
            orphan: null,
            idempotency: [$row],
            writeResult: null,
        );
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        $result = $this->store($connection, $clock, $uuids, onUse: static function (string $event) use (
            &$events,
        ): void {
            $events[] = $event;
        })->appendDurable($draft, $key);

        self::assertFalse($result->inserted());
        self::assertSame($row['protected_body'], $result->record()->toArray()['protectedBody']);
        self::assertSame($row['record_id'], $result->record()->toArray()['recordId']);
        self::assertSame(['partition-lock', 'state', 'aggregate', 'idempotency', 'key'], $events);
    }

    public function testAuthenticatedRetryMismatchesAndTamperingFailClosedWithoutMutation(): void
    {
        $draft = $this->durableDraft();
        $storedKey = 'stored-key';
        $baseRow = $this->outboxRow($draft, self::FIRST_RECORD_ID, self::NOW, 1, $storedKey);
        $cases = [
            'raw key substitution' => [
                'row' => $baseRow,
                'draft' => $draft,
                'key' => 'different-key',
                'failure' => AuditOutboxStoreFailure::IdempotencyConflict,
            ],
            'draft substitution' => [
                'row' => $baseRow,
                'draft' => $this->draftWithResource('different-operation'),
                'key' => $storedKey,
                'failure' => AuditOutboxStoreFailure::IdempotencyConflict,
            ],
            'content hash substitution' => [
                'row' => [...$baseRow, 'content_hash' => str_repeat('b', 64)],
                'draft' => $draft,
                'key' => $storedKey,
                'failure' => AuditOutboxStoreFailure::IntegrityInvalid,
            ],
            'idempotency hash substitution' => [
                'row' => [...$baseRow, 'idempotency_key_hash' => str_repeat('c', 64)],
                'draft' => $draft,
                'key' => $storedKey,
                'failure' => AuditOutboxStoreFailure::IntegrityInvalid,
            ],
            'body substitution' => [
                'row' => [...$baseRow, 'protected_body' => $baseRow['protected_body'] . 'x'],
                'draft' => $draft,
                'key' => $storedKey,
                'failure' => AuditOutboxStoreFailure::IntegrityInvalid,
            ],
            'record id substitution' => [
                'row' => [...$baseRow, 'record_id' => self::SECOND_RECORD_ID],
                'draft' => $draft,
                'key' => $storedKey,
                'failure' => AuditOutboxStoreFailure::IntegrityInvalid,
            ],
        ];

        foreach ($cases as $label => $case) {
            $events = [];
            $connection = $this->appendConnection(
                callerOwned: false,
                events: $events,
                state: $this->state(null, self::NOW, 1),
                aggregate: $this->aggregate(1, 1, 1, 0),
                orphan: null,
                idempotency: [$case['row']],
                writeResult: null,
                expectSuccess: false,
            );
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids, onUse: static function (string $event) use (&$events): void {
                    $events[] = $event;
                })->appendDurable($case['draft'], $case['key']);
                self::fail($label . ' was accepted');
            } catch (AuditOutboxStoreException $exception) {
                self::assertSame($case['failure'], $exception->failure(), $label);
                self::assertNull($exception->getPrevious(), $label);
                self::assertStringNotContainsString('different-key', $exception->getMessage(), $label);
                $this->assertNoCanaryOnExceptionSurfaces($exception, 'outbox-retry-canary');
            }
            $expectedEvents = ['partition-lock', 'state', 'aggregate', 'idempotency'];
            if ($label !== 'body substitution') {
                $expectedEvents[] = 'key';
            }
            self::assertSame($expectedEvents, $events, $label);
        }
    }

    public function testTenantAndPartitionBindingRejectsBeforeKeyResolution(): void
    {
        $tenant = new AuditTypedReference('account', 'tenant-a');
        $draft = $this->durableDraft($tenant);
        $row = $this->outboxRow($draft, self::FIRST_RECORD_ID, self::NOW, 1, 'tenant-key');
        $wrongPartitionRow = [
            ...$row,
            'partition_id' => self::SYSTEM_PARTITION,
            'tenant_type' => null,
            'tenant_id' => null,
        ];
        $events = [];
        $connection = $this->appendConnection(
            callerOwned: false,
            events: $events,
            state: $this->state($tenant, self::NOW, 1),
            aggregate: $this->aggregate(1, 1, 1, 0),
            orphan: null,
            idempotency: [$wrongPartitionRow],
            writeResult: null,
            expectSuccess: false,
        );
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        try {
            $this->store($connection, $clock, $uuids, onUse: static function (string $event) use (&$events): void {
                $events[] = $event;
            })->appendDurable($draft, 'tenant-key');
            self::fail('cross-partition row was accepted');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame(['partition-lock', 'state', 'aggregate', 'idempotency'], $events);
    }

    public function testRetainedAggregateCorruptionStopsBeforeClockUuidAndProtection(): void
    {
        $tenant = new AuditTypedReference('account', 'tenant-a');
        $cases = [
            'missing state with orphan aggregate' => [
                'tenant' => null,
                'state' => false,
                'aggregate' => $this->aggregate(1, 1, 1, 0),
                'orphan' => null,
            ],
            'state ahead' => [
                'tenant' => null,
                'state' => $this->state(null, self::NOW, 2),
                'aggregate' => $this->aggregate(1, 1, 1, 0),
                'orphan' => null,
            ],
            'state behind' => [
                'tenant' => null,
                'state' => $this->state(null, self::NOW, 1),
                'aggregate' => $this->aggregate(2, 1, 2, 0),
                'orphan' => null,
            ],
            'interior gap' => [
                'tenant' => null,
                'state' => $this->state(null, self::NOW, 2),
                'aggregate' => $this->aggregate(2, 1, 3, 0),
                'orphan' => null,
            ],
            'tenant mismatch count' => [
                'tenant' => $tenant,
                'state' => $this->state($tenant, self::NOW, 1),
                'aggregate' => $this->aggregate(1, 1, 1, 1),
                'orphan' => null,
            ],
            'system head tenant mismatch' => [
                'tenant' => null,
                'state' => $this->state(null, self::NOW, 1),
                'aggregate' => $this->aggregate(1, 1, 1, 1),
                'orphan' => null,
            ],
            'tenant interior tenant mismatch' => [
                'tenant' => $tenant,
                'state' => $this->state($tenant, self::NOW, 2),
                'aggregate' => $this->aggregate(2, 1, 2, 1),
                'orphan' => null,
            ],
            'zero row state' => [
                'tenant' => $tenant,
                'state' => $this->state($tenant, self::NOW, 1),
                'aggregate' => $this->aggregate(0, null, null, 0),
                'orphan' => null,
            ],
            'missing state explicit orphan probe' => [
                'tenant' => null,
                'state' => false,
                'aggregate' => $this->aggregate(0, null, null, 0),
                'orphan' => '1',
            ],
        ];

        foreach ($cases as $label => $case) {
            $events = [];
            $connection = $this->appendConnection(
                callerOwned: false,
                events: $events,
                state: $case['state'],
                aggregate: $case['aggregate'],
                orphan: $case['orphan'],
                idempotency: [],
                writeResult: null,
            );
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids)->appendDurable(
                    $this->durableDraft($case['tenant']),
                    'aggregate-' . $label,
                );
                self::fail($label . ' was accepted');
            } catch (AuditOutboxStoreException $exception) {
                self::assertSame(AuditOutboxStoreFailure::IntegrityInvalid, $exception->failure(), $label);
                self::assertNull($exception->getPrevious(), $label);
            }
            self::assertContains('partition-lock', $events, $label);
            self::assertContains('state', $events, $label);
            self::assertContains('aggregate', $events, $label);
            self::assertNotContains('clock', $events, $label);
            self::assertNotContains('uuid', $events, $label);
            self::assertNotContains('key', $events, $label);
            self::assertNotContains('nonce', $events, $label);
            self::assertNotContains('write', $events, $label);
        }
    }

    public function testGeneratedRecordIdCollisionAndCapacityFailBeforeFinalizerOrProtection(): void
    {
        $events = [];
        $connection = $this->appendConnection(
            callerOwned: false,
            events: $events,
            state: false,
            aggregate: $this->aggregate(0, null, null, 0),
            orphan: null,
            idempotency: [],
            writeResult: 'collision',
        );
        $clock = new FixtureClock(static function () use (&$events): void {
            $events[] = 'clock';
        }, new \DateTimeImmutable(self::NOW));
        $uuids = new SequenceUuidGenerator([self::FIRST_RECORD_ID], static function () use (&$events): void {
            $events[] = 'uuid';
        });
        try {
            $this->store($connection, $clock, $uuids, onUse: static function (string $event) use (&$events): void {
                $events[] = $event;
            })->appendDurable($this->durableDraft(), 'collision-key');
            self::fail('generated record collision was accepted');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::ResourceUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame(
            [
                'partition-lock',
                'state',
                'aggregate',
                'orphan',
                'idempotency',
                'clock',
                'uuid',
                'record-lock',
                'record-probe',
            ],
            $events,
        );

        foreach ([
            'ordinal max' => ['lastIngressOrdinal' => 9_007_199_254_740_991, 'stateVersion' => 9_007_199_254_740_991],
            'version max' => ['lastIngressOrdinal' => 9_007_199_254_740_991, 'stateVersion' => 9_007_199_254_740_991],
        ] as $label => $values) {
            $events = [];
            $state = $this->state(null, self::NOW, $values['lastIngressOrdinal']);
            $connection = $this->appendConnection(
                callerOwned: false,
                events: $events,
                state: $state,
                aggregate: $this->aggregate($values['lastIngressOrdinal'], 1, $values['lastIngressOrdinal'], 0),
                orphan: null,
                idempotency: [],
                writeResult: null,
            );
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');
            try {
                $this->store($connection, $clock, $uuids)->appendDurable($this->durableDraft(), 'capacity-' . $label);
                self::fail($label . ' was accepted');
            } catch (AuditOutboxStoreException $exception) {
                self::assertSame(AuditOutboxStoreFailure::ResourceUnavailable, $exception->failure(), $label);
                self::assertNull($exception->getPrevious(), $label);
            }
            self::assertNotContains('clock', $events, $label);
            self::assertNotContains('uuid', $events, $label);
            self::assertNotContains('key', $events, $label);
            self::assertNotContains('write', $events, $label);
        }
    }

    public function testOwnedBeginCommitAndRollbackFailuresCollapseAndQuarantine(): void
    {
        $begin = $this->createMock(Connection::class);
        $begin->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $begin->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $begin->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $begin
            ->expects(self::once())
            ->method('beginTransaction')
            ->willReturnCallback(static function (): never {
                throw new \RuntimeException('begin-canary');
            });
        $begin->expects(self::once())->method('close');
        $this->expectNoConnectionUse($begin, [
            'isAutoCommit',
            'isTransactionActive',
            'getTransactionNestingLevel',
            'beginTransaction',
            'close',
        ]);
        try {
            $this->store($begin)->appendDurable($this->durableDraft(), 'begin-key');
            self::fail('begin failure was accepted');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
            $this->assertNoCanaryOnExceptionSurfaces($exception, 'begin-canary');
        }

        $events = [];
        $connection = $this->appendConnection(
            callerOwned: false,
            events: $events,
            state: false,
            aggregate: $this->aggregate(0, null, null, 0),
            orphan: null,
            idempotency: [],
            writeResult: '1',
            commitFailure: true,
        );
        $clock = new FixtureClock(null, new \DateTimeImmutable(self::NOW));
        $uuids = new SequenceUuidGenerator([self::FIRST_RECORD_ID]);
        try {
            $this->store($connection, $clock, $uuids)->appendDurable($this->durableDraft(), 'commit-key');
            self::fail('commit failure was accepted');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
            $this->assertNoCanaryOnExceptionSurfaces($exception, 'commit-canary');
        }
    }

    public function testFindUsesExactRecordPartitionTenantPredicatesWithoutTransactionControl(): void
    {
        $tenant = new AuditTypedReference('account', 'tenant-a');
        $cases = [
            [
                'tenant' => null,
                'partition' => self::SYSTEM_PARTITION,
                'row' => $this->outboxRow($this->durableDraft(), self::FIRST_RECORD_ID, self::NOW, 1, 'find-system'),
                'predicate' => 'tenant_type IS NULL AND tenant_id IS NULL',
                'parameters' => ['record_id', 'partition_id'],
            ],
            [
                'tenant' => $tenant,
                'partition' => $this->partition($tenant),
                'row' => $this->outboxRow(
                    $this->durableDraft($tenant),
                    self::SECOND_RECORD_ID,
                    self::NOW,
                    1,
                    'find-tenant',
                ),
                'predicate' => 'tenant_type = :tenant_type AND tenant_id = :tenant_id',
                'parameters' => ['record_id', 'partition_id', 'tenant_type', 'tenant_id'],
            ],
        ];

        foreach ($cases as $case) {
            $queries = [];
            $events = [];
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection
                ->expects(self::once())
                ->method('fetchAllAssociative')
                ->willReturnCallback(static function (string $sql, array $parameters, array $types) use (
                    &$queries,
                    $case,
                ): array {
                    $queries[] = ['sql' => $sql, 'parameters' => $parameters, 'types' => $types];
                    return [$case['row']];
                });
            $this->expectNoConnectionUse($connection, [
                'isAutoCommit',
                'isTransactionActive',
                'getTransactionNestingLevel',
                'fetchAllAssociative',
            ]);

            $result = $this->store($connection, onUse: static function (string $event) use (&$events): void {
                $events[] = $event;
            })->find(
                self::FIRST_RECORD_ID === $case['row']['record_id'] ? self::FIRST_RECORD_ID : self::SECOND_RECORD_ID,
                $case['tenant'],
                $case['partition'],
            );

            self::assertTrue($result->isFound());
            self::assertNotNull($result->record());
            self::assertSame($case['row']['record_id'], $result->record()?->toArray()['recordId']);
            self::assertSame(['key'], $events);
            self::assertCount(1, $queries);
            self::assertStringContainsString('record_id = CAST(:record_id AS uuid)', $queries[0]['sql']);
            self::assertStringContainsString('partition_id = :partition_id', $queries[0]['sql']);
            self::assertStringContainsString($case['predicate'], $queries[0]['sql']);
            self::assertStringNotContainsString('FOR UPDATE', $queries[0]['sql']);
            self::assertStringNotContainsString('SKIP LOCKED', $queries[0]['sql']);
            self::assertSame($case['parameters'], array_keys($queries[0]['parameters']));
        }

        $missingConnection = $this->createMock(Connection::class);
        $missingConnection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $missingConnection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $missingConnection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $missingConnection->expects(self::once())->method('fetchAllAssociative')->willReturn([]);
        $this->expectNoConnectionUse($missingConnection, [
            'isAutoCommit',
            'isTransactionActive',
            'getTransactionNestingLevel',
            'fetchAllAssociative',
        ]);
        $missing = $this->store($missingConnection)->find(self::THIRD_RECORD_ID, null, self::SYSTEM_PARTITION);
        self::assertFalse($missing->isFound());
        self::assertNull($missing->record());

        $wrongTenantRow = $cases[1]['row'];
        $wrongTenantConnection = $this->createMock(Connection::class);
        $wrongTenantConnection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $wrongTenantConnection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $wrongTenantConnection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $wrongTenantConnection->expects(self::once())->method('fetchAllAssociative')->willReturn([$wrongTenantRow]);
        $this->expectNoConnectionUse($wrongTenantConnection, [
            'isAutoCommit',
            'isTransactionActive',
            'getTransactionNestingLevel',
            'fetchAllAssociative',
        ]);
        $key = $this->createMock(AuditProtectionKeySource::class);
        $key->expects(self::never())->method('key');
        $wrongTenantStore = $this->store($wrongTenantConnection, onUse: static function (string $event): void {
            if ($event === 'key') {
                self::fail('tenant binding was checked after key resolution');
            }
        });
        try {
            $wrongTenantStore->find(self::SECOND_RECORD_ID, null, self::SYSTEM_PARTITION);
            self::fail('cross-tenant find was accepted');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        $failureConnection = $this->createMock(Connection::class);
        $failureConnection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $failureConnection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $failureConnection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $failureConnection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturnCallback(static function (): never {
                throw new \RuntimeException('find-canary');
            });
        $this->expectNoConnectionUse($failureConnection, [
            'isAutoCommit',
            'isTransactionActive',
            'getTransactionNestingLevel',
            'fetchAllAssociative',
        ]);
        try {
            $this->store($failureConnection)->find(self::FIRST_RECORD_ID, null, self::SYSTEM_PARTITION);
            self::fail('find storage failure was accepted');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
            $this->assertNoCanaryOnExceptionSurfaces($exception, 'find-canary');
        }
    }

    public function testClaimUsesOneClockGlobalFifoAndAuthenticatedTokenOrdering(): void
    {
        $row = $this->outboxRow($this->durableDraft(), self::FIRST_RECORD_ID, self::NOW, 1, 'claim-key');
        $queries = [];
        $events = [];
        $connection = $this->deliveryConnection(
            events: $events,
            candidate: ['record_id' => self::FIRST_RECORD_ID, 'partition_id' => self::SYSTEM_PARTITION],
            state: $this->state(null, self::NOW, 1),
            head: $row,
            casResult: 1,
            expectCommit: true,
            expectRollback: false,
            queries: $queries,
        );
        $clock = new FixtureClock(static function () use (&$events): void {
            $events[] = 'clock';
        }, new \DateTimeImmutable(self::NOW));
        $uuids = new SequenceUuidGenerator([self::TOKEN], static function () use (&$events): void {
            $events[] = 'uuid';
        });

        $result = $this->store($connection, $clock, $uuids, onUse: static function (string $event) use (
            &$events,
        ): void {
            $events[] = $event;
        })->claimNext();

        self::assertTrue($result->isFound());
        $claimed = $result->record();
        self::assertNotNull($claimed);
        self::assertSame('leased', $claimed->toArray()['state']);
        self::assertSame(1, $claimed->toArray()['attempt']);
        self::assertSame(self::TOKEN, $claimed->toArray()['leaseFencingToken']);
        self::assertSame('2026-09-01T03:05:00.123456Z', $claimed->toArray()['leaseExpiresAt']);
        self::assertSame(2, $claimed->toArray()['version']);
        self::assertSame(['clock', 'candidate', 'partition-lock', 'state', 'head', 'key', 'uuid', 'cas'], $events);
        self::assertSame(1, count(array_filter($events, static fn(string $event): bool => $event === 'clock')));

        $candidateQuery = $this->queryByMethod($queries, 'fetchAll', 0);
        $headQuery = $this->queryByMethod($queries, 'fetchAll', 1);
        $casQuery = $this->queryByMethod($queries, 'executeStatement', 0);
        self::assertStringContainsString('r.available_at <= CAST(:trusted_now AS timestamptz)', $candidateQuery['sql']);
        self::assertStringContainsString(
            'ORDER BY r.available_at ASC, r.partition_id ASC, r.ingress_ordinal ASC',
            $candidateQuery['sql'],
        );
        self::assertStringContainsString("lower_row.state IN ('pending', 'leased')", $candidateQuery['sql']);
        self::assertStringContainsString('FOR UPDATE', $headQuery['sql']);
        self::assertStringContainsString('outbox_row.ingress_ordinal', $headQuery['sql']);
        self::assertStringNotContainsString('SKIP LOCKED', $candidateQuery['sql']);
        self::assertStringNotContainsString('SKIP LOCKED', $headQuery['sql']);
        self::assertStringContainsString(
            'SET state = :next_state, attempt = CAST(:next_attempt AS smallint), available_at = CAST(:next_available_at AS timestamptz), lease_fencing_token = CAST(:next_lease_token AS uuid), lease_expires_at = CAST(:next_lease_expires_at AS timestamptz), version = CAST(:next_version AS bigint)',
            $casQuery['sql'],
        );
        self::assertStringNotContainsString('protected_body', $casQuery['sql']);
        self::assertStringNotContainsString('idempotency_key_hash', $casQuery['sql']);
        self::assertStringNotContainsString('content_hash', $casQuery['sql']);
        self::assertSame(self::NOW, $casQuery['parameters']['trusted_now']);
    }

    public function testExpandedYearClockIsResourceUnavailableAtEveryCanonicalNowBoundary(): void
    {
        $expandedYear = new \DateTimeImmutable('+10000-09-01T03:00:00.123456Z');

        $appendEvents = [];
        $appendConnection = $this->appendConnection(
            callerOwned: false,
            events: $appendEvents,
            state: false,
            aggregate: $this->aggregate(0, null, null, 0),
            orphan: null,
            idempotency: [],
            writeResult: null,
        );
        $appendClock = new FixtureClock(static function () use (&$appendEvents): void {
            $appendEvents[] = 'clock';
        }, $expandedYear);
        $appendUuids = $this->createMock(Uuidv7Generator::class);
        $appendUuids->expects(self::never())->method('generate');
        try {
            $this->store($appendConnection, $appendClock, $appendUuids)->appendDurable(
                $this->durableDraft(),
                'expanded-year-append',
            );
            self::fail('expanded-year append clock was accepted');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::ResourceUnavailable, $exception->failure());
            self::assertSame('Audit outbox persistence failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            $this->assertNoCanaryOnExceptionSurfaces($exception, 'expanded-year-append-canary');
        }
        self::assertSame(['partition-lock', 'state', 'aggregate', 'orphan', 'idempotency', 'clock'], $appendEvents);
        self::assertSame(1, count(array_filter($appendEvents, static fn(string $event): bool => $event === 'clock')));

        foreach ([
            'claim' => static fn(PostgreSqlAuditOutboxStore $store): AuditOutboxLookupResult => $store->claimNext(),
            'recovery' =>
                static fn(PostgreSqlAuditOutboxStore $store): AuditOutboxLookupResult => $store->recoverNextExpired(),
        ] as $label => $operation) {
            $events = [];
            $connection = $this->ownedTransactionFailureConnection();
            $clock = new FixtureClock(static function () use (&$events): void {
                $events[] = 'clock';
            }, $expandedYear);
            try {
                $operation($this->store($connection, $clock));
                self::fail($label . ' expanded-year clock was accepted');
            } catch (AuditOutboxStoreException $exception) {
                self::assertSame(AuditOutboxStoreFailure::ResourceUnavailable, $exception->failure(), $label);
                self::assertSame('Audit outbox persistence failed.', $exception->getMessage(), $label);
                self::assertNull($exception->getPrevious(), $label);
                $this->assertNoCanaryOnExceptionSurfaces($exception, 'expanded-year-' . $label . '-canary');
            }
            self::assertSame(['clock'], $events, $label);
            self::assertSame(
                1,
                count(array_filter($events, static fn(string $event): bool => $event === 'clock')),
                $label,
            );
        }

        $settlementEvents = [];
        $settlementQueries = [];
        $settlementConnection = $this->deliveryConnection(
            events: $settlementEvents,
            candidate: null,
            state: $this->state(null, self::NOW, 1),
            head: $this->leasedRow(
                $this->durableDraft(),
                self::FIRST_RECORD_ID,
                self::NOW,
                1,
                'expanded-year-settlement',
                '2026-09-01T03:05:00.123456Z',
            ),
            casResult: null,
            expectCommit: false,
            expectRollback: true,
            queries: $settlementQueries,
            includeCandidate: false,
        );
        $settlementClock = new FixtureClock(static function () use (&$settlementEvents): void {
            $settlementEvents[] = 'clock';
        }, $expandedYear);
        try {
            $this->store($settlementConnection, $settlementClock, onUse: static function (string $event) use (
                &$settlementEvents,
            ): void {
                $settlementEvents[] = $event;
            })->relay(self::FIRST_RECORD_ID, null, self::SYSTEM_PARTITION, 2, self::TOKEN);
            self::fail('expanded-year settlement clock was accepted');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::ResourceUnavailable, $exception->failure());
            self::assertSame('Audit outbox persistence failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            $this->assertNoCanaryOnExceptionSurfaces($exception, 'expanded-year-settlement-canary');
        }
        self::assertSame(['partition-lock', 'state', 'head', 'key', 'clock'], $settlementEvents);
        self::assertSame(
            1,
            count(array_filter($settlementEvents, static fn(string $event): bool => $event === 'clock')),
        );
        self::assertSame(
            [],
            array_values(array_filter(
                $settlementQueries,
                static fn(array $query): bool => $query['method'] === 'executeStatement',
            )),
        );
    }

    public function testPersistedMalformedTimestampRemainsIntegrityInvalid(): void
    {
        $events = [];
        $connection = $this->deliveryConnection(
            events: $events,
            candidate: null,
            state: $this->state(null, self::NOW, 1),
            head: $this->leasedRow(
                $this->durableDraft(),
                self::FIRST_RECORD_ID,
                self::NOW,
                1,
                'persisted-expanded-year',
                '10000-09-01T03:05:00.123456Z',
            ),
            casResult: null,
            expectCommit: false,
            expectRollback: true,
            includeCandidate: false,
        );
        try {
            $this->store($connection)->relay(self::FIRST_RECORD_ID, null, self::SYSTEM_PARTITION, 2, self::TOKEN);
            self::fail('persisted malformed timestamp was accepted');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertSame('Audit outbox persistence failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            $this->assertNoCanaryOnExceptionSurfaces($exception, 'persisted-expanded-year-canary');
        }
        self::assertSame(['partition-lock', 'state', 'head'], $events);
    }

    public function testClaimRejectsNotDueAndRechecksBlockedOrdinalWithoutSkipLocked(): void
    {
        $notDueQueries = [];
        $notDueEvents = [];
        $notDueConnection = $this->deliveryConnection(
            events: $notDueEvents,
            candidate: null,
            state: false,
            head: null,
            casResult: null,
            expectCommit: true,
            expectRollback: false,
            queries: $notDueQueries,
        );
        $notDue = $this->store($notDueConnection, new FixtureClock(static function () use (&$notDueEvents): void {
            $notDueEvents[] = 'clock';
        }, new \DateTimeImmutable('2026-09-01T02:59:59.999999Z')))->claimNext();
        self::assertFalse($notDue->isFound());
        self::assertSame(['clock', 'candidate'], $notDueEvents);
        $notDueQuery = $this->queryByMethod($notDueQueries, 'fetchAll', 0);
        self::assertStringContainsString('r.available_at <= CAST(:trusted_now AS timestamptz)', $notDueQuery['sql']);
        self::assertSame('2026-09-01T02:59:59.999999Z', $notDueQuery['parameters']['trusted_now']);

        foreach (['pending', 'leased'] as $blockingState) {
            $events = [];
            $queries = [];
            $connection = $this->deliveryConnection(
                events: $events,
                candidate: ['record_id' => self::SECOND_RECORD_ID, 'partition_id' => self::SYSTEM_PARTITION],
                state: $this->state(null, self::NOW, 2),
                head: null,
                casResult: null,
                expectCommit: true,
                expectRollback: false,
                queries: $queries,
            );
            $result = $this->store($connection, new FixtureClock(static function () use (&$events): void {
                $events[] = 'clock';
            }, new \DateTimeImmutable(self::NOW)))->claimNext();
            self::assertFalse($result->isFound(), $blockingState);
            self::assertSame(['clock', 'candidate', 'partition-lock', 'state', 'head'], $events, $blockingState);
            $candidateQuery = $this->queryByMethod($queries, 'fetchAll', 0);
            $headQuery = $this->queryByMethod($queries, 'fetchAll', 1);
            self::assertStringContainsString("lower_row.state IN ('pending', 'leased')", $candidateQuery['sql']);
            self::assertStringContainsString("lower_row.state IN ('pending', 'leased')", $headQuery['sql']);
            self::assertStringNotContainsString('SKIP LOCKED', $candidateQuery['sql']);
            self::assertStringNotContainsString('SKIP LOCKED', $headQuery['sql']);
        }
    }

    public function testRecoveryUsesMicrosecondExpiryBoundariesAndPoisonsAtMaximum(): void
    {
        $boundaryCases = [
            [
                'label' => 'expired one microsecond before',
                'now' => '2026-09-01T03:00:00.123455Z',
                'candidate' => null,
                'expectedFound' => false,
            ],
            [
                'label' => 'expiry equality',
                'now' => self::NOW,
                'candidate' => ['record_id' => self::FIRST_RECORD_ID, 'partition_id' => self::SYSTEM_PARTITION],
                'expectedFound' => true,
            ],
            [
                'label' => 'expired one microsecond after',
                'now' => '2026-09-01T03:00:00.123457Z',
                'candidate' => ['record_id' => self::FIRST_RECORD_ID, 'partition_id' => self::SYSTEM_PARTITION],
                'expectedFound' => true,
            ],
        ];

        foreach ($boundaryCases as $case) {
            $events = [];
            $queries = [];
            $row = $this->leasedRow(
                $this->durableDraft(),
                self::FIRST_RECORD_ID,
                self::NOW,
                1,
                'recover-' . $case['label'],
                self::NOW,
            );
            $connection = $this->deliveryConnection(
                events: $events,
                candidate: $case['candidate'],
                state: $this->state(null, self::NOW, 1),
                head: $case['expectedFound'] ? $row : null,
                casResult: $case['expectedFound'] ? 1 : null,
                expectCommit: true,
                expectRollback: false,
                queries: $queries,
            );
            $result = $this->store(
                $connection,
                new FixtureClock(static function () use (&$events): void {
                    $events[] = 'clock';
                }, new \DateTimeImmutable($case['now'])),
                new SequenceUuidGenerator([], static function () use (&$events): void {
                    $events[] = 'uuid';
                }),
                onUse: static function (string $event) use (&$events): void {
                    $events[] = $event;
                },
            )->recoverNextExpired();

            self::assertSame($case['expectedFound'], $result->isFound(), $case['label']);
            self::assertSame(
                $case['expectedFound']
                    ? ['clock', 'candidate', 'partition-lock', 'state', 'head', 'key', 'cas']
                    : ['clock', 'candidate'],
                $events,
                $case['label'],
            );
            $candidateQuery = $this->queryByMethod($queries, 'fetchAll', 0);
            self::assertStringContainsString(
                'r.lease_expires_at <= CAST(:trusted_now AS timestamptz)',
                $candidateQuery['sql'],
                $case['label'],
            );
            self::assertSame($case['now'], $candidateQuery['parameters']['trusted_now'], $case['label']);
            self::assertStringContainsString(
                'ORDER BY r.lease_expires_at ASC, r.partition_id ASC, r.ingress_ordinal ASC',
                $candidateQuery['sql'],
                $case['label'],
            );
            self::assertStringNotContainsString('SKIP LOCKED', $candidateQuery['sql'], $case['label']);
            if ($case['expectedFound']) {
                $casQuery = $this->queryByMethod($queries, 'executeStatement', 0);
                self::assertStringContainsString(
                    "AND state = 'leased' AND lease_expires_at <= CAST(:trusted_now AS timestamptz)",
                    $casQuery['sql'],
                    $case['label'],
                );
                self::assertStringNotContainsString(
                    "AND state = 'leased' AND lease_expires_at > CAST(:trusted_now AS timestamptz)",
                    $casQuery['sql'],
                    $case['label'],
                );
                $recovered = $result->record();
                self::assertNotNull($recovered, $case['label']);
                self::assertSame('pending', $recovered->toArray()['state'], $case['label']);
                self::assertSame(1, $recovered->toArray()['attempt'], $case['label']);
                self::assertSame(3, $recovered->toArray()['version'], $case['label']);
                self::assertSame(
                    new \DateTimeImmutable($case['now'])
                        ->modify('+1 second')
                        ->format('Y-m-d\TH:i:s.u\Z'),
                    $recovered->toArray()['availableAt'],
                    $case['label'],
                );
            }
        }

        $maxRow = $this->leasedRow(
            $this->durableDraft(),
            self::FIRST_RECORD_ID,
            self::NOW,
            1,
            'recover-max',
            self::NOW,
            attempt: 5,
            version: 2,
        );
        $events = [];
        $queries = [];
        $connection = $this->deliveryConnection(
            events: $events,
            candidate: ['record_id' => self::FIRST_RECORD_ID, 'partition_id' => self::SYSTEM_PARTITION],
            state: $this->state(null, self::NOW, 1),
            head: $maxRow,
            casResult: 1,
            expectCommit: true,
            expectRollback: false,
            queries: $queries,
        );
        $result = $this->store(
            $connection,
            new FixtureClock(static function () use (&$events): void {
                $events[] = 'clock';
            }, new \DateTimeImmutable(self::NOW)),
            onUse: static function (string $event) use (&$events): void {
                $events[] = $event;
            },
        )->recoverNextExpired();
        self::assertTrue($result->isFound());
        self::assertSame('poisoned', $result->record()?->toArray()['state']);
        self::assertSame(5, $result->record()?->toArray()['attempt']);
        self::assertSame(3, $result->record()?->toArray()['version']);
        self::assertSame(['clock', 'candidate', 'partition-lock', 'state', 'head', 'key', 'cas'], $events);
    }

    public function testRealPostgreSqlRecoveryUsesExactMicrosecondExpiryBoundaries(): void
    {
        foreach ([
            ['label' => 'before', 'now' => '2026-09-01T03:00:00.123455Z', 'found' => false],
            ['label' => 'equal', 'now' => self::NOW, 'found' => true],
            ['label' => 'after', 'now' => '2026-09-01T03:00:00.123457Z', 'found' => true],
        ] as $case) {
            $admin = $operation = $recovery = $probe = null;
            $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
            $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
            try {
                $admin = $this->realConnection();
                $this->createRealOutboxSchema($admin, $schema);
                $operation = $this->realConnection();
                $store = $this->store(
                    $operation,
                    new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                    new SequenceUuidGenerator([self::FIRST_RECORD_ID, self::TOKEN]),
                    schema: $schema,
                );
                self::assertTrue($store->appendDurable($this->durableDraft(), 'recovery-boundary')->inserted());
                self::assertTrue($store->claimNext()->isFound());
                $admin->executeStatement(
                    'UPDATE '
                    . $outboxSchema->recordsTable()
                    . ' SET lease_expires_at = CAST(:expires_at AS timestamptz)'
                    . ' WHERE record_id = CAST(:record_id AS uuid)',
                    ['expires_at' => self::NOW, 'record_id' => self::FIRST_RECORD_ID],
                    ['expires_at' => ParameterType::STRING, 'record_id' => ParameterType::STRING],
                );

                $recovery = $this->realConnection();
                $result = $this->store(
                    $recovery,
                    new FixtureClock(null, new \DateTimeImmutable($case['now'])),
                    schema: $schema,
                )->recoverNextExpired();
                self::assertSame($case['found'], $result->isFound(), $case['label']);
                $probe = $this->realConnection();
                $row = $probe->fetchAssociative(
                    'SELECT state, lease_expires_at, available_at FROM '
                    . $outboxSchema->recordsTable()
                    . ' WHERE record_id = CAST(:record_id AS uuid)',
                    ['record_id' => self::FIRST_RECORD_ID],
                    ['record_id' => ParameterType::STRING],
                );
                self::assertIsArray($row, $case['label']);
                self::assertSame($case['found'] ? 'pending' : 'leased', $row['state'], $case['label']);
            } finally {
                foreach ([$operation, $recovery, $probe] as $connection) {
                    if ($connection instanceof Connection) {
                        try {
                            if ($connection->isTransactionActive()) {
                                $connection->rollBack();
                            }
                        } catch (\Throwable) {
                        }
                        try {
                            $connection->close();
                        } catch (\Throwable) {
                        }
                    }
                }
                if ($admin instanceof Connection) {
                    try {
                        $admin->executeStatement('DROP SCHEMA IF EXISTS ' . $schema->quoted() . ' CASCADE');
                    } catch (\Throwable) {
                    }
                    try {
                        $admin->close();
                    } catch (\Throwable) {
                    }
                }
            }
        }
    }

    public function testRelayAndPoisonClearLeasesAndOnlyCasMutableColumnsRemain(): void
    {
        foreach (['relay' => 'relayed', 'poison' => 'poisoned'] as $operation => $expectedState) {
            $events = [];
            $queries = [];
            $row = $this->leasedRow(
                $this->durableDraft(),
                self::FIRST_RECORD_ID,
                self::NOW,
                1,
                $operation . '-key',
                '2026-09-01T03:05:00.123456Z',
            );
            $connection = $this->deliveryConnection(
                events: $events,
                candidate: null,
                state: $this->state(null, self::NOW, 1),
                head: $row,
                casResult: 1,
                expectCommit: true,
                expectRollback: false,
                queries: $queries,
                includeCandidate: false,
            );
            $result = $this->store(
                $connection,
                new FixtureClock(static function () use (&$events): void {
                    $events[] = 'clock';
                }, new \DateTimeImmutable(self::NOW)),
                onUse: static function (string $event) use (&$events): void {
                    $events[] = $event;
                },
            )->{$operation}(self::FIRST_RECORD_ID, null, self::SYSTEM_PARTITION, 2, self::TOKEN);

            self::assertSame($expectedState, $result->toArray()['state'], $operation);
            self::assertNull($result->toArray()['leaseFencingToken'], $operation);
            self::assertNull($result->toArray()['leaseExpiresAt'], $operation);
            self::assertSame(['partition-lock', 'state', 'head', 'key', 'clock', 'cas'], $events, $operation);
            $casQuery = $this->queryByMethod($queries, 'executeStatement', 0);
            self::assertStringContainsString(
                'SET state = :next_state, attempt = CAST(:next_attempt AS smallint), available_at = CAST(:next_available_at AS timestamptz), lease_fencing_token = CAST(:next_lease_token AS uuid), lease_expires_at = CAST(:next_lease_expires_at AS timestamptz), version = CAST(:next_version AS bigint)',
                $casQuery['sql'],
                $operation,
            );
            self::assertStringNotContainsString('protected_body', $casQuery['sql'], $operation);
            self::assertStringNotContainsString('record_id = :next', $casQuery['sql'], $operation);
            self::assertStringNotContainsString('SKIP LOCKED', $casQuery['sql'], $operation);
            self::assertStringContainsString(
                "AND state = 'leased' AND lease_expires_at > CAST(:trusted_now AS timestamptz)",
                $casQuery['sql'],
                $operation,
            );
            self::assertStringNotContainsString(
                "AND state = 'leased' AND lease_expires_at <= CAST(:trusted_now AS timestamptz)",
                $casQuery['sql'],
                $operation,
            );
        }
    }

    public function testSettlementsRejectWrongBindingsAndCasCountsWithoutLeakingOrMutating(): void
    {
        $tenant = new AuditTypedReference('account', 'tenant-a');
        $invalidPartition = $this->createMock(Connection::class);
        $invalidPartition->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $this->expectNoConnectionUse($invalidPartition, ['isAutoCommit']);
        try {
            $this->store($invalidPartition)->relay(
                self::FIRST_RECORD_ID,
                $tenant,
                self::SYSTEM_PARTITION,
                2,
                self::TOKEN,
            );
            self::fail('invalid tenant partition was accepted');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        foreach (['wrong version', 'wrong token', 'wrong state'] as $case) {
            $events = [];
            $queries = [];
            $row = $case === 'wrong state'
                ? $this->outboxRow($this->durableDraft(), self::FIRST_RECORD_ID, self::NOW, 1, 'wrong-state')
                : null;
            $connection = $this->deliveryConnection(
                events: $events,
                candidate: null,
                state: $this->state(null, self::NOW, 1),
                head: $row,
                casResult: null,
                expectCommit: false,
                expectRollback: true,
                queries: $queries,
                includeCandidate: false,
            );
            $expectedVersion = $case === 'wrong version' ? 3 : 2;
            $expectedToken = $case === 'wrong token' ? self::NEXT_TOKEN : self::TOKEN;
            try {
                $this->store(
                    $connection,
                    new FixtureClock(static function () use (&$events): void {
                        $events[] = 'clock';
                    }),
                    onUse: static function (string $event) use (&$events): void {
                        $events[] = $event;
                    },
                )->relay(self::FIRST_RECORD_ID, null, self::SYSTEM_PARTITION, $expectedVersion, $expectedToken);
                self::fail($case . ' was accepted');
            } catch (AuditOutboxStoreException $exception) {
                self::assertSame(AuditOutboxStoreFailure::TransitionConflict, $exception->failure(), $case);
                self::assertNull($exception->getPrevious(), $case);
                self::assertSame(
                    $case === 'wrong state'
                        ? ['partition-lock', 'state', 'head', 'key', 'clock']
                        : ['partition-lock', 'state', 'head'],
                    $events,
                    $case,
                );
                $this->assertNoCanaryOnExceptionSurfaces($exception, 'wrong-transition-canary');
            }
            $headQuery = $this->queryByMethod($queries, 'fetchAll', 0);
            self::assertStringContainsString('state = :state', $headQuery['sql'], $case);
            self::assertStringContainsString('version = CAST(:expected_version AS bigint)', $headQuery['sql'], $case);
            self::assertStringContainsString(
                'lease_fencing_token = CAST(:expected_token AS uuid)',
                $headQuery['sql'],
                $case,
            );
            self::assertStringNotContainsString('SKIP LOCKED', $headQuery['sql'], $case);
        }

        $wrongTenantRow = $this->leasedRow(
            $this->durableDraft($tenant),
            self::FIRST_RECORD_ID,
            self::NOW,
            1,
            'wrong-tenant',
            '2026-09-01T03:05:00.123456Z',
        );
        $events = [];
        $queries = [];
        $connection = $this->deliveryConnection(
            events: $events,
            candidate: null,
            state: $this->state(null, self::NOW, 1),
            head: $wrongTenantRow,
            casResult: null,
            expectCommit: false,
            expectRollback: true,
            queries: $queries,
            includeCandidate: false,
        );
        try {
            $this->store($connection, onUse: static function (string $event) use (&$events): void {
                if ($event === 'key') {
                    self::fail('tenant was authenticated after key resolution');
                }
                $events[] = $event;
            })->relay(self::FIRST_RECORD_ID, null, self::SYSTEM_PARTITION, 2, self::TOKEN);
            self::fail('wrong tenant was accepted');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
            self::assertSame(['partition-lock', 'state', 'head'], $events);
        }

        foreach ([0, 2] as $affected) {
            $events = [];
            $queries = [];
            $row = $this->leasedRow(
                $this->durableDraft(),
                self::FIRST_RECORD_ID,
                self::NOW,
                1,
                'cas-' . $affected,
                '2026-09-01T03:05:00.123456Z',
            );
            $connection = $this->deliveryConnection(
                events: $events,
                candidate: null,
                state: $this->state(null, self::NOW, 1),
                head: $row,
                casResult: $affected,
                expectCommit: false,
                expectRollback: true,
                queries: $queries,
                includeCandidate: false,
            );
            try {
                $this->store(
                    $connection,
                    new FixtureClock(static function () use (&$events): void {
                        $events[] = 'clock';
                    }),
                    onUse: static function (string $event) use (&$events): void {
                        $events[] = $event;
                    },
                )->relay(self::FIRST_RECORD_ID, null, self::SYSTEM_PARTITION, 2, self::TOKEN);
                self::fail('CAS affected ' . $affected . ' was accepted');
            } catch (AuditOutboxStoreException $exception) {
                self::assertSame(
                    $affected === 0
                        ? AuditOutboxStoreFailure::TransitionConflict
                        : AuditOutboxStoreFailure::IntegrityInvalid,
                    $exception->failure(),
                );
                self::assertNull($exception->getPrevious());
                $this->assertNoCanaryOnExceptionSurfaces($exception, 'cas-canary');
            }
            self::assertSame(['partition-lock', 'state', 'head', 'key', 'clock', 'cas'], $events);
        }
    }

    public function testRetryAtMaximumFailsButPoisonAcknowledgementUnlocksTheNextHead(): void
    {
        $maxRow = $this->leasedRow(
            $this->durableDraft(),
            self::FIRST_RECORD_ID,
            self::NOW,
            1,
            'retry-max',
            '2026-09-01T03:05:00.123456Z',
            attempt: 5,
            version: 2,
        );
        $events = [];
        $queries = [];
        $connection = $this->deliveryConnection(
            events: $events,
            candidate: null,
            state: $this->state(null, self::NOW, 1),
            head: $maxRow,
            casResult: null,
            expectCommit: false,
            expectRollback: true,
            queries: $queries,
            includeCandidate: false,
        );
        try {
            $this->store(
                $connection,
                new FixtureClock(static function () use (&$events): void {
                    $events[] = 'clock';
                }),
                onUse: static function (string $event) use (&$events): void {
                    $events[] = $event;
                },
                configuration: new AuditOutboxConfiguration(maxAttempts: 5),
            )->retry(self::FIRST_RECORD_ID, null, self::SYSTEM_PARTITION, 2, self::TOKEN);
            self::fail('retry at maximum was accepted');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::TransitionConflict, $exception->failure());
            self::assertNull($exception->getPrevious());
            self::assertSame(['partition-lock', 'state', 'head', 'key', 'clock'], $events);
        }

        $events = [];
        $queries = [];
        $connection = $this->deliveryConnection(
            events: $events,
            candidate: null,
            state: $this->state(null, self::NOW, 1),
            head: $maxRow,
            casResult: 1,
            expectCommit: true,
            expectRollback: false,
            queries: $queries,
            includeCandidate: false,
        );
        $poisoned = $this->store(
            $connection,
            new FixtureClock(static function () use (&$events): void {
                $events[] = 'clock';
            }),
            onUse: static function (string $event) use (&$events): void {
                $events[] = $event;
            },
            configuration: new AuditOutboxConfiguration(maxAttempts: 5),
        )->poison(self::FIRST_RECORD_ID, null, self::SYSTEM_PARTITION, 2, self::TOKEN);
        self::assertSame('poisoned', $poisoned->toArray()['state']);
        self::assertNull($poisoned->toArray()['leaseFencingToken']);
        self::assertSame(['partition-lock', 'state', 'head', 'key', 'clock', 'cas'], $events);

        $nextEvents = [];
        $nextQueries = [];
        $nextRow = $this->outboxRow($this->durableDraft(), self::SECOND_RECORD_ID, self::NOW, 2, 'next-head');
        $nextConnection = $this->deliveryConnection(
            events: $nextEvents,
            candidate: ['record_id' => self::SECOND_RECORD_ID, 'partition_id' => self::SYSTEM_PARTITION],
            state: $this->state(null, self::NOW, 2),
            head: $nextRow,
            casResult: 1,
            expectCommit: true,
            expectRollback: false,
            queries: $nextQueries,
        );
        $next = $this->store(
            $nextConnection,
            new FixtureClock(static function () use (&$nextEvents): void {
                $nextEvents[] = 'clock';
            }),
            new SequenceUuidGenerator([self::NEXT_TOKEN], static function () use (&$nextEvents): void {
                $nextEvents[] = 'uuid';
            }),
            onUse: static function (string $event) use (&$nextEvents): void {
                $nextEvents[] = $event;
            },
        )->claimNext();
        self::assertSame(self::SECOND_RECORD_ID, $next->record()?->toArray()['recordId']);
        self::assertSame(['clock', 'candidate', 'partition-lock', 'state', 'head', 'key', 'uuid', 'cas'], $nextEvents);
        self::assertStringContainsString(
            "lower_row.state IN ('pending', 'leased')",
            $this->queryByMethod($nextQueries, 'fetchAll', 0)['sql'],
        );
    }

    public function testRealPostgreSqlTwoConnectionAppendRacesAreGapFreeAndRetryable(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('posix_kill')) {
            self::markTestSkipped('pcntl and posix are required for the append race fixture.');
        }

        $cases = [
            'same-key' => [
                'parentKey' => 'race-same-key',
                'childKey' => 'race-same-key',
                'expectedCount' => 1,
                'expectedChildInserted' => false,
                'expectedChildOrdinal' => 1,
            ],
            'different-key' => [
                'parentKey' => 'race-parent-key',
                'childKey' => 'race-child-key',
                'expectedCount' => 2,
                'expectedChildInserted' => true,
                'expectedChildOrdinal' => 2,
            ],
        ];

        foreach ($cases as $label => $case) {
            $admin = $parent = $probe = null;
            $pid = null;
            $status = 0;
            $childFinished = false;
            $ready = tempnam(sys_get_temp_dir(), 'outbox-race-ready-');
            $resultFile = tempnam(sys_get_temp_dir(), 'outbox-race-result-');
            $parentReady = tempnam(sys_get_temp_dir(), 'outbox-race-parent-');
            self::assertIsString($ready, $label);
            self::assertIsString($resultFile, $label);
            self::assertIsString($parentReady, $label);
            $this->unlinkRealTemporary($ready);
            $this->unlinkRealTemporary($resultFile);
            $this->unlinkRealTemporary($parentReady);
            $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
            $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);

            try {
                $admin = $this->realConnection();
                $this->createRealOutboxSchema($admin, $schema);
                $admin->close();
                $admin = null;

                $pid = pcntl_fork();
                if ($pid === -1) {
                    self::fail('Unable to fork the append race fixture.');
                }
                if ($pid === 0) {
                    $child = null;
                    $payload = ['ok' => false];
                    try {
                        $child = $this->realConnection();
                        file_put_contents($ready, (string) $child->fetchOne('SELECT pg_backend_pid()'));
                        $this->waitForRealFile($parentReady, 'append race parent signal');
                        $clock = new FixtureClock(null, new \DateTimeImmutable(self::NOW));
                        $uuids = new SequenceUuidGenerator([
                            self::RACE_CHILD_RECORD_ID,
                            self::RACE_RETRY_RECORD_ID,
                        ]);
                        $store = $this->store($child, $clock, $uuids, schema: $schema);
                        $first = $store->appendDurable($this->durableDraft(), $case['childKey']);
                        $retry = $store->appendDurable($this->durableDraft(), $case['childKey']);
                        $firstValue = $first->record()->toArray();
                        $retryValue = $retry->record()->toArray();
                        $payload = [
                            'ok' => true,
                            'firstInserted' => $first->inserted(),
                            'firstRecordId' => $firstValue['recordId'],
                            'firstOrdinal' => $firstValue['ingressOrdinal'],
                            'retryInserted' => $retry->inserted(),
                            'retryRecordId' => $retryValue['recordId'],
                            'retryOrdinal' => $retryValue['ingressOrdinal'],
                            'connectedAfterRetry' => $child->isConnected(),
                            'activeAfterRetry' => $child->isTransactionActive(),
                        ];
                    } catch (\Throwable $failure) {
                        $payload = ['ok' => false, 'errorClass' => get_class($failure)];
                    } finally {
                        if ($child instanceof Connection) {
                            try {
                                if ($child->isTransactionActive()) {
                                    $child->rollBack();
                                }
                            } catch (\Throwable) {
                            }
                            try {
                                $child->close();
                            } catch (\Throwable) {
                            }
                        }
                        file_put_contents($resultFile, json_encode($payload, JSON_THROW_ON_ERROR));
                    }
                    exit($payload['ok'] ?? false ? 0 : 1);
                }

                $admin = $this->realConnection();
                $parent = $this->realConnection();
                $probe = $this->realConnection();
                $parent->beginTransaction();
                $this->lockRealPartition($parent, $schema, self::SYSTEM_PARTITION);
                $this->waitForRealFile($ready, 'append race child readiness');
                file_put_contents($parentReady, 'ready');
                $wait = $this->waitForRealDatabaseLock($probe, $ready, $resultFile);
                self::assertSame('Lock', $wait['wait_event_type'], $label);
                self::assertSame('advisory', $wait['wait_event'], $label);
                self::assertStringContainsString('pg_advisory_xact_lock', $wait['query'], $label);

                $parentResult = $this->store(
                    $parent,
                    new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                    new SequenceUuidGenerator([self::RACE_PARENT_RECORD_ID]),
                    schema: $schema,
                )->appendDurable($this->durableDraft(), $case['parentKey']);
                self::assertTrue($parentResult->inserted(), $label);
                self::assertSame(1, $parentResult->record()->toArray()['ingressOrdinal'], $label);
                $parent->commit();

                $childFinished = $this->waitForRealChild($pid, $status);
                self::assertTrue($childFinished, $label);
                self::assertTrue(pcntl_wifexited($status), $label);
                self::assertSame(0, pcntl_wexitstatus($status), $label);
                $childResult = json_decode((string) file_get_contents($resultFile), true, 512, JSON_THROW_ON_ERROR);
                self::assertIsArray($childResult, $label);
                self::assertTrue((bool) ($childResult['ok'] ?? false), $label);
                self::assertSame($case['expectedChildInserted'], $childResult['firstInserted'], $label);
                self::assertSame($case['expectedChildOrdinal'], (int) $childResult['firstOrdinal'], $label);
                self::assertSame($childResult['firstRecordId'], $childResult['retryRecordId'], $label);
                self::assertFalse((bool) $childResult['retryInserted'], $label);
                self::assertTrue((bool) $childResult['connectedAfterRetry'], $label);
                self::assertFalse((bool) $childResult['activeAfterRetry'], $label);
                if ($case['expectedChildInserted']) {
                    self::assertSame(self::RACE_CHILD_RECORD_ID, $childResult['firstRecordId'], $label);
                } else {
                    self::assertSame(self::RACE_PARENT_RECORD_ID, $childResult['firstRecordId'], $label);
                }

                $records = $this->realOutboxRecords($probe, $outboxSchema);
                self::assertCount($case['expectedCount'], $records, $label);
                self::assertSame(
                    range(1, $case['expectedCount']),
                    array_map(static fn(array $record): int => (int) $record['ingress_ordinal'], $records),
                    $label,
                );
                self::assertSame(
                    $case['expectedCount'],
                    (int) $probe->fetchOne('SELECT count(*) FROM ' . $outboxSchema->recordsTable()),
                    $label,
                );
                $state = $this->realOutboxState($probe, $outboxSchema, self::SYSTEM_PARTITION);
                self::assertSame((string) $case['expectedCount'], (string) $state['last_ingress_ordinal'], $label);
                self::assertSame((string) $case['expectedCount'], (string) $state['state_version'], $label);
            } finally {
                if (is_int($pid) && !$childFinished) {
                    $this->terminateRealChild($pid, $status);
                }
                $this->cleanupRealOutboxSchema($admin, $parent, $probe, $schema);
                $this->unlinkRealTemporary($ready);
                $this->unlinkRealTemporary($resultFile);
                $this->unlinkRealTemporary($parentReady);
            }
        }
    }

    public function testRealPostgreSqlConcurrentSameHeadClaimHasOneWinnerAndOneToken(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('posix_kill')) {
            self::markTestSkipped('pcntl and posix are required for the claim race fixture.');
        }

        $admin = $primary = $parent = $probe = null;
        $pid = null;
        $status = 0;
        $childFinished = false;
        $ready = tempnam(sys_get_temp_dir(), 'outbox-claim-ready-');
        $resultFile = tempnam(sys_get_temp_dir(), 'outbox-claim-result-');
        $parentReady = tempnam(sys_get_temp_dir(), 'outbox-claim-parent-');
        self::assertIsString($ready);
        self::assertIsString($resultFile);
        self::assertIsString($parentReady);
        $this->unlinkRealTemporary($ready);
        $this->unlinkRealTemporary($resultFile);
        $this->unlinkRealTemporary($parentReady);
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);

        try {
            $admin = $this->realConnection();
            $this->createRealOutboxSchema($admin, $schema);
            $admin->close();
            $admin = null;
            $primary = $this->realConnection();
            $this->store(
                $primary,
                new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                new SequenceUuidGenerator([self::RACE_PARENT_RECORD_ID]),
                schema: $schema,
            )->appendDurable($this->durableDraft(), 'claim-race-key');
            $primary->close();
            $primary = null;

            $pid = pcntl_fork();
            if ($pid === -1) {
                self::fail('Unable to fork the claim race fixture.');
            }
            if ($pid === 0) {
                $child = null;
                $uuidCalls = 0;
                $payload = ['ok' => false];
                try {
                    $child = $this->realConnection();
                    file_put_contents($ready, (string) $child->fetchOne('SELECT pg_backend_pid()'));
                    $this->waitForRealFile($parentReady, 'claim race parent signal');
                    $claim = $this->store(
                        $child,
                        new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                        new SequenceUuidGenerator([
                            self::CLAIM_CHILD_TOKEN,
                        ], static function () use (&$uuidCalls): void {
                            $uuidCalls++;
                        }),
                        schema: $schema,
                    )->claimNext();
                    $payload = [
                        'ok' => true,
                        'found' => $claim->isFound(),
                        'token' => $claim->record()?->toArray()['leaseFencingToken'],
                        'uuidCalls' => $uuidCalls,
                        'connected' => $child->isConnected(),
                        'active' => $child->isTransactionActive(),
                    ];
                } catch (\Throwable $failure) {
                    $payload = ['ok' => false, 'errorClass' => get_class($failure)];
                } finally {
                    if ($child instanceof Connection) {
                        try {
                            if ($child->isTransactionActive()) {
                                $child->rollBack();
                            }
                        } catch (\Throwable) {
                        }
                        try {
                            $child->close();
                        } catch (\Throwable) {
                        }
                    }
                    file_put_contents($resultFile, json_encode($payload, JSON_THROW_ON_ERROR));
                }
                exit($payload['ok'] ?? false ? 0 : 1);
            }

            $admin = $this->realConnection();
            $parent = $this->realConnection();
            $probe = $this->realConnection();
            $parent->beginTransaction();
            $this->lockRealPartition($parent, $schema, self::SYSTEM_PARTITION);
            $this->waitForRealFile($ready, 'claim race child readiness');
            file_put_contents($parentReady, 'ready');
            $wait = $this->waitForRealDatabaseLock($probe, $ready, $resultFile);
            self::assertSame('Lock', $wait['wait_event_type']);
            self::assertSame('advisory', $wait['wait_event']);

            $winner = $this->store(
                $parent,
                new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                new SequenceUuidGenerator([self::CLAIM_PARENT_TOKEN]),
                schema: $schema,
            )->claimNext();
            self::assertTrue($winner->isFound());
            self::assertSame(self::CLAIM_PARENT_TOKEN, $winner->record()?->toArray()['leaseFencingToken']);
            self::assertSame(1, $winner->record()?->toArray()['attempt']);
            self::assertSame(2, $winner->record()?->toArray()['version']);
            $parent->commit();

            $childFinished = $this->waitForRealChild($pid, $status);
            self::assertTrue($childFinished);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
            $childResult = json_decode((string) file_get_contents($resultFile), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($childResult);
            self::assertTrue((bool) ($childResult['ok'] ?? false));
            self::assertFalse((bool) $childResult['found']);
            self::assertNull($childResult['token']);
            self::assertSame(0, (int) $childResult['uuidCalls']);
            self::assertTrue((bool) $childResult['connected']);
            self::assertFalse((bool) $childResult['active']);
            $row = $probe->fetchAssociative(
                'SELECT state, attempt, lease_fencing_token::text AS lease_fencing_token, version FROM '
                . $outboxSchema->recordsTable()
                . ' WHERE record_id = CAST(:record_id AS uuid)',
                ['record_id' => self::RACE_PARENT_RECORD_ID],
                ['record_id' => ParameterType::STRING],
            );
            self::assertIsArray($row);
            self::assertSame('leased', $row['state']);
            self::assertSame('1', (string) $row['attempt']);
            self::assertSame(self::CLAIM_PARENT_TOKEN, $row['lease_fencing_token']);
            self::assertSame('2', (string) $row['version']);
        } finally {
            if (is_int($pid) && !$childFinished) {
                $this->terminateRealChild($pid, $status);
            }
            $this->cleanupRealOutboxSchema($admin, $parent, $probe, $schema);
            if ($primary instanceof Connection) {
                try {
                    $primary->close();
                } catch (\Throwable) {
                }
            }
            $this->unlinkRealTemporary($ready);
            $this->unlinkRealTemporary($resultFile);
            $this->unlinkRealTemporary($parentReady);
        }
    }

    public function testRealPostgreSqlPartitionThenRecordLockOrderAllowsUnrelatedPartitionProgress(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('posix_kill')) {
            self::markTestSkipped('pcntl and posix are required for the lock-order fixture.');
        }

        $admin = $seed = $parent = $probe = $unrelated = null;
        $firstPid = $secondPid = null;
        $firstStatus = $secondStatus = 0;
        $firstFinished = $secondFinished = false;
        $firstReady = tempnam(sys_get_temp_dir(), 'outbox-lock-first-ready-');
        $firstResult = tempnam(sys_get_temp_dir(), 'outbox-lock-first-result-');
        $firstStart = tempnam(sys_get_temp_dir(), 'outbox-lock-first-start-');
        $secondReady = tempnam(sys_get_temp_dir(), 'outbox-lock-second-ready-');
        $secondResult = tempnam(sys_get_temp_dir(), 'outbox-lock-second-result-');
        $secondStart = tempnam(sys_get_temp_dir(), 'outbox-lock-second-start-');
        foreach ([$firstReady, $firstResult, $firstStart, $secondReady, $secondResult, $secondStart] as $path) {
            self::assertIsString($path);
            $this->unlinkRealTemporary($path);
        }
        self::assertIsString($firstReady);
        self::assertIsString($firstResult);
        self::assertIsString($firstStart);
        self::assertIsString($secondReady);
        self::assertIsString($secondResult);
        self::assertIsString($secondStart);
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        $tenant = new AuditTypedReference('account', 'unrelated-lock-tenant');

        try {
            $admin = $this->realConnection();
            $this->createRealOutboxSchema($admin, $schema);
            $seed = $this->realConnection();
            $this->store(
                $seed,
                new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                new SequenceUuidGenerator([self::LOCK_EXISTING_RECORD_ID]),
                schema: $schema,
            )->appendDurable($this->durableDraft(), 'lock-existing-key');
            $seed->close();
            $seed = null;

            $firstPid = pcntl_fork();
            if ($firstPid === -1) {
                self::fail('Unable to fork the first lock-order worker.');
            }
            if ($firstPid === 0) {
                $child = null;
                $payload = ['ok' => false];
                try {
                    $child = $this->realConnection();
                    file_put_contents($firstReady, (string) $child->fetchOne('SELECT pg_backend_pid()'));
                    $this->waitForRealFile($firstStart, 'first lock-order start');
                    $store = $this->store(
                        $child,
                        new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                        new SequenceUuidGenerator([self::LOCK_EXISTING_RECORD_ID, self::LOCK_RETRY_RECORD_ID]),
                        schema: $schema,
                    );
                    try {
                        $store->appendDurable($this->durableDraft(), 'lock-collision-key');
                        throw new \RuntimeException('record collision unexpectedly succeeded');
                    } catch (AuditOutboxStoreException $exception) {
                        $payload['failureCode'] = $exception->code();
                        $payload['connectedAfterFailure'] = $child->isConnected();
                        $payload['activeAfterFailure'] = $child->isTransactionActive();
                    }
                    $retry = $store->appendDurable($this->durableDraft(), 'lock-collision-key');
                    $payload['retry'] = [
                        'recordId' => $retry->record()->toArray()['recordId'],
                        'ordinal' => $retry->record()->toArray()['ingressOrdinal'],
                        'inserted' => $retry->inserted(),
                    ];
                    $payload['connectedAfterRetry'] = $child->isConnected();
                    $payload['activeAfterRetry'] = $child->isTransactionActive();
                    $payload['ok'] = true;
                } catch (\Throwable $failure) {
                    $payload = ['ok' => false, 'errorClass' => get_class($failure)];
                } finally {
                    if ($child instanceof Connection) {
                        try {
                            if ($child->isTransactionActive()) {
                                $child->rollBack();
                            }
                        } catch (\Throwable) {
                        }
                        try {
                            $child->close();
                        } catch (\Throwable) {
                        }
                    }
                    file_put_contents($firstResult, json_encode($payload, JSON_THROW_ON_ERROR));
                }
                exit($payload['ok'] ?? false ? 0 : 1);
            }

            $secondPid = pcntl_fork();
            if ($secondPid === -1) {
                self::fail('Unable to fork the second lock-order worker.');
            }
            if ($secondPid === 0) {
                $child = null;
                $payload = ['ok' => false];
                try {
                    $child = $this->realConnection();
                    file_put_contents($secondReady, (string) $child->fetchOne('SELECT pg_backend_pid()'));
                    $this->waitForRealFile($secondStart, 'second lock-order start');
                    $result = $this->store(
                        $child,
                        new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                        new SequenceUuidGenerator([self::LOCK_BLOCKED_RECORD_ID]),
                        schema: $schema,
                    )->appendDurable($this->durableDraft(), 'lock-blocked-key');
                    $payload = [
                        'ok' => true,
                        'recordId' => $result->record()->toArray()['recordId'],
                        'ordinal' => $result->record()->toArray()['ingressOrdinal'],
                        'connected' => $child->isConnected(),
                        'active' => $child->isTransactionActive(),
                    ];
                } catch (\Throwable $failure) {
                    $payload = ['ok' => false, 'errorClass' => get_class($failure)];
                } finally {
                    if ($child instanceof Connection) {
                        try {
                            if ($child->isTransactionActive()) {
                                $child->rollBack();
                            }
                        } catch (\Throwable) {
                        }
                        try {
                            $child->close();
                        } catch (\Throwable) {
                        }
                    }
                    file_put_contents($secondResult, json_encode($payload, JSON_THROW_ON_ERROR));
                }
                exit($payload['ok'] ?? false ? 0 : 1);
            }

            $admin = $this->realConnection();
            $parent = $this->realConnection();
            $probe = $this->realConnection();
            $parent->beginTransaction();
            $this->lockRealRecord($parent, $schema, self::LOCK_EXISTING_RECORD_ID);
            $this->waitForRealFile($firstReady, 'first lock-order readiness');
            file_put_contents($firstStart, 'ready');
            $firstWait = $this->waitForRealDatabaseLock($probe, $firstReady, $firstResult);
            self::assertSame('Lock', $firstWait['wait_event_type']);
            self::assertSame('advisory', $firstWait['wait_event']);
            self::assertTrue($this->realAdvisoryLockPresent(
                $probe,
                $firstReady,
                $this->partitionLockKey(new PostgreSqlAuditOutboxSchema($schema), self::SYSTEM_PARTITION),
                true,
            ));
            self::assertTrue($this->realAdvisoryLockPresent(
                $probe,
                $firstReady,
                $this->recordLockKey(new PostgreSqlAuditOutboxSchema($schema), self::LOCK_EXISTING_RECORD_ID),
                false,
            ));

            $this->waitForRealFile($secondReady, 'second lock-order readiness');
            file_put_contents($secondStart, 'ready');
            $secondWait = $this->waitForRealDatabaseLock($probe, $secondReady, $secondResult);
            self::assertSame('Lock', $secondWait['wait_event_type']);
            self::assertSame('advisory', $secondWait['wait_event']);
            self::assertTrue($this->realAdvisoryLockPresent(
                $probe,
                $secondReady,
                $this->partitionLockKey(new PostgreSqlAuditOutboxSchema($schema), self::SYSTEM_PARTITION),
                false,
            ));

            $unrelated = $this->realConnection();
            $unrelatedResult = $this->store(
                $unrelated,
                new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                new SequenceUuidGenerator([self::LOCK_TENANT_RECORD_ID]),
                schema: $schema,
            )->appendDurable($this->durableDraft($tenant), 'unrelated-lock-key');
            self::assertTrue($unrelatedResult->inserted());
            self::assertSame(1, $unrelatedResult->record()->toArray()['ingressOrdinal']);
            $unrelated->close();
            $unrelated = null;

            $parent->commit();
            $firstFinished = $this->waitForRealChild($firstPid, $firstStatus);
            $secondFinished = $this->waitForRealChild($secondPid, $secondStatus);
            self::assertTrue($firstFinished);
            self::assertTrue($secondFinished);
            self::assertTrue(pcntl_wifexited($firstStatus));
            self::assertTrue(pcntl_wifexited($secondStatus));
            self::assertSame(0, pcntl_wexitstatus($firstStatus));
            self::assertSame(0, pcntl_wexitstatus($secondStatus));
            $firstResultPayload = json_decode((string) file_get_contents($firstResult), true, 512, JSON_THROW_ON_ERROR);
            $secondResultPayload = json_decode(
                (string) file_get_contents($secondResult),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            self::assertTrue((bool) ($firstResultPayload['ok'] ?? false));
            self::assertTrue((bool) ($secondResultPayload['ok'] ?? false));
            self::assertSame('resource_unavailable', $firstResultPayload['failureCode']);
            self::assertTrue((bool) $firstResultPayload['connectedAfterFailure']);
            self::assertFalse((bool) $firstResultPayload['activeAfterFailure']);
            self::assertTrue((bool) $firstResultPayload['connectedAfterRetry']);
            self::assertFalse((bool) $firstResultPayload['activeAfterRetry']);
            self::assertTrue((bool) $firstResultPayload['retry']['inserted']);
            self::assertSame(self::LOCK_RETRY_RECORD_ID, $firstResultPayload['retry']['recordId']);
            self::assertSame(self::LOCK_BLOCKED_RECORD_ID, $secondResultPayload['recordId']);
            self::assertTrue((bool) $secondResultPayload['connected']);
            self::assertFalse((bool) $secondResultPayload['active']);

            $systemRecords = array_values(array_filter(
                $this->realOutboxRecords($probe, $outboxSchema),
                static fn(array $record): bool => $record['partition_id'] === self::SYSTEM_PARTITION,
            ));
            self::assertCount(3, $systemRecords);
            self::assertSame(
                [1, 2, 3],
                array_map(static fn(array $record): int => (int) $record['ingress_ordinal'], $systemRecords),
            );
            self::assertSame(
                1,
                (int) $probe->fetchOne(
                    'SELECT count(*) FROM ' . $outboxSchema->recordsTable() . ' WHERE partition_id = :partition_id',
                    ['partition_id' => $this->partition($tenant)],
                    ['partition_id' => ParameterType::STRING],
                ),
            );
        } finally {
            if (is_int($firstPid) && !$firstFinished) {
                $this->terminateRealChild($firstPid, $firstStatus);
            }
            if (is_int($secondPid) && !$secondFinished) {
                $this->terminateRealChild($secondPid, $secondStatus);
            }
            $this->cleanupRealOutboxSchema($admin, $parent, $probe, $schema);
            if ($seed instanceof Connection) {
                try {
                    $seed->close();
                } catch (\Throwable) {
                }
            }
            if ($unrelated instanceof Connection) {
                try {
                    $unrelated->close();
                } catch (\Throwable) {
                }
            }
            $this->unlinkRealTemporary($firstReady);
            $this->unlinkRealTemporary($firstResult);
            $this->unlinkRealTemporary($firstStart);
            $this->unlinkRealTemporary($secondReady);
            $this->unlinkRealTemporary($secondResult);
            $this->unlinkRealTemporary($secondStart);
        }
    }

    public function testRealPostgreSqlCallerOwnedLateFenceAbortsRootAndReconnectsSafely(): void
    {
        $admin = $caller = $owned = $reconnected = null;
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        try {
            $admin = $this->realConnection();
            $this->createRealOutboxSchema($admin, $schema);
            $this->installOutboxAbortTrigger($admin, $outboxSchema);

            $caller = $this->realConnection();
            $caller->beginTransaction();
            try {
                $this->store(
                    $caller,
                    new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                    new SequenceUuidGenerator([self::RACE_PARENT_RECORD_ID]),
                    schema: $schema,
                )->appendDurable($this->durableDraft(), 'caller-fence-key');
                self::fail('caller-owned CTE fence failure was accepted');
            } catch (AuditOutboxStoreException $exception) {
                self::assertSame(AuditOutboxStoreFailure::StorageUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
            self::assertTrue($caller->isTransactionActive());
            self::assertSame(1, $caller->getTransactionNestingLevel());
            try {
                $caller->fetchOne('SELECT 1');
                self::fail('aborted caller root accepted a statement');
            } catch (\Throwable) {
            }
            $caller->rollBack();
            self::assertFalse($caller->isTransactionActive());

            $this->dropOutboxAbortTrigger($admin, $outboxSchema);
            $owned = $this->realConnection();
            $this->installOutboxAbortTrigger($admin, $outboxSchema);
            try {
                $this->store(
                    $owned,
                    new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                    new SequenceUuidGenerator([self::SECOND_RECORD_ID]),
                    schema: $schema,
                )->appendDurable($this->durableDraft(), 'owned-fence-key');
                self::fail('owned CTE fence failure was accepted');
            } catch (AuditOutboxStoreException $exception) {
                self::assertSame(AuditOutboxStoreFailure::StorageUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
            self::assertFalse($owned->isTransactionActive());
            $this->dropOutboxAbortTrigger($admin, $outboxSchema);

            $reconnected = $this->realConnection();
            $result = $this->store(
                $reconnected,
                new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                new SequenceUuidGenerator([self::THIRD_RECORD_ID]),
                schema: $schema,
            )->appendDurable($this->durableDraft(), 'reconnect-key');
            self::assertTrue($result->inserted());
            self::assertSame(1, $result->record()->toArray()['ingressOrdinal']);
            self::assertSame(1, (int) $reconnected->fetchOne('SELECT count(*) FROM ' . $outboxSchema->recordsTable()));
        } finally {
            if ($admin instanceof Connection) {
                try {
                    $this->dropOutboxAbortTrigger($admin, $outboxSchema);
                } catch (\Throwable) {
                }
            }
            $this->cleanupRealOutboxSchema($admin, $caller, $owned, $schema);
            if ($reconnected instanceof Connection) {
                try {
                    $reconnected->close();
                } catch (\Throwable) {
                }
            }
        }
    }

    public function testRealPostgreSqlRetainedBoundaryAndPersistedFindTamperingStayUnchanged(): void
    {
        $cases = [
            'state ahead' => ['seedCount' => 1, 'tenant' => null, 'mutation' => 'state-ahead'],
            'state behind' => ['seedCount' => 2, 'tenant' => null, 'mutation' => 'state-behind'],
            'interior gap' => ['seedCount' => 3, 'tenant' => null, 'mutation' => 'interior-gap'],
            'state tenant mismatch' => [
                'seedCount' => 1,
                'tenant' => new AuditTypedReference('account', 'boundary-tenant'),
                'mutation' => 'state-tenant',
            ],
            'head tenant mismatch' => [
                'seedCount' => 1,
                'tenant' => new AuditTypedReference('account', 'boundary-head-tenant'),
                'mutation' => 'head-tenant',
            ],
        ];

        foreach ($cases as $label => $case) {
            $admin = $seed = $probe = $operation = null;
            $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
            $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
            $tenant = $case['tenant'];
            $partition = $this->partition($tenant);
            try {
                $admin = $this->realConnection();
                $this->createRealOutboxSchema($admin, $schema);
                $seed = $this->realConnection();
                $ids = [self::FIRST_RECORD_ID, self::SECOND_RECORD_ID, self::THIRD_RECORD_ID];
                foreach (range(1, $case['seedCount']) as $ordinal) {
                    $this->store(
                        $seed,
                        new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                        new SequenceUuidGenerator([$ids[$ordinal - 1]]),
                        schema: $schema,
                    )->appendDurable($this->durableDraft($tenant), 'boundary-seed-' . $ordinal);
                }
                $seed->close();
                $seed = null;
                $this->mutateRealBoundary($admin, $outboxSchema, $partition, $case['mutation']);
                $probe = $this->realConnection();
                $before = $this->realOutboxSnapshot($probe, $outboxSchema, $partition);
                $events = [];
                $operation = $this->realConnection();
                try {
                    $this->store(
                        $operation,
                        new FixtureClock(static function () use (&$events): void {
                            $events[] = 'clock';
                        }, new \DateTimeImmutable(self::NOW)),
                        new SequenceUuidGenerator([self::RACE_RETRY_RECORD_ID], static function () use (
                            &$events,
                        ): void {
                            $events[] = 'uuid';
                        }),
                        onUse: static function (string $event) use (&$events): void {
                            $events[] = $event;
                        },
                        schema: $schema,
                    )->appendDurable($this->durableDraft($tenant), 'boundary-new-' . $label);
                    self::fail($label . ' was accepted');
                } catch (AuditOutboxStoreException $exception) {
                    self::assertSame(AuditOutboxStoreFailure::IntegrityInvalid, $exception->failure(), $label);
                    self::assertNull($exception->getPrevious(), $label);
                }
                self::assertSame([], $events, $label);
                $after = $this->realOutboxSnapshot($probe, $outboxSchema, $partition);
                self::assertSame($before, $after, $label);
            } finally {
                $this->cleanupRealOutboxSchema($admin, $operation ?? $seed, $probe, $schema);
                if ($seed instanceof Connection) {
                    try {
                        $seed->close();
                    } catch (\Throwable) {
                    }
                }
            }
        }

        $admin = $seed = $probe = $operation = null;
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        try {
            $admin = $this->realConnection();
            $this->createRealOutboxSchema($admin, $schema);
            $seed = $this->realConnection();
            $valid = $this->store(
                $seed,
                new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                new SequenceUuidGenerator([self::FIRST_RECORD_ID]),
                schema: $schema,
            )->appendDurable($this->durableDraft(), 'persisted-tamper-key');
            $seed->close();
            $seed = null;
            $admin->executeStatement(
                'UPDATE '
                . $outboxSchema->recordsTable()
                . ' SET protected_body = set_byte(protected_body, 20, (get_byte(protected_body, 20) + 1) % 256) '
                . 'WHERE record_id = CAST(:record_id AS uuid)',
                ['record_id' => self::FIRST_RECORD_ID],
                ['record_id' => ParameterType::STRING],
            );
            $probe = $this->realConnection();
            $before = $this->realOutboxSnapshot($probe, $outboxSchema, self::SYSTEM_PARTITION);
            $events = [];
            $operation = $this->realConnection();
            try {
                $this->store(
                    $operation,
                    onUse: static function (string $event) use (&$events): void {
                        $events[] = $event;
                    },
                    schema: $schema,
                )->find(self::FIRST_RECORD_ID, null, self::SYSTEM_PARTITION);
                self::fail('persisted protected-body tamper was accepted');
            } catch (AuditOutboxStoreException $exception) {
                self::assertSame(AuditOutboxStoreFailure::IntegrityInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
            self::assertSame(['key'], $events);
            $after = $this->realOutboxSnapshot($probe, $outboxSchema, self::SYSTEM_PARTITION);
            self::assertSame($before, $after);
            self::assertSame($valid->record()->toArray()['recordId'], $before['records'][0]['record_id']);
        } finally {
            $this->cleanupRealOutboxSchema($admin, $operation ?? $seed, $probe, $schema);
            if ($seed instanceof Connection) {
                try {
                    $seed->close();
                } catch (\Throwable) {
                }
            }
        }
    }

    public function testRollbackUncertaintyQuarantinesTheConnectionWithoutLeakingTheCause(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturnCallback(static function (): never {
                throw new \RuntimeException('rollback-uncertainty-canary');
            });
        $connection
            ->expects(self::once())
            ->method('rollBack')
            ->willReturnCallback(static function (): never {
                throw new \RuntimeException('rollback-uncertainty-canary');
            });
        $connection->expects(self::once())->method('close');
        $this->expectNoConnectionUse($connection, [
            'isAutoCommit',
            'isTransactionActive',
            'getTransactionNestingLevel',
            'beginTransaction',
            'fetchOne',
            'rollBack',
            'close',
        ]);
        try {
            $this->store($connection)->appendDurable($this->durableDraft(), 'rollback-uncertainty-key');
            self::fail('rollback uncertainty was accepted');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
            $this->assertNoCanaryOnExceptionSurfaces($exception, 'rollback-uncertainty-canary');
        }
    }

    public function testRealPostgreSqlFindTenantTamperIsIndistinguishableAndLeavesBytesUnchanged(): void
    {
        $admin = $seed = $probe = $operation = null;
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        $tenant = new AuditTypedReference('account', 'find-tamper-tenant');
        try {
            $admin = $this->realConnection();
            $this->createRealOutboxSchema($admin, $schema);
            $seed = $this->realConnection();
            $this->store(
                $seed,
                new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                new SequenceUuidGenerator([self::FIRST_RECORD_ID]),
                schema: $schema,
            )->appendDurable($this->durableDraft($tenant), 'find-tenant-key');
            $seed->close();
            $seed = null;
            $partition = $this->partition($tenant);
            $admin->executeStatement(
                'UPDATE '
                . $outboxSchema->recordsTable()
                . ' SET tenant_id = :tenant_id WHERE record_id = CAST(:record_id AS uuid)',
                ['tenant_id' => 'tampered-tenant', 'record_id' => self::FIRST_RECORD_ID],
                ['tenant_id' => ParameterType::STRING, 'record_id' => ParameterType::STRING],
            );
            $probe = $this->realConnection();
            $before = $this->realOutboxSnapshot($probe, $outboxSchema, $partition);
            $operation = $this->realConnection();
            $result = $this->store($operation, schema: $schema)->find(self::FIRST_RECORD_ID, $tenant, $partition);
            self::assertFalse($result->isFound());
            self::assertNull($result->record());
            $after = $this->realOutboxSnapshot($probe, $outboxSchema, $partition);
            self::assertSame($before, $after);
        } finally {
            $this->cleanupRealOutboxSchema($admin, $operation ?? $seed, $probe, $schema);
            if ($seed instanceof Connection) {
                try {
                    $seed->close();
                } catch (\Throwable) {
                }
            }
        }
    }

    public function testRealPostgreSqlAppendsSystemAndTenantOrdinalsWithHighWaterClamp(): void
    {
        $admin = $this->realConnection();
        $connection = null;
        $probe = null;
        $schema = new PostgreSqlMigrationSchema(self::REAL_SCHEMA);
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        try {
            $admin->executeStatement('DROP SCHEMA IF EXISTS "' . self::REAL_SCHEMA . '" CASCADE');
            $admin->executeStatement('CREATE SCHEMA "' . self::REAL_SCHEMA . '"');
            foreach ($outboxSchema->statements() as $statement) {
                $admin->executeStatement($statement);
            }
            $connection = $this->realConnection();
            $clock = new SequenceClock([
                new \DateTimeImmutable('2026-09-01T03:00:00.123456Z'),
                new \DateTimeImmutable('2026-09-01T02:00:00.123456Z'),
                new \DateTimeImmutable('2026-09-01T04:00:00.123456Z'),
            ]);
            $uuids = new SequenceUuidGenerator([
                self::FIRST_RECORD_ID,
                self::SECOND_RECORD_ID,
                self::THIRD_RECORD_ID,
            ]);
            $store = $this->store($connection, $clock, $uuids, schema: $schema);
            $system = $this->durableDraft();
            $tenant = new AuditTypedReference('account', 'tenant-a');

            $first = $store->appendDurable($system, 'system-key-1');
            $second = $store->appendDurable($system, 'system-key-2');
            $tenantResult = $store->appendDurable($this->durableDraft($tenant), 'tenant-key-1');

            self::assertTrue($first->inserted());
            self::assertTrue($second->inserted());
            self::assertTrue($tenantResult->inserted());
            self::assertSame(1, $first->record()->toArray()['ingressOrdinal']);
            self::assertSame(2, $second->record()->toArray()['ingressOrdinal']);
            self::assertSame(1, $tenantResult->record()->toArray()['ingressOrdinal']);
            self::assertSame('2026-09-01T03:00:00.123456Z', $second->record()->toArray()['availableAt']);
            self::assertSame(
                2,
                (int) $connection->fetchOne(
                    'SELECT count(*) FROM ' . $outboxSchema->recordsTable() . ' WHERE partition_id = :partition_id',
                    ['partition_id' => self::SYSTEM_PARTITION],
                    ['partition_id' => ParameterType::STRING],
                ),
            );
            self::assertSame(
                1,
                (int) $connection->fetchOne(
                    'SELECT count(*) FROM ' . $outboxSchema->recordsTable() . ' WHERE partition_id <> :partition_id',
                    ['partition_id' => self::SYSTEM_PARTITION],
                    ['partition_id' => ParameterType::STRING],
                ),
            );

            $probe = $this->realConnection();
            $probe->executeStatement("SET TIME ZONE 'America/Los_Angeles'");
            $roundTrip = $this->store(
                $probe,
                new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                schema: $schema,
            )->find(self::FIRST_RECORD_ID, null, self::SYSTEM_PARTITION);
            self::assertTrue($roundTrip->isFound());
            self::assertSame(
                $first->record()->toArray()['protectedBody'],
                $roundTrip->record()?->toArray()['protectedBody'],
            );
            self::assertSame(self::NOW, $roundTrip->record()?->toArray()['availableAt']);

            $lifecycle = $this->store(
                $connection,
                new FixtureClock(null, new \DateTimeImmutable(self::NOW)),
                new SequenceUuidGenerator([self::TOKEN, self::NEXT_TOKEN]),
                schema: $schema,
            );
            $claimed = $lifecycle->claimNext();
            self::assertSame(self::FIRST_RECORD_ID, $claimed->record()?->toArray()['recordId']);
            self::assertSame(self::TOKEN, $claimed->record()?->toArray()['leaseFencingToken']);
            $relayed = $lifecycle->relay(self::FIRST_RECORD_ID, null, self::SYSTEM_PARTITION, 2, self::TOKEN);
            self::assertSame('relayed', $relayed->toArray()['state']);
            $next = $lifecycle->claimNext();
            self::assertSame(self::SECOND_RECORD_ID, $next->record()?->toArray()['recordId']);
        } finally {
            if ($probe instanceof Connection) {
                try {
                    if ($probe->isTransactionActive()) {
                        $probe->rollBack();
                    }
                } catch (\Throwable) {
                }
                $probe->close();
            }
            if ($connection instanceof Connection) {
                try {
                    if ($connection->isTransactionActive()) {
                        $connection->rollBack();
                    }
                } catch (\Throwable) {
                }
                $connection->close();
            }
            try {
                $admin->executeStatement('DROP SCHEMA IF EXISTS "' . self::REAL_SCHEMA . '" CASCADE');
            } finally {
                $admin->close();
            }
        }
    }

    /** @param list<string> $allowed */
    private function expectNoConnectionUse(Connection $connection, array $allowed): void
    {
        foreach ([
            'fetchAllAssociative',
            'fetchAssociative',
            'fetchOne',
            'executeQuery',
            'executeStatement',
            'isTransactionActive',
            'getTransactionNestingLevel',
            'beginTransaction',
            'commit',
            'rollBack',
            'createSavepoint',
            'releaseSavepoint',
            'rollbackSavepoint',
            'close',
        ] as $method) {
            if (!in_array($method, $allowed, true)) {
                $connection->expects(self::never())->method($method);
            }
        }
    }

    private function ownedTransactionFailureConnection(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::never())->method('commit');
        $connection->expects(self::once())->method('rollBack');
        $this->expectNoConnectionUse($connection, [
            'isAutoCommit',
            'isTransactionActive',
            'getTransactionNestingLevel',
            'beginTransaction',
            'commit',
            'rollBack',
        ]);

        return $connection;
    }

    /**
     * @param list<string> $events
     * @param list<array{method:string,sql:string,parameters:array<string,mixed>,types:array<string,mixed>}>|null $queries
     */
    private function deliveryConnection(
        array &$events,
        ?array $candidate,
        array|false $state,
        ?array $head,
        ?int $casResult,
        bool $expectCommit,
        bool $expectRollback,
        ?array &$queries = null,
        bool $includeCandidate = true,
    ): Connection {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        if ($expectCommit) {
            $connection->expects(self::once())->method('commit');
        } else {
            $connection->expects(self::never())->method('commit');
        }
        if ($expectRollback) {
            $connection->expects(self::once())->method('rollBack');
        } else {
            $connection->expects(self::never())->method('rollBack');
        }

        $hasPartitionWork = $includeCandidate && $candidate !== null || !$includeCandidate;
        if ($hasPartitionWork) {
            $connection
                ->expects(self::once())
                ->method('fetchOne')
                ->willReturnCallback(static function (string $sql, array $parameters, array $types) use (
                    &$events,
                    &$queries,
                ): string {
                    $events[] = 'partition-lock';
                    if ($queries !== null) {
                        $queries[] = [
                            'method' => 'fetchOne',
                            'sql' => $sql,
                            'parameters' => $parameters,
                            'types' => $types,
                        ];
                    }
                    return '0';
                });
        } else {
            $connection->expects(self::never())->method('fetchOne');
        }

        $fetchAllCalls = $includeCandidate ? ($candidate === null ? 1 : 2) : 1;
        $fetchAllCall = 0;
        $connection
            ->expects(self::exactly($fetchAllCalls))
            ->method('fetchAllAssociative')
            ->willReturnCallback(static function (string $sql, array $parameters, array $types) use (
                &$fetchAllCall,
                &$events,
                &$queries,
                $candidate,
                $head,
                $includeCandidate,
            ): array {
                $fetchAllCall++;
                $isCandidate = $includeCandidate && $fetchAllCall === 1;
                $events[] = $isCandidate ? 'candidate' : 'head';
                if ($queries !== null) {
                    $queries[] = [
                        'method' => 'fetchAll',
                        'sql' => $sql,
                        'parameters' => $parameters,
                        'types' => $types,
                    ];
                }
                if ($isCandidate) {
                    return $candidate === null ? [] : [$candidate];
                }

                return $head === null ? [] : [$head];
            });

        if ($hasPartitionWork) {
            $connection
                ->expects(self::once())
                ->method('fetchAssociative')
                ->willReturnCallback(static function (string $sql, array $parameters, array $types) use (
                    &$events,
                    &$queries,
                    $state,
                ): array|false {
                    $events[] = 'state';
                    if ($queries !== null) {
                        $queries[] = [
                            'method' => 'fetchAssociative',
                            'sql' => $sql,
                            'parameters' => $parameters,
                            'types' => $types,
                        ];
                    }
                    return $state;
                });
        } else {
            $connection->expects(self::never())->method('fetchAssociative');
        }

        if ($casResult !== null) {
            $connection
                ->expects(self::once())
                ->method('executeStatement')
                ->willReturnCallback(static function (string $sql, array $parameters, array $types) use (
                    &$events,
                    &$queries,
                    $casResult,
                ): int {
                    $events[] = 'cas';
                    if ($queries !== null) {
                        $queries[] = [
                            'method' => 'executeStatement',
                            'sql' => $sql,
                            'parameters' => $parameters,
                            'types' => $types,
                        ];
                    }
                    return $casResult;
                });
        } else {
            $connection->expects(self::never())->method('executeStatement');
        }

        $this->expectNoConnectionUse($connection, [
            'isAutoCommit',
            'isTransactionActive',
            'getTransactionNestingLevel',
            'beginTransaction',
            'commit',
            'rollBack',
            'fetchOne',
            'fetchAllAssociative',
            'fetchAssociative',
            'executeStatement',
        ]);

        return $connection;
    }

    /** @param list<array{method:string,sql:string,parameters:array<string,mixed>,types:array<string,mixed>}> $queries */
    private function queryByMethod(array $queries, string $method, int $index): array
    {
        $matches = array_values(array_filter($queries, static fn(array $query): bool => $query['method'] === $method));
        self::assertArrayHasKey($index, $matches);

        return $matches[$index];
    }

    /** @return array<string,mixed> */
    private function state(?AuditTypedReference $tenant, string $assignedAt, int $ordinal): array
    {
        $partition = $this->partition($tenant);
        return [
            'partition_id' => $partition,
            'tenant_type' => $tenant?->type,
            'tenant_id' => $tenant?->id,
            'last_ingress_ordinal' => $ordinal,
            'last_assigned_recorded_at' => $assignedAt,
            'state_version' => $ordinal,
        ];
    }

    /** @return array<string,mixed> */
    private function aggregate(int $count, ?int $minimum, ?int $maximum, int $mismatch): array
    {
        return [
            'row_count' => $count,
            'min_ordinal' => $minimum,
            'max_ordinal' => $maximum,
            'tenant_mismatch_count' => $mismatch,
        ];
    }

    /** @param list<array<string,mixed>> $idempotency */
    private function appendConnection(
        bool $callerOwned,
        array &$events,
        array|false $state,
        array $aggregate,
        mixed $orphan,
        array $idempotency,
        ?string $writeResult,
        bool $commitFailure = false,
        ?bool $expectSuccess = null,
        ?array &$queries = null,
    ): Connection {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn($callerOwned);
        $connection
            ->expects(self::once())
            ->method('getTransactionNestingLevel')
            ->willReturn($callerOwned ? 1 : 0);
        $validBoundary = $state === false
            ? $aggregate === $this->aggregate(0, null, null, 0)
            : $aggregate === $this->aggregate($state['last_ingress_ordinal'], 1, $state['last_ingress_ordinal'], 0);
        $orphanExists = $orphan !== null && (string) $orphan !== '0';
        $expectsIdempotency = $validBoundary && !$orphanExists;
        $success = $expectSuccess ?? $expectsIdempotency && ($idempotency !== [] || $writeResult === '1');

        if ($callerOwned) {
            foreach (['beginTransaction', 'commit', 'rollBack', 'close'] as $method) {
                $connection->expects(self::never())->method($method);
            }
        } else {
            $connection->expects(self::once())->method('beginTransaction');
            if ($commitFailure) {
                $connection
                    ->expects(self::once())
                    ->method('commit')
                    ->willReturnCallback(static function (): never {
                        throw new \RuntimeException('commit-canary');
                    });
                $connection->expects(self::once())->method('rollBack');
                $connection->expects(self::once())->method('close');
            } else {
                if ($success) {
                    $connection->expects(self::once())->method('commit');
                } else {
                    $connection->expects(self::never())->method('commit');
                }
                if (!$success) {
                    $connection->expects(self::once())->method('rollBack');
                } else {
                    $connection->expects(self::never())->method('rollBack');
                }
            }
        }

        $fetchOneReturns = ['0'];
        $fetchOneLabels = ['partition-lock'];
        if ($state === false && $validBoundary) {
            $fetchOneReturns[] = $orphan === null ? '0' : (string) $orphan;
            $fetchOneLabels[] = 'orphan';
        }
        if ($expectsIdempotency && $idempotency === [] && $writeResult !== null) {
            $fetchOneReturns[] = '0';
            $fetchOneLabels[] = 'record-lock';
            $fetchOneReturns[] = $writeResult === 'collision' ? '1' : '0';
            $fetchOneLabels[] = 'record-probe';
            if ($writeResult !== 'collision') {
                $fetchOneReturns[] = $writeResult;
                $fetchOneLabels[] = 'write';
            }
        }
        $fetchOneCall = 0;
        $connection
            ->expects(self::exactly(count($fetchOneReturns)))
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql, array $parameters, array $types) use (
                &$fetchOneCall,
                &$events,
                $fetchOneReturns,
                $fetchOneLabels,
                &$queries,
            ): string {
                $fetchOneCall++;
                $index = $fetchOneCall - 1;
                $events[] = $fetchOneLabels[$index] ?? 'unexpected-fetch-one';
                if ($queries !== null) {
                    $queries[] = [
                        'method' => 'fetchOne',
                        'sql' => $sql,
                        'parameters' => $parameters,
                        'types' => $types,
                    ];
                }
                return (string) ($fetchOneReturns[$index] ?? '0');
            });

        $fetchAssociativeReturns = [$state, $aggregate];
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnCallback(static function (string $sql, array $parameters, array $types) use (
                &$fetchAssociativeReturns,
                &$events,
                &$queries,
            ): array|false {
                $events[] = count($fetchAssociativeReturns) === 2 ? 'state' : 'aggregate';
                if ($queries !== null) {
                    $queries[] = [
                        'method' => 'fetchAssociative',
                        'sql' => $sql,
                        'parameters' => $parameters,
                        'types' => $types,
                    ];
                }
                $value = array_shift($fetchAssociativeReturns);
                return $value;
            });
        if ($expectsIdempotency) {
            $connection
                ->expects(self::once())
                ->method('fetchAllAssociative')
                ->willReturnCallback(static function (string $sql, array $parameters, array $types) use (
                    &$events,
                    $idempotency,
                    &$queries,
                ): array {
                    $events[] = 'idempotency';
                    if ($queries !== null) {
                        $queries[] = [
                            'method' => 'fetchAll',
                            'sql' => $sql,
                            'parameters' => $parameters,
                            'types' => $types,
                        ];
                    }
                    return $idempotency;
                });
        } else {
            $connection->expects(self::never())->method('fetchAllAssociative');
        }

        return $connection;
    }

    private function store(
        Connection $connection,
        ?ClockInterface $clock = null,
        ?Uuidv7Generator $uuids = null,
        bool|string $keyFailure = false,
        ?\Closure $onUse = null,
        ?PostgreSqlMigrationSchema $schema = null,
        ?AuditOutboxConfiguration $configuration = null,
    ): PostgreSqlAuditOutboxStore {
        $parts = $this->components($keyFailure, $onUse);
        return new PostgreSqlAuditOutboxStore(
            $connection,
            new PostgreSqlAuditOutboxSchema($schema ?? new PostgreSqlMigrationSchema('blackops')),
            $clock ?? new FixtureClock(),
            $uuids ?? new SequenceUuidGenerator([self::FIRST_RECORD_ID]),
            $parts['validator'],
            $parts['finalizer'],
            $parts['partitions'],
            $parts['envelopes'],
            $parts['protection'],
            $parts['hasher'],
            $parts['hydrator'],
            $configuration ?? new AuditOutboxConfiguration(),
        );
    }

    /** @return array<string,mixed> */
    private function components(bool|string $keyFailure = false, ?\Closure $onUse = null): array
    {
        $jcs = new AuditJcsCodec();
        $partitions = new AuditPartitionIdDeriver($jcs);
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            FrameworkAuditEventCatalog::catalog(),
        ]));
        $records = new AuditRecordCodec($validator, $jcs);
        $envelopes = new AuditIngressEnvelopeCodec($records, $partitions, $jcs);
        $protection = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            new FixtureAuditKeySource($keyFailure, $onUse),
            new FixtureNonceSource($onUse),
            new AuditAssociatedData(),
            $records,
            $envelopes,
        );
        return [
            'jcs' => $jcs,
            'partitions' => $partitions,
            'validator' => $validator,
            'records' => $records,
            'envelopes' => $envelopes,
            'protection' => $protection,
            'finalizer' => new AuditRecordFinalizer($validator, $records),
            'hasher' => new \BlackOps\Internal\Audit\Outbox\AuditOutboxIdempotencyKeyHasher(),
            'hydrator' => new \BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxRecordHydrator(
                new \BlackOps\Internal\Audit\Outbox\PostgreSqlAuditProtectedIngressHydrator(),
            ),
        ];
    }

    /** @return array<string,mixed> */
    private function outboxRow(
        AuditRecordDraft $draft,
        string $recordId,
        string $recordedAt,
        int $ordinal,
        string $idempotencyKey,
    ): array {
        $parts = $this->components();
        $logical = $parts['finalizer']->finalize($draft, new AuditRecordStamp($recordId, $recordedAt));
        $envelope = $parts['envelopes']->create($logical, $ordinal, $idempotencyKey);
        $ingress = $parts['protection']->protectOutbox($envelope)->toArray();
        $tenant = $ingress['tenantBinding'];
        return [
            'schema_version' => 1,
            'kind' => 'audit_outbox_record',
            'record_id' => $ingress['recordId'],
            'partition_id' => $ingress['partitionId'],
            'tenant_type' => is_array($tenant) ? $tenant['type'] : null,
            'tenant_id' => is_array($tenant) ? $tenant['id'] : null,
            'ingress_ordinal' => $ingress['ingressOrdinal'],
            'idempotency_key_hash' => $ingress['idempotencyKeyHash'],
            'content_hash' => $ingress['contentHash'],
            'state' => 'pending',
            'attempt' => 0,
            'available_at' => $recordedAt,
            'lease_fencing_token' => null,
            'lease_expires_at' => null,
            'protected_body' => $ingress['protectedBody'],
            'version' => 1,
        ];
    }

    /** @return array<string,mixed> */
    private function leasedRow(
        AuditRecordDraft $draft,
        string $recordId,
        string $recordedAt,
        int $ordinal,
        string $idempotencyKey,
        string $leaseExpiresAt,
        int $attempt = 1,
        int $version = 2,
    ): array {
        return [
            ...$this->outboxRow($draft, $recordId, $recordedAt, $ordinal, $idempotencyKey),
            'state' => 'leased',
            'attempt' => $attempt,
            'available_at' => null,
            'lease_fencing_token' => self::TOKEN,
            'lease_expires_at' => $leaseExpiresAt,
            'version' => $version,
        ];
    }

    private function durableDraft(?AuditTypedReference $tenant = null): AuditRecordDraft
    {
        $definition = $this->durableDefinition();
        $tuple = $definition->semanticTuples[0];
        return new AuditRecordDraft(
            new AuditRecordEvent(
                $definition->type,
                $definition->version,
                $definition->category,
                FrameworkAuditEventCatalog::catalog()->catalogHash,
            ),
            new AuditRecordAction('action-requested', $tuple->actionType, $tuple->phase),
            new AuditRecordActor(
                new AuditTypedReference('user', 'actor-1'),
                new AuditTypedReference('process', 'blackops-cli'),
            ),
            new AuditRecordResource($tuple->resourceType, 'operation-1'),
            new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
            new AuditRecordPolicy(
                'operation-read',
                1,
                $tuple->policyDecision,
                $tuple->policyReasonCode,
                AuditDelivery::Durable,
            ),
            $tenant,
            '2026-08-19T00:00:00.000000Z',
            new AuditRecordSource(AuditSourceOwner::Framework, 'operation-status', AuditSourceRuntime::Cli, 'build-1'),
            AuditRecordCorrelation::fromArray([
                'operationId' => null,
                'correlationId' => null,
                'causationId' => null,
                'attempt' => null,
                'traceId' => null,
                'spanId' => null,
                'sampled' => null,
            ]),
            new AuditRecordEvidence($definition->evidenceVersion),
        );
    }

    private function draftWithResource(string $resourceId): AuditRecordDraft
    {
        $draft = $this->durableDraft();
        return new AuditRecordDraft(
            $draft->event,
            $draft->action,
            $draft->actor,
            new AuditRecordResource($draft->resource->type, $resourceId),
            $draft->outcome,
            $draft->policy,
            $draft->tenant,
            $draft->occurredAt,
            $draft->source,
            $draft->correlation,
            $draft->evidence,
        );
    }

    private function durableDefinition(): AuditEventDefinition
    {
        foreach (FrameworkAuditEventCatalog::catalog()->events as $definition) {
            if ($definition->delivery === AuditDelivery::Durable) {
                return $definition;
            }
        }

        throw new \RuntimeException('durable fixture definition is unavailable');
    }

    private function partition(?AuditTypedReference $tenant): string
    {
        return new AuditPartitionIdDeriver(new AuditJcsCodec())->derive($tenant);
    }

    private function realConnection(): Connection
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

    private function createRealOutboxSchema(Connection $admin, PostgreSqlMigrationSchema $schema): void
    {
        $admin->executeStatement('DROP SCHEMA IF EXISTS ' . $schema->quoted() . ' CASCADE');
        $admin->executeStatement('CREATE SCHEMA ' . $schema->quoted());
        $outboxSchema = new PostgreSqlAuditOutboxSchema($schema);
        foreach ($outboxSchema->statements() as $statement) {
            $admin->executeStatement($statement);
        }
    }

    private function cleanupRealOutboxSchema(
        ?Connection $admin,
        ?Connection $primary,
        ?Connection $probe,
        PostgreSqlMigrationSchema $schema,
    ): void {
        foreach ([$primary, $probe] as $connection) {
            if ($connection instanceof Connection) {
                try {
                    if ($connection->isTransactionActive()) {
                        $connection->rollBack();
                    }
                } catch (\Throwable) {
                }
                try {
                    $connection->close();
                } catch (\Throwable) {
                }
            }
        }
        if ($admin instanceof Connection) {
            try {
                $admin->executeStatement('DROP SCHEMA IF EXISTS ' . $schema->quoted() . ' CASCADE');
            } catch (\Throwable) {
            }
            try {
                $admin->close();
            } catch (\Throwable) {
            }
        }
    }

    private function installOutboxAbortTrigger(Connection $admin, PostgreSqlAuditOutboxSchema $schema): void
    {
        $function = $this->realFunctionIdentifier($schema, 'audit_outbox_test_abort_insert');
        $admin->executeStatement(
            'CREATE FUNCTION ' . $function . '() RETURNS trigger LANGUAGE plpgsql AS ' . "'BEGIN RETURN NULL; END;'",
        );
        $admin->executeStatement(
            'CREATE TRIGGER audit_outbox_test_abort BEFORE INSERT ON '
            . $schema->recordsTable()
            . ' FOR EACH ROW EXECUTE FUNCTION '
            . $function
            . '()',
        );
    }

    private function dropOutboxAbortTrigger(Connection $admin, PostgreSqlAuditOutboxSchema $schema): void
    {
        $function = $this->realFunctionIdentifier($schema, 'audit_outbox_test_abort_insert');
        $admin->executeStatement('DROP TRIGGER IF EXISTS audit_outbox_test_abort ON ' . $schema->recordsTable());
        $admin->executeStatement('DROP FUNCTION IF EXISTS ' . $function . '()');
    }

    private function mutateRealBoundary(
        Connection $admin,
        PostgreSqlAuditOutboxSchema $schema,
        string $partition,
        string $mutation,
    ): void {
        $state = $schema->partitionStateTable();
        $records = $schema->recordsTable();
        $parameters = ['partition_id' => $partition];
        $types = ['partition_id' => ParameterType::STRING];
        match ($mutation) {
            'state-ahead' => $admin->executeStatement(
                'UPDATE '
                . $state
                . ' SET last_ingress_ordinal = 2, state_version = 2 WHERE partition_id = :partition_id',
                $parameters,
                $types,
            ),
            'state-behind' => $admin->executeStatement(
                'UPDATE '
                . $state
                . ' SET last_ingress_ordinal = 1, state_version = 1 WHERE partition_id = :partition_id',
                $parameters,
                $types,
            ),
            'interior-gap' => $admin->executeStatement(
                'DELETE FROM ' . $records . ' WHERE partition_id = :partition_id AND ingress_ordinal = 2',
                $parameters,
                $types,
            ),
            'state-tenant' => $admin->executeStatement(
                'UPDATE '
                . $state
                . ' SET tenant_type = \'tampered\', tenant_id = \'tampered\' WHERE partition_id = :partition_id',
                $parameters,
                $types,
            ),
            'head-tenant' => $admin->executeStatement(
                'UPDATE '
                . $records
                . ' SET tenant_type = \'tampered\', tenant_id = \'tampered\' '
                . 'WHERE partition_id = :partition_id AND ingress_ordinal = 1',
                $parameters,
                $types,
            ),
            default => throw new \InvalidArgumentException('unknown boundary mutation'),
        };
    }

    /** @return array{state:array<string,mixed>|false,records:list<array<string,mixed>>} */
    private function realOutboxSnapshot(
        Connection $connection,
        PostgreSqlAuditOutboxSchema $schema,
        string $partition,
    ): array {
        return [
            'state' => $connection->fetchAssociative(
                'SELECT partition_id, tenant_type, tenant_id, last_ingress_ordinal, '
                . 'to_char(last_assigned_recorded_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS last_assigned_recorded_at, '
                . 'state_version FROM '
                . $schema->partitionStateTable()
                . ' WHERE partition_id = :partition_id',
                ['partition_id' => $partition],
                ['partition_id' => ParameterType::STRING],
            ),
            'records' => $this->realOutboxRecords($connection, $schema, $partition),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function realOutboxRecords(
        Connection $connection,
        PostgreSqlAuditOutboxSchema $schema,
        ?string $partition = null,
    ): array {
        $where = $partition === null ? '' : ' WHERE partition_id = :partition_id';
        $rows = $connection->fetchAllAssociative(
            'SELECT schema_version, kind, record_id::text AS record_id, partition_id, tenant_type, tenant_id, '
            . 'ingress_ordinal, idempotency_key_hash, content_hash, state, attempt, '
            . "CASE WHEN available_at IS NULL THEN NULL ELSE to_char(available_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') END AS available_at, "
            . 'lease_fencing_token::text AS lease_fencing_token, '
            . "CASE WHEN lease_expires_at IS NULL THEN NULL ELSE to_char(lease_expires_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') END AS lease_expires_at, "
            . 'protected_body, version FROM '
            . $schema->recordsTable()
            . $where
            . ' ORDER BY partition_id, ingress_ordinal',
            $partition === null ? [] : ['partition_id' => $partition],
            $partition === null ? [] : ['partition_id' => ParameterType::STRING],
        );
        foreach ($rows as &$row) {
            if (is_resource($row['protected_body'] ?? null)) {
                $body = stream_get_contents($row['protected_body']);
                if (is_string($body)) {
                    $row['protected_body'] = $body;
                }
            }
        }
        unset($row);

        return $rows;
    }

    /** @return array<string,mixed> */
    private function realOutboxState(
        Connection $connection,
        PostgreSqlAuditOutboxSchema $schema,
        string $partition,
    ): array {
        $row = $connection->fetchAssociative(
            'SELECT partition_id, tenant_type, tenant_id, last_ingress_ordinal, '
            . 'to_char(last_assigned_recorded_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS last_assigned_recorded_at, '
            . 'state_version FROM '
            . $schema->partitionStateTable()
            . ' WHERE partition_id = :partition_id',
            ['partition_id' => $partition],
            ['partition_id' => ParameterType::STRING],
        );
        self::assertIsArray($row);

        return $row;
    }

    private function realFunctionIdentifier(PostgreSqlAuditOutboxSchema $schema, string $name): string
    {
        return '"' . $schema->schemaName() . '"."' . $name . '"';
    }

    private function lockRealPartition(
        Connection $connection,
        PostgreSqlMigrationSchema $schema,
        string $partition,
    ): void {
        $connection->fetchOne(
            'SELECT pg_advisory_xact_lock(hashtextextended(:lock_key, 0))',
            ['lock_key' => $this->partitionLockKey(new PostgreSqlAuditOutboxSchema($schema), $partition)],
            ['lock_key' => ParameterType::STRING],
        );
    }

    private function lockRealRecord(Connection $connection, PostgreSqlMigrationSchema $schema, string $recordId): void
    {
        $connection->fetchOne(
            'SELECT pg_advisory_xact_lock(hashtextextended(:lock_key, 0))',
            ['lock_key' => $this->recordLockKey(new PostgreSqlAuditOutboxSchema($schema), $recordId)],
            ['lock_key' => ParameterType::STRING],
        );
    }

    private function partitionLockKey(PostgreSqlAuditOutboxSchema $schema, string $partition): string
    {
        return 'blackops.audit.outbox.partition.v1:' . $schema->schemaName() . ':' . $partition;
    }

    private function recordLockKey(PostgreSqlAuditOutboxSchema $schema, string $recordId): string
    {
        return 'blackops.audit.outbox.record.v1:' . $schema->schemaName() . ':' . $recordId;
    }

    private function realAdvisoryLockPresent(Connection $probe, string $pidFile, string $lockKey, bool $granted): bool
    {
        $contents = file_get_contents($pidFile);
        self::assertIsString($contents);
        $pid = (int) trim($contents);
        $grantedLiteral = $granted ? 'TRUE' : 'FALSE';
        $count = $probe->fetchOne(
            'WITH lock_hash AS (SELECT hashtextextended(:lock_key, 0) AS value) '
            . 'SELECT count(*) FROM pg_locks, lock_hash '
            . 'WHERE pg_locks.pid = :pid AND locktype = \'advisory\' '
            . 'AND granted = '
            . $grantedLiteral
            . ' AND classid::bigint = ((lock_hash.value >> 32) & 4294967295)'
            . ' AND objid::bigint = (lock_hash.value & 4294967295)',
            ['lock_key' => $lockKey, 'pid' => $pid],
            ['lock_key' => ParameterType::STRING, 'pid' => ParameterType::INTEGER],
        );

        return (int) $count === 1;
    }

    private function waitForRealFile(string $path, string $description): void
    {
        $deadline = microtime(true) + 5.0;
        while (!is_file($path) && microtime(true) < $deadline) {
            usleep(10_000);
        }
        if (!is_file($path)) {
            self::fail($description . ' timed out');
        }
    }

    /** @return array{wait_event_type:string,wait_event:string,state:string,query:string} */
    private function waitForRealDatabaseLock(Connection $probe, string $ready, ?string $resultFile = null): array
    {
        $deadline = microtime(true) + 5.0;
        $lastWait = null;
        $lastPid = null;
        while (microtime(true) < $deadline) {
            $readyContents = file_get_contents($ready);
            $backendPid = trim(is_string($readyContents) ? $readyContents : '');
            if ($backendPid !== '') {
                $lastPid = $backendPid;
                $wait = $probe->fetchAssociative(
                    'SELECT wait_event_type, wait_event, state, query FROM pg_stat_activity WHERE pid = :pid',
                    ['pid' => (int) $backendPid],
                    ['pid' => ParameterType::INTEGER],
                );
                $lastWait = $wait;
                if (
                    is_array($wait)
                    && $wait['wait_event_type'] === 'Lock'
                    && is_string($wait['wait_event'])
                    && $wait['wait_event'] !== ''
                ) {
                    /** @var array{wait_event_type:string,wait_event:string,state:string,query:string} $wait */
                    return $wait;
                }
            }
            usleep(10_000);
        }

        self::fail(
            'PostgreSQL lock wait timed out: '
                . json_encode([
                    'pid' => $lastPid,
                    'wait' => $lastWait,
                    'result' => $resultFile !== null && is_file($resultFile) ? file_get_contents($resultFile) : null,
                ], JSON_THROW_ON_ERROR),
        );
    }

    private function waitForRealChild(int $pid, int &$status): bool
    {
        $deadline = microtime(true) + 5.0;
        do {
            $waited = pcntl_waitpid($pid, $status, WNOHANG);
            if ($waited === $pid) {
                return true;
            }
            if ($waited === -1) {
                return false;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        $this->terminateRealChild($pid, $status);

        return false;
    }

    private function terminateRealChild(int $pid, int &$status): void
    {
        posix_kill($pid, SIGKILL);
        $deadline = microtime(true) + 2.0;
        do {
            $waited = pcntl_waitpid($pid, $status, WNOHANG);
            if ($waited === $pid || $waited === -1) {
                return;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);
    }

    private function unlinkRealTemporary(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function assertNoCanaryOnExceptionSurfaces(AuditOutboxStoreException $exception, string $canary): void
    {
        $surfaces = [
            $exception->getMessage(),
            $exception->getPrevious()?->getMessage() ?? '',
            $exception->getTraceAsString(),
            print_r($exception, true),
            $this->safeVarExport($exception),
            serialize($exception),
            json_encode($exception, JSON_THROW_ON_ERROR),
        ];
        foreach ($surfaces as $index => $surface) {
            self::assertStringNotContainsString($canary, $surface, 'leak surface ' . $index);
        }
    }

    private function safeVarExport(mixed $value): string
    {
        set_error_handler(static fn(): bool => true);
        try {
            $exported = var_export($value, true);
        } finally {
            restore_error_handler();
        }
        self::assertIsString($exported);

        return $exported;
    }
}

final class FixtureClock implements ClockInterface
{
    public function __construct(
        private ?\Closure $onUse = null,
        private ?\DateTimeImmutable $value = null,
    ) {}

    public function now(): \DateTimeImmutable
    {
        $this->onUse?->__invoke();
        return $this->value ?? new \DateTimeImmutable('2026-09-01T03:00:00.123456Z');
    }
}

final class SequenceClock implements ClockInterface
{
    /** @param list<\DateTimeImmutable> $values */
    public function __construct(
        private array $values,
    ) {}

    public function now(): \DateTimeImmutable
    {
        $value = array_shift($this->values);
        if (!$value instanceof \DateTimeImmutable) {
            throw new \RuntimeException('clock sequence exhausted');
        }
        return $value;
    }
}

final class SequenceUuidGenerator implements Uuidv7Generator
{
    /** @param list<string> $ids */
    public function __construct(
        private array $ids,
        private ?\Closure $onUse = null,
    ) {}

    public function generate(\DateTimeImmutable $time): string
    {
        $this->onUse?->__invoke();
        $id = array_shift($this->ids);
        if (!is_string($id)) {
            throw new \RuntimeException('uuid sequence exhausted');
        }
        return $id;
    }
}

final class FixtureNonceSource implements NonceSource
{
    public function __construct(
        private ?\Closure $onUse = null,
    ) {}

    public function generate(): string
    {
        $this->onUse?->__invoke('nonce');
        return str_repeat('n', 24);
    }
}

final class FixtureAuditKeySource implements AuditProtectionKeySource
{
    public function __construct(
        private bool|string $failure = false,
        private ?\Closure $onUse = null,
    ) {}

    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        $this->onUse?->__invoke('key');
        if ($this->failure !== false) {
            throw new \LogicException(is_string($this->failure) ? $this->failure : 'key failure');
        }
        return new StorageKey('test-key', str_repeat('k', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    }

    public function key(string $keyId, ?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        $this->onUse?->__invoke('key');
        if ($this->failure !== false) {
            throw new \LogicException(is_string($this->failure) ? $this->failure : 'key failure');
        }
        return new StorageKey($keyId, str_repeat('k', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    }
}
