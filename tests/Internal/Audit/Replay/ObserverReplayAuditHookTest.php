<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Replay;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Replay\ObserverReplayAuditHook;
use BlackOps\Internal\Audit\Replay\ObserverReplayAuditHookGuard;
use BlackOps\Internal\Audit\Replay\ObserverReplayAuditHookLineage;
use BlackOps\Internal\Audit\Replay\ObserverReplayAuditWriter;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionClass;
use ReflectionProperty;

final class ObserverReplayAuditHookTest extends TestCase
{
    private const string AUDIT_ID = '0123456789abcdef0123456789abcdef';
    private const string CHECKPOINT = 'observer-replay-checkpoint';
    private const string RECORD_ID = '019f80ab-4000-7000-8000-000000000011';
    private const string CANARY = 'observer-replay-hook-private-canary';

    public function testEntryAdmissionIsReadOnlyAndUsesTheBoundConnection(): void
    {
        $connection = $this->connection(true, false, 0);
        $clock = new ObserverReplayHookClock();
        $hook = $this->hook($connection, $clock);

        $hook->assertEntry($connection, self::CHECKPOINT);

        self::assertSame(0, $clock->calls);
    }

    public function testEntryRejectsAmbientTransactionBeforeNativeWork(): void
    {
        $connection = $this->connection(true, true, 1);
        $hook = $this->hook($connection, new ObserverReplayHookClock());

        try {
            $hook->assertEntry($connection, self::CHECKPOINT);
            self::fail('ambient transaction was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
    }

    public function testEntryAndEventsRejectNonAutoCommitAndNestedRootsBeforeWriterEffects(): void
    {
        foreach ([
            $this->connection(false, false, 0),
            $this->connection(true, true, 2),
        ] as $connection) {
            $clock = new ObserverReplayHookClock();
            $hook = $this->hook($connection, $clock);
            $failure = null;
            try {
                $hook->started($connection, self::CHECKPOINT, self::AUDIT_ID);
            } catch (AuditDeliveryException $exception) {
                $failure = $exception;
            }
            self::assertInstanceOf(AuditDeliveryException::class, $failure);
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
            self::assertSame(0, $clock->calls);
        }
    }

    public function testRootAndConnectionAdmissionRejectForeignRoots(): void
    {
        $connection = $this->connection(true, true, 1);
        $hook = $this->hook($connection, new ObserverReplayHookClock());

        try {
            $hook->started($this->connection(true, true, 1), self::CHECKPOINT, self::AUDIT_ID);
            self::fail('a foreign connection was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
    }

    public function testProgressRequiresASelectedRecordAndStrictCursorLineage(): void
    {
        $connection = $this->connection(true, true, 1);
        $clock = new ObserverReplayHookClock();
        $hook = $this->hook($connection, $clock);
        $record = $this->record(new TenantRef('account', 'tenant-1'));

        $cases = [
            ['cursor' => 'bad-cursor', 'source' => $record],
            ['cursor' => '2|' . self::RECORD_ID, 'source' => $record],
        ];
        foreach ($cases as $case) {
            try {
                $hook->progressed($connection, self::CHECKPOINT, self::AUDIT_ID, $case['cursor'], $case['source']);
                self::fail('invalid selected lineage was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
                self::assertNull($failure->getPrevious());
            }
        }
        self::assertSame(0, $clock->calls);
    }

    public function testPausedFinishedValidatesAdmissionWithoutClockOrDelivery(): void
    {
        $connection = $this->connection(true, true, 1);
        $clock = new ObserverReplayHookClock();
        $hook = $this->hook($connection, $clock);

        $hook->finished($connection, self::CHECKPOINT, self::AUDIT_ID, 'paused');

        try {
            $hook->finished($connection, self::CHECKPOINT, self::AUDIT_ID, 'invalid');
            self::fail('an invalid terminal state was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
        }

        self::assertSame(0, $clock->calls);
    }

    public function testValidLineageReachesWriterAfterHistoricalValidation(): void
    {
        $connection = $this->connection(true, true, 1);
        $clock = new ObserverReplayHookClock();
        $hook = $this->hook($connection, $clock);

        try {
            $hook->progressed(
                $connection,
                self::CHECKPOINT,
                self::AUDIT_ID,
                '1|' . self::RECORD_ID,
                $this->record(new TenantRef('account', 'tenant-1')),
            );
            self::fail('an uninitialized delivery binding was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        self::assertSame(1, $clock->calls);
    }

    public function testConsumedRecordValidationRejectsMalformedHistoricalFields(): void
    {
        $connection = $this->connection(true, true, 1);
        $clock = new ObserverReplayHookClock();
        $hook = $this->hook($connection, $clock);
        $valid = $this->record(new TenantRef('account', 'tenant-1'));
        $cases = [
            ['record' => $this->corruptRecord('uuid-v4'), 'cursor' => self::RECORD_ID],
            ['record' => $this->corruptRecord('uppercase-id'), 'cursor' => self::RECORD_ID],
            ['record' => $this->corruptRecord('zero-sequence'), 'cursor' => self::RECORD_ID],
            ['record' => $this->corruptRecord('negative-sequence'), 'cursor' => self::RECORD_ID],
            ['record' => $this->corruptRecord('non-utc-time'), 'cursor' => self::RECORD_ID],
            ['record' => $this->corruptRecord('malformed-tenant'), 'cursor' => self::RECORD_ID],
            ['record' => $this->corruptRecord('uninitialized-operation'), 'cursor' => self::RECORD_ID],
            ['record' => $this->corruptRecord('uninitialized-tenant'), 'cursor' => self::RECORD_ID],
            ['record' => $this->corruptRecord('uninitialized-record-id'), 'cursor' => self::RECORD_ID],
            ['record' => $this->corruptRecord('uninitialized-sequence'), 'cursor' => self::RECORD_ID],
            ['record' => $this->corruptRecord('uninitialized-occurred-at'), 'cursor' => self::RECORD_ID],
            ['record' => $valid, 'cursor' => '2026-09-29 12:00:02.000000+00:00|' . self::RECORD_ID],
        ];

        foreach ($cases as $case) {
            $failure = null;
            try {
                $hook->progressed($connection, self::CHECKPOINT, self::AUDIT_ID, $case['cursor'], $case['record']);
                self::fail('malformed consumed lineage was accepted');
            } catch (AuditDeliveryException $exception) {
                $failure = $exception;
            }
            self::assertInstanceOf(AuditDeliveryException::class, $failure);
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        self::assertSame(0, $clock->calls);
    }

    public function testConsumedLineageAcceptsTenantAndGlobalRecordsBeforeWriter(): void
    {
        foreach ([null, new TenantRef('account', 'tenant-1')] as $tenant) {
            $connection = $this->connection(true, true, 1);
            $clock = new ObserverReplayHookClock();
            $hook = $this->hook($connection, $clock);
            try {
                $hook->progressed(
                    $connection,
                    self::CHECKPOINT,
                    self::AUDIT_ID,
                    '1|' . self::RECORD_ID,
                    $this->record($tenant),
                );
                self::fail('valid consumed lineage was rejected without a writer binding');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
                self::assertNull($failure->getPrevious());
            }
            self::assertSame(1, $clock->calls);
        }
    }

    public function testMalformedHookSnapshotFailsClosedWithoutLeakingWriter(): void
    {
        $hook = $this->hook($this->connection(true, false, 0), new ObserverReplayHookClock());
        new ReflectionProperty($hook, 'snapshot')->setValue($hook, new \SensitiveParameterValue([
            'writer' => self::CANARY,
        ]));

        try {
            $hook->assertEntry($this->connection(true, false, 0), self::CHECKPOINT);
            self::fail('a malformed hook snapshot was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString(self::CANARY, $failure->getTraceAsString());
        }
        self::assertSame([], $hook->__debugInfo());
        self::assertSame([], $hook->__serialize());
        try {
            clone $hook;
            self::fail('hook cloning must fail closed');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        try {
            $hook->__unserialize([]);
            self::fail('hook unserialization must fail closed');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
    }

    public function testOwnedHookFramesRedactSensitivePhaseArguments(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            $connection = $this->connection(true, true, 1);
            $hook = $this->hook($connection, new ObserverReplayHookClock());
            $validRecord = $this->record(new TenantRef('account', 'tenant-1'));
            $cases = [
                'assertEntry' => static function (ObserverReplayAuditHook $hook, Connection $connection): void {
                    $hook->assertEntry($connection, self::CANARY . '!');
                },
                'started' => static function (ObserverReplayAuditHook $hook, Connection $connection): void {
                    $hook->started($connection, self::CANARY . '!', self::AUDIT_ID);
                },
                'progressed' => static function (ObserverReplayAuditHook $hook, Connection $connection) use (
                    $validRecord,
                ): void {
                    $hook->progressed(
                        $connection,
                        self::CANARY . '!',
                        self::AUDIT_ID,
                        '1|' . self::RECORD_ID,
                        $validRecord,
                    );
                },
                'finished' => static function (ObserverReplayAuditHook $hook, Connection $connection): void {
                    $hook->finished($connection, self::CANARY . '!', self::AUDIT_ID, 'complete');
                },
                'failed' => static function (ObserverReplayAuditHook $hook, Connection $connection): void {
                    $hook->failed($connection, self::CANARY . '!', self::AUDIT_ID);
                },
            ];
            foreach ($cases as $method => $case) {
                $phaseConnection = $method === 'assertEntry' ? $this->connection(true, false, 0) : $connection;
                $phaseHook = $method === 'assertEntry'
                    ? $this->hook($phaseConnection, new ObserverReplayHookClock())
                    : $hook;
                try {
                    $case($phaseHook, $phaseConnection);
                    self::fail($method . ' accepted a sensitive checkpoint');
                } catch (AuditDeliveryException $failure) {
                    self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
                    self::assertNull($failure->getPrevious());
                    $frames = array_filter(
                        $failure->getTrace(),
                        static fn(array $frame): bool => (
                            ($frame['class'] ?? null) === ObserverReplayAuditHook::class
                            && ($frame['function'] ?? null) === $method
                        ),
                    );
                    $this->assertSensitiveFrame($frames, match ($method) {
                        'assertEntry' => 2,
                        'started', 'failed' => 3,
                        'finished' => 4,
                        'progressed' => 5,
                    });
                    self::assertStringNotContainsString(self::CANARY, print_r(array_map(
                        static fn(array $frame): array => [
                            'class' => $frame['class'] ?? null,
                            'function' => $frame['function'] ?? null,
                            'args' => $frame['args'] ?? null,
                        ],
                        $frames,
                    ), true));
                }
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testLineageHelperOwnsTheRedactedRawSourceFrame(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            try {
                ObserverReplayAuditHookLineage::validate(
                    self::CANARY . '!',
                    $this->record(new TenantRef('account', 'tenant-1')),
                );
                self::fail('invalid lineage was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
                self::assertNull($failure->getPrevious());
                $frames = array_values(array_filter(
                    $failure->getTrace(),
                    static fn(array $frame): bool => (
                        ($frame['class'] ?? null) === ObserverReplayAuditHookLineage::class
                        && ($frame['function'] ?? null) === 'validate'
                    ),
                ));
                $this->assertSensitiveFrame($frames, 2);
                self::assertStringNotContainsString(self::CANARY, print_r($frames, true));
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testGuardHelpersOwnTheirSensitiveFailureFrames(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            $cases = [
                'entry' => function (): void {
                    ObserverReplayAuditHookGuard::entry(
                        new \SensitiveParameterValue([]),
                        $this->createStub(Connection::class),
                    );
                },
                'root' => function (): void {
                    ObserverReplayAuditHookGuard::root(
                        new \SensitiveParameterValue([]),
                        $this->createStub(Connection::class),
                    );
                },
                'checkpoint' => static function (): void {
                    ObserverReplayAuditHookGuard::checkpoint(self::CANARY . '!');
                },
                'auditId' => static function (): void {
                    ObserverReplayAuditHookGuard::auditId(self::CANARY);
                },
                'writer' => static function (): void {
                    ObserverReplayAuditHookGuard::writer(new \SensitiveParameterValue(['writer' => self::CANARY]));
                },
            ];
            foreach ($cases as $method => $case) {
                $failure = null;
                try {
                    $case();
                } catch (\Throwable $exception) {
                    $failure = $exception;
                }
                self::assertInstanceOf(\Throwable::class, $failure);
                $this->assertSensitiveFrame(
                    array_values(array_filter(
                        $failure->getTrace(),
                        static fn(array $frame): bool => (
                            ($frame['class'] ?? null) === ObserverReplayAuditHookGuard::class
                            && ($frame['function'] ?? null) === $method
                        ),
                    )),
                    in_array($method, ['entry', 'root'], true) ? 2 : 1,
                );
                self::assertStringNotContainsString(self::CANARY, print_r($failure->getTrace(), true));
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    /** @param list<array<string,mixed>> $frames */
    private function assertSensitiveFrame(array $frames, int $argumentCount): void
    {
        $frames = array_values($frames);
        self::assertNotEmpty($frames);
        $frame = $frames[0];
        self::assertArrayHasKey('args', $frame);
        self::assertIsArray($frame['args']);
        self::assertCount($argumentCount, $frame['args']);
        foreach ($frame['args'] as $argument) {
            self::assertInstanceOf(\SensitiveParameterValue::class, $argument);
        }
    }

    private function hook(Connection $connection, ObserverReplayHookClock $clock): ObserverReplayAuditHook
    {
        return new ObserverReplayAuditHook(
            new ObserverReplayAuditWriter(
                new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
                $this->selector(),
                $this->coordinator($connection),
                $clock,
                new ActorContext(null, new ActorRef('principal-1', 'user'), new ActorRef('execution-1', 'worker')),
                AuditRecordCorrelation::fromArray([]),
                'observer-replay-hook-test',
            ),
        );
    }

    private function connection(bool $autoCommit, bool $active, int $nesting): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('isAutoCommit')->willReturn($autoCommit);
        $connection->method('isTransactionActive')->willReturn($active);
        $connection->method('getTransactionNestingLevel')->willReturn($nesting);

        return $connection;
    }

    private function selector(): AuditDeliverySelector
    {
        $catalog = FrameworkOperationalAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);

        return new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
    }

    private function coordinator(Connection $connection): AuditDeliveryCoordinator
    {
        $records = new ReflectionClass(\BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore::class)->newInstanceWithoutConstructor();
        $outbox = new ReflectionClass(PostgreSqlAuditOutboxStore::class)->newInstanceWithoutConstructor();

        return new AuditDeliveryCoordinator($connection, $this->selector(), $records, $outbox);
    }

    private function record(?TenantRef $tenant): JournalRecord
    {
        return new JournalRecord(
            JournalRecordId::fromString(self::RECORD_ID),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable('2026-09-29T12:00:01Z'),
            1,
            new JournalOperation(
                OperationId::fromString('019f80ab-4000-7000-8000-000000000001'),
                'order.create',
                1,
                'inline',
                CorrelationId::fromString('019f80ab-4000-7000-8000-000000000002'),
                tenant: $tenant,
            ),
            null,
            new EmptyJournalData(),
        );
    }

    private function corruptRecord(string $kind): JournalRecord
    {
        $reflection = new ReflectionClass(JournalRecord::class);
        $record = $reflection->newInstanceWithoutConstructor();
        $recordId = JournalRecordId::fromString(self::RECORD_ID);
        if ($kind === 'uuid-v4' || $kind === 'uppercase-id') {
            $idReflection = new ReflectionClass(JournalRecordId::class);
            $recordId = $idReflection->newInstanceWithoutConstructor();
            $idReflection->getProperty('value')->setValue(
                $recordId,
                $kind === 'uuid-v4' ? '019f80ab-4000-4000-8000-000000000011' : strtoupper(self::RECORD_ID),
            );
        }
        $operation = $this->record(new TenantRef('account', 'tenant-1'))->operation;
        if ($kind === 'malformed-tenant') {
            $tenantReflection = new ReflectionClass(TenantRef::class);
            $malformedTenant = $tenantReflection->newInstanceWithoutConstructor();
            $tenantReflection->getProperty('type')->setValue($malformedTenant, 'account');
            $tenantReflection->getProperty('id')->setValue($malformedTenant, ' tenant-1');

            $operationReflection = new ReflectionClass(JournalOperation::class);
            $operation = $operationReflection->newInstanceWithoutConstructor();
            foreach ([
                'id' => OperationId::fromString('019f80ab-4000-7000-8000-000000000001'),
                'type' => 'order.create',
                'schemaVersion' => 1,
                'strategy' => 'inline',
                'correlationId' => CorrelationId::fromString('019f80ab-4000-7000-8000-000000000002'),
                'causationId' => null,
                'actorContext' => null,
                'schedule' => null,
                'tenant' => $malformedTenant,
                'telemetry' => null,
            ] as $property => $value) {
                $operationReflection->getProperty($property)->setValue($operation, $value);
            }
        }
        if ($kind === 'uninitialized-tenant') {
            $operationReflection = new ReflectionClass(JournalOperation::class);
            $operation = $operationReflection->newInstanceWithoutConstructor();
            foreach ([
                'id' => OperationId::fromString('019f80ab-4000-7000-8000-000000000001'),
                'type' => 'order.create',
                'schemaVersion' => 1,
                'strategy' => 'inline',
                'correlationId' => CorrelationId::fromString('019f80ab-4000-7000-8000-000000000002'),
                'causationId' => null,
                'actorContext' => null,
                'schedule' => null,
                'telemetry' => null,
            ] as $property => $value) {
                $operationReflection->getProperty($property)->setValue($operation, $value);
            }
        }
        $properties = [
            'recordId' => $recordId,
            'schemaVersion' => 1,
            'event' => JournalEvent::OperationReceived,
            'occurredAt' => $kind === 'non-utc-time'
                ? new DateTimeImmutable('2026-09-29T12:00:01+00:00')
                : new DateTimeImmutable('2026-09-29T12:00:01', new \DateTimeZone('UTC')),
            'sequence' => match ($kind) {
                'zero-sequence' => 0,
                'negative-sequence' => -1,
                default => 1,
            },
            'operation' => $operation,
            'attempt' => null,
            'data' => new EmptyJournalData(),
        ];
        $uninitializedProperty =
            [
                'uninitialized-operation' => 'operation',
                'uninitialized-record-id' => 'recordId',
                'uninitialized-sequence' => 'sequence',
                'uninitialized-occurred-at' => 'occurredAt',
            ][$kind] ?? null;
        if ($uninitializedProperty !== null) {
            unset($properties[$uninitializedProperty]);
        }
        foreach ($properties as $property => $value) {
            $reflection->getProperty($property)->setValue($record, $value);
        }

        return $record;
    }
}

final class ObserverReplayHookClock implements ClockInterface
{
    public int $calls = 0;

    public function now(): DateTimeImmutable
    {
        ++$this->calls;

        return new DateTimeImmutable('2026-09-21T12:34:56.123456+09:00');
    }
}
