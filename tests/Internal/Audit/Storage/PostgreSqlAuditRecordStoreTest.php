<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Storage;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditEvidenceClassification;
use BlackOps\Core\Audit\AuditEvidenceFieldDefinition;
use BlackOps\Core\Audit\AuditEvidenceType;
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
use BlackOps\Core\Audit\AuditSemanticTuple;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\AuditEventCatalogCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Protection\AuditStoredRecord;
use BlackOps\Internal\Audit\Record\AuditChainPosition;
use BlackOps\Internal\Audit\Record\AuditLogicalRecordCandidate;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordIntegrity;
use BlackOps\Internal\Audit\Record\AuditRecordStamp;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use BlackOps\Internal\Audit\Record\CompleteAuditRecord;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
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
final class PostgreSqlAuditRecordStoreTest extends TestCase
{
    private const string RECORD_ID = '01941f29-7c00-7000-8000-000000000001';

    private const string REAL_SCHEMA = 'blackops_audit_store_real';

    private const string LATE_FENCE_FUNCTION = 'audit_store_late_fence_fn';

    private const string LATE_FENCE_TRIGGER = 'audit_store_late_fence_trigger';

    private const string DEFERRED_FAILURE_FUNCTION = 'audit_store_deferred_failure_fn';

    private const string DEFERRED_FAILURE_TRIGGER = 'audit_store_deferred_failure_trigger';

    private const string DEFERRED_FAILURE_CANARY = 'audit-store-deferred-canary-7f3c';

    private const string DEFERRED_SEGMENT_FAILURE_FUNCTION = 'audit_store_deferred_segment_failure_fn';

    private const string DEFERRED_SEGMENT_FAILURE_TRIGGER = 'audit_store_deferred_segment_failure_trigger';

    private const string DEFERRED_SEGMENT_FAILURE_CANARY = 'audit-store-deferred-segment-canary-8a4d';

    private const string DEFERRED_UNIQUE_FAILURE_FUNCTION = 'audit_store_deferred_unique_failure_fn';

    private const string DEFERRED_UNIQUE_FAILURE_TRIGGER = 'audit_store_deferred_unique_failure_trigger';

    private const string DEFERRED_UNIQUE_FAILURE_CANARY = 'audit-store-deferred-unique-canary-9b5e';

    public function testAutoCommitFalseIsTheFirstFailureForEveryPublicOperation(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(4))->method('isAutoCommit')->willReturn(false);
        $this->expectNoConnectionUse($connection);
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');
        $store = $this->store($connection, $clock, $uuids);
        $draft = $this->draft();
        $logical = $this->durableLogical('durableSystemIngress');

        foreach ([
            fn() => $store->appendRequired($draft),
            fn() => $store->appendDurable($logical),
            fn() => $store->find(self::RECORD_ID, null, 'system:global'),
            fn() => $store->closeSegment('system:global', '01941f29-7c00-7000-8000-000000000101'),
        ] as $operation) {
            try {
                $operation();
                self::fail('auto-commit precondition was not enforced');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
                self::assertSame('Audit store operation failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testCloseRejectsMalformedInputAfterAutoCommitCheck(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $this->expectNoConnectionUse($connection);
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');
        $store = $this->store($connection, $clock, $uuids);

        try {
            $store->closeSegment('not-a-partition', '01941f29-7c00-7000-8000-000000000101');
            self::fail('malformed close input was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame('integrity_invalid', $exception->code());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testCloseChangesAnActiveSegmentWithClampedTimeAndOneFencedWrite(): void
    {
        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $successor = '01941f29-7c00-7000-8000-000000000102';
        $queries = [];
        $fetchOneCalls = 0;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection
            ->expects(self::exactly(2))
            ->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $parameters, array $types) use (
                &$fetchOneCalls,
                &$queries,
            ): string {
                $fetchOneCalls++;
                $queries[] = [$sql, $parameters, $types];

                return $fetchOneCalls === 2 ? '1' : '0';
            });
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $segment);
        $connection
            ->expects(self::exactly(3))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls([$boundary], [$boundary], []);
        $connection->expects(self::once())->method('commit');
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::once())
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-08-19T00:00:02.000003Z'));
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids
            ->expects(self::once())
            ->method('generate')
            ->with(self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn($successor);

        $result = $this->store($connection, $clock, $uuids)->closeSegment('system:global', $segment['segment_id']);

        self::assertTrue($result->changed());
        self::assertSame('system:global', $result->partitionId());
        self::assertSame($segment['segment_id'], $result->closedSegmentId());
        self::assertSame($successor, $result->successorSegmentId());
        self::assertSame(1, $result->firstSequence());
        self::assertSame(1, $result->finalSequence());
        self::assertNull($result->predecessorRecordHash());
        self::assertSame($state['head_record_hash'], $result->headHash());
        self::assertSame('2026-08-19T00:00:02.000003Z', $result->closedAt());
        self::assertSame(
            $this->expectedCloseToken(
                'system:global',
                $segment['segment_id'],
                1,
                $state['head_record_hash'],
                $successor,
                $result->closedAt(),
            ),
            $result->closeFencingToken(),
        );
        self::assertStringContainsString('WITH closed_segment AS', $queries[1][0]);
        self::assertStringContainsString('CAST(:closed_at AS timestamptz)', $queries[1][0]);
        self::assertStringContainsString('CAST(:successor_segment_id AS uuid)', $queries[1][0]);
        self::assertStringContainsString("ELSE 'audit_store_abort' END AS bigint", $queries[1][0]);
        self::assertSame(
            [
                'partition_id',
                'closed_segment_id',
                'successor_segment_id',
                'final_sequence',
                'head_record_hash',
                'closed_at',
                'close_fencing_token',
                'segment_version',
                'state_version',
            ],
            array_keys($queries[1][1]),
        );
        self::assertSame(
            [
                'partition_id' => 'system:global',
                'closed_segment_id' => $segment['segment_id'],
                'successor_segment_id' => $successor,
                'final_sequence' => 1,
                'head_record_hash' => $state['head_record_hash'],
                'closed_at' => '2026-08-19T00:00:02.000003Z',
                'close_fencing_token' => $result->closeFencingToken(),
                'segment_version' => 1,
                'state_version' => 1,
            ],
            $queries[1][1],
        );
        self::assertSame(
            [
                'partition_id' => ParameterType::STRING,
                'closed_segment_id' => ParameterType::STRING,
                'successor_segment_id' => ParameterType::STRING,
                'final_sequence' => ParameterType::INTEGER,
                'head_record_hash' => ParameterType::STRING,
                'closed_at' => ParameterType::STRING,
                'close_fencing_token' => ParameterType::STRING,
                'segment_version' => ParameterType::INTEGER,
                'state_version' => ParameterType::INTEGER,
            ],
            $queries[1][2],
        );
    }

    public function testCloseResourceFailuresAreFixedAndDoNotWrite(): void
    {
        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $modes = ['clock', 'uuid', 'invalid-uuid', 'collision'];
        foreach ($modes as $mode) {
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($state, $segment);
            $connection
                ->expects($mode === 'collision' ? self::exactly(3) : self::exactly(2))
                ->method('fetchAllAssociative')
                ->willReturnOnConsecutiveCalls(
                    [$boundary],
                    [$boundary],
                    $mode === 'collision' ? [['segment_id' => '01941f29-7c00-7000-8000-000000000299']] : [],
                );
            $connection->expects(self::once())->method('rollBack');
            $connection->expects(self::never())->method('commit');
            $connection->expects(self::never())->method('close');
            $clock = $this->createMock(ClockInterface::class);
            if ($mode === 'clock') {
                $clock
                    ->expects(self::once())
                    ->method('now')
                    ->willReturnCallback(static function (): never {
                        throw new \RuntimeException('close-resource-clock-canary-91af');
                    });
            } else {
                $clock
                    ->expects(self::once())
                    ->method('now')
                    ->willReturn(new \DateTimeImmutable('2026-08-19T00:00:02.000003Z'));
            }
            $uuids = $this->createMock(Uuidv7Generator::class);
            if ($mode === 'clock') {
                $uuids->expects(self::never())->method('generate');
            } elseif ($mode === 'uuid') {
                $uuids
                    ->expects(self::once())
                    ->method('generate')
                    ->willReturnCallback(static function (): never {
                        throw new \RuntimeException('close-resource-uuid-canary-91af');
                    });
            } else {
                $uuids
                    ->expects(self::once())
                    ->method('generate')
                    ->willReturn($mode === 'invalid-uuid' ? 'invalid-uuid' : '01941f29-7c00-7000-8000-000000000102');
            }

            try {
                $this->store($connection, $clock, $uuids)->closeSegment('system:global', $segment['segment_id']);
                self::fail($mode . ' failure was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::ResourceUnavailable, $exception->failure(), $mode);
                self::assertSame('Audit store operation failed.', $exception->getMessage(), $mode);
                self::assertNull($exception->getPrevious(), $mode);
                self::assertStringNotContainsString('canary', $exception->getMessage(), $mode);
                if ($mode === 'clock') {
                    $this->assertNoCanaryOnExceptionSurfaces($exception, 'close-resource-clock-canary-91af');
                } elseif ($mode === 'uuid') {
                    $this->assertNoCanaryOnExceptionSurfaces($exception, 'close-resource-uuid-canary-91af');
                }
            }
        }
    }

    public function testCloseDatabaseFailuresAreFixedBeforeOrAfterResources(): void
    {
        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        foreach (['lock', 'state', 'current', 'boundary', 'collision', 'write', 'fence'] as $mode) {
            $fetchOneCalls = 0;
            $fetchAssociativeCalls = 0;
            $fetchAllCalls = 0;
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection
                ->expects($mode === 'write' || $mode === 'fence' ? self::exactly(2) : self::once())
                ->method('fetchOne')
                ->willReturnCallback(static function () use (&$fetchOneCalls, $mode): string {
                    $fetchOneCalls++;
                    if ($mode === 'lock' && $fetchOneCalls === 1) {
                        throw new \RuntimeException('close-database-lock-canary-91af');
                    }
                    if (($mode === 'write' || $mode === 'fence') && $fetchOneCalls === 2) {
                        if ($mode === 'write') {
                            throw new \RuntimeException('close-database-write-canary-91af');
                        }

                        return '0';
                    }

                    return '0';
                });
            $connection
                ->expects($mode === 'lock' ? self::never() : ($mode === 'state' ? self::once() : self::exactly(2)))
                ->method('fetchAssociative')
                ->willReturnCallback(static function () use (&$fetchAssociativeCalls, $mode, $state, $segment): array {
                    $fetchAssociativeCalls++;
                    if ($mode === 'state' && $fetchAssociativeCalls === 1) {
                        throw new \RuntimeException('close-database-state-canary-91af');
                    }
                    if ($mode === 'current' && $fetchAssociativeCalls === 2) {
                        throw new \RuntimeException('close-database-current-canary-91af');
                    }

                    return $fetchAssociativeCalls === 1 ? $state : $segment;
                });
            $connection
                ->expects(
                    $mode === 'lock' || $mode === 'state' || $mode === 'current'
                        ? self::never()
                        : ($mode === 'boundary' ? self::once() : self::exactly(3)),
                )
                ->method('fetchAllAssociative')
                ->willReturnCallback(static function () use (&$fetchAllCalls, $mode, $boundary): array {
                    $fetchAllCalls++;
                    if ($mode === 'boundary' && $fetchAllCalls === 1) {
                        throw new \RuntimeException('close-database-boundary-canary-91af');
                    }
                    if ($mode === 'collision' && $fetchAllCalls === 3) {
                        throw new \RuntimeException('close-database-collision-canary-91af');
                    }

                    return $fetchAllCalls <= 2 ? [$boundary] : [];
                });
            $connection->expects(self::once())->method('rollBack');
            $connection->expects(self::never())->method('commit');
            $connection->expects(self::never())->method('close');
            $clock = $this->createMock(ClockInterface::class);
            if ($mode === 'collision' || $mode === 'write' || $mode === 'fence') {
                $clock
                    ->expects(self::once())
                    ->method('now')
                    ->willReturn(new \DateTimeImmutable('2026-08-19T00:00:02.000003Z'));
            } else {
                $clock->expects(self::never())->method('now');
            }
            $uuids = $this->createMock(Uuidv7Generator::class);
            if ($mode === 'collision' || $mode === 'write' || $mode === 'fence') {
                $uuids->expects(self::once())->method('generate')->willReturn('01941f29-7c00-7000-8000-000000000102');
            } else {
                $uuids->expects(self::never())->method('generate');
            }

            try {
                $this->store($connection, $clock, $uuids)->closeSegment('system:global', $segment['segment_id']);
                self::fail($mode . ' database failure was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure(), $mode);
                self::assertSame('Audit store operation failed.', $exception->getMessage(), $mode);
                self::assertNull($exception->getPrevious(), $mode);
                self::assertStringNotContainsString('canary', $exception->getMessage(), $mode);
                if ($mode !== 'fence') {
                    $this->assertNoCanaryOnExceptionSurfaces($exception, match ($mode) {
                        'lock' => 'close-database-lock-canary-91af',
                        'state' => 'close-database-state-canary-91af',
                        'current' => 'close-database-current-canary-91af',
                        'boundary' => 'close-database-boundary-canary-91af',
                        'collision' => 'close-database-collision-canary-91af',
                        default => 'close-database-write-canary-91af',
                    });
                }
            }
        }
    }

    public function testDurableAndCloseRejectInvalidTransactionPairsBeforeSqlOrResources(): void
    {
        $logical = $this->durableLogical('durableSystemIngress');
        foreach ([
            'inactive-level-one' => [false, 1],
            'active-level-zero' => [true, 0],
            'active-level-two' => [true, 2],
            'inactive-level-two' => [false, 2],
        ] as $pairName => [$active, $level]) {
            foreach (['durable', 'close'] as $operation) {
                $connection = $this->createMock(Connection::class);
                $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
                $connection->expects(self::once())->method('isTransactionActive')->willReturn($active);
                $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn($level);
                $this->expectNoConnectionUseExceptTransactionState($connection);
                $clock = $this->createMock(ClockInterface::class);
                $clock->expects(self::never())->method('now');
                $uuids = $this->createMock(Uuidv7Generator::class);
                $uuids->expects(self::never())->method('generate');

                try {
                    $store = $this->store($connection, $clock, $uuids);
                    if ($operation === 'durable') {
                        $store->appendDurable($logical);
                    } else {
                        $store->closeSegment('system:global', '01941f29-7c00-7000-8000-000000000101');
                    }
                    self::fail($pairName . ' ' . $operation . ' was accepted');
                } catch (AuditStoreException $exception) {
                    self::assertSame(
                        AuditStoreFailure::StorageUnavailable,
                        $exception->failure(),
                        $pairName . ' ' . $operation,
                    );
                    self::assertNull($exception->getPrevious(), $pairName . ' ' . $operation);
                    self::assertStringNotContainsString(
                        'canary',
                        $exception->getMessage(),
                        $pairName . ' ' . $operation,
                    );
                }
            }
        }
    }

    public function testCloseCallerOwnedLevelOneSuccessLeavesTransactionControlToCaller(): void
    {
        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(1);
        foreach ([
            'beginTransaction',
            'commit',
            'rollBack',
            'createSavepoint',
            'releaseSavepoint',
            'rollbackSavepoint',
            'close',
        ] as $method) {
            $connection->expects(self::never())->method($method);
        }
        $connection->expects(self::exactly(2))->method('fetchOne')->willReturnOnConsecutiveCalls('0', '1');
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $segment);
        $connection
            ->expects(self::exactly(3))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls([$boundary], [$boundary], []);
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::once())
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-08-19T00:00:02.000003Z'));
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::once())->method('generate')->willReturn('01941f29-7c00-7000-8000-000000000102');

        $result = $this->store($connection, $clock, $uuids)->closeSegment('system:global', $segment['segment_id']);

        self::assertTrue($result->changed());
    }

    public function testCloseCallerOwnedLevelOneFailureLeavesTransactionControlToCaller(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(1);
        foreach ([
            'beginTransaction',
            'commit',
            'rollBack',
            'createSavepoint',
            'releaseSavepoint',
            'rollbackSavepoint',
            'close',
        ] as $method) {
            $connection->expects(self::never())->method($method);
        }
        $connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturnCallback(static function (): never {
                throw new \RuntimeException('caller-owned-close-canary-91af');
            });
        foreach (['fetchAssociative', 'fetchAllAssociative'] as $method) {
            $connection->expects(self::never())->method($method);
        }
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        try {
            $this->store($connection, $clock, $uuids)->closeSegment(
                'system:global',
                '01941f29-7c00-7000-8000-000000000101',
            );
            self::fail('caller-owned close failure was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('caller-owned-close-canary-91af', $exception->getMessage());
            $this->assertNoCanaryOnExceptionSurfaces($exception, 'caller-owned-close-canary-91af');
        }
    }

    public function testDurableCallerOwnedLevelOneSuccessLeavesTransactionControlToCaller(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(1);
        foreach ([
            'beginTransaction',
            'commit',
            'rollBack',
            'createSavepoint',
            'releaseSavepoint',
            'rollbackSavepoint',
            'close',
        ] as $method) {
            $connection->expects(self::never())->method($method);
        }
        $connection->expects(self::exactly(3))->method('fetchOne')->willReturnOnConsecutiveCalls('0', '0', '1');
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(false, ['segment_exists' => 0, 'record_exists' => 0]);
        $connection->expects(self::exactly(2))->method('fetchAllAssociative')->willReturn([]);
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = new SequenceUuidGenerator(['01941f29-7c00-7000-8000-000000000201']);

        $result = $this->store($connection, $clock, $uuids)->appendDurable($this->durableLogical(
            'durableSystemIngress',
        ));

        self::assertTrue($result->inserted());
    }

    public function testCloseCallerOwnedLevelOneFailureKindsLeaveTransactionControlToCaller(): void
    {
        [$baseState, $baseSegment, $baseBoundary] = $this->appendRowsForStored($this->storedRecord(), null);
        foreach (['conflict', 'integrity', 'resource'] as $mode) {
            $state = $baseState;
            $segment = $baseSegment;
            $expected = $segment['segment_id'];
            $wrong = $segment;
            if ($mode === 'conflict') {
                $expected = '01941f29-7c00-7000-8000-000000000199';
                $wrong['segment_id'] = $expected;
            } elseif ($mode === 'integrity') {
                $segment['closed_at'] = '2026-08-19T00:00:01.000000Z';
            }
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(1);
            foreach ([
                'beginTransaction',
                'commit',
                'rollBack',
                'createSavepoint',
                'releaseSavepoint',
                'rollbackSavepoint',
                'close',
            ] as $method) {
                $connection->expects(self::never())->method($method);
            }
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection
                ->expects($mode === 'conflict' ? self::exactly(3) : self::exactly(2))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($state, $segment, $wrong);
            if ($mode === 'integrity') {
                $connection->expects(self::never())->method('fetchAllAssociative');
            } else {
                $connection
                    ->expects(self::exactly(2))
                    ->method('fetchAllAssociative')
                    ->willReturnOnConsecutiveCalls([$baseBoundary], [$baseBoundary]);
            }
            $clock = $this->createMock(ClockInterface::class);
            if ($mode === 'resource') {
                $clock
                    ->expects(self::once())
                    ->method('now')
                    ->willReturn(new \DateTimeImmutable('2026-08-19T00:00:02.000003Z'));
            } else {
                $clock->expects(self::never())->method('now');
            }
            $uuids = $this->createMock(Uuidv7Generator::class);
            if ($mode === 'resource') {
                $uuids->expects(self::once())->method('generate')->willReturn('invalid-uuid');
            } else {
                $uuids->expects(self::never())->method('generate');
            }

            try {
                $this->store($connection, $clock, $uuids)->closeSegment('system:global', $expected);
                self::fail($mode . ' caller-owned outcome was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(
                    match ($mode) {
                        'conflict' => AuditStoreFailure::SegmentConflict,
                        'integrity' => AuditStoreFailure::IntegrityInvalid,
                        default => AuditStoreFailure::ResourceUnavailable,
                    },
                    $exception->failure(),
                    $mode,
                );
                self::assertNull($exception->getPrevious(), $mode);
                self::assertStringNotContainsString('canary', $exception->getMessage(), $mode);
            }
        }
    }

    public function testDurableCallerOwnedLevelOneFailureLeavesTransactionControlToCaller(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(1);
        foreach ([
            'beginTransaction',
            'commit',
            'rollBack',
            'createSavepoint',
            'releaseSavepoint',
            'rollbackSavepoint',
            'close',
        ] as $method) {
            $connection->expects(self::never())->method($method);
        }
        $connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturnCallback(static function (): never {
                throw new \RuntimeException('caller-owned-durable-canary-91af');
            });
        foreach (['fetchAssociative', 'fetchAllAssociative'] as $method) {
            $connection->expects(self::never())->method($method);
        }
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        try {
            $this->store($connection, $clock, $uuids)->appendDurable($this->durableLogical('durableSystemIngress'));
            self::fail('durable caller-owned failure was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('caller-owned-durable-canary-91af', $exception->getMessage());
            $this->assertNoCanaryOnExceptionSurfaces($exception, 'caller-owned-durable-canary-91af');
        }
    }

    public function testCloseOwnedFailureMatrixRollsBackAndQuarantinesWithoutLeaks(): void
    {
        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        foreach (['begin', 'commit', 'rollback', 'commit-close'] as $mode) {
            $fetchOneCalls = 0;
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            if ($mode === 'begin') {
                $connection
                    ->expects(self::once())
                    ->method('beginTransaction')
                    ->willReturnCallback(static function (): never {
                        throw new \RuntimeException('owned-close-begin-canary-91af');
                    });
                $connection->expects(self::never())->method('rollBack');
                $connection->expects(self::never())->method('commit');
                foreach (['fetchOne', 'fetchAssociative', 'fetchAllAssociative'] as $method) {
                    $connection->expects(self::never())->method($method);
                }
            } else {
                $connection->expects(self::once())->method('beginTransaction');
                $connection
                    ->expects(self::exactly($mode === 'rollback' ? 1 : 2))
                    ->method('fetchOne')
                    ->willReturnCallback(static function () use (&$fetchOneCalls, $mode): string {
                        $fetchOneCalls++;
                        if ($mode === 'rollback' && $fetchOneCalls === 1) {
                            throw new \RuntimeException('owned-close-rollback-canary-91af');
                        }
                        if ($fetchOneCalls === 2) {
                            if ($mode === 'commit' || $mode === 'commit-close') {
                                return '1';
                            }
                        }

                        return '0';
                    });
                $connection
                    ->expects($mode === 'rollback' ? self::never() : self::exactly(2))
                    ->method('fetchAssociative')
                    ->willReturnOnConsecutiveCalls($state, $segment);
                $connection
                    ->expects($mode === 'rollback' ? self::never() : self::exactly(3))
                    ->method('fetchAllAssociative')
                    ->willReturnOnConsecutiveCalls([$boundary], [$boundary], []);
                $connection
                    ->expects($mode === 'rollback' ? self::never() : self::once())
                    ->method('commit')
                    ->willReturnCallback(static function (): never {
                        throw new \RuntimeException('owned-close-commit-canary-91af');
                    });
                if ($mode === 'commit') {
                    $connection->expects(self::once())->method('rollBack');
                } else {
                    $connection
                        ->expects(self::once())
                        ->method('rollBack')
                        ->willReturnCallback(static function (): never {
                            throw new \RuntimeException('owned-close-cleanup-canary-91af');
                        });
                }
            }
            $connection
                ->expects(self::once())
                ->method('close')
                ->willReturnCallback(static function (): never {
                    throw new \RuntimeException('owned-close-quarantine-canary-91af');
                });
            $clock = $this->createMock(ClockInterface::class);
            if ($mode === 'begin' || $mode === 'rollback') {
                $clock->expects(self::never())->method('now');
            } else {
                $clock
                    ->expects(self::once())
                    ->method('now')
                    ->willReturn(new \DateTimeImmutable('2026-08-19T00:00:02.000003Z'));
            }
            $uuids = $this->createMock(Uuidv7Generator::class);
            if ($mode === 'begin' || $mode === 'rollback') {
                $uuids->expects(self::never())->method('generate');
            } else {
                $uuids->expects(self::once())->method('generate')->willReturn('01941f29-7c00-7000-8000-000000000102');
            }

            try {
                $this->store($connection, $clock, $uuids)->closeSegment('system:global', $segment['segment_id']);
                self::fail($mode . ' owned failure was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure(), $mode);
                self::assertSame('Audit store operation failed.', $exception->getMessage(), $mode);
                self::assertNull($exception->getPrevious(), $mode);
                self::assertStringNotContainsString('canary', $exception->getMessage(), $mode);
                $canaries = match ($mode) {
                    'begin' => [
                        'owned-close-begin-canary-91af',
                        'owned-close-quarantine-canary-91af',
                    ],
                    'rollback' => [
                        'owned-close-rollback-canary-91af',
                        'owned-close-cleanup-canary-91af',
                        'owned-close-quarantine-canary-91af',
                    ],
                    default => [
                        'owned-close-commit-canary-91af',
                        ...($mode === 'commit-close' ? ['owned-close-cleanup-canary-91af'] : []),
                        'owned-close-quarantine-canary-91af',
                    ],
                };
                foreach ($canaries as $canary) {
                    $this->assertNoCanaryOnExceptionSurfaces($exception, $canary);
                }
            }
        }
    }

    public function testCloseRejectsTenantPartitionWithNullPersistedTenantPairBeforeResources(): void
    {
        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $tenant = new AuditTypedReference('account', 'tenant-a');
        $state['partition_id'] = $this->partition($tenant);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('fetchOne')->willReturn('0');
        $connection->expects(self::once())->method('fetchAssociative')->willReturn($state);
        $connection->expects(self::once())->method('rollBack');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        try {
            $this->store($connection, $clock, $uuids)->closeSegment($state['partition_id'], $segment['segment_id']);
            self::fail('null tenant pair was accepted for a tenant partition');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testCloseTenantPersistedStateMatrixIsStrictBeforeSegmentOrResources(): void
    {
        [$baseState, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $tenantA = new AuditTypedReference('account', 'tenant-a');
        $tenantB = new AuditTypedReference('account', 'tenant-b');
        $tenantPartition = $this->partition($tenantA);
        $validState = $baseState;
        $validState['partition_id'] = $tenantPartition;
        $validState['tenant_type'] = $tenantA->type;
        $validState['tenant_id'] = $tenantA->id;
        $validState['active_segment_id'] = '01941f29-7c00-7000-8000-000000000103';
        $validSegment = $segment;
        $validSegment['partition_id'] = $tenantPartition;
        $validSegment['segment_id'] = $validState['active_segment_id'];
        $validSegment['first_sequence'] = 1;
        $validSegment['final_sequence'] = 1;
        $validSegment['head_record_hash'] = $validState['head_record_hash'];
        $validSegment['predecessor_record_hash'] = null;
        $validSegment['opened_at'] = $validState['last_assigned_recorded_at'];
        $validBoundary = $boundary;
        $validBoundary['segment_id'] = $validState['active_segment_id'];
        self::assertSame($tenantPartition, $validState['partition_id']);
        self::assertSame($tenantPartition, $validSegment['partition_id']);
        self::assertSame($validState['active_segment_id'], $validSegment['segment_id']);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('fetchOne')->willReturn('0');
        $connection
            ->expects(self::exactly(3))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($validState, $validSegment, false);
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls([$validBoundary], [$validBoundary]);
        $connection->expects(self::once())->method('rollBack');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');
        try {
            $this->store($connection, $clock, $uuids)->closeSegment($tenantPartition, $segment['segment_id']);
            self::fail('valid persisted tenant pair did not reach segment lookup');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::SegmentConflict, $exception->failure());
        }

        $variants = [];
        $partialType = $baseState;
        $partialType['partition_id'] = $tenantPartition;
        $partialType['tenant_type'] = $tenantA->type;
        $variants['partial-type'] = [$partialType, $tenantPartition];
        $partialId = $baseState;
        $partialId['partition_id'] = $tenantPartition;
        $partialId['tenant_id'] = $tenantA->id;
        $variants['partial-id'] = [$partialId, $tenantPartition];
        $tenantNull = $baseState;
        $tenantNull['partition_id'] = $tenantPartition;
        $variants['tenant-null'] = [$tenantNull, $tenantPartition];
        $systemNonNull = $baseState;
        $systemNonNull['tenant_type'] = $tenantA->type;
        $systemNonNull['tenant_id'] = $tenantA->id;
        $variants['system-nonnull'] = [$systemNonNull, 'system:global'];
        $wrongDerived = $baseState;
        $wrongDerived['partition_id'] = $tenantPartition;
        $wrongDerived['tenant_type'] = $tenantB->type;
        $wrongDerived['tenant_id'] = $tenantB->id;
        $variants['wrong-derivation'] = [$wrongDerived, $tenantPartition];

        foreach ($variants as $name => [$state, $partition]) {
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection->expects(self::once())->method('fetchAssociative')->willReturn($state);
            $connection->expects(self::once())->method('rollBack');
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');
            try {
                $this->store($connection, $clock, $uuids)->closeSegment($partition, $segment['segment_id']);
                self::fail($name . ' persisted state was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $name);
            }
        }
    }

    public function testCloseRetryReturnsPersistedClosedSegmentWithoutResources(): void
    {
        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $successor = '01941f29-7c00-7000-8000-000000000102';
        $closedAt = '2026-08-19T00:00:02.000003Z';
        $token = $this->expectedCloseToken(
            'system:global',
            $segment['segment_id'],
            1,
            $state['head_record_hash'],
            $successor,
            $closedAt,
        );
        $closed = $segment;
        $closed['state'] = 'closed';
        $closed['closed_at'] = $closedAt;
        $closed['successor_segment_id'] = $successor;
        $closed['close_fencing_token'] = $token;
        $closed['state_version'] = 2;
        $successorRow = $segment;
        $successorRow['segment_id'] = $successor;
        $successorRow['first_sequence'] = 2;
        $successorRow['final_sequence'] = null;
        $successorRow['head_record_hash'] = null;
        $successorRow['predecessor_record_hash'] = $state['head_record_hash'];
        $successorRow['opened_at'] = $closedAt;
        $successorRow['state_version'] = 1;
        $state['active_segment_id'] = $successor;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('fetchOne')->willReturn('0');
        $connection
            ->expects(self::exactly(3))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $successorRow, $closed);
        $connection
            ->expects(self::exactly(3))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls([$boundary], [$boundary], [$boundary]);
        $connection->expects(self::once())->method('commit');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        $result = $this->store($connection, $clock, $uuids)->closeSegment('system:global', $segment['segment_id']);

        self::assertFalse($result->changed());
        self::assertSame($token, $result->closeFencingToken());
        self::assertSame($successor, $result->successorSegmentId());
        self::assertSame($closedAt, $result->closedAt());
    }

    public function testCloseRetryRejectsDetachedOpenImmediateSuccessor(): void
    {
        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $successor = '01941f29-7c00-7000-8000-000000000102';
        $closedAt = '2026-08-19T00:00:02.000003Z';
        $closed = $segment;
        $closed['state'] = 'closed';
        $closed['closed_at'] = $closedAt;
        $closed['successor_segment_id'] = $successor;
        $closed['close_fencing_token'] = $this->expectedCloseToken(
            'system:global',
            $segment['segment_id'],
            1,
            $state['head_record_hash'],
            $successor,
            $closedAt,
        );
        $closed['state_version'] = 2;
        $successorRow = $segment;
        $successorRow['segment_id'] = $successor;
        $successorRow['first_sequence'] = 2;
        $successorRow['head_record_hash'] = null;
        $successorRow['predecessor_record_hash'] = $state['head_record_hash'];
        $successorRow['opened_at'] = $closedAt;
        $successorRow['final_sequence'] = null;
        $state['active_segment_id'] = '01941f29-7c00-7000-8000-000000000103';
        $currentRow = $segment;
        $currentRow['segment_id'] = $state['active_segment_id'];
        $currentRow['first_sequence'] = 2;
        $currentRow['final_sequence'] = null;
        $currentRow['head_record_hash'] = null;
        $currentRow['predecessor_record_hash'] = $state['head_record_hash'];
        $currentRow['opened_at'] = $closedAt;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('fetchOne')->willReturn('0');
        $connection
            ->expects(self::exactly(4))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $currentRow, $closed, $successorRow);
        $connection->expects(self::once())->method('fetchAllAssociative')->willReturn([$boundary]);
        $connection->expects(self::once())->method('rollBack');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        try {
            $this->store($connection, $clock, $uuids)->closeSegment('system:global', $segment['segment_id']);
            self::fail('detached open successor was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
        }
    }

    public function testCloseRetryAllowsClosedImmediateSuccessorWithLaterActiveSegment(): void
    {
        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $oldHead = $state['head_record_hash'];
        $successor = '01941f29-7c00-7000-8000-000000000102';
        $current = '01941f29-7c00-7000-8000-000000000103';
        $closedAt = '2026-08-19T00:00:02.000003Z';
        $successorClosedAt = '2026-08-19T00:00:03.000003Z';
        $successorHead = str_repeat('b', 64);
        $closed = $segment;
        $closed['state'] = 'sealed';
        $closed['closed_at'] = $closedAt;
        $closed['successor_segment_id'] = $successor;
        $closed['close_fencing_token'] = $this->expectedCloseToken(
            'system:global',
            $segment['segment_id'],
            1,
            $oldHead,
            $successor,
            $closedAt,
        );
        $closed['state_version'] = 2;
        $successorRow = $segment;
        $successorRow['segment_id'] = $successor;
        $successorRow['state'] = 'closed';
        $successorRow['first_sequence'] = 2;
        $successorRow['final_sequence'] = 2;
        $successorRow['head_record_hash'] = $successorHead;
        $successorRow['predecessor_record_hash'] = $oldHead;
        $successorRow['opened_at'] = $closedAt;
        $successorRow['closed_at'] = $successorClosedAt;
        $successorRow['successor_segment_id'] = $current;
        $successorRow['close_fencing_token'] = $this->expectedCloseToken(
            'system:global',
            $successor,
            2,
            $successorHead,
            $current,
            $successorClosedAt,
        );
        $successorRow['state_version'] = 2;
        $currentRow = $segment;
        $currentRow['segment_id'] = $current;
        $currentRow['first_sequence'] = 3;
        $currentRow['final_sequence'] = null;
        $currentRow['head_record_hash'] = null;
        $currentRow['predecessor_record_hash'] = $successorHead;
        $currentRow['opened_at'] = $successorClosedAt;
        $state['active_segment_id'] = $current;
        $state['last_sequence'] = 2;
        $state['head_record_hash'] = $successorHead;
        $state['state_version'] = 9007199254740991;
        $closed['state_version'] = $state['state_version'];
        $secondBoundary = $boundary;
        $secondBoundary['segment_id'] = $successor;
        $secondBoundary['sequence'] = 2;
        $secondBoundary['previous_record_hash'] = $oldHead;
        $secondBoundary['record_hash'] = $successorHead;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('fetchOne')->willReturn('0');
        $connection
            ->expects(self::exactly(4))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $currentRow, $closed, $successorRow);
        $connection
            ->expects(self::exactly(6))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [$secondBoundary],
                [$secondBoundary],
                [$secondBoundary],
                [$boundary],
                [$boundary],
                [$boundary],
            );
        $connection->expects(self::once())->method('commit');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        $result = $this->store($connection, $clock, $uuids)->closeSegment('system:global', $segment['segment_id']);

        self::assertFalse($result->changed());
        self::assertSame($successor, $result->successorSegmentId());
        self::assertSame($closedAt, $result->closedAt());
    }

    public function testCloseRetryAllowsLastSequenceMaximumBeforeCapacityForClosedAndSealedSegments(): void
    {
        $maximum = 9007199254740991;
        $oldFinal = $maximum - 2;
        foreach (['closed', 'sealed'] as $stateName) {
            [$state, $open, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
            $oldSegmentId = $open['segment_id'];
            $currentSegmentId = '01941f29-7c00-7000-8000-000000000103';
            $oldHead = $open['head_record_hash'];
            $currentHead = str_repeat('b', 64);
            $oldPrevious = str_repeat('a', 64);
            $oldMinimumHead = str_repeat('c', 64);
            $currentMinimumHead = str_repeat('d', 64);
            $closedAt = '2026-08-19T00:00:02.000003Z';
            $old = $open;
            $old['state'] = $stateName;
            $old['final_sequence'] = $oldFinal;
            $old['closed_at'] = $closedAt;
            $old['successor_segment_id'] = $currentSegmentId;
            $old['close_fencing_token'] = $this->expectedCloseToken(
                'system:global',
                $oldSegmentId,
                $oldFinal,
                $oldHead,
                $currentSegmentId,
                $closedAt,
            );
            $old['state_version'] = $maximum;

            $current = $open;
            $current['segment_id'] = $currentSegmentId;
            $current['first_sequence'] = $oldFinal + 1;
            $current['final_sequence'] = $maximum;
            $current['head_record_hash'] = $currentHead;
            $current['predecessor_record_hash'] = $oldHead;
            $current['opened_at'] = $closedAt;
            $current['state_version'] = $maximum;
            $state['active_segment_id'] = $currentSegmentId;
            $state['last_sequence'] = $maximum;
            $state['head_record_hash'] = $currentHead;
            $state['state_version'] = $maximum;

            $currentEndpoint = $boundary;
            $currentEndpoint['segment_id'] = $currentSegmentId;
            $currentEndpoint['sequence'] = $maximum;
            $currentEndpoint['previous_record_hash'] = $currentMinimumHead;
            $currentEndpoint['record_hash'] = $currentHead;
            $currentMinimum = $currentEndpoint;
            $currentMinimum['sequence'] = $oldFinal + 1;
            $currentMinimum['record_hash'] = $currentMinimumHead;
            $currentMinimum['previous_record_hash'] = $oldHead;
            $oldEndpoint = $boundary;
            $oldEndpoint['previous_record_hash'] = $oldPrevious;
            $oldEndpoint['sequence'] = $oldFinal;
            $oldMinimum = $boundary;
            $oldMinimum['sequence'] = 1;
            $oldMinimum['record_hash'] = $oldMinimumHead;
            $oldPredecessor = $oldEndpoint;
            $oldPredecessor['segment_id'] = $oldSegmentId;

            self::assertNotSame($oldMinimum['record_hash'], $oldEndpoint['record_hash'], $stateName);
            self::assertNotSame($currentMinimum['record_hash'], $currentEndpoint['record_hash'], $stateName);
            self::assertSame($current['head_record_hash'], $currentEndpoint['record_hash'], $stateName);
            self::assertSame($state['head_record_hash'], $currentEndpoint['record_hash'], $stateName);
            self::assertSame($current['predecessor_record_hash'], $oldHead, $stateName);

            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection
                ->expects(self::exactly(3))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($state, $current, $old);
            $connection
                ->expects(self::exactly(5))
                ->method('fetchAllAssociative')
                ->willReturnOnConsecutiveCalls(
                    [$currentEndpoint],
                    [$currentMinimum],
                    [$oldPredecessor],
                    [$oldEndpoint],
                    [$oldMinimum],
                );
            $connection->expects(self::once())->method('commit');
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            $result = $this->store($connection, $clock, $uuids)->closeSegment('system:global', $oldSegmentId);

            self::assertFalse($result->changed(), $stateName);
            self::assertSame($currentSegmentId, $result->successorSegmentId(), $stateName);
            self::assertSame($oldFinal, $result->finalSequence(), $stateName);
            self::assertSame($old['close_fencing_token'], $result->closeFencingToken(), $stateName);
        }
    }

    public function testCloseWrongActiveMaximumLastSequenceConflictsBeforeCapacity(): void
    {
        [$state, $current, $endpoint, $minimum] = $this->maximumAppendRowsForStored($this->storedRecord(), null);
        $expected = '01941f29-7c00-7000-8000-000000000199';
        $wrong = $current;
        $wrong['segment_id'] = $expected;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('fetchOne')->willReturn('0');
        $connection
            ->expects(self::exactly(3))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $current, $wrong);
        self::assertNotSame($endpoint['record_hash'], $minimum['record_hash']);
        self::assertNotNull($endpoint['previous_record_hash']);
        self::assertSame($state['head_record_hash'], $endpoint['record_hash']);
        self::assertSame($current['head_record_hash'], $endpoint['record_hash']);
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls([$endpoint], [$minimum]);
        $connection->expects(self::once())->method('rollBack');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        try {
            $this->store($connection, $clock, $uuids)->closeSegment('system:global', $expected);
            self::fail('maximum wrong-active segment was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::SegmentConflict, $exception->failure());
        }
    }

    public function testCloseRetryRejectsCurrentActiveAndRecomputedTokenMutations(): void
    {
        [$baseState, $baseSegment, $baseBoundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $oldHead = $baseState['head_record_hash'];
        $successor = '01941f29-7c00-7000-8000-000000000102';
        $current = '01941f29-7c00-7000-8000-000000000103';
        $closedAt = '2026-08-19T00:00:02.000003Z';
        $successorClosedAt = '2026-08-19T00:00:03.000003Z';
        $successorHead = str_repeat('b', 64);
        $variants = [
            'state-last-sequence' => static function (
                array &$state,
                array &$closed,
                array &$currentRow,
                array &$endpoint,
            ): void {
                $state['last_sequence'] = 3;
            },
            'state-head-hash' => static function (
                array &$state,
                array &$closed,
                array &$currentRow,
                array &$endpoint,
            ): void {
                $state['head_record_hash'] = str_repeat('c', 64);
            },
            'current-final-sequence' => static function (
                array &$state,
                array &$closed,
                array &$currentRow,
                array &$endpoint,
            ): void {
                $currentRow['final_sequence'] = 1;
            },
            'current-head-hash' => static function (
                array &$state,
                array &$closed,
                array &$currentRow,
                array &$endpoint,
            ): void {
                $currentRow['head_record_hash'] = str_repeat('d', 64);
            },
            'current-first-sequence' => static function (
                array &$state,
                array &$closed,
                array &$currentRow,
                array &$endpoint,
            ): void {
                $currentRow['first_sequence'] = 4;
            },
            'current-predecessor-hash' => static function (
                array &$state,
                array &$closed,
                array &$currentRow,
                array &$endpoint,
            ): void {
                $currentRow['predecessor_record_hash'] = str_repeat('d', 64);
            },
            'endpoint-segment' => static function (
                array &$state,
                array &$closed,
                array &$currentRow,
                array &$endpoint,
            ): void {
                $endpoint['segment_id'] = $currentRow['segment_id'];
            },
            'endpoint-hash' => static function (
                array &$state,
                array &$closed,
                array &$currentRow,
                array &$endpoint,
            ): void {
                $endpoint['record_hash'] = str_repeat('d', 64);
            },
            'endpoint-sequence' => static function (
                array &$state,
                array &$closed,
                array &$currentRow,
                array &$endpoint,
            ): void {
                $endpoint['sequence'] = 1;
            },
            'wrong-recomputed-token' => static function (
                array &$state,
                array &$closed,
                array &$currentRow,
                array &$endpoint,
            ): void {
                $closed['close_fencing_token'] = 'aft:v1:' . str_repeat('d', 64);
            },
        ];

        foreach ($variants as $name => $mutate) {
            $state = $baseState;
            $state['active_segment_id'] = $current;
            $state['last_sequence'] = 2;
            $state['head_record_hash'] = $successorHead;
            $closed = $baseSegment;
            $closed['state'] = 'sealed';
            $closed['closed_at'] = $closedAt;
            $closed['successor_segment_id'] = $successor;
            $closed['close_fencing_token'] = $this->expectedCloseToken(
                'system:global',
                $closed['segment_id'],
                1,
                $oldHead,
                $successor,
                $closedAt,
            );
            $closed['state_version'] = 2;
            $successorRow = $baseSegment;
            $successorRow['segment_id'] = $successor;
            $successorRow['state'] = 'closed';
            $successorRow['first_sequence'] = 2;
            $successorRow['final_sequence'] = 2;
            $successorRow['head_record_hash'] = $successorHead;
            $successorRow['predecessor_record_hash'] = $oldHead;
            $successorRow['opened_at'] = $closedAt;
            $successorRow['closed_at'] = $successorClosedAt;
            $successorRow['successor_segment_id'] = $current;
            $successorRow['close_fencing_token'] = $this->expectedCloseToken(
                'system:global',
                $successor,
                2,
                $successorHead,
                $current,
                $successorClosedAt,
            );
            $successorRow['state_version'] = 2;
            $currentRow = $baseSegment;
            $currentRow['segment_id'] = $current;
            $currentRow['first_sequence'] = 3;
            $currentRow['final_sequence'] = null;
            $currentRow['head_record_hash'] = null;
            $currentRow['predecessor_record_hash'] = $successorHead;
            $currentRow['opened_at'] = $successorClosedAt;
            $endpoint = $baseBoundary;
            $endpoint['segment_id'] = $successor;
            $endpoint['sequence'] = 2;
            $endpoint['previous_record_hash'] = $oldHead;
            $endpoint['record_hash'] = $successorHead;
            $mutate($state, $closed, $currentRow, $endpoint);

            $fetchAssociativeCalls = 0;
            $fetchAllCalls = 0;
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection
                ->expects(self::atMost(4))
                ->method('fetchAssociative')
                ->willReturnCallback(static function (...$arguments) use (
                    &$fetchAssociativeCalls,
                    $state,
                    $currentRow,
                    $closed,
                    $successorRow,
                ): mixed {
                    $rows = [$state, $currentRow, $closed, $successorRow];
                    $row = $rows[$fetchAssociativeCalls] ?? false;
                    $fetchAssociativeCalls++;

                    return $row;
                });
            $connection
                ->expects(self::atMost(6))
                ->method('fetchAllAssociative')
                ->willReturnCallback(static function () use (&$fetchAllCalls, $endpoint, $baseBoundary): array {
                    $rows = [[$endpoint], [$endpoint], [$endpoint], [$baseBoundary], [$baseBoundary], [$baseBoundary]];
                    $row = $rows[$fetchAllCalls] ?? [];
                    $fetchAllCalls++;

                    return $row;
                });
            $connection->expects(self::once())->method('rollBack');
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids)->closeSegment('system:global', $closed['segment_id']);
                self::fail($name . ' mutation was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $name);
            }
            if (strcmp($name, 'wrong-recomputed-token') === 0) {
                self::assertSame(4, $fetchAssociativeCalls, $name . ' query reachability');
                self::assertSame(6, $fetchAllCalls, $name . ' boundary reachability');
            }
        }
    }

    public function testCloseRetryRejectsImmediateSuccessorLifecycleTokenAndBoundaryMutations(): void
    {
        [$baseState, $baseSegment, $baseBoundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $oldHead = $baseState['head_record_hash'];
        $successor = '01941f29-7c00-7000-8000-000000000102';
        $current = '01941f29-7c00-7000-8000-000000000103';
        $closedAt = '2026-08-19T00:00:02.000003Z';
        $successorClosedAt = '2026-08-19T00:00:03.000003Z';
        $successorHead = str_repeat('b', 64);
        $variants = [
            'successor-self-link' => static function (array &$successorRow, array &$successorBoundary): void {
                $successorRow['successor_segment_id'] = $successorRow['segment_id'];
            },
            'successor-version' => static function (array &$successorRow, array &$successorBoundary): void {
                $successorRow['state_version'] = 1;
            },
            'successor-lifecycle' => static function (array &$successorRow, array &$successorBoundary): void {
                $successorRow['final_sequence'] = 1;
            },
            'successor-token' => static function (array &$successorRow, array &$successorBoundary): void {
                $successorRow['close_fencing_token'] = 'aft:v1:' . str_repeat('d', 64);
            },
            'successor-boundary' => static function (array &$successorRow, array &$successorBoundary): void {
                $successorBoundary['record_hash'] = str_repeat('c', 64);
            },
        ];

        foreach ($variants as $name => $mutate) {
            $state = $baseState;
            $state['active_segment_id'] = $current;
            $state['last_sequence'] = 2;
            $state['head_record_hash'] = $successorHead;
            $closed = $baseSegment;
            $closed['state'] = 'sealed';
            $closed['closed_at'] = $closedAt;
            $closed['successor_segment_id'] = $successor;
            $closed['close_fencing_token'] = $this->expectedCloseToken(
                'system:global',
                $closed['segment_id'],
                1,
                $oldHead,
                $successor,
                $closedAt,
            );
            $closed['state_version'] = 2;
            $successorRow = $baseSegment;
            $successorRow['segment_id'] = $successor;
            $successorRow['state'] = 'closed';
            $successorRow['first_sequence'] = 2;
            $successorRow['final_sequence'] = 2;
            $successorRow['head_record_hash'] = $successorHead;
            $successorRow['predecessor_record_hash'] = $oldHead;
            $successorRow['opened_at'] = $closedAt;
            $successorRow['closed_at'] = $successorClosedAt;
            $successorRow['successor_segment_id'] = $current;
            $successorRow['close_fencing_token'] = $this->expectedCloseToken(
                'system:global',
                $successor,
                2,
                $successorHead,
                $current,
                $successorClosedAt,
            );
            $successorRow['state_version'] = 2;
            $currentRow = $baseSegment;
            $currentRow['segment_id'] = $current;
            $currentRow['first_sequence'] = 3;
            $currentRow['final_sequence'] = null;
            $currentRow['head_record_hash'] = null;
            $currentRow['predecessor_record_hash'] = $successorHead;
            $currentRow['opened_at'] = $successorClosedAt;
            $currentBoundary = $baseBoundary;
            $currentBoundary['segment_id'] = $successor;
            $currentBoundary['sequence'] = 2;
            $currentBoundary['previous_record_hash'] = $oldHead;
            $currentBoundary['record_hash'] = $successorHead;
            $successorBoundary = $currentBoundary;
            $mutate($successorRow, $successorBoundary);
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection
                ->expects(self::exactly(4))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($state, $currentRow, $closed, $successorRow);
            $connection
                ->expects(self::atMost(6))
                ->method('fetchAllAssociative')
                ->willReturnOnConsecutiveCalls(
                    [$currentBoundary],
                    [$successorBoundary],
                    [$successorBoundary],
                    [$baseBoundary],
                    [$baseBoundary],
                    [$baseBoundary],
                );
            $connection->expects(self::once())->method('rollBack');
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');
            try {
                $this->store($connection, $clock, $uuids)->closeSegment('system:global', $closed['segment_id']);
                self::fail($name . ' immediate successor mutation was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $name);
            }
        }
    }

    public function testCloseRetryRejectsMissingSuccessorAsIntegrityBeforeResources(): void
    {
        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $current = '01941f29-7c00-7000-8000-000000000103';
        $state['active_segment_id'] = $current;
        $currentRow = $segment;
        $currentRow['segment_id'] = $current;
        $currentRow['first_sequence'] = 2;
        $currentRow['final_sequence'] = null;
        $currentRow['head_record_hash'] = null;
        $currentRow['predecessor_record_hash'] = $state['head_record_hash'];
        $successor = '01941f29-7c00-7000-8000-000000000102';
        $closedAt = '2026-08-19T00:00:02.000003Z';
        $closed = $segment;
        $closed['state'] = 'closed';
        $closed['closed_at'] = $closedAt;
        $closed['successor_segment_id'] = $successor;
        $closed['close_fencing_token'] = $this->expectedCloseToken(
            'system:global',
            $segment['segment_id'],
            1,
            $state['head_record_hash'],
            $successor,
            $closedAt,
        );
        $closed['state_version'] = 2;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('fetchOne')->willReturn('0');
        $connection
            ->expects(self::exactly(4))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $currentRow, $closed, false);
        $connection->expects(self::exactly(1))->method('fetchAllAssociative')->willReturn([$boundary]);
        $connection->expects(self::once())->method('rollBack');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');
        try {
            $this->store($connection, $clock, $uuids)->closeSegment('system:global', $segment['segment_id']);
            self::fail('missing successor was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
        }
    }

    public function testCloseRetryRejectsMalformedPersistedLifecycleAndTokenBeforeResources(): void
    {
        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $current = '01941f29-7c00-7000-8000-000000000103';
        $state['active_segment_id'] = $current;
        $currentRow = $segment;
        $currentRow['segment_id'] = $current;
        $currentRow['first_sequence'] = 2;
        $currentRow['final_sequence'] = null;
        $currentRow['head_record_hash'] = null;
        $currentRow['predecessor_record_hash'] = $state['head_record_hash'];
        $successor = '01941f29-7c00-7000-8000-000000000102';
        $closedAt = '2026-08-19T00:00:02.000003Z';
        $variants = [
            'final-before-first' => static function (array &$row): void {
                $row['final_sequence'] = 0;
            },
            'closed-before-opened' => static function (array &$row): void {
                $row['closed_at'] = '2026-08-18T00:00:02.000003Z';
            },
            'bad-token-grammar' => static function (array &$row): void {
                $row['close_fencing_token'] = str_repeat('x', 10);
            },
        ];
        foreach ($variants as $name => $mutate) {
            $closed = $segment;
            $closed['state'] = 'closed';
            $closed['closed_at'] = $closedAt;
            $closed['successor_segment_id'] = $successor;
            $closed['close_fencing_token'] = $this->expectedCloseToken(
                'system:global',
                $segment['segment_id'],
                1,
                $state['head_record_hash'],
                $successor,
                $closedAt,
            );
            $closed['state_version'] = 2;
            $mutate($closed);
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection
                ->expects(self::exactly(3))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($state, $currentRow, $closed);
            $connection
                ->expects(self::atMost(2))
                ->method('fetchAllAssociative')
                ->willReturnOnConsecutiveCalls([$boundary], [$boundary]);
            $connection->expects(self::once())->method('rollBack');
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');
            try {
                $this->store($connection, $clock, $uuids)->closeSegment('system:global', $segment['segment_id']);
                self::fail($name . ' persisted lifecycle was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $name);
            }
        }
    }

    public function testCloseRejectsUnknownOrEmptySegmentsBeforeClockAndUuid(): void
    {
        foreach (['unknown' => false, 'empty' => true] as $kind => $empty) {
            $rows = $empty ? $this->genesisAppendRows() : $this->appendRowsForStored($this->storedRecord(), null);
            $state = $rows[0];
            $segment = $rows[1];
            $boundary = $rows[2] ?? null;
            $expectedSegment = $segment;
            if (!$empty) {
                $expectedSegment['segment_id'] = '01941f29-7c00-7000-8000-000000000199';
            }
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection
                ->expects($empty ? self::exactly(2) : self::exactly(3))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($state, $segment, ...$empty ? [] : [false]);
            if (!$empty) {
                $connection
                    ->expects(self::exactly(2))
                    ->method('fetchAllAssociative')
                    ->willReturnOnConsecutiveCalls([$boundary], [$boundary]);
            }
            $connection->expects(self::once())->method('rollBack');
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');
            $store = $this->store($connection, $clock, $uuids);

            try {
                $store->closeSegment('system:global', $expectedSegment['segment_id']);
                self::fail($kind . ' close unexpectedly succeeded');
            } catch (AuditStoreException $exception) {
                self::assertSame('segment_conflict', $exception->code(), $kind);
            }
        }
    }

    public function testCloseValidatesCurrentActiveBeforeWrongExpectedConflict(): void
    {
        [$baseState, $baseSegment, $baseBoundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $current = '01941f29-7c00-7000-8000-000000000103';
        $expected = '01941f29-7c00-7000-8000-000000000199';
        $variants = [
            'missing-current' => static function (
                array &$state,
                mixed &$currentRow,
                array &$endpoint,
                array &$minimum,
            ): void {
                $currentRow = false;
            },
            'current-lifecycle' => static function (
                array &$state,
                array &$currentRow,
                array &$endpoint,
                array &$minimum,
            ): void {
                $currentRow['closed_at'] = '2026-08-19T00:00:01.000000Z';
            },
            'current-endpoint' => static function (
                array &$state,
                array &$currentRow,
                array &$endpoint,
                array &$minimum,
            ): void {
                $endpoint['record_hash'] = str_repeat('c', 64);
            },
            'current-minimum' => static function (
                array &$state,
                array &$currentRow,
                array &$endpoint,
                array &$minimum,
            ): void {
                $minimum['sequence'] = 2;
            },
            'current-predecessor' => static function (
                array &$state,
                array &$currentRow,
                array &$endpoint,
                array &$minimum,
            ): void {
                $currentRow['predecessor_record_hash'] = str_repeat('c', 64);
            },
        ];

        foreach ($variants as $name => $mutate) {
            $state = $baseState;
            $state['active_segment_id'] = $current;
            $currentRow = $baseSegment;
            $currentRow['segment_id'] = $current;
            $currentRow['first_sequence'] = 1;
            $currentRow['final_sequence'] = 1;
            $endpoint = $baseBoundary;
            $endpoint['segment_id'] = $current;
            $minimum = $endpoint;
            $wrong = $baseSegment;
            $wrong['segment_id'] = $expected;
            $mutate($state, $currentRow, $endpoint, $minimum);
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection
                ->expects(self::atMost(3))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($state, $currentRow, $wrong);
            if ($currentRow !== false) {
                $connection
                    ->expects(self::atMost(2))
                    ->method('fetchAllAssociative')
                    ->willReturnOnConsecutiveCalls([$endpoint], [$minimum]);
            }
            $connection->expects(self::once())->method('rollBack');
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');
            try {
                $this->store($connection, $clock, $uuids)->closeSegment('system:global', $expected);
                self::fail($name . ' current-active mutation was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $name);
            }
        }
    }

    public function testCloseValidWrongActiveOpenSegmentIsAConflictAfterCurrentValidation(): void
    {
        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $expected = '01941f29-7c00-7000-8000-000000000199';
        $wrong = $segment;
        $wrong['segment_id'] = $expected;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('fetchOne')->willReturn('0');
        $connection
            ->expects(self::exactly(3))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $segment, $wrong);
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls([$boundary], [$boundary]);
        $connection->expects(self::once())->method('rollBack');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');
        try {
            $this->store($connection, $clock, $uuids)->closeSegment('system:global', $expected);
            self::fail('valid wrong-active segment was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::SegmentConflict, $exception->failure());
        }
    }

    public function testCloseRejectsMalformedWrongActiveBeforeConflict(): void
    {
        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $expected = '01941f29-7c00-7000-8000-000000000199';
        $wrong = $segment;
        $wrong['segment_id'] = $expected;
        $wrong['final_sequence'] = 1;
        $wrong['head_record_hash'] = null;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('fetchOne')->willReturn('0');
        $connection
            ->expects(self::exactly(3))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $segment, $wrong);
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls([$boundary], [$boundary]);
        $connection->expects(self::once())->method('rollBack');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');
        try {
            $this->store($connection, $clock, $uuids)->closeSegment('system:global', $expected);
            self::fail('malformed wrong-active segment was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
        }
    }

    public function testCloseConflictPrecedesCapacityForValidEmptyAndWrongActiveAtMaximumVersion(): void
    {
        $maximum = 9007199254740991;
        $cases = [];
        [$emptyState, $emptySegment] = $this->genesisAppendRows();
        $emptyState['state_version'] = $maximum;
        $emptySegment['state_version'] = $maximum;
        $cases['empty'] = [$emptyState, $emptySegment, null];

        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $state['state_version'] = $maximum;
        $segment['state_version'] = $maximum;
        $wrong = $segment;
        $wrong['segment_id'] = '01941f29-7c00-7000-8000-000000000199';
        $cases['wrong-active'] = [$state, $segment, [$wrong, $boundary]];

        foreach ($cases as $name => [$state, $current, $tail]) {
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            if ($name === 'empty') {
                $connection
                    ->expects(self::exactly(2))
                    ->method('fetchAssociative')
                    ->willReturnOnConsecutiveCalls($state, $current);
            } else {
                [$wrong, $boundary] = $tail;
                $connection
                    ->expects(self::exactly(3))
                    ->method('fetchAssociative')
                    ->willReturnOnConsecutiveCalls($state, $current, $wrong);
                $connection
                    ->expects(self::exactly(2))
                    ->method('fetchAllAssociative')
                    ->willReturnOnConsecutiveCalls([$boundary], [$boundary]);
            }
            $connection->expects(self::once())->method('rollBack');
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $expected = $name === 'empty' ? $current['segment_id'] : '01941f29-7c00-7000-8000-000000000199';
                $this->store($connection, $clock, $uuids)->closeSegment('system:global', $expected);
                self::fail($name . ' conflict unexpectedly succeeded');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::SegmentConflict, $exception->failure(), $name);
            }
        }
    }

    public function testCloseValidMaximumStateAndSegmentVersionsFailBeforeResources(): void
    {
        $maximum = 9007199254740991;
        $variants = [];
        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $state['last_sequence'] = $maximum;
        $segment['final_sequence'] = $maximum;
        $boundary['sequence'] = $maximum;
        $minimum = $boundary;
        $minimum['sequence'] = 1;
        $variants['last-sequence'] = [$state, $segment, $boundary, $minimum];
        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $state['state_version'] = $maximum;
        $variants['state-version'] = [$state, $segment, $boundary, $boundary];
        [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $segment['state_version'] = $maximum;
        $variants['segment-version'] = [$state, $segment, $boundary, $boundary];

        foreach ($variants as $name => [$state, $segment, $boundary, $minimum]) {
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($state, $segment);
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAllAssociative')
                ->willReturnOnConsecutiveCalls([$boundary], [$minimum], [$boundary], [$minimum]);
            $connection->expects(self::once())->method('rollBack');
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');
            try {
                $this->store($connection, $clock, $uuids)->closeSegment('system:global', $segment['segment_id']);
                self::fail($name . ' capacity was not enforced');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::ResourceUnavailable, $exception->failure(), $name);
            }
        }
    }

    public function testCloseRejectsMalformedStateAndCapacityScalarBeforeExpectedSegment(): void
    {
        [$baseState, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
        $variants = [
            'state-shape' => static function (array &$state): void {
                unset($state['head_record_hash']);
            },
            'state-capacity' => static function (array &$state): void {
                $state['last_sequence'] = '9007199254740992';
            },
        ];
        foreach ($variants as $name => $mutate) {
            $state = $baseState;
            $mutate($state);
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection->expects(self::once())->method('fetchAssociative')->willReturn($state);
            $connection->expects(self::once())->method('rollBack');
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');
            try {
                $this->store($connection, $clock, $uuids)->closeSegment('system:global', $segment['segment_id']);
                self::fail($name . ' malformed state was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $name);
            }
        }
    }

    public function testCloseRejectsCapPlusOneStateSegmentAndBoundaryBeforeConflict(): void
    {
        $capPlusOne = '9007199254740992';
        $expected = '01941f29-7c00-7000-8000-000000000199';
        $variants = [
            'state-version' => 'state',
            'current-segment-version' => 'current',
            'expected-segment-version' => 'expected',
            'boundary-sequence' => 'boundary',
        ];
        foreach ($variants as $name => $kind) {
            [$state, $segment, $boundary] = $this->appendRowsForStored($this->storedRecord(), null);
            $wrong = $segment;
            $wrong['segment_id'] = $expected;
            if ($kind === 'state') {
                $state['state_version'] = $capPlusOne;
            } elseif ($kind === 'current') {
                $segment['state_version'] = $capPlusOne;
            } elseif ($kind === 'expected') {
                $wrong['state_version'] = $capPlusOne;
            } else {
                $boundary['sequence'] = $capPlusOne;
            }
            $fetchAssociativeCalls = 0;
            $fetchAllCalls = 0;
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection
                ->expects(self::atMost(3))
                ->method('fetchAssociative')
                ->willReturnCallback(static function () use (
                    &$fetchAssociativeCalls,
                    $state,
                    $segment,
                    $wrong,
                    $kind,
                ): mixed {
                    $rows = $kind === 'expected'
                        ? [$state, $segment, $wrong]
                        : ($kind === 'state' ? [$state] : [$state, $segment]);
                    $row = $rows[$fetchAssociativeCalls] ?? false;
                    $fetchAssociativeCalls++;

                    return $row;
                });
            $connection
                ->expects(self::atMost(2))
                ->method('fetchAllAssociative')
                ->willReturnCallback(static function () use (&$fetchAllCalls, $boundary): array {
                    $fetchAllCalls++;

                    return [$boundary];
                });
            $connection->expects(self::once())->method('rollBack');
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');
            try {
                $this->store($connection, $clock, $uuids)->closeSegment('system:global', $expected);
                self::fail($name . ' cap+1 value was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $name);
            }
            self::assertLessThanOrEqual(2, $fetchAllCalls, $name . ' boundary bound');
        }
    }

    public function testAppendDurableRejectsRequiredLogicalBeforeDatabaseOrResources(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $this->expectNoConnectionUseExceptAutoCommit($connection);
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        try {
            $this->store($connection, $clock, $uuids)->appendDurable($this->canonical(
                $this->fixture()['records']['systemGenesis']['logical'],
            ));
            self::fail('required logical record was accepted by the durable path');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testAppendDurableAppendsToAnExistingPartitionWithoutClockOrUuid(): void
    {
        $logical = $this->durableLogical('durableSystemIngress');
        [$state, $segment, $boundary] = $this->existingAppendRows(
            'systemGenesis',
            null,
            '01941f29-7c00-7000-8000-000000000101',
        );
        $minimum = $boundary;
        $identityCalls = 0;
        $writeCalls = 0;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection
            ->expects(self::exactly(3))
            ->method('fetchOne')
            ->willReturnCallback(static function () use (&$writeCalls): string {
                $writeCalls++;

                return $writeCalls === 3 ? '1' : '0';
            });
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $segment);
        $connection
            ->expects(self::exactly(3))
            ->method('fetchAllAssociative')
            ->willReturnCallback(function () use (&$identityCalls, $boundary, $minimum): array {
                $identityCalls++;

                return match ($identityCalls) {
                    1 => [$boundary],
                    2 => [$minimum],
                    default => [],
                };
            });
        $connection->expects(self::once())->method('commit');
        $events = [];
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        $result = $this->store($connection, $clock, $uuids, onUse: static function (string $event) use (
            &$events,
        ): void {
            $events[] = $event;
        })->appendDurable($logical);

        self::assertTrue($result->inserted());
        self::assertSame(2, $result->record()->toArray()['sequence']);
        self::assertSame($logical->toArray()['recordId'], $result->record()->toArray()['recordId']);
        self::assertSame($logical->toArray()['time']['recordedAt'], $result->record()->toArray()['recordedAt']);
        self::assertSame(['key', 'nonce'], $events);
    }

    public function testAppendDurableBootstrapsWithOneSegmentUuidAndLogicalRecordedAt(): void
    {
        $logical = $this->durableLogical('durableSystemIngress');
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $fetchOneCalls = 0;
        $events = [];
        $queries = [];
        $connection
            ->expects(self::exactly(3))
            ->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $parameters, array $types) use (
                &$fetchOneCalls,
                &$events,
                &$queries,
            ): string {
                $fetchOneCalls++;
                $label = match ($fetchOneCalls) {
                    1 => 'partition-lock',
                    2 => 'record-lock',
                    default => 'write',
                };
                $events[] = $label;
                $queries[$label] = [$sql, $parameters, $types];

                return $fetchOneCalls === 3 ? '1' : '0';
            });
        $fetchAssociativeCalls = 0;
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnCallback(function (string $sql, array $parameters, array $types) use (
                &$fetchAssociativeCalls,
                &$events,
                &$queries,
            ): array|false {
                $fetchAssociativeCalls++;
                $label = $fetchAssociativeCalls === 1 ? 'state' : 'orphan';
                $events[] = $label;
                $queries[$label] = [$sql, $parameters, $types];

                return $fetchAssociativeCalls === 1 ? false : ['segment_exists' => 0, 'record_exists' => 0];
            });
        $fetchAllCalls = 0;
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql, array $parameters, array $types) use (
                &$fetchAllCalls,
                &$events,
                &$queries,
            ): array {
                $fetchAllCalls++;
                $label = $fetchAllCalls === 1 ? 'identity' : 'segment-collision';
                $events[] = $label;
                $queries[$label] = [$sql, $parameters, $types];

                return [];
            });
        $connection->expects(self::once())->method('commit');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = new SequenceUuidGenerator([
            '01941f29-7c00-7000-8000-000000000201',
        ], static function (string $event) use (&$events): void {
            $events[] = $event;
        });

        $result = $this->store($connection, $clock, $uuids, onUse: static function (string $event) use (
            &$events,
        ): void {
            $events[] = $event;
        })->appendDurable($logical);

        $value = $result->record()->toArray();
        self::assertTrue($result->inserted());
        self::assertSame(1, $uuids->calls);
        self::assertSame('01941f29-7c00-7000-8000-000000000201', $value['segmentId']);
        self::assertSame($logical->toArray()['time']['recordedAt'], $value['recordedAt']);
        self::assertSame($value['recordedAt'], $queries['write'][1]['assigned_at']);
        self::assertSame($value['recordedAt'], $queries['write'][1]['recorded_at']);
        self::assertSame(1, $queries['write'][1]['bootstrap']);
        self::assertSame(ParameterType::STRING, $queries['write'][2]['assigned_at']);
        self::assertSame(ParameterType::STRING, $queries['write'][2]['recorded_at']);
        self::assertSame(
            [
                'partition-lock',
                'state',
                'orphan',
                'record-lock',
                'identity',
                'segment-uuid',
                'segment-collision',
                'key',
                'nonce',
                'write',
            ],
            $events,
        );
        self::assertSame(
            'SELECT CASE WHEN EXISTS ( SELECT 1 FROM "blackops"."audit_segments" WHERE partition_id = :partition_id ) '
            . 'THEN 1 ELSE 0 END AS segment_exists, CASE WHEN EXISTS ( SELECT 1 FROM "blackops"."audit_records" '
            . 'WHERE partition_id = :partition_id ) THEN 1 ELSE 0 END AS record_exists',
            $this->normalizeSql($queries['orphan'][0]),
        );
        self::assertSame(['partition_id' => 'system:global'], $queries['orphan'][1]);
        self::assertSame(['partition_id' => ParameterType::STRING], $queries['orphan'][2]);
        self::assertSame(
            'SELECT record_id::text AS record_id, partition_id FROM "blackops"."audit_records" '
            . 'WHERE record_id = CAST(:record_id AS uuid)',
            $this->normalizeSql($queries['identity'][0]),
        );
        self::assertSame(['record_id' => $logical->toArray()['recordId']], $queries['identity'][1]);
        self::assertSame(['record_id' => ParameterType::STRING], $queries['identity'][2]);
    }

    public function testAppendDurableReturnsFalseForAnAuthenticatedSamePartitionRetry(): void
    {
        $logical = $this->durableLogical('durableSystemIngress');
        [$stored] = $this->protectedLogicalRecord($logical);
        $row = $this->rowForLogical($stored, $logical);
        [$state, $segment, $boundary] = $this->appendRowsForStored($stored, null);
        $minimum = $boundary;
        $identity = [
            'record_id' => $logical->toArray()['recordId'],
            'partition_id' => 'system:global',
        ];
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::exactly(2))->method('fetchOne')->willReturn('0');
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $segment);
        $connection
            ->expects(self::exactly(4))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls([$boundary], [$minimum], [$identity], [$row]);
        $connection->expects(self::once())->method('commit');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        $result = $this->store($connection, $clock, $uuids)->appendDurable($logical);

        self::assertFalse($result->inserted());
        self::assertSame($stored->toArray()['protectedBody'], $result->record()->toArray()['protectedBody']);
    }

    public function testAppendDurableRecordsOneOrderedDbLogWithExactIdentityAndSameRowQueries(): void
    {
        $logical = $this->durableLogical('durableSystemIngress');
        [$stored] = $this->protectedLogicalRecord($logical);
        $row = $this->rowForLogical($stored, $logical);
        [$state, $segment, $boundary] = $this->existingAppendRows(
            'systemGenesis',
            null,
            '01941f29-7c00-7000-8000-000000000101',
        );
        $recordId = $logical->toArray()['recordId'];
        $minimum = $boundary;
        $events = [];
        $queries = [];
        $fetchOneCalls = 0;
        $fetchAssociativeCalls = 0;
        $fetchAllCalls = 0;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection
            ->expects(self::exactly(2))
            ->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $parameters, array $types) use (
                &$events,
                &$queries,
                &$fetchOneCalls,
            ): string {
                $fetchOneCalls++;
                $label = $fetchOneCalls === 1 ? 'partition-lock' : 'record-lock';
                $events[] = $label;
                $queries[$label] = [$sql, $parameters, $types];

                return '0';
            });
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnCallback(function (string $sql, array $parameters, array $types) use (
                &$events,
                &$queries,
                &$fetchAssociativeCalls,
                $state,
                $segment,
            ): array {
                $fetchAssociativeCalls++;
                $label = $fetchAssociativeCalls === 1 ? 'state' : 'segment';
                $events[] = $label;
                $queries[$label] = [$sql, $parameters, $types];

                return $fetchAssociativeCalls === 1 ? $state : $segment;
            });
        $connection
            ->expects(self::exactly(4))
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql, array $parameters, array $types) use (
                &$events,
                &$queries,
                &$fetchAllCalls,
                $boundary,
                $minimum,
                $recordId,
                $row,
            ): array {
                $fetchAllCalls++;
                $label = match ($fetchAllCalls) {
                    1 => 'boundary',
                    2 => 'minimum',
                    3 => 'identity',
                    default => 'same-row',
                };
                $events[] = $label;
                $queries[$label] = [$sql, $parameters, $types];

                return match ($fetchAllCalls) {
                    1 => [$boundary],
                    2 => [$minimum],
                    3 => [[
                        'record_id' => $recordId,
                        'partition_id' => 'system:global',
                    ]],
                    default => [$row],
                };
            });

        $result = $this->store($connection)->appendDurable($logical);

        self::assertFalse($result->inserted());
        self::assertSame(
            ['partition-lock', 'state', 'segment', 'boundary', 'minimum', 'record-lock', 'identity', 'same-row'],
            $events,
        );
        self::assertSame(
            'SELECT pg_advisory_xact_lock(hashtextextended(:lock_key, 0))',
            $this->normalizeSql($queries['partition-lock'][0]),
        );
        self::assertSame(
            ['lock_key' => 'blackops.audit.partition.v1:blackops:system:global'],
            $queries['partition-lock'][1],
        );
        self::assertSame(['lock_key' => ParameterType::STRING], $queries['partition-lock'][2]);
        self::assertSame(['lock_key' => 'blackops.audit.record.v1:blackops:' . $recordId], $queries['record-lock'][1]);
        self::assertSame(['lock_key' => ParameterType::STRING], $queries['record-lock'][2]);

        self::assertSame(
            'SELECT record_id::text AS record_id, partition_id FROM "blackops"."audit_records" '
            . 'WHERE record_id = CAST(:record_id AS uuid)',
            $this->normalizeSql($queries['identity'][0]),
        );
        self::assertSame(['record_id' => $recordId], $queries['identity'][1]);
        self::assertSame(['record_id' => ParameterType::STRING], $queries['identity'][2]);
        foreach (['protected_body', 'content_hash', 'action_type', 'resource_type', 'resource_id'] as $column) {
            self::assertStringNotContainsString($column, $queries['identity'][0], $column);
        }

        self::assertSame($this->expectedSameRecordSql(), $this->normalizeSql($queries['same-row'][0]));
        self::assertSame(['record_id' => $recordId, 'partition_id' => 'system:global'], $queries['same-row'][1]);
        self::assertSame(
            ['record_id' => ParameterType::STRING, 'partition_id' => ParameterType::STRING],
            $queries['same-row'][2],
        );
    }

    public function testAppendDurableRejectsCrossPartitionIdentityBeforeProtectedBodyUse(): void
    {
        $snapshot = $this->fixture()['ingress']['durableTenantIngress']['logical'];
        $snapshot['recordId'] = self::RECORD_ID;
        $logical = $this->canonical($snapshot);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::exactly(2))->method('fetchOne')->willReturn('0');
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(false, ['segment_exists' => 0, 'record_exists' => 0]);
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([[
                'record_id' => self::RECORD_ID,
                'partition_id' => 'system:global',
            ]]);
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::never())->method('commit');
        $events = [];
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        try {
            $this->store($connection, $clock, $uuids, onUse: static function (string $event) use (&$events): void {
                $events[] = $event;
            })->appendDurable($logical);
            self::fail('cross-partition identity was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IdempotencyConflict, $exception->failure());
            self::assertNull($exception->getPrevious());
            self::assertSame([], $events);
        }
    }

    public function testAppendDurableValidatesPersistedStructureBeforeResolvingAnExistingId(): void
    {
        $tenant = new AuditTypedReference('account', 'tenant-a');
        $cases = [
            'same partition' => [$this->durableLogical('durableSystemIngress'), null, 'systemGenesis'],
            'cross partition' => [$this->durableLogical('durableTenantIngress'), $tenant, 'tenantGenesis'],
        ];
        foreach ($cases as $label => [$logical, $currentTenant, $fixtureName]) {
            [$state] = $this->existingAppendRows($fixtureName, $currentTenant, '01941f29-7c00-7000-8000-000000000101');
            $state['last_sequence'] = '01';
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection->expects(self::once())->method('fetchAssociative')->willReturn($state);
            $connection->expects(self::once())->method('rollBack');
            foreach (['fetchAllAssociative', 'commit', 'close'] as $method) {
                $connection->expects(self::never())->method($method);
            }
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids)->appendDurable($logical);
                self::fail($label . ' malformed state was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $label);
                self::assertNull($exception->getPrevious(), $label);
            }
        }
    }

    public function testAppendDurableMapsAuthenticatedRowContentAndCiphertextTamperingToIntegrity(): void
    {
        $logical = $this->durableLogical('durableSystemIngress');
        [$stored] = $this->protectedLogicalRecord($logical);
        $baseRow = $this->rowForLogical($stored, $logical);
        [$state, $segment, $boundary] = $this->existingAppendRows(
            'systemGenesis',
            null,
            '01941f29-7c00-7000-8000-000000000101',
        );
        $minimum = $boundary;
        $identity = [
            'record_id' => $logical->toArray()['recordId'],
            'partition_id' => 'system:global',
        ];
        foreach ([
            'content hash' => static function (array $row): array {
                $row['content_hash'] = str_repeat('b', 64);

                return $row;
            },
            'ciphertext' => static function (array $row): array {
                $row['protected_body'] .= 'x';

                return $row;
            },
        ] as $label => $mutate) {
            $row = $mutate($baseRow);
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::exactly(2))->method('fetchOne')->willReturn('0');
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($state, $segment);
            $connection
                ->expects(self::exactly(4))
                ->method('fetchAllAssociative')
                ->willReturnOnConsecutiveCalls([$boundary], [$minimum], [$identity], [$row]);
            $connection->expects(self::once())->method('rollBack');
            $connection->expects(self::never())->method('commit');
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids)->appendDurable($logical);
                self::fail($label . ' tampering was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $label);
                self::assertNull($exception->getPrevious(), $label);
            }
        }
    }

    public function testAppendDurableRejectsDifferentAuthenticatedContentForAnExistingRecordId(): void
    {
        $logical = $this->durableLogical('durableSystemIngress');
        $storedSnapshot = $logical->toArray();
        $storedSnapshot['resource']['id'] = 'stored-operation';
        $storedLogical = $this->canonical($storedSnapshot);
        [$stored] = $this->protectedLogicalRecord($storedLogical);
        $row = $this->rowForLogical($stored, $storedLogical);
        [$state, $segment, $boundary] = $this->appendRowsForStored($stored, null);
        $identity = [
            'record_id' => $logical->toArray()['recordId'],
            'partition_id' => 'system:global',
        ];
        $events = [];
        $fetchOneCalls = 0;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection
            ->expects(self::exactly(2))
            ->method('fetchOne')
            ->willReturnCallback(static function () use (&$fetchOneCalls): string {
                $fetchOneCalls++;

                return '0';
            });
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $segment);
        $connection
            ->expects(self::exactly(4))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls([$boundary], [$boundary], [$identity], [$row]);
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::never())->method('commit');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        try {
            $this->store($connection, $clock, $uuids, onUse: static function (string $event) use (&$events): void {
                $events[] = $event;
            })->appendDurable($logical);
            self::fail('different authenticated content was accepted for an existing record ID');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IdempotencyConflict, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertNotSame($logical->toArray()['resource']['id'], $storedLogical->toArray()['resource']['id']);
        self::assertSame(['key'], $events);
        self::assertSame(2, $fetchOneCalls);
    }

    public function testAppendDurableRejectsEachAuthenticatedQueryIndexMismatchWithoutMutation(): void
    {
        $logical = $this->durableLogical('durableSystemIngress');
        [$stored] = $this->protectedLogicalRecord($logical);
        $baseRow = $this->rowForLogical($stored, $logical);
        [$state, $segment, $boundary] = $this->appendRowsForStored($stored, null);
        $identity = [
            'record_id' => $logical->toArray()['recordId'],
            'partition_id' => 'system:global',
        ];
        foreach ([
            'action_type' => 'other.action',
            'resource_type' => 'other.resource',
            'resource_id' => 'other-id',
        ] as $field => $value) {
            $row = $baseRow;
            $row[$field] = $value;
            $events = [];
            $fetchOneCalls = 0;
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection
                ->expects(self::exactly(2))
                ->method('fetchOne')
                ->willReturnCallback(static function () use (&$fetchOneCalls): string {
                    $fetchOneCalls++;

                    return '0';
                });
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($state, $segment);
            $connection
                ->expects(self::exactly(4))
                ->method('fetchAllAssociative')
                ->willReturnOnConsecutiveCalls([$boundary], [$boundary], [$identity], [$row]);
            $connection->expects(self::once())->method('rollBack');
            $connection->expects(self::never())->method('commit');
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids, onUse: static function (string $event) use (&$events): void {
                    $events[] = $event;
                })->appendDurable($logical);
                self::fail($field . ' mismatch was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $field);
                self::assertNull($exception->getPrevious(), $field);
            }
            self::assertSame(['key'], $events, $field);
            self::assertSame(2, $fetchOneCalls, $field);
        }
    }

    public function testAppendDurableResolvesExistingIdsBeforeMaximumCapacity(): void
    {
        $logical = $this->durableLogical('durableSystemIngress');
        $recordId = $logical->toArray()['recordId'];
        $differentSnapshot = $logical->toArray();
        $differentSnapshot['resource']['id'] = 'operation-2';
        $differentStored = $this->canonical($differentSnapshot);
        [$maximumStored] = $this->protectedLogicalRecord($logical, 9007199254740991, str_repeat('a', 64));
        [$maximumDifferentStored] = $this->protectedLogicalRecord(
            $differentStored,
            9007199254740991,
            str_repeat('b', 64),
        );
        $maximumStoredRow = $this->rowForLogical($maximumStored, $logical);
        $maximumDifferentStoredRow = $this->rowForLogical($maximumDifferentStored, $differentStored);
        [$sameState, $sameSegment, $sameBoundary, $sameMinimum] = $this->maximumAppendRowsForStored(
            $maximumStored,
            null,
        );
        [$differentState, $differentSegment, $differentBoundary, $differentMinimum] = $this->maximumAppendRowsForStored(
            $maximumDifferentStored,
            null,
        );
        foreach ([
            [$maximumStoredRow,          $sameState,      $sameSegment,      $sameBoundary],
            [$maximumDifferentStoredRow, $differentState, $differentSegment, $differentBoundary],
        ] as [$storedRowAtMaximum, $stateAtMaximum, $segmentAtMaximum, $boundaryAtMaximum]) {
            self::assertSame($boundaryAtMaximum['segment_id'], $storedRowAtMaximum['segment_id']);
            self::assertSame($boundaryAtMaximum['sequence'], $storedRowAtMaximum['sequence']);
            self::assertNotNull($boundaryAtMaximum['previous_record_hash']);
            self::assertSame($boundaryAtMaximum['record_hash'], $storedRowAtMaximum['record_hash']);
            self::assertSame($boundaryAtMaximum['sequence'], $stateAtMaximum['last_sequence']);
            self::assertSame($boundaryAtMaximum['record_hash'], $stateAtMaximum['head_record_hash']);
            self::assertSame($boundaryAtMaximum['sequence'], $segmentAtMaximum['final_sequence']);
            self::assertSame($boundaryAtMaximum['record_hash'], $segmentAtMaximum['head_record_hash']);
            self::assertSame($boundaryAtMaximum['segment_id'], $stateAtMaximum['active_segment_id']);
            self::assertSame($boundaryAtMaximum['segment_id'], $segmentAtMaximum['segment_id']);
        }
        $tenant = new AuditTypedReference('account', 'tenant-a');
        $crossSnapshot = $this->durableLogical('durableTenantIngress')->toArray();
        $crossSnapshot['recordId'] = $recordId;
        $cross = $this->canonical($crossSnapshot);
        $cases = [
            'same ID equal' => [
                $logical,
                $sameState,
                $sameSegment,
                $sameBoundary,
                $sameMinimum,
                ['record_id' => $recordId, 'partition_id' => 'system:global'],
                $maximumStoredRow,
                'same',
            ],
            'same ID different content' => [
                $logical,
                $differentState,
                $differentSegment,
                $differentBoundary,
                $differentMinimum,
                ['record_id' => $recordId, 'partition_id' => 'system:global'],
                $maximumDifferentStoredRow,
                'different',
            ],
            'cross partition ID' => [
                $cross,
                ...$this->maximumAppendRows('tenantGenesis', $tenant, '01941f29-7c00-7000-8000-000000000102'),
                ['record_id' => $recordId, 'partition_id' => 'system:global'],
                null,
                'cross',
            ],
            'absent ID' => [
                $this->canonical(array_replace($logical->toArray(), [
                    'recordId' => '01941f29-7c00-7000-8000-000000000299',
                ])),
                ...$this->maximumAppendRows('systemGenesis', null, '01941f29-7c00-7000-8000-000000000101'),
                null,
                null,
                'absent',
            ],
        ];
        foreach ($cases as $label => [$candidate, $state, $segment, $boundary, $minimum, $identity, $row, $outcome]) {
            $events = [];
            $fetchOneCalls = 0;
            $fetchAllCalls = 0;
            $fetchAssociativeCalls = 0;
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection
                ->expects(self::exactly(2))
                ->method('fetchOne')
                ->willReturnCallback(static function () use (&$fetchOneCalls): string {
                    $fetchOneCalls++;

                    return '0';
                });
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAssociative')
                ->willReturnCallback(function () use (&$fetchAssociativeCalls, $state, $segment): array {
                    $fetchAssociativeCalls++;

                    return $fetchAssociativeCalls === 1 ? $state : $segment;
                });
            $connection
                ->expects(self::exactly($outcome === 'same' || $outcome === 'different' ? 4 : 3))
                ->method('fetchAllAssociative')
                ->willReturnCallback(function () use (&$fetchAllCalls, $boundary, $minimum, $identity, $row): array {
                    $fetchAllCalls++;

                    return match ($fetchAllCalls) {
                        1 => [$boundary],
                        2 => [$minimum],
                        3 => $identity === null ? [] : [$identity],
                        default => $row === null ? [] : [$row],
                    };
                });
            if ($outcome === 'same') {
                $connection->expects(self::once())->method('commit');
                $connection->expects(self::never())->method('rollBack');
            } else {
                $connection->expects(self::once())->method('rollBack');
                $connection->expects(self::never())->method('commit');
            }
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $result = $this->store($connection, $clock, $uuids, onUse: static function (string $event) use (
                    &$events,
                ): void {
                    $events[] = $event;
                })->appendDurable($candidate);
                self::assertSame('same', $outcome, $label);
                self::assertFalse($result->inserted(), $label);
            } catch (AuditStoreException $exception) {
                self::assertNotSame('same', $outcome, $label);
                self::assertSame(
                    $outcome === 'absent'
                        ? AuditStoreFailure::ResourceUnavailable
                        : (
                            $outcome === 'cross' || $outcome === 'different'
                                ? AuditStoreFailure::IdempotencyConflict
                                : AuditStoreFailure::IntegrityInvalid
                        ),
                    $exception->failure(),
                    $label,
                );
            }
            self::assertSame($outcome === 'same' || $outcome === 'different' ? ['key'] : [], $events, $label);
            self::assertSame(2, $fetchOneCalls, $label);
            self::assertSame($outcome === 'same' || $outcome === 'different' ? 4 : 3, $fetchAllCalls, $label);
        }
    }

    public function testAppendDurablePreservesOrAdvancesLogicalRecordedAtHighWater(): void
    {
        $logical = $this->durableLogical('durableSystemIngress');
        $logicalRecordedAt = $logical->toArray()['time']['recordedAt'];

        foreach ([
            'newer high-water' => ['2026-08-19T00:00:03.000000Z', '2026-08-19T00:00:03.000000Z'],
            'older high-water' => ['2026-08-19T00:00:01.000000Z', $logicalRecordedAt],
        ] as $label => [$existingHighWater, $expectedAssignedAt]) {
            [$state, $segment, $boundary] = $this->existingAppendRows(
                'systemGenesis',
                null,
                '01941f29-7c00-7000-8000-000000000101',
            );
            $state['last_assigned_recorded_at'] = $existingHighWater;
            $minimum = $boundary;
            $write = [];
            $fetchOneCalls = 0;
            $fetchAssociativeCalls = 0;
            $fetchAllCalls = 0;
            $events = [];
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('commit');
            $connection
                ->expects(self::exactly(3))
                ->method('fetchOne')
                ->willReturnCallback(function (string $sql, array $parameters, array $types) use (
                    &$fetchOneCalls,
                    &$write,
                    &$events,
                ): string {
                    $fetchOneCalls++;
                    if ($fetchOneCalls === 1) {
                        $events[] = 'partition-lock';
                    } elseif ($fetchOneCalls === 2) {
                        $events[] = 'record-lock';
                    } else {
                        $events[] = 'write';
                        $write = [$sql, $parameters, $types];
                    }

                    return $fetchOneCalls === 3 ? '1' : '0';
                });
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAssociative')
                ->willReturnCallback(function () use (&$fetchAssociativeCalls, $state, $segment): array {
                    $fetchAssociativeCalls++;

                    return $fetchAssociativeCalls === 1 ? $state : $segment;
                });
            $connection
                ->expects(self::exactly(3))
                ->method('fetchAllAssociative')
                ->willReturnCallback(function () use (&$fetchAllCalls, $boundary, $minimum): array {
                    $fetchAllCalls++;

                    return match ($fetchAllCalls) {
                        1 => [$boundary],
                        2 => [$minimum],
                        default => [],
                    };
                });
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');
            $store = $this->store($connection, $clock, $uuids, onUse: static function (string $event) use (
                &$events,
            ): void {
                $events[] = $event;
            });

            $result = $store->appendDurable($logical);
            $value = $result->record()->toArray();

            self::assertTrue($result->inserted(), $label);
            self::assertSame($logicalRecordedAt, $value['recordedAt'], $label);
            self::assertSame($expectedAssignedAt, $write[1]['assigned_at'], $label);
            self::assertSame($logicalRecordedAt, $write[1]['recorded_at'], $label);
            self::assertSame(0, $write[1]['bootstrap'], $label);
            self::assertSame(2, $write[1]['next_sequence'], $label);
            self::assertSame(
                [
                    'bootstrap' => 0,
                    'partition_id' => 'system:global',
                    'tenant_type' => null,
                    'tenant_id' => null,
                    'active_segment_id' => $segment['segment_id'],
                    'segment_id' => $segment['segment_id'],
                    'record_id' => $value['recordId'],
                    'schema_version' => $value['schemaVersion'],
                    'kind' => $value['kind'],
                    'next_sequence' => 2,
                    'previous_record_hash' => $boundary['record_hash'],
                    'content_hash' => $value['contentHash'],
                    'record_hash' => $value['recordHash'],
                    'recorded_at' => $logicalRecordedAt,
                    'event_category' => $value['eventCategory'],
                    'protected_body' => $value['protectedBody'],
                    'action_type' => $logical->toArray()['action']['type'],
                    'resource_type' => $logical->toArray()['resource']['type'],
                    'resource_id' => $logical->toArray()['resource']['id'],
                    'assigned_at' => $expectedAssignedAt,
                    'state_version' => $state['state_version'],
                    'segment_version' => $segment['state_version'],
                ],
                $write[1],
                $label,
            );
            self::assertSame(ParameterType::STRING, $write[2]['assigned_at'], $label);
            self::assertSame(ParameterType::STRING, $write[2]['recorded_at'], $label);
            self::assertSame(ParameterType::INTEGER, $write[2]['next_sequence'], $label);
            self::assertSame(
                [
                    'bootstrap' => ParameterType::INTEGER,
                    'partition_id' => ParameterType::STRING,
                    'tenant_type' => ParameterType::STRING,
                    'tenant_id' => ParameterType::STRING,
                    'active_segment_id' => ParameterType::STRING,
                    'segment_id' => ParameterType::STRING,
                    'record_id' => ParameterType::STRING,
                    'schema_version' => ParameterType::INTEGER,
                    'kind' => ParameterType::STRING,
                    'next_sequence' => ParameterType::INTEGER,
                    'previous_record_hash' => ParameterType::STRING,
                    'content_hash' => ParameterType::STRING,
                    'record_hash' => ParameterType::STRING,
                    'recorded_at' => ParameterType::STRING,
                    'event_category' => ParameterType::STRING,
                    'protected_body' => ParameterType::BINARY,
                    'action_type' => ParameterType::STRING,
                    'resource_type' => ParameterType::STRING,
                    'resource_id' => ParameterType::STRING,
                    'assigned_at' => ParameterType::STRING,
                    'state_version' => ParameterType::INTEGER,
                    'segment_version' => ParameterType::INTEGER,
                ],
                $write[2],
                $label,
            );
            $this->assertWriteCteInvariants($write[0]);
            self::assertStringContainsString('CAST(:assigned_at AS timestamptz)', $write[0], $label);
            self::assertStringContainsString('last_assigned_recorded_at', $write[0], $label);
            self::assertSame(['partition-lock', 'record-lock', 'key', 'nonce', 'write'], $events, $label);
            self::assertSame(3, $fetchOneCalls, $label);
            self::assertSame(2, $fetchAssociativeCalls, $label);
            self::assertSame(3, $fetchAllCalls, $label);
        }
    }

    public function testAppendDurableRejectsCapPlusOneStateAndBoundaryBeforeRecordIdentityOrResources(): void
    {
        $tenant = new AuditTypedReference('account', 'tenant-a');
        $maximumPlusOne = '9007199254740992';
        foreach ([
            'same-partition state' => ['durableSystemIngress', null, 'state'],
            'cross-partition state' => ['durableTenantIngress', $tenant, 'state'],
            'same-partition boundary' => ['durableSystemIngress', null, 'boundary'],
            'cross-partition boundary' => ['durableTenantIngress', $tenant, 'boundary'],
        ] as $label => [$fixtureName, $currentTenant, $scalar]) {
            $logical = $this->durableLogical($fixtureName);
            [$stored] = $this->protectedLogicalRecord($logical);
            if ($currentTenant !== null) {
                $systemRecordId = $this->durableLogical('durableSystemIngress')->toArray()['recordId'];
                $tenantStoredRecordId = $stored->toArray()['recordId'];
                $candidateSnapshot = $logical->toArray();
                $candidateSnapshot['recordId'] = $systemRecordId;
                $logical = $this->canonical($candidateSnapshot);
                self::assertSame($systemRecordId, $logical->toArray()['recordId'], $label);
                self::assertNotSame($tenantStoredRecordId, $logical->toArray()['recordId'], $label);
                self::assertSame($this->partition($currentTenant), $stored->toArray()['partitionId'], $label);
                self::assertSame(
                    $this->partition($currentTenant),
                    $this->partition(
                        new AuditTypedReference(
                            $logical->toArray()['tenant']['type'],
                            $logical->toArray()['tenant']['id'],
                        ),
                    ),
                    $label,
                );
            }
            [$state, $segment, $boundary] = $this->appendRowsForStored($stored, $currentTenant);
            if ($scalar === 'state') {
                $state['last_sequence'] = $maximumPlusOne;
            } else {
                $boundary['sequence'] = $maximumPlusOne;
            }
            $events = [];
            $fetchOneCalls = 0;
            $fetchAssociativeCalls = 0;
            $fetchAllCalls = 0;
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection
                ->expects(self::once())
                ->method('fetchOne')
                ->willReturnCallback(static function () use (&$fetchOneCalls): string {
                    $fetchOneCalls++;

                    return '0';
                });
            $connection
                ->expects(self::exactly($scalar === 'state' ? 1 : 2))
                ->method('fetchAssociative')
                ->willReturnCallback(function () use (&$fetchAssociativeCalls, $state, $segment): array {
                    $fetchAssociativeCalls++;

                    return $fetchAssociativeCalls === 1 ? $state : $segment;
                });
            $connection
                ->expects(self::exactly($scalar === 'state' ? 0 : 1))
                ->method('fetchAllAssociative')
                ->willReturnCallback(function () use (&$fetchAllCalls, $boundary): array {
                    $fetchAllCalls++;

                    return [$boundary];
                });
            $connection->expects(self::once())->method('rollBack');
            $connection->expects(self::never())->method('commit');
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids, onUse: static function (string $event) use (&$events): void {
                    $events[] = $event;
                })->appendDurable($logical);
                self::fail($label . ' cap+1 was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $label);
                self::assertNull($exception->getPrevious(), $label);
            }
            self::assertSame([], $events, $label);
            self::assertSame(1, $fetchOneCalls, $label);
            self::assertSame($scalar === 'state' ? 1 : 2, $fetchAssociativeCalls, $label);
            self::assertSame($scalar === 'state' ? 0 : 1, $fetchAllCalls, $label);
        }
    }

    public function testRealPostgreSqlBootstrapAndExistingAppendPreserveSystemAndTenantChains(): void
    {
        $admin = null;
        $primary = null;
        $probe = null;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            $clock = new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:00.123456Z'));
            $uuids = new SequenceUuidGenerator([
                '01941f29-7c00-7000-8000-000000000201',
                '01941f29-7c00-7000-8000-000000000202',
                '01941f29-7c00-7000-8000-000000000203',
                '01941f29-7c00-7000-8000-000000000204',
                '01941f29-7c00-7000-8000-000000000205',
                '01941f29-7c00-7000-8000-000000000206',
            ]);
            $store = $this->store($primary, $clock, $uuids, schema: $schema);
            $tenant = new AuditTypedReference('account', 'tenant-real');

            foreach ([null, $tenant] as $currentTenant) {
                $partition = $currentTenant === null ? 'system:global' : $this->partition($currentTenant);
                $first = $store->appendRequired($this->draftForTenant($currentTenant));
                $second = $store->appendRequired($this->draftForTenant($currentTenant));
                $firstValue = $first->record()->toArray();
                $secondValue = $second->record()->toArray();

                self::assertSame($partition, $firstValue['partitionId']);
                self::assertSame($partition, $secondValue['partitionId']);
                self::assertSame(1, $firstValue['sequence']);
                self::assertSame(2, $secondValue['sequence']);
                self::assertNull($firstValue['previousRecordHash']);
                self::assertSame($firstValue['recordHash'], $secondValue['previousRecordHash']);
                self::assertSame($firstValue['segmentId'], $secondValue['segmentId']);
                self::assertSame('2026-08-30T00:00:00.123456Z', $firstValue['recordedAt']);
                self::assertSame($firstValue['recordedAt'], $secondValue['recordedAt']);
                self::assertSame($currentTenant?->toArray(), $firstValue['tenantBinding']);
                self::assertSame($firstValue['tenantBinding'], $secondValue['tenantBinding']);

                $records = $this->realRecords($probe, $schema, $partition);
                self::assertCount(2, $records);
                self::assertSame($firstValue['recordId'], $records[0]['record_id']);
                self::assertSame($secondValue['recordId'], $records[1]['record_id']);
                self::assertSame('1', $records[0]['sequence']);
                self::assertSame('2', $records[1]['sequence']);
                self::assertNull($records[0]['previous_record_hash']);
                self::assertSame($records[0]['record_hash'], $records[1]['previous_record_hash']);
                self::assertSame($firstValue['recordHash'], $records[0]['record_hash']);
                self::assertSame($secondValue['recordHash'], $records[1]['record_hash']);
                self::assertSame($firstValue['segmentId'], $records[0]['segment_id']);
                self::assertSame($firstValue['segmentId'], $records[1]['segment_id']);
                self::assertSame('2026-08-30T00:00:00.123456Z', $records[0]['recorded_at']);
                self::assertSame($records[0]['recorded_at'], $records[1]['recorded_at']);
                foreach ($records as $record) {
                    self::assertSame($partition, $record['partition_id']);
                    self::assertSame($currentTenant?->type, $record['tenant_type']);
                    self::assertSame($currentTenant?->id, $record['tenant_id']);
                    $protectedBody = PostgreSqlBytea::string($record['protected_body']);
                    self::assertSame('BOPD', substr($protectedBody, 0, 4));
                    self::assertGreaterThanOrEqual(53, strlen($protectedBody));
                }

                $state = $this->realState($probe, $schema, $partition);
                self::assertSame($partition, $state['partition_id']);
                self::assertSame($currentTenant?->type, $state['tenant_type']);
                self::assertSame($currentTenant?->id, $state['tenant_id']);
                self::assertSame($firstValue['segmentId'], $state['active_segment_id']);
                self::assertSame('2', $state['last_sequence']);
                self::assertSame($secondValue['recordHash'], $state['head_record_hash']);
                self::assertSame('2026-08-30T00:00:00.123456Z', $state['last_assigned_recorded_at']);
                self::assertSame('2', $state['state_version']);

                $segment = $this->realSegment($probe, $schema, $partition, $firstValue['segmentId']);
                self::assertSame($partition, $segment['partition_id']);
                self::assertSame('open', $segment['state']);
                self::assertSame('1', $segment['first_sequence']);
                self::assertSame('2', $segment['final_sequence']);
                self::assertSame($secondValue['recordHash'], $segment['head_record_hash']);
                self::assertNull($segment['predecessor_record_hash']);
                self::assertSame('2026-08-30T00:00:00.123456Z', $segment['opened_at']);
                self::assertSame('2', $segment['state_version']);
            }

            self::assertSame(6, $uuids->calls);
        } finally {
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
        }
    }

    public function testRealPostgreSqlAppendAndFindRoundTripsUnicodeLineSeparatorTenant(): void
    {
        $admin = $primary = $probe = null;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            $clock = new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:00.123456Z'));
            $uuids = new SequenceUuidGenerator([
                '01941f29-7c00-7000-8000-000000000301',
                '01941f29-7c00-7000-8000-000000000302',
            ]);
            $store = $this->store($primary, $clock, $uuids, schema: $schema);
            $tenant = new AuditTypedReference('account', "tenant-real\u{2028}separatoré");

            $appended = $store->appendRequired($this->draftForTenant($tenant));
            $expected = $appended->record()->toArray();
            self::assertSame($tenant->toArray(), $expected['tenantBinding']);

            $found = $store->find($expected['recordId'], $tenant, $expected['partitionId']);
            self::assertTrue($found->isFound());
            $record = $found->record();
            self::assertNotNull($record);
            $roundTrip = $record->toArray();
            self::assertSame($expected['recordId'], $roundTrip['recordId']);
            self::assertSame($expected['tenantBinding'], $roundTrip['tenant']);
            self::assertSame($expected['recordedAt'], $roundTrip['time']['recordedAt']);
            self::assertSame($expected['partitionId'], $roundTrip['integrity']['partitionId']);
            self::assertSame($expected['segmentId'], $roundTrip['integrity']['segmentId']);
            self::assertSame($expected['sequence'], $roundTrip['integrity']['sequence']);
            self::assertSame($expected['contentHash'], $roundTrip['integrity']['contentHash']);
            self::assertSame($expected['recordHash'], $roundTrip['integrity']['recordHash']);
        } finally {
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
        }
    }

    public function testRealPostgreSqlCloseBootstrapsSystemAndTenantChainsAndRetriesDurably(): void
    {
        $admin = $primary = $probe = null;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $clockCalls = 0;
        $retryClockCalls = 0;

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            $primary->executeStatement("SET TIME ZONE 'America/Los_Angeles'");
            $probe->executeStatement("SET TIME ZONE 'Pacific/Kiritimati'");
            $clock = new FixtureClock(static function (string $event) use (&$clockCalls): void {
                if ($event === 'clock') {
                    $clockCalls++;
                }
            }, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z'));
            $uuids = new SequenceUuidGenerator([
                '01941f29-7c00-7000-8000-000000000281',
                '01941f29-7c00-7000-8000-000000000282',
                '01941f29-7c00-7000-8000-000000000283',
                '01941f29-7c00-7000-8000-000000000284',
                '01941f29-7c00-7000-8000-000000000285',
                '01941f29-7c00-7000-8000-000000000286',
                '01941f29-7c00-7000-8000-000000000287',
                '01941f29-7c00-7000-8000-000000000288',
            ]);
            $store = $this->store($primary, $clock, $uuids, schema: $schema);
            $tenant = new AuditTypedReference('account', 'tenant-real-close');

            foreach ([null, $tenant] as $currentTenant) {
                $partition = $currentTenant === null ? 'system:global' : $this->partition($currentTenant);
                $first = $store->appendRequired($this->draftForTenant($currentTenant));
                $second = $store->appendRequired($this->draftForTenant($currentTenant));
                $firstValue = $first->record()->toArray();
                $secondValue = $second->record()->toArray();
                $beforeState = $this->realState($probe, $schema, $partition);
                $beforeSegment = $this->realSegment($probe, $schema, $partition, $firstValue['segmentId']);
                $result = $store->closeSegment($partition, $firstValue['segmentId']);

                self::assertTrue($result->changed());
                self::assertSame($partition, $result->partitionId());
                self::assertSame($firstValue['segmentId'], $result->closedSegmentId());
                self::assertSame(1, $result->firstSequence());
                self::assertSame(2, $result->finalSequence());
                self::assertNull($result->predecessorRecordHash());
                self::assertSame($secondValue['recordHash'], $result->headHash());
                self::assertSame('2026-08-30T00:00:05.123456Z', $result->closedAt());
                self::assertSame(
                    $this->expectedCloseToken(
                        $partition,
                        $firstValue['segmentId'],
                        2,
                        $secondValue['recordHash'],
                        $result->successorSegmentId(),
                        $result->closedAt(),
                    ),
                    $result->closeFencingToken(),
                );

                $state = $this->realState($probe, $schema, $partition);
                self::assertSame($result->successorSegmentId(), $state['active_segment_id']);
                self::assertSame('2', $state['last_sequence']);
                self::assertSame($secondValue['recordHash'], $state['head_record_hash']);
                self::assertSame($beforeState['last_assigned_recorded_at'], $state['last_assigned_recorded_at']);
                self::assertSame('3', $state['state_version']);

                $closed = $this->realSegment($probe, $schema, $partition, $firstValue['segmentId']);
                self::assertSame('closed', $closed['state']);
                self::assertSame('1', $closed['first_sequence']);
                self::assertSame('2', $closed['final_sequence']);
                self::assertSame($secondValue['recordHash'], $closed['head_record_hash']);
                self::assertSame($beforeSegment['predecessor_record_hash'], $closed['predecessor_record_hash']);
                self::assertSame('2026-08-30T00:00:05.123456Z', $closed['closed_at']);
                self::assertSame($result->successorSegmentId(), $closed['successor_segment_id']);
                self::assertSame($result->closeFencingToken(), $closed['close_fencing_token']);
                self::assertSame('3', $closed['state_version']);

                $successor = $this->realSegment($probe, $schema, $partition, $result->successorSegmentId());
                self::assertSame('open', $successor['state']);
                self::assertSame('3', $successor['first_sequence']);
                self::assertNull($successor['final_sequence']);
                self::assertNull($successor['head_record_hash']);
                self::assertSame($secondValue['recordHash'], $successor['predecessor_record_hash']);
                self::assertSame('2026-08-30T00:00:05.123456Z', $successor['opened_at']);
                self::assertNull($successor['closed_at']);
                self::assertSame('1', $successor['state_version']);

                $retryClock = new FixtureClock(static function (string $event) use (&$retryClockCalls): void {
                    if ($event === 'clock') {
                        $retryClockCalls++;
                    }
                });
                $retryUuids = new SequenceUuidGenerator([]);
                $retry = $this->store($primary, $retryClock, $retryUuids, schema: $schema)->closeSegment(
                    $partition,
                    $firstValue['segmentId'],
                );
                self::assertFalse($retry->changed());
                self::assertSame($result->partitionId(), $retry->partitionId());
                self::assertSame($result->closedSegmentId(), $retry->closedSegmentId());
                self::assertSame($result->successorSegmentId(), $retry->successorSegmentId());
                self::assertSame($result->firstSequence(), $retry->firstSequence());
                self::assertSame($result->finalSequence(), $retry->finalSequence());
                self::assertSame($result->predecessorRecordHash(), $retry->predecessorRecordHash());
                self::assertSame($result->headHash(), $retry->headHash());
                self::assertSame($result->closeFencingToken(), $retry->closeFencingToken());
                self::assertSame($result->closedAt(), $retry->closedAt());
                self::assertSame(0, $retryClockCalls);
                self::assertSame(0, $retryUuids->calls);
            }

            self::assertSame(8, $uuids->calls);
            self::assertSame(6, $clockCalls);
        } finally {
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
        }
    }

    public function testRealPostgreSqlCloseCallerTransactionRollbackRetryAndSuccessorClamp(): void
    {
        $admin = $primary = $probe = null;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            $primary->executeStatement("SET TIME ZONE 'America/Los_Angeles'");
            $probe->executeStatement("SET TIME ZONE 'Pacific/Kiritimati'");
            $clock = new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z'));
            $uuids = new SequenceUuidGenerator([
                '01941f29-7c00-7000-8000-000000000291',
                '01941f29-7c00-7000-8000-000000000292',
                '01941f29-7c00-7000-8000-000000000293',
                '01941f29-7c00-7000-8000-000000000294',
                '01941f29-7c00-7000-8000-000000000295',
                '01941f29-7c00-7000-8000-000000000296',
                '01941f29-7c00-7000-8000-000000000297',
            ]);
            $store = $this->store($primary, $clock, $uuids, schema: $schema);
            $first = $store->appendRequired($this->draft());
            $second = $store->appendRequired($this->draft());
            $firstValue = $first->record()->toArray();
            $secondValue = $second->record()->toArray();
            $normalizeRecords = static function (array $records): array {
                foreach ($records as &$record) {
                    $record['protected_body'] = PostgreSqlBytea::string($record['protected_body']);
                }

                return $records;
            };
            $beforeRecords = $normalizeRecords($this->realExact17Records($probe, $schema, 'system:global'));
            $beforeState = $this->realState($probe, $schema, 'system:global');
            $beforeSegment = $this->realSegment($probe, $schema, 'system:global', $firstValue['segmentId']);

            $primary->beginTransaction();
            $rolledBack = $store->closeSegment('system:global', $firstValue['segmentId']);
            self::assertTrue($rolledBack->changed());
            self::assertSame($firstValue['segmentId'], $rolledBack->closedSegmentId());
            self::assertSame(
                $beforeRecords,
                $normalizeRecords($this->realExact17Records($probe, $schema, 'system:global')),
            );
            self::assertSame($beforeState, $this->realState($probe, $schema, 'system:global'));
            self::assertSame($beforeSegment, $this->realSegment(
                $probe,
                $schema,
                'system:global',
                $firstValue['segmentId'],
            ));
            $primary->rollBack();
            self::assertSame(
                $beforeRecords,
                $normalizeRecords($this->realExact17Records($probe, $schema, 'system:global')),
            );
            self::assertSame($beforeState, $this->realState($probe, $schema, 'system:global'));
            self::assertSame($beforeSegment, $this->realSegment(
                $probe,
                $schema,
                'system:global',
                $firstValue['segmentId'],
            ));

            $primary->beginTransaction();
            $committed = $store->closeSegment('system:global', $firstValue['segmentId']);
            $primary->commit();
            self::assertTrue($committed->changed());
            self::assertNotSame($rolledBack->successorSegmentId(), $committed->successorSegmentId());
            $closed = $this->realSegment($probe, $schema, 'system:global', $firstValue['segmentId']);
            self::assertSame('closed', $closed['state']);
            self::assertSame($committed->successorSegmentId(), $closed['successor_segment_id']);
            self::assertSame('2026-08-30T00:00:05.123456Z', $closed['closed_at']);
            $successor = $this->realSegment($probe, $schema, 'system:global', $committed->successorSegmentId());
            self::assertSame('open', $successor['state']);
            self::assertSame('3', $successor['first_sequence']);
            self::assertSame($secondValue['recordHash'], $successor['predecessor_record_hash']);

            $lateClock = new FixtureClock(null, new \DateTimeImmutable('2026-08-19T00:00:01.000000Z'));
            $lateUuids = new SequenceUuidGenerator([
                '01941f29-7c00-7000-8000-000000000298',
                '01941f29-7c00-7000-8000-000000000299',
            ]);
            $lateStore = $this->store($primary, $lateClock, $lateUuids, schema: $schema);
            $successorRecord = $lateStore->appendRequired($this->draft());
            $successorValue = $successorRecord->record()->toArray();
            self::assertSame(3, $successorValue['sequence']);
            self::assertSame($secondValue['recordHash'], $successorValue['previousRecordHash']);
            self::assertSame($committed->successorSegmentId(), $successorValue['segmentId']);
            $beforeSecondState = $this->realState($probe, $schema, 'system:global');
            $beforeSecondSegment = $this->realSegment(
                $probe,
                $schema,
                'system:global',
                $committed->successorSegmentId(),
            );

            $closedSuccessor = $lateStore->closeSegment('system:global', $committed->successorSegmentId());
            self::assertTrue($closedSuccessor->changed());
            self::assertSame(3, $closedSuccessor->firstSequence());
            self::assertSame(3, $closedSuccessor->finalSequence());
            self::assertSame($secondValue['recordHash'], $closedSuccessor->predecessorRecordHash());
            self::assertSame($successorValue['recordHash'], $closedSuccessor->headHash());
            self::assertSame('2026-08-30T00:00:05.123456Z', $closedSuccessor->closedAt());
            self::assertSame(
                $this->expectedCloseToken(
                    'system:global',
                    $committed->successorSegmentId(),
                    3,
                    $successorValue['recordHash'],
                    $closedSuccessor->successorSegmentId(),
                    $closedSuccessor->closedAt(),
                ),
                $closedSuccessor->closeFencingToken(),
            );
            $closedSuccessorRow = $this->realSegment(
                $probe,
                $schema,
                'system:global',
                $committed->successorSegmentId(),
            );
            self::assertSame('closed', $closedSuccessorRow['state']);
            self::assertSame('3', $closedSuccessorRow['first_sequence']);
            self::assertSame('3', $closedSuccessorRow['final_sequence']);
            self::assertSame($successorValue['recordHash'], $closedSuccessorRow['head_record_hash']);
            self::assertSame($secondValue['recordHash'], $closedSuccessorRow['predecessor_record_hash']);
            self::assertSame('2026-08-30T00:00:05.123456Z', $closedSuccessorRow['closed_at']);
            self::assertSame($closedSuccessor->successorSegmentId(), $closedSuccessorRow['successor_segment_id']);
            self::assertSame($closedSuccessor->closeFencingToken(), $closedSuccessorRow['close_fencing_token']);
            self::assertSame(
                (string) ((int) $beforeSecondSegment['state_version'] + 1),
                $closedSuccessorRow['state_version'],
            );

            $newSuccessorRow = $this->realSegment(
                $probe,
                $schema,
                'system:global',
                $closedSuccessor->successorSegmentId(),
            );
            self::assertSame('open', $newSuccessorRow['state']);
            self::assertSame('4', $newSuccessorRow['first_sequence']);
            self::assertNull($newSuccessorRow['final_sequence']);
            self::assertNull($newSuccessorRow['head_record_hash']);
            self::assertSame($successorValue['recordHash'], $newSuccessorRow['predecessor_record_hash']);
            self::assertSame($closedSuccessor->closedAt(), $newSuccessorRow['opened_at']);
            self::assertNull($newSuccessorRow['closed_at']);
            self::assertSame('1', $newSuccessorRow['state_version']);

            $afterSecondState = $this->realState($probe, $schema, 'system:global');
            self::assertSame($closedSuccessor->successorSegmentId(), $afterSecondState['active_segment_id']);
            self::assertSame('3', $afterSecondState['last_sequence']);
            self::assertSame($successorValue['recordHash'], $afterSecondState['head_record_hash']);
            self::assertSame(
                $beforeSecondState['last_assigned_recorded_at'],
                $afterSecondState['last_assigned_recorded_at'],
            );
            self::assertSame(
                (string) ((int) $beforeSecondState['state_version'] + 1),
                $afterSecondState['state_version'],
            );
        } finally {
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
        }
    }

    public function testRealPostgreSqlCloseCallerOwnedLateFenceAbortsAndRestoresExactly(): void
    {
        $admin = $primary = $probe = null;
        $lateFenceInstalled = false;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            $clock = new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z'));
            $uuids = new SequenceUuidGenerator([
                '01941f29-7c00-7000-8000-000000000301',
                '01941f29-7c00-7000-8000-000000000302',
                '01941f29-7c00-7000-8000-000000000303',
                '01941f29-7c00-7000-8000-000000000304',
                '01941f29-7c00-7000-8000-000000000305',
            ]);
            $store = $this->store($primary, $clock, $uuids, schema: $schema);
            $first = $store->appendRequired($this->draft());
            $firstValue = $first->record()->toArray();
            $normalizeRecords = static function (array $records): array {
                foreach ($records as &$record) {
                    $record['protected_body'] = PostgreSqlBytea::string($record['protected_body']);
                }

                return $records;
            };
            $beforeRecords = $normalizeRecords($this->realExact17Records($probe, $schema, 'system:global'));
            $beforeState = $this->realState($probe, $schema, 'system:global');
            $beforeSegment = $this->realSegment($probe, $schema, 'system:global', $firstValue['segmentId']);

            $this->installLateFence($admin, $schema);
            $lateFenceInstalled = true;
            $primary->beginTransaction();
            try {
                $store->closeSegment('system:global', $firstValue['segmentId']);
                self::fail('caller-owned close late fence was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
                self::assertSame('Audit store operation failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
            self::assertTrue($primary->isTransactionActive());
            self::assertSame(1, $primary->getTransactionNestingLevel());
            $aborted = false;
            try {
                $primary->fetchOne('SELECT 1');
            } catch (\Throwable) {
                $aborted = true;
            }
            self::assertTrue($aborted, 'caller-owned close transaction was not aborted by the late fence');
            self::assertTrue($primary->isTransactionActive());
            try {
                $primary->commit();
            } catch (\Throwable) {
                if ($primary->isTransactionActive()) {
                    $primary->rollBack();
                }
            }
            if ($primary->isTransactionActive()) {
                $primary->rollBack();
            }
            self::assertFalse($primary->isTransactionActive());
            self::assertSame(0, $primary->getTransactionNestingLevel());
            $this->dropLateFence($admin, $schema);
            $lateFenceInstalled = false;

            self::assertSame(
                $beforeRecords,
                $normalizeRecords($this->realExact17Records($probe, $schema, 'system:global')),
            );
            self::assertSame($beforeState, $this->realState($probe, $schema, 'system:global'));
            self::assertSame($beforeSegment, $this->realSegment(
                $probe,
                $schema,
                'system:global',
                $firstValue['segmentId'],
            ));
            self::assertSame(1, $this->realSegmentCount($probe, $schema, 'system:global'));
            self::assertSame(0, $this->realSegmentIdCount(
                $probe,
                $schema,
                'system:global',
                '01941f29-7c00-7000-8000-000000000303',
            ));

            $retry = $this->store(
                $primary,
                new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                new SequenceUuidGenerator([
                    '01941f29-7c00-7000-8000-000000000306',
                ]),
                schema: $schema,
            )->closeSegment('system:global', $firstValue['segmentId']);
            self::assertTrue($retry->changed());
            self::assertSame($firstValue['segmentId'], $retry->closedSegmentId());
            self::assertSame(
                'closed',
                $this->realSegment($probe, $schema, 'system:global', $firstValue['segmentId'])['state'],
            );
            self::assertSame(2, $this->realSegmentCount($probe, $schema, 'system:global'));
            self::assertSame(1, $this->realSegmentIdCount(
                $probe,
                $schema,
                'system:global',
                $retry->successorSegmentId(),
            ));
        } finally {
            if ($lateFenceInstalled && $admin instanceof Connection) {
                $this->dropLateFence($admin, $schema);
            }
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
        }
    }

    public function testRealPostgreSqlCloseOwnedDeferredSuccessorFailureRestoresAndReconnects(): void
    {
        $admin = $primary = $probe = null;
        $deferredFailureInstalled = false;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            $clock = new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z'));
            $uuids = new SequenceUuidGenerator([
                '01941f29-7c00-7000-8000-000000000311',
                '01941f29-7c00-7000-8000-000000000312',
                '01941f29-7c00-7000-8000-000000000313',
                '01941f29-7c00-7000-8000-000000000314',
            ]);
            $store = $this->store($primary, $clock, $uuids, schema: $schema);
            $first = $store->appendRequired($this->draft());
            $firstValue = $first->record()->toArray();
            $normalizeRecords = static function (array $records): array {
                foreach ($records as &$record) {
                    $record['protected_body'] = PostgreSqlBytea::string($record['protected_body']);
                }

                return $records;
            };
            $beforeRecords = $normalizeRecords($this->realExact17Records($probe, $schema, 'system:global'));
            $beforeState = $this->realState($probe, $schema, 'system:global');
            $beforeSegment = $this->realSegment($probe, $schema, 'system:global', $firstValue['segmentId']);

            $this->installDeferredSegmentFailure($admin, $schema);
            $deferredFailureInstalled = true;
            try {
                $store->closeSegment('system:global', $firstValue['segmentId']);
                self::fail('deferred close successor failure was not raised');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
                self::assertSame('Audit store operation failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
                $this->assertNoCanaryOnExceptionSurfaces($exception, self::DEFERRED_SEGMENT_FAILURE_CANARY);
            }
            self::assertFalse($primary->isConnected());
            self::assertFalse($primary->isTransactionActive());
            self::assertSame(0, $primary->getTransactionNestingLevel());
            self::assertSame(
                $beforeRecords,
                $normalizeRecords($this->realExact17Records($probe, $schema, 'system:global')),
            );
            self::assertSame($beforeState, $this->realState($probe, $schema, 'system:global'));
            self::assertSame($beforeSegment, $this->realSegment(
                $probe,
                $schema,
                'system:global',
                $firstValue['segmentId'],
            ));
            self::assertSame(1, $this->realSegmentCount($probe, $schema, 'system:global'));
            self::assertSame(0, $this->realSegmentIdCount(
                $probe,
                $schema,
                'system:global',
                '01941f29-7c00-7000-8000-000000000313',
            ));

            $this->dropDeferredSegmentFailure($admin, $schema);
            $deferredFailureInstalled = false;
            $retry = $store->closeSegment('system:global', $firstValue['segmentId']);
            self::assertTrue($retry->changed());
            self::assertSame($firstValue['segmentId'], $retry->closedSegmentId());
            self::assertSame(
                'closed',
                $this->realSegment($probe, $schema, 'system:global', $firstValue['segmentId'])['state'],
            );
            self::assertSame(
                'open',
                $this->realSegment($probe, $schema, 'system:global', $retry->successorSegmentId())['state'],
            );
            self::assertSame(2, $this->realSegmentCount($probe, $schema, 'system:global'));
            self::assertSame(1, $this->realSegmentIdCount(
                $probe,
                $schema,
                'system:global',
                $retry->successorSegmentId(),
            ));
        } finally {
            if ($deferredFailureInstalled && $admin instanceof Connection) {
                $this->dropDeferredSegmentFailure($admin, $schema);
            }
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
        }
    }

    public function testRealPostgreSqlUniqueViolationMapsSafelyAndReconnects(): void
    {
        $admin = $primary = $probe = $failureProbe = null;
        $uniqueFailureInstalled = false;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            $clock = new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z'));
            $uuids = new SequenceUuidGenerator([
                '01941f29-7c00-7000-8000-000000000371',
                '01941f29-7c00-7000-8000-000000000372',
                '01941f29-7c00-7000-8000-000000000373',
                '01941f29-7c00-7000-8000-000000000374',
            ]);
            $store = $this->store($primary, $clock, $uuids, schema: $schema);
            $first = $store->appendRequired($this->draft());
            $firstValue = $first->record()->toArray();
            $normalizeRecords = static function (array $records): array {
                foreach ($records as &$record) {
                    $record['protected_body'] = PostgreSqlBytea::string($record['protected_body']);
                }

                return $records;
            };
            $beforeRecords = $normalizeRecords($this->realExact17Records($probe, $schema, 'system:global'));
            $beforeState = $this->realState($probe, $schema, 'system:global');
            $beforeSegment = $this->realSegment($probe, $schema, 'system:global', $firstValue['segmentId']);

            $this->installDeferredUniqueFailure($admin, $schema);
            $uniqueFailureInstalled = true;

            $failureProbe = $this->realConnection();
            $tenant = new AuditTypedReference('account', 'unique-violation-tenant');
            $tenantPartition = $this->partition($tenant);
            $failureProbe->beginTransaction();
            $failureProbe->executeStatement(
                'INSERT INTO '
                . $schema->segmentsTable()
                . ' (partition_id, segment_id, state, first_sequence, opened_at, state_version) '
                . 'VALUES (:partition_id, :segment_id, \'open\', 1, '
                . '\'2026-08-30T00:00:00.000000Z\', 1)',
                [
                    'partition_id' => $tenantPartition,
                    'segment_id' => '01941f29-7c00-7000-8000-000000000379',
                ],
                [
                    'partition_id' => ParameterType::STRING,
                    'segment_id' => ParameterType::STRING,
                ],
            );
            $sqlFailure = null;
            try {
                $failureProbe->commit();
            } catch (\Throwable $failure) {
                $sqlFailure = $failure;
            }
            self::assertInstanceOf(\Throwable::class, $sqlFailure);
            self::assertContains('23505', $this->realThrowableCodes($sqlFailure));
            $failureProbe->close();
            $failureProbe = null;
            self::assertSame(0, $this->realSegmentIdCount(
                $probe,
                $schema,
                $tenantPartition,
                '01941f29-7c00-7000-8000-000000000379',
            ));

            try {
                $store->closeSegment('system:global', $firstValue['segmentId']);
                self::fail('unique violation was not mapped to storage_unavailable');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
                self::assertSame('Audit store operation failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
                $this->assertNoCanaryOnExceptionSurfaces($exception, self::DEFERRED_UNIQUE_FAILURE_CANARY);
            }
            self::assertFalse($primary->isConnected());
            self::assertFalse($primary->isTransactionActive());
            self::assertSame(0, $primary->getTransactionNestingLevel());
            self::assertSame(
                $beforeRecords,
                $normalizeRecords($this->realExact17Records($probe, $schema, 'system:global')),
            );
            self::assertSame($beforeState, $this->realState($probe, $schema, 'system:global'));
            self::assertSame($beforeSegment, $this->realSegment(
                $probe,
                $schema,
                'system:global',
                $firstValue['segmentId'],
            ));
            self::assertSame(1, $this->realSegmentCount($probe, $schema, 'system:global'));
            self::assertSame(0, $this->realSegmentIdCount(
                $probe,
                $schema,
                'system:global',
                '01941f29-7c00-7000-8000-000000000373',
            ));

            $this->dropDeferredUniqueFailure($admin, $schema);
            $uniqueFailureInstalled = false;
            $retry = $store->closeSegment('system:global', $firstValue['segmentId']);
            self::assertTrue($retry->changed());
            self::assertSame('01941f29-7c00-7000-8000-000000000374', $retry->successorSegmentId());
            self::assertTrue($primary->isConnected());
            self::assertSame(
                'closed',
                $this->realSegment($probe, $schema, 'system:global', $firstValue['segmentId'])['state'],
            );
            self::assertSame(
                'open',
                $this->realSegment($probe, $schema, 'system:global', $retry->successorSegmentId())['state'],
            );
            self::assertSame(2, $this->realSegmentCount($probe, $schema, 'system:global'));
            self::assertSame(4, $uuids->calls);
        } finally {
            if ($failureProbe instanceof Connection) {
                try {
                    $failureProbe->close();
                } catch (\Throwable) {
                }
            }
            if ($uniqueFailureInstalled && $admin instanceof Connection) {
                $this->dropDeferredUniqueFailure($admin, $schema);
            }
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
        }
    }

    public function testRealPostgreSqlNaturalSegmentUniqueRaceMapsSafelyAndRetriesWithNewUuid(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the natural unique-race fixture.');
        }

        $admin = $parent = $probe = null;
        $pid = null;
        $status = 0;
        $childFinished = false;
        $ready = tempnam(sys_get_temp_dir(), 'audit-natural-unique-ready-');
        $resultFile = tempnam(sys_get_temp_dir(), 'audit-natural-unique-result-');
        $parentReady = tempnam(sys_get_temp_dir(), 'audit-natural-unique-parent-');
        self::assertIsString($ready);
        self::assertIsString($resultFile);
        self::assertIsString($parentReady);
        $this->unlinkRealTemporary($ready);
        $this->unlinkRealTemporary($resultFile);
        $this->unlinkRealTemporary($parentReady);
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $sharedSegmentId = '01941f29-7c00-7000-8000-000000000381';
        $parentRecordId = '01941f29-7c00-7000-8000-000000000382';
        $childRecordId = '01941f29-7c00-7000-8000-000000000383';
        $retrySegmentId = '01941f29-7c00-7000-8000-000000000384';
        $retryRecordId = '01941f29-7c00-7000-8000-000000000385';
        $parentTenant = new AuditTypedReference('account', 'natural-unique-parent');
        $childTenant = new AuditTypedReference('account', 'natural-unique-child');
        $childPartition = $this->partition($childTenant);

        try {
            $admin = $this->realConnection();
            $parent = $this->realConnection();
            $probe = $this->realConnection();
            $this->createRealAuditSchema($admin, $schema);
            foreach ([$admin, $parent, $probe] as $connection) {
                $connection->close();
            }
            $admin = $parent = $probe = null;

            $pid = pcntl_fork();
            if ($pid === -1) {
                self::fail('Unable to fork the natural unique-race fixture.');
            }
            if ($pid === 0) {
                $child = null;
                $payload = ['ok' => false];
                $childStore = null;
                try {
                    $child = $this->observedRealConnection();
                    file_put_contents($ready, (string) $child->fetchOne('SELECT pg_backend_pid()'));
                    $deadline = microtime(true) + 5.0;
                    while (!is_file($parentReady) && microtime(true) < $deadline) {
                        usleep(10_000);
                    }
                    if (!is_file($parentReady)) {
                        throw new \RuntimeException('natural unique-race parent signal timed out');
                    }
                    $clock = new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z'));
                    $uuids = new SequenceUuidGenerator([
                        $sharedSegmentId,
                        $childRecordId,
                        $retrySegmentId,
                        $retryRecordId,
                    ]);
                    $childStore = $this->store($child, $clock, $uuids, schema: $schema);
                    try {
                        $childStore->appendRequired($this->draftForTenant($childTenant));
                        throw new \RuntimeException('natural unique race unexpectedly committed');
                    } catch (AuditStoreException $exception) {
                        $payload['failureCode'] = $exception->code();
                        $payload['failureCodes'] = $child->failureCodes();
                        $payload['failureStages'] = $child->failureStages();
                        $payload['connectedAfterFailure'] = $child->isConnected();
                        $payload['activeAfterFailure'] = $child->isTransactionActive();
                        $payload['levelAfterFailure'] = $child->getTransactionNestingLevel();
                        $counts = $this->realPartitionCounts($child, $schema, $childPartition);
                        $payload['countsAfterFailure'] = $counts;
                    }
                    $retry = $childStore->appendRequired($this->draftForTenant($childTenant));
                    $retryValue = $retry->record()->toArray();
                    $payload['retry'] = [
                        'recordId' => $retryValue['recordId'],
                        'segmentId' => $retryValue['segmentId'],
                        'sequence' => $retryValue['sequence'],
                        'previousRecordHash' => $retryValue['previousRecordHash'],
                    ];
                    $payload['connectedAfterRetry'] = $child->isConnected();
                    $payload['ok'] = true;
                } catch (\Throwable $failure) {
                    $payload['error'] = get_class($failure);
                    if ($child instanceof ObservedFailureConnection) {
                        $payload['failureCodes'] = $child->failureCodes();
                        $payload['failureStages'] = $child->failureStages();
                    }
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
                $exitCode = $payload['ok'] ?? false ? 0 : 1;
                exit($exitCode);
            }

            $admin = $this->realConnection();
            $parent = $this->observedRealConnection();
            $probe = $this->realConnection();
            $parent->beginTransaction();
            $parentUuids = new SequenceUuidGenerator([$sharedSegmentId, $parentRecordId]);
            $parentStore = $this->store(
                $parent,
                new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                $parentUuids,
                schema: $schema,
            );
            $first = $parentStore->appendRequired($this->draftForTenant($parentTenant));
            $firstValue = $first->record()->toArray();
            self::assertTrue($parent->isTransactionActive());
            self::assertSame(1, $parent->getTransactionNestingLevel());
            file_put_contents($parentReady, 'ready');
            $wait = $this->waitForRealDatabaseLock($probe, $ready, $resultFile);
            self::assertSame('Lock', $wait['wait_event_type']);
            self::assertNotSame('', (string) $wait['wait_event']);
            self::assertStringContainsString('WITH new_segment', (string) $wait['query']);
            $parent->commit();
            self::assertTrue($this->waitForRealChild($pid, $status));
            $childFinished = true;
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
            $childResult = json_decode((string) file_get_contents($resultFile), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($childResult);
            self::assertTrue((bool) ($childResult['ok'] ?? false));
            self::assertSame('storage_unavailable', $childResult['failureCode']);
            self::assertContains('23505', $childResult['failureCodes']);
            self::assertContains('write', $childResult['failureStages']);
            self::assertTrue((bool) $childResult['connectedAfterFailure']);
            self::assertFalse((bool) $childResult['activeAfterFailure']);
            self::assertSame(0, $childResult['levelAfterFailure']);
            self::assertSame(['state' => 0, 'segments' => 0, 'records' => 0], $childResult['countsAfterFailure']);
            self::assertTrue((bool) $childResult['connectedAfterRetry']);
            self::assertSame(
                [
                    'recordId' => $retryRecordId,
                    'segmentId' => $retrySegmentId,
                    'sequence' => 1,
                    'previousRecordHash' => null,
                ],
                $childResult['retry'],
            );

            $parentPartition = $this->partition($parentTenant);
            $parentRecords = $this->realRecords($probe, $schema, $parentPartition);
            self::assertCount(1, $parentRecords);
            self::assertSame($parentRecordId, $parentRecords[0]['record_id']);
            self::assertSame('1', $parentRecords[0]['sequence']);
            self::assertSame($sharedSegmentId, $parentRecords[0]['segment_id']);
            $childRecords = $this->realRecords($probe, $schema, $childPartition);
            self::assertCount(1, $childRecords);
            self::assertSame($retryRecordId, $childRecords[0]['record_id']);
            self::assertSame('1', $childRecords[0]['sequence']);
            self::assertSame($retrySegmentId, $childRecords[0]['segment_id']);
            self::assertSame(1, $this->realSegmentIdCount($probe, $schema, $parentPartition, $sharedSegmentId));
            self::assertSame(0, $this->realSegmentIdCount($probe, $schema, $childPartition, $sharedSegmentId));
            self::assertSame(1, $this->realSegmentIdCount($probe, $schema, $childPartition, $retrySegmentId));
            self::assertSame('1', $this->realState($probe, $schema, $parentPartition)['last_sequence']);
            self::assertSame('1', $this->realState($probe, $schema, $childPartition)['last_sequence']);
        } finally {
            if (is_int($pid) && !$childFinished) {
                $this->terminateRealChild($pid, $status);
            }
            $this->cleanupRealAuditSchema($admin, $parent, $probe, $schema);
            $this->unlinkRealTemporary($ready);
            $this->unlinkRealTemporary($resultFile);
            $this->unlinkRealTemporary($parentReady);
        }
    }

    public function testRealPostgreSqlNaturalAdvisoryDeadlockMapsSafelyAndRetriesWithNewUuid(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the natural deadlock fixture.');
        }

        $admin = $parent = $probe = null;
        $pid = null;
        $status = 0;
        $childFinished = false;
        $ready = tempnam(sys_get_temp_dir(), 'audit-natural-deadlock-ready-');
        $resultFile = tempnam(sys_get_temp_dir(), 'audit-natural-deadlock-result-');
        $parentReady = tempnam(sys_get_temp_dir(), 'audit-natural-deadlock-parent-');
        self::assertIsString($ready);
        self::assertIsString($resultFile);
        self::assertIsString($parentReady);
        $this->unlinkRealTemporary($ready);
        $this->unlinkRealTemporary($resultFile);
        $this->unlinkRealTemporary($parentReady);
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $parentSegmentId = '01941f29-7c00-7000-8000-000000000391';
        $parentRecordId = '01941f29-7c00-7000-8000-000000000392';
        $childSegmentId = '01941f29-7c00-7000-8000-000000000393';
        $childRecordId = '01941f29-7c00-7000-8000-000000000394';
        $parentRetrySegmentId = '01941f29-7c00-7000-8000-000000000395';
        $parentRetryRecordId = '01941f29-7c00-7000-8000-000000000396';
        $childRetrySegmentId = '01941f29-7c00-7000-8000-000000000397';
        $childRetryRecordId = '01941f29-7c00-7000-8000-000000000398';
        $parentTenant = new AuditTypedReference('account', 'natural-deadlock-parent');
        $childTenant = new AuditTypedReference('account', 'natural-deadlock-child');
        $parentPartition = $this->partition($parentTenant);
        $childPartition = $this->partition($childTenant);

        try {
            [$admin, $parent, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            foreach ([$admin, $parent, $probe] as $connection) {
                $connection->close();
            }
            $admin = $parent = $probe = null;

            $pid = pcntl_fork();
            if ($pid === -1) {
                self::fail('Unable to fork the natural deadlock fixture.');
            }
            if ($pid === 0) {
                $child = null;
                $payload = [
                    'ok' => false,
                    'failureCodes' => [],
                    'failureStages' => [],
                ];
                try {
                    $child = $this->observedRealConnection();
                    $child->beginTransaction();
                    $this->lockRealRecord($child, $schema, $parentRecordId);
                    file_put_contents($ready, (string) $child->fetchOne('SELECT pg_backend_pid()'));
                    $deadline = microtime(true) + 5.0;
                    while (!is_file($parentReady) && microtime(true) < $deadline) {
                        usleep(10_000);
                    }
                    if (!is_file($parentReady)) {
                        throw new \RuntimeException('natural deadlock parent signal timed out');
                    }
                    $uuids = new SequenceUuidGenerator([
                        $childSegmentId,
                        $childRecordId,
                        $childRetrySegmentId,
                        $childRetryRecordId,
                    ]);
                    $store = $this->store(
                        $child,
                        new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                        $uuids,
                        schema: $schema,
                    );
                    try {
                        $initialRecord = $store
                            ->appendRequired($this->draftForTenant($childTenant))
                            ->record()
                            ->toArray();
                        $initial = 'success';
                    } catch (AuditStoreException $exception) {
                        $initial = 'failure';
                        $payload['failureCode'] = $exception->code();
                        $payload['failureCodes'] = $child->failureCodes();
                        $payload['failureStages'] = $child->failureStages();
                        if ($child->isTransactionActive()) {
                            $child->rollBack();
                        }
                        $counts = $this->realPartitionCounts($child, $schema, $childPartition);
                        $payload['countsAfterFailure'] = $counts;
                        $retry = $store->appendRequired($this->draftForTenant($childTenant));
                        $retryValue = $retry->record()->toArray();
                        $payload['retry'] = [
                            'recordId' => $retryValue['recordId'],
                            'segmentId' => $retryValue['segmentId'],
                            'sequence' => $retryValue['sequence'],
                            'previousRecordHash' => $retryValue['previousRecordHash'],
                        ];
                    }
                    if ($initial === 'success') {
                        if ($child->isTransactionActive()) {
                            $child->commit();
                        }
                        $payload['success'] = true;
                        $payload['initialRecord'] = [
                            'recordId' => $initialRecord['recordId'],
                            'segmentId' => $initialRecord['segmentId'],
                            'sequence' => $initialRecord['sequence'],
                            'previousRecordHash' => $initialRecord['previousRecordHash'],
                        ];
                    } else {
                        $payload['success'] = false;
                    }
                    $payload['connectedAfterOperation'] = $child->isConnected();
                    $payload['ok'] = true;
                } catch (\Throwable $failure) {
                    $payload['error'] = get_class($failure);
                    if ($child instanceof ObservedFailureConnection) {
                        $payload['failureCodes'] = $child->failureCodes();
                        $payload['failureStages'] = $child->failureStages();
                    }
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
                $exitCode = $payload['ok'] ?? false ? 0 : 1;
                exit($exitCode);
            }

            $admin = $this->realConnection();
            $parent = $this->observedRealConnection();
            $probe = $this->realConnection();
            $parent->beginTransaction();
            $this->lockRealRecord($parent, $schema, $childRecordId);
            file_put_contents($parentReady, 'ready');
            $wait = $this->waitForRealDatabaseLock($probe, $ready, $resultFile);
            self::assertSame('Lock', $wait['wait_event_type']);
            self::assertSame('advisory', $wait['wait_event']);
            self::assertStringContainsString('hashtextextended', (string) $wait['query']);
            $uuids = new SequenceUuidGenerator([
                $parentSegmentId,
                $parentRecordId,
                $parentRetrySegmentId,
                $parentRetryRecordId,
            ]);
            $store = $this->store(
                $parent,
                new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                $uuids,
                schema: $schema,
            );
            $parentInitialRecord = null;
            $parentFailureCode = null;
            $parentFailureCodes = [];
            $parentFailureStages = [];
            $parentCountsAfterFailure = null;
            $parentRetryResult = null;
            try {
                $initialRecord = $store->appendRequired($this->draftForTenant($parentTenant))->record()->toArray();
                $parentInitialRecord = [
                    'recordId' => $initialRecord['recordId'],
                    'segmentId' => $initialRecord['segmentId'],
                    'sequence' => $initialRecord['sequence'],
                    'previousRecordHash' => $initialRecord['previousRecordHash'],
                ];
                $parentInitial = 'success';
            } catch (AuditStoreException $exception) {
                $parentInitial = 'failure';
                $parentFailureCode = $exception->code();
                $parentFailureCodes = $parent instanceof ObservedFailureConnection ? $parent->failureCodes() : [];
                $parentFailureStages = $parent instanceof ObservedFailureConnection ? $parent->failureStages() : [];
                if ($parent->isTransactionActive()) {
                    $parent->rollBack();
                }
                $parentCountsAfterFailure = $this->realPartitionCounts($parent, $schema, $parentPartition);
                $parentRetry = $store->appendRequired($this->draftForTenant($parentTenant));
                $parentRetryValue = $parentRetry->record()->toArray();
                $parentRetryResult = [
                    'recordId' => $parentRetryValue['recordId'],
                    'segmentId' => $parentRetryValue['segmentId'],
                    'sequence' => $parentRetryValue['sequence'],
                    'previousRecordHash' => $parentRetryValue['previousRecordHash'],
                ];
            }
            if ($parentInitial === 'success') {
                $parent->commit();
            }
            self::assertTrue($this->waitForRealChild($pid, $status));
            $childFinished = true;
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
            $childResult = json_decode((string) file_get_contents($resultFile), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($childResult);
            self::assertTrue((bool) ($childResult['ok'] ?? false));
            self::assertIsBool($childResult['success'] ?? null);
            self::assertIsArray($childResult['failureCodes'] ?? null);
            self::assertIsArray($childResult['failureStages'] ?? null);
            $parentFailed = $parentInitial === 'failure';
            $childFailed = !$childResult['success'];
            self::assertNotSame($parentFailed, $childFailed);
            $failureCodes = array_merge($parentFailureCodes, $childResult['failureCodes']);
            $failureStages = array_merge($parentFailureStages, $childResult['failureStages']);
            $failureCode = $parentFailed ? $parentFailureCode : $childResult['failureCode'] ?? null;
            self::assertSame('storage_unavailable', $failureCode);
            self::assertSame(1, count(array_filter($failureCodes, static fn(string $code): bool => $code === '40P01')));
            self::assertSame(
                1,
                count(array_filter($failureStages, static fn(string $stage): bool => $stage === 'lock')),
            );

            if ($parentFailed) {
                self::assertSame(['state' => 0, 'segments' => 0, 'records' => 0], $parentCountsAfterFailure);
                self::assertTrue($childResult['success']);
                self::assertSame([], $childResult['failureCodes']);
                self::assertSame([], $childResult['failureStages']);
                self::assertSame(
                    [
                        'recordId' => $childRecordId,
                        'segmentId' => $childSegmentId,
                        'sequence' => 1,
                        'previousRecordHash' => null,
                    ],
                    $childResult['initialRecord'],
                );
                self::assertArrayNotHasKey('retry', $childResult);
                self::assertSame(
                    [
                        'recordId' => $parentRetryRecordId,
                        'segmentId' => $parentRetrySegmentId,
                        'sequence' => 1,
                        'previousRecordHash' => null,
                    ],
                    $parentRetryResult,
                );
            } else {
                self::assertSame([], $parentFailureCodes);
                self::assertSame([], $parentFailureStages);
                self::assertSame(
                    [
                        'recordId' => $parentRecordId,
                        'segmentId' => $parentSegmentId,
                        'sequence' => 1,
                        'previousRecordHash' => null,
                    ],
                    $parentInitialRecord,
                );
                self::assertNull($parentRetryResult);
                self::assertFalse($childResult['success']);
                self::assertSame(['state' => 0, 'segments' => 0, 'records' => 0], $childResult['countsAfterFailure']);
                self::assertSame(
                    [
                        'recordId' => $childRetryRecordId,
                        'segmentId' => $childRetrySegmentId,
                        'sequence' => 1,
                        'previousRecordHash' => null,
                    ],
                    $childResult['retry'],
                );
            }
            self::assertTrue((bool) ($childResult['connectedAfterOperation'] ?? false));

            $parentRecords = $this->realRecords($probe, $schema, $parentPartition);
            $childRecords = $this->realRecords($probe, $schema, $childPartition);
            self::assertCount(1, $parentRecords);
            self::assertCount(1, $childRecords);
            self::assertSame('1', $parentRecords[0]['sequence']);
            self::assertNull($parentRecords[0]['previous_record_hash']);
            self::assertSame('1', $childRecords[0]['sequence']);
            self::assertNull($childRecords[0]['previous_record_hash']);
            self::assertSame($parentFailed ? $parentRetryRecordId : $parentRecordId, $parentRecords[0]['record_id']);
            self::assertSame(
                $childResult['success'] ? $childRecordId : $childRetryRecordId,
                $childRecords[0]['record_id'],
            );
            self::assertSame('1', $this->realState($probe, $schema, $parentPartition)['last_sequence']);
            self::assertSame('1', $this->realState($probe, $schema, $childPartition)['last_sequence']);
        } finally {
            if (is_int($pid) && !$childFinished) {
                $this->terminateRealChild($pid, $status);
            }
            $this->cleanupRealAuditSchema($admin, $parent, $probe, $schema);
            $this->unlinkRealTemporary($ready);
            $this->unlinkRealTemporary($resultFile);
            $this->unlinkRealTemporary($parentReady);
        }
    }

    public function testRealPostgreSqlAutoCommitFalseRejectsEveryOperationAtLevelZeroAndLevelOne(): void
    {
        $admin = $probe = null;
        $connection = null;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $tenant = new AuditTypedReference('account', 'transaction-matrix-tenant');
        $partition = $this->partition($tenant);
        $recordId = '01941f29-7c00-7000-8000-000000000411';

        try {
            $admin = $this->realConnection();
            $connection = $this->observedOperationConnection();
            $probe = $this->realConnection();
            $this->createRealAuditSchema($admin, $schema);
            $resourceUses = [
                'clock' => 0,
                'segment-uuid' => 0,
                'record-uuid' => 0,
                'key' => 0,
                'decrypt' => 0,
            ];
            $failIfUsed = static function (string $resource) use (&$resourceUses): never {
                $resourceUses[$resource]++;
                throw new \LogicException('early-rejection-resource-' . $resource);
            };
            $store = $this->store(
                $connection,
                new FixtureClock($failIfUsed),
                new SequenceUuidGenerator([], $failIfUsed),
                keyFailure: 'early-rejection-key-source',
                onUse: $failIfUsed,
                schema: $schema,
                onDecrypt: $failIfUsed,
            );
            $logical = $this->durableLogical('durableSystemIngress');
            $operations = [
                'appendRequired' => fn() => $store->appendRequired($this->draftForTenant($tenant)),
                'appendDurable' => fn() => $store->appendDurable($logical),
                'find' => fn() => $store->find($recordId, $tenant, $partition),
                'closeSegment' => fn() => $store->closeSegment($partition, '01941f29-7c00-7000-8000-000000000412'),
            ];

            $connection->setAutoCommit(false);
            self::assertFalse($connection->isConnected());
            self::assertFalse($connection->isAutoCommit());
            self::assertFalse($connection->isTransactionActive());
            self::assertSame(0, $connection->getTransactionNestingLevel());
            foreach ($operations as $name => $operation) {
                try {
                    $operation();
                    self::fail($name . ' accepted a disconnected autoCommit=false connection');
                } catch (AuditStoreException $exception) {
                    self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure(), $name);
                }
                self::assertFalse($connection->isConnected(), $name);
                self::assertFalse($connection->isAutoCommit(), $name);
                self::assertFalse($connection->isTransactionActive(), $name);
                self::assertSame(0, $connection->getTransactionNestingLevel(), $name);
                self::assertSame(
                    [
                        'clock' => 0,
                        'segment-uuid' => 0,
                        'record-uuid' => 0,
                        'key' => 0,
                        'decrypt' => 0,
                    ],
                    $resourceUses,
                    $name . ' disconnected resource use',
                );
            }
            self::assertSame(0, $connection->queryCount());
            self::assertSame(['setAutoCommit(false)'], $connection->controlCalls());
            self::assertSame([false], $connection->autoCommitCalls());

            $connection->fetchOne('SELECT 1');
            self::assertTrue($connection->isConnected());
            self::assertFalse($connection->isAutoCommit());
            self::assertTrue($connection->isTransactionActive());
            self::assertSame(1, $connection->getTransactionNestingLevel());
            $beforeCalls = $connection->queryCount();
            $beforeControls = $connection->controlCalls();
            $beforeAutoCommitCalls = $connection->autoCommitCalls();
            foreach ($operations as $name => $operation) {
                try {
                    $operation();
                    self::fail($name . ' accepted a connected ambient level-one transaction');
                } catch (AuditStoreException $exception) {
                    self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure(), $name);
                }
                self::assertTrue($connection->isConnected(), $name);
                self::assertFalse($connection->isAutoCommit(), $name);
                self::assertTrue($connection->isTransactionActive(), $name);
                self::assertSame(1, $connection->getTransactionNestingLevel(), $name);
                self::assertSame(
                    [
                        'clock' => 0,
                        'segment-uuid' => 0,
                        'record-uuid' => 0,
                        'key' => 0,
                        'decrypt' => 0,
                    ],
                    $resourceUses,
                    $name . ' connected resource use',
                );
            }
            self::assertSame($beforeCalls, $connection->queryCount());
            self::assertSame($beforeControls, $connection->controlCalls());
            self::assertSame($beforeAutoCommitCalls, $connection->autoCommitCalls());
            self::assertSame(
                ['state' => 0, 'segments' => 0, 'records' => 0],
                $this->realPartitionCounts($probe, $schema, $partition),
            );
            self::assertSame(0, $this->realAdvisoryLockCount($probe, $connection));

            $connection->rollBack();
            $connection->setAutoCommit(true);
            self::assertTrue($connection->isAutoCommit());
            self::assertFalse($connection->isTransactionActive());
            self::assertSame(0, $connection->getTransactionNestingLevel());
        } finally {
            $this->cleanupRealAuditSchema($admin, $connection, $probe, $schema);
        }
    }

    public function testRealPostgreSqlLevelTwoRejectsEveryMutationAndPreservesOuterMarkerUntilRollback(): void
    {
        $admin = $probe = null;
        $connection = null;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $tenant = new AuditTypedReference('account', 'transaction-level-two-tenant');
        $partition = $this->partition($tenant);
        $recordId = '01941f29-7c00-7000-8000-000000000421';

        try {
            $admin = $this->realConnection();
            $connection = $this->observedOperationConnection();
            $probe = $this->realConnection();
            $this->createRealAuditSchema($admin, $schema);
            $connection->executeStatement(
                'CREATE TEMPORARY TABLE audit_store_tx_marker (value text) ON COMMIT PRESERVE ROWS',
            );
            $resourceUses = [
                'clock' => 0,
                'segment-uuid' => 0,
                'record-uuid' => 0,
                'key' => 0,
                'decrypt' => 0,
            ];
            $failIfUsed = static function (string $resource) use (&$resourceUses): never {
                $resourceUses[$resource]++;
                throw new \LogicException('level-two-early-rejection-resource-' . $resource);
            };
            $store = $this->store(
                $connection,
                new FixtureClock($failIfUsed),
                new SequenceUuidGenerator([], $failIfUsed),
                keyFailure: 'level-two-early-rejection-key-source',
                onUse: $failIfUsed,
                schema: $schema,
                onDecrypt: $failIfUsed,
            );
            $logical = $this->durableLogical('durableSystemIngress');
            $operations = [
                'appendRequired' => fn() => $store->appendRequired($this->draftForTenant($tenant)),
                'appendDurable' => fn() => $store->appendDurable($logical),
                'closeSegment' => fn() => $store->closeSegment($partition, '01941f29-7c00-7000-8000-000000000422'),
            ];

            $connection->beginTransaction();
            $connection->executeStatement("INSERT INTO audit_store_tx_marker (value) VALUES ('outer')");
            $connection->beginTransaction();
            self::assertTrue($connection->isConnected());
            self::assertTrue($connection->isAutoCommit());
            self::assertTrue($connection->isTransactionActive());
            self::assertSame(2, $connection->getTransactionNestingLevel());
            $beforeCalls = $connection->queryCount();
            $beforeControls = $connection->controlCalls();
            $beforeAutoCommitCalls = $connection->autoCommitCalls();
            foreach ($operations as $name => $operation) {
                try {
                    $operation();
                    self::fail($name . ' accepted a real level-two savepoint');
                } catch (AuditStoreException $exception) {
                    self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure(), $name);
                }
                self::assertTrue($connection->isConnected(), $name);
                self::assertTrue($connection->isAutoCommit(), $name);
                self::assertTrue($connection->isTransactionActive(), $name);
                self::assertSame(2, $connection->getTransactionNestingLevel(), $name);
                self::assertSame(
                    [
                        'clock' => 0,
                        'segment-uuid' => 0,
                        'record-uuid' => 0,
                        'key' => 0,
                        'decrypt' => 0,
                    ],
                    $resourceUses,
                    $name . ' level-two resource use',
                );
            }
            self::assertSame($beforeCalls, $connection->queryCount());
            self::assertSame($beforeControls, $connection->controlCalls());
            self::assertSame($beforeAutoCommitCalls, $connection->autoCommitCalls());
            self::assertSame(
                ['state' => 0, 'segments' => 0, 'records' => 0],
                $this->realPartitionCounts($probe, $schema, $partition),
            );
            self::assertSame(0, $this->realAdvisoryLockCount($probe, $connection));
            self::assertSame(1, (int) $connection->fetchOne('SELECT count(*) FROM audit_store_tx_marker'));

            $connection->executeStatement("INSERT INTO audit_store_tx_marker (value) VALUES ('inner')");
            self::assertSame(2, (int) $connection->fetchOne('SELECT count(*) FROM audit_store_tx_marker'));
            $connection->rollBack();
            self::assertSame(1, $connection->getTransactionNestingLevel());
            self::assertSame(1, (int) $connection->fetchOne('SELECT count(*) FROM audit_store_tx_marker'));
            $connection->commit();
            self::assertFalse($connection->isTransactionActive());
            self::assertSame(0, $connection->getTransactionNestingLevel());
            self::assertSame(1, (int) $connection->fetchOne('SELECT count(*) FROM audit_store_tx_marker'));
        } finally {
            $this->cleanupRealAuditSchema($admin, $connection, $probe, $schema);
        }
    }

    public function testRealPostgreSqlSecondConnectionRoundTripsNulProtectedBodiesWithBinaryBinding(): void
    {
        $admin = $writer = $reader = $probe = null;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $firstRecordId = '01941f29-7c00-7000-8000-000000000452';
        $secondRecordId = '01941f29-7c00-7000-8000-000000000454';
        $minimumSegmentId = '01941f29-7c00-7000-8000-000000000451';
        $maximumSegmentId = '01941f29-7c00-7000-8000-000000000453';
        $minimumCatalog = $this->minimumBoundaryCatalog();
        $maximumCatalog = $this->maximumBoundaryCatalog();
        $maximumTenant = new AuditTypedReference(str_repeat('t', 256), str_repeat('u', 256));
        $maximumPartition = $this->partition($maximumTenant);
        $writerEvents = [];
        $readerEvents = [];
        $readerDecrypts = 0;

        try {
            $admin = $this->realConnection();
            $writer = $this->observedRealConnection();
            $reader = $this->realConnection();
            $probe = $this->realConnection();
            $this->createRealAuditSchema($admin, $schema);
            self::assertNotSame(
                $writer->fetchOne('SELECT pg_backend_pid()'),
                $reader->fetchOne('SELECT pg_backend_pid()'),
            );
            $writerOnUse = static function (string $event) use (&$writerEvents): void {
                $writerEvents[] = $event;
            };
            $writerStore = $this->store(
                $writer,
                new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                new SequenceUuidGenerator([
                    $minimumSegmentId,
                    $firstRecordId,
                ]),
                onUse: $writerOnUse,
                schema: $schema,
                nonces: new NulNonceSource($writerOnUse),
                catalog: $minimumCatalog,
                keyId: 'k',
            );
            $first = $writerStore->appendRequired($this->minimumBoundaryDraft($minimumCatalog));
            $maximumStore = $this->store(
                $writer,
                new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                new SequenceUuidGenerator([$maximumSegmentId, $secondRecordId]),
                onUse: $writerOnUse,
                schema: $schema,
                nonces: new NulNonceSource($writerOnUse),
                catalog: $maximumCatalog,
                keyId: str_repeat('k', 128),
            );
            $second = $maximumStore->appendRequired($this->maximumBoundaryDraft($maximumCatalog, $maximumTenant));
            $firstValue = $first->record()->toArray();
            $secondValue = $second->record()->toArray();
            self::assertSame($firstRecordId, $firstValue['recordId']);
            self::assertSame($secondRecordId, $secondValue['recordId']);
            self::assertSame(1, $firstValue['sequence']);
            self::assertSame(1, $secondValue['sequence']);
            self::assertSame($minimumSegmentId, $firstValue['segmentId']);
            self::assertSame($maximumSegmentId, $secondValue['segmentId']);
            self::assertSame($maximumTenant->toArray(), $secondValue['tenantBinding']);
            self::assertContains('nonce', $writerEvents);
            self::assertSame(2, count(array_filter($writerEvents, static fn(string $event): bool => $event === 'key')));
            self::assertSame(
                2,
                count(array_filter($writerEvents, static fn(string $event): bool => $event === 'nonce')),
            );

            $writeTypes = $writer->writeTypeMaps();
            self::assertCount(2, $writeTypes);
            foreach ($writeTypes as $types) {
                self::assertSame(ParameterType::BINARY, $types['protected_body']);
            }
            $minimumRows = $this->realRecords($probe, $schema, 'system:global');
            $maximumRows = $this->realRecords($probe, $schema, $maximumPartition);
            self::assertCount(1, $minimumRows);
            self::assertCount(1, $maximumRows);
            $minimumComplete = $this->unprotectForTest($first->record(), null, 'system:global', $minimumCatalog);
            $maximumComplete = $this->unprotectForTest(
                $second->record(),
                $maximumTenant,
                $maximumPartition,
                $maximumCatalog,
            );
            $minimumBody = PostgreSqlBytea::string($minimumRows[0]['protected_body']);
            $maximumBody = PostgreSqlBytea::string($maximumRows[0]['protected_body']);
            self::assertSame(1_055, strlen($minimumComplete->bytes()));
            self::assertSame(13_788, strlen($maximumComplete->bytes()));
            self::assertSame(1_108, strlen($minimumBody));
            self::assertSame(13_968, strlen($maximumBody));
            foreach ([
                [$firstValue,  $minimumComplete, $minimumBody, 1],
                [$secondValue, $maximumComplete, $maximumBody, 128],
            ] as [$value, $complete, $body, $keyLength]) {
                self::assertSame($value['protectedBody'], $body);
                self::assertSame(strlen($value['protectedBody']), strlen($body));
                self::assertSame(strlen($complete->bytes()) + 52 + $keyLength, strlen($body));
                self::assertGreaterThan(0, substr_count($body, "\0"));
                self::assertSame('BOPD', substr($body, 0, 4));
            }

            $readerOnUse = static function (string $event) use (&$readerEvents): void {
                $readerEvents[] = $event;
            };
            $readerStore = $this->store(
                $reader,
                new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                new SequenceUuidGenerator([]),
                onUse: $readerOnUse,
                schema: $schema,
                catalog: $minimumCatalog,
                onDecrypt: static function () use (&$readerDecrypts): void {
                    $readerDecrypts++;
                },
            );
            $firstLookup = $readerStore->find($firstRecordId, null, 'system:global');
            $maximumReaderStore = $this->store(
                $reader,
                new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                new SequenceUuidGenerator([]),
                onUse: $readerOnUse,
                schema: $schema,
                catalog: $maximumCatalog,
                onDecrypt: static function () use (&$readerDecrypts): void {
                    $readerDecrypts++;
                },
            );
            $secondLookup = $maximumReaderStore->find($secondRecordId, $maximumTenant, $maximumPartition);
            self::assertTrue($firstLookup->isFound());
            self::assertTrue($secondLookup->isFound());
            self::assertNotNull($firstLookup->record());
            self::assertNotNull($secondLookup->record());
            self::assertSame($minimumComplete->bytes(), $firstLookup->record()->bytes());
            self::assertSame($minimumComplete->toArray(), $firstLookup->record()->toArray());
            self::assertSame($maximumComplete->bytes(), $secondLookup->record()->bytes());
            self::assertSame($maximumComplete->toArray(), $secondLookup->record()->toArray());
            self::assertSame(['key', 'key'], $readerEvents);
            self::assertSame(2, $readerDecrypts);
            self::assertFalse($reader->isTransactionActive());
            self::assertSame(0, $reader->getTransactionNestingLevel());
        } finally {
            $this->cleanupRealAuditSchema($admin, $writer, $probe, $schema);
            if ($reader instanceof Connection) {
                try {
                    $reader->close();
                } catch (\Throwable) {
                }
            }
        }
    }

    public function testRealPostgreSqlTwoConnectionsBootstrapSameEmptyPartitionWithoutGap(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the same-partition bootstrap fixture.');
        }

        $admin = $primary = $probe = null;
        $pid = null;
        $status = 0;
        $childFinished = false;
        $ready = tempnam(sys_get_temp_dir(), 'audit-empty-bootstrap-ready-');
        $resultFile = tempnam(sys_get_temp_dir(), 'audit-empty-bootstrap-result-');
        $parentReady = tempnam(sys_get_temp_dir(), 'audit-empty-bootstrap-parent-');
        self::assertIsString($ready);
        self::assertIsString($resultFile);
        self::assertIsString($parentReady);
        $this->unlinkRealTemporary($ready);
        $this->unlinkRealTemporary($resultFile);
        $this->unlinkRealTemporary($parentReady);
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            foreach ([$admin, $primary, $probe] as $connection) {
                $connection->close();
            }
            $admin = $primary = $probe = null;

            $pid = pcntl_fork();
            if ($pid === -1) {
                self::fail('Unable to fork the same-partition bootstrap fixture.');
            }
            if ($pid === 0) {
                $deadline = microtime(true) + 5.0;
                while (!is_file($parentReady) && microtime(true) < $deadline) {
                    usleep(10_000);
                }
                if (!is_file($parentReady)) {
                    file_put_contents($resultFile, json_encode(['ok' => false], JSON_THROW_ON_ERROR));
                    exit(1);
                }
                $child = null;
                $payload = ['ok' => false];
                $ok = false;
                try {
                    $child = $this->realConnection();
                    file_put_contents($ready, (string) $child->fetchOne('SELECT pg_backend_pid()'));
                    $appended = $this->store(
                        $child,
                        new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                        new SequenceUuidGenerator([
                            '01941f29-7c00-7000-8000-000000000353',
                        ]),
                        schema: $schema,
                    )->appendRequired($this->draft());
                    $value = $appended->record()->toArray();
                    $payload = [
                        'ok' => true,
                        'sequence' => $value['sequence'],
                        'segmentId' => $value['segmentId'],
                        'recordHash' => $value['recordHash'],
                        'previousRecordHash' => $value['previousRecordHash'],
                    ];
                    $ok = true;
                } catch (\Throwable $failure) {
                    $payload = ['ok' => false, 'error' => get_class($failure)];
                } finally {
                    if ($child instanceof Connection) {
                        try {
                            $child->close();
                        } catch (\Throwable) {
                        }
                    }
                    file_put_contents($resultFile, json_encode($payload, JSON_THROW_ON_ERROR));
                }
                exit($ok ? 0 : 1);
            }

            [$admin, $primary, $probe] = $this->realConnections();
            $primary->beginTransaction();
            $first = $this->store(
                $primary,
                new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                new SequenceUuidGenerator([
                    '01941f29-7c00-7000-8000-000000000351',
                    '01941f29-7c00-7000-8000-000000000352',
                ]),
                schema: $schema,
            )->appendRequired($this->draft());
            $firstValue = $first->record()->toArray();
            self::assertSame(1, $firstValue['sequence']);
            self::assertTrue($primary->isTransactionActive());
            file_put_contents($parentReady, 'ready');
            $this->waitForRealAdvisoryLock($probe, $ready, $resultFile);
            $primary->commit();
            self::assertTrue($this->waitForRealChild($pid, $status));
            $childFinished = true;
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
            $childResult = json_decode((string) file_get_contents($resultFile), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($childResult);
            self::assertTrue((bool) ($childResult['ok'] ?? false));
            self::assertSame(2, $childResult['sequence']);
            self::assertSame($firstValue['segmentId'], $childResult['segmentId']);
            self::assertSame($firstValue['recordHash'], $childResult['previousRecordHash']);

            $records = $this->realRecords($probe, $schema, 'system:global');
            self::assertCount(2, $records);
            self::assertSame('1', $records[0]['sequence']);
            self::assertSame('2', $records[1]['sequence']);
            self::assertSame($firstValue['recordHash'], $records[0]['record_hash']);
            self::assertSame($firstValue['recordHash'], $records[1]['previous_record_hash']);
            self::assertSame($childResult['recordHash'], $records[1]['record_hash']);
            self::assertSame($firstValue['segmentId'], $records[0]['segment_id']);
            self::assertSame($firstValue['segmentId'], $records[1]['segment_id']);
            $state = $this->realState($probe, $schema, 'system:global');
            self::assertSame('2', $state['last_sequence']);
            self::assertSame($childResult['recordHash'], $state['head_record_hash']);
            self::assertSame($firstValue['segmentId'], $state['active_segment_id']);
            self::assertSame(1, $this->realSegmentCount($probe, $schema, 'system:global'));
            $segment = $this->realSegment($probe, $schema, 'system:global', $firstValue['segmentId']);
            self::assertSame('open', $segment['state']);
            self::assertSame('2', $segment['final_sequence']);
            self::assertSame($childResult['recordHash'], $segment['head_record_hash']);
        } finally {
            if (is_int($pid) && !$childFinished) {
                $this->terminateRealChild($pid, $status);
            }
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
            $this->unlinkRealTemporary($ready);
            $this->unlinkRealTemporary($resultFile);
            $this->unlinkRealTemporary($parentReady);
        }
    }

    public function testRealPostgreSqlForcedAdvisoryTokenSerializesDistinctEmptyPartitions(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the forced advisory-token fixture.');
        }

        $admin = $primary = $probe = null;
        $pid = null;
        $status = 0;
        $childFinished = false;
        $ready = tempnam(sys_get_temp_dir(), 'audit-forced-lock-ready-');
        $resultFile = tempnam(sys_get_temp_dir(), 'audit-forced-lock-result-');
        $parentReady = tempnam(sys_get_temp_dir(), 'audit-forced-lock-parent-');
        self::assertIsString($ready);
        self::assertIsString($resultFile);
        self::assertIsString($parentReady);
        $this->unlinkRealTemporary($ready);
        $this->unlinkRealTemporary($resultFile);
        $this->unlinkRealTemporary($parentReady);
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $tenant = new AuditTypedReference('account', 'forced-advisory-tenant');
        $tenantPartition = $this->partition($tenant);

        try {
            $admin = $this->realConnection();
            $primary = $this->forcedRealConnection();
            $probe = $this->realConnection();
            $this->createRealAuditSchema($admin, $schema);
            foreach ([$admin, $primary, $probe] as $connection) {
                $connection->close();
            }
            $admin = $primary = $probe = null;

            $pid = pcntl_fork();
            if ($pid === -1) {
                self::fail('Unable to fork the forced advisory-token fixture.');
            }
            if ($pid === 0) {
                $deadline = microtime(true) + 5.0;
                while (!is_file($parentReady) && microtime(true) < $deadline) {
                    usleep(10_000);
                }
                if (!is_file($parentReady)) {
                    file_put_contents($resultFile, json_encode(['ok' => false], JSON_THROW_ON_ERROR));
                    exit(1);
                }
                $child = null;
                $payload = ['ok' => false];
                $ok = false;
                try {
                    $child = $this->forcedRealConnection();
                    file_put_contents($ready, (string) $child->fetchOne('SELECT pg_backend_pid()'));
                    $appended = $this->store(
                        $child,
                        new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                        new SequenceUuidGenerator([
                            '01941f29-7c00-7000-8000-000000000363',
                            '01941f29-7c00-7000-8000-000000000364',
                        ]),
                        schema: $schema,
                    )->appendRequired($this->draftForTenant($tenant));
                    $value = $appended->record()->toArray();
                    $partitionKeys = array_values(array_filter($child->lockKeys(), static fn(string $key): bool => str_starts_with(
                        $key,
                        'blackops.audit.partition.v1:',
                    )));
                    $payload = [
                        'ok' => true,
                        'partition' => $value['partitionId'],
                        'sequence' => $value['sequence'],
                        'segmentId' => $value['segmentId'],
                        'recordHash' => $value['recordHash'],
                        'partitionLockKey' => $partitionKeys[0] ?? null,
                    ];
                    $ok = count($partitionKeys) === 1;
                } catch (\Throwable $failure) {
                    $payload = ['ok' => false, 'error' => get_class($failure)];
                } finally {
                    if ($child instanceof Connection) {
                        try {
                            $child->close();
                        } catch (\Throwable) {
                        }
                    }
                    file_put_contents($resultFile, json_encode($payload, JSON_THROW_ON_ERROR));
                }
                exit($ok ? 0 : 1);
            }

            $admin = $this->realConnection();
            $primary = $this->forcedRealConnection();
            $probe = $this->realConnection();
            $primary->beginTransaction();
            $first = $this->store(
                $primary,
                new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                new SequenceUuidGenerator([
                    '01941f29-7c00-7000-8000-000000000361',
                    '01941f29-7c00-7000-8000-000000000362',
                ]),
                schema: $schema,
            )->appendRequired($this->draft());
            $firstValue = $first->record()->toArray();
            $partitionKeys = array_values(array_filter($primary->lockKeys(), static fn(string $key): bool => str_starts_with(
                $key,
                'blackops.audit.partition.v1:',
            )));
            self::assertCount(1, $partitionKeys);
            self::assertStringContainsString(':system:global', $partitionKeys[0]);
            $parentPid = (int) $primary->fetchOne('SELECT pg_backend_pid()');
            self::assertSame(
                1,
                (int) $probe->fetchOne(
                    "SELECT count(*) FROM pg_locks WHERE pid = :pid AND locktype = 'advisory' "
                    . 'AND classid = 0 AND objid = :token AND granted',
                    ['pid' => $parentPid, 'token' => ForcedAdvisoryTokenConnection::TOKEN],
                    ['pid' => ParameterType::INTEGER, 'token' => ParameterType::INTEGER],
                ),
            );
            file_put_contents($parentReady, 'ready');
            $this->waitForRealAdvisoryLock($probe, $ready, $resultFile);
            $primary->commit();
            self::assertTrue($this->waitForRealChild($pid, $status));
            $childFinished = true;
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
            $childResult = json_decode((string) file_get_contents($resultFile), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($childResult);
            self::assertTrue((bool) ($childResult['ok'] ?? false));
            self::assertSame($tenantPartition, $childResult['partition']);
            self::assertSame(1, $childResult['sequence']);
            self::assertIsString($childResult['partitionLockKey']);
            self::assertNotSame($partitionKeys[0], $childResult['partitionLockKey']);
            self::assertStringContainsString(':' . $tenantPartition, $childResult['partitionLockKey']);

            $systemRecords = $this->realRecords($probe, $schema, 'system:global');
            $tenantRecords = $this->realRecords($probe, $schema, $tenantPartition);
            self::assertCount(1, $systemRecords);
            self::assertCount(1, $tenantRecords);
            self::assertSame('1', $systemRecords[0]['sequence']);
            self::assertSame('1', $tenantRecords[0]['sequence']);
            self::assertSame($firstValue['recordHash'], $systemRecords[0]['record_hash']);
            self::assertSame($childResult['recordHash'], $tenantRecords[0]['record_hash']);
            self::assertSame(1, $this->realSegmentCount($probe, $schema, 'system:global'));
            self::assertSame(1, $this->realSegmentCount($probe, $schema, $tenantPartition));
            self::assertSame('1', $this->realState($probe, $schema, 'system:global')['last_sequence']);
            self::assertSame('1', $this->realState($probe, $schema, $tenantPartition)['last_sequence']);
        } finally {
            if (is_int($pid) && !$childFinished) {
                $this->terminateRealChild($pid, $status);
            }
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
            $this->unlinkRealTemporary($ready);
            $this->unlinkRealTemporary($resultFile);
            $this->unlinkRealTemporary($parentReady);
        }
    }

    public function testRealPostgreSqlAppendBeforeCloseWaitsForPartitionAdvisoryLock(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the two-connection ordering fixture.');
        }

        $admin = $primary = $probe = null;
        $pid = null;
        $status = 0;
        $childFinished = false;
        $ready = tempnam(sys_get_temp_dir(), 'audit-close-order-ready-');
        $resultFile = tempnam(sys_get_temp_dir(), 'audit-close-order-result-');
        $parentReady = tempnam(sys_get_temp_dir(), 'audit-close-order-parent-ready-');
        self::assertIsString($ready);
        self::assertIsString($resultFile);
        self::assertIsString($parentReady);
        $this->unlinkRealTemporary($ready);
        $this->unlinkRealTemporary($resultFile);
        $this->unlinkRealTemporary($parentReady);
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            $setupStore = $this->store(
                $primary,
                new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                new SequenceUuidGenerator([
                    '01941f29-7c00-7000-8000-000000000321',
                    '01941f29-7c00-7000-8000-000000000322',
                ]),
                schema: $schema,
            );
            $first = $setupStore->appendRequired($this->draft());
            $firstValue = $first->record()->toArray();

            foreach ([$admin, $primary, $probe] as $connection) {
                $connection->close();
            }
            $admin = $primary = $probe = null;

            $pid = pcntl_fork();
            if ($pid === -1) {
                self::fail('Unable to fork the append-before-close ordering fixture.');
            }
            if ($pid === 0) {
                $deadline = microtime(true) + 5.0;
                while (!is_file($parentReady) && microtime(true) < $deadline) {
                    usleep(10_000);
                }
                if (!is_file($parentReady)) {
                    file_put_contents($resultFile, json_encode(['ok' => false], JSON_THROW_ON_ERROR));
                    exit(1);
                }
                $child = null;
                $ok = false;
                $payload = ['ok' => false];
                try {
                    $child = $this->realConnection();
                    file_put_contents($ready, (string) $child->fetchOne('SELECT pg_backend_pid()'));
                    $childStore = $this->store(
                        $child,
                        new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                        new SequenceUuidGenerator([
                            '01941f29-7c00-7000-8000-000000000325',
                        ]),
                        schema: $schema,
                    );
                    $childStore->closeSegment('system:global', $firstValue['segmentId']);
                    $ok = true;
                    $payload = ['ok' => true];
                } catch (\Throwable) {
                } finally {
                    if ($child instanceof Connection) {
                        try {
                            $child->close();
                        } catch (\Throwable) {
                        }
                    }
                    file_put_contents($resultFile, json_encode($payload, JSON_THROW_ON_ERROR));
                }
                exit($ok ? 0 : 1);
            }

            [$admin, $primary, $probe] = $this->realConnections();
            $store = $this->store(
                $primary,
                new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                new SequenceUuidGenerator([
                    '01941f29-7c00-7000-8000-000000000323',
                ]),
                schema: $schema,
            );
            $primary->beginTransaction();
            $second = $store->appendRequired($this->draft());
            $secondValue = $second->record()->toArray();
            self::assertSame(2, $secondValue['sequence']);
            self::assertSame($firstValue['recordHash'], $secondValue['previousRecordHash']);
            self::assertTrue($primary->isTransactionActive());
            self::assertSame(1, $primary->getTransactionNestingLevel());
            self::assertGreaterThan(
                0,
                (int) $probe->fetchOne(
                    "SELECT count(*) FROM pg_locks WHERE pid = :pid AND locktype = 'advisory' AND granted",
                    ['pid' => (int) $primary->fetchOne('SELECT pg_backend_pid()')],
                    ['pid' => ParameterType::INTEGER],
                ),
            );
            file_put_contents($parentReady, 'ready');
            $this->waitForRealAdvisoryLock($probe, $ready, $resultFile);
            $primary->commit();
            self::assertTrue($this->waitForRealChild($pid, $status));
            $childFinished = true;
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
            $childResult = json_decode((string) file_get_contents($resultFile), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($childResult);
            self::assertTrue($childResult['ok']);

            $records = $this->realRecords($probe, $schema, 'system:global');
            self::assertCount(2, $records);
            self::assertSame('1', $records[0]['sequence']);
            self::assertSame('2', $records[1]['sequence']);
            self::assertSame($firstValue['recordHash'], $records[0]['record_hash']);
            self::assertSame($secondValue['recordHash'], $records[1]['record_hash']);
            self::assertSame($firstValue['recordHash'], $records[1]['previous_record_hash']);
            self::assertSame($firstValue['segmentId'], $records[0]['segment_id']);
            self::assertSame($firstValue['segmentId'], $records[1]['segment_id']);
            $state = $this->realState($probe, $schema, 'system:global');
            self::assertSame('2', $state['last_sequence']);
            self::assertSame($secondValue['recordHash'], $state['head_record_hash']);
            $closed = $this->realSegment($probe, $schema, 'system:global', $firstValue['segmentId']);
            self::assertSame('closed', $closed['state']);
            self::assertSame('2', $closed['final_sequence']);
            self::assertSame($secondValue['recordHash'], $closed['head_record_hash']);
            self::assertNotNull($closed['successor_segment_id']);
            self::assertSame('01941f29-7c00-7000-8000-000000000325', $closed['successor_segment_id']);
            $successor = $this->realSegment($probe, $schema, 'system:global', $closed['successor_segment_id']);
            self::assertSame('open', $successor['state']);
            self::assertSame('3', $successor['first_sequence']);
            self::assertNull($successor['final_sequence']);
            self::assertNull($successor['head_record_hash']);
            self::assertSame($secondValue['recordHash'], $successor['predecessor_record_hash']);
            self::assertSame($successor['segment_id'], $state['active_segment_id']);
            self::assertSame(2, $this->realSegmentCount($probe, $schema, 'system:global'));
        } finally {
            if (is_int($pid) && !$childFinished) {
                $this->terminateRealChild($pid, $status);
            }
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
            $this->unlinkRealTemporary($ready);
            $this->unlinkRealTemporary($resultFile);
            $this->unlinkRealTemporary($parentReady);
        }
    }

    public function testRealPostgreSqlCloseBeforeAppendWaitsForPartitionAdvisoryLock(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the two-connection ordering fixture.');
        }

        $admin = $primary = $probe = null;
        $pid = null;
        $status = 0;
        $childFinished = false;
        $ready = tempnam(sys_get_temp_dir(), 'audit-append-order-ready-');
        $resultFile = tempnam(sys_get_temp_dir(), 'audit-append-order-result-');
        $parentReady = tempnam(sys_get_temp_dir(), 'audit-append-order-parent-ready-');
        self::assertIsString($ready);
        self::assertIsString($resultFile);
        self::assertIsString($parentReady);
        $this->unlinkRealTemporary($ready);
        $this->unlinkRealTemporary($resultFile);
        $this->unlinkRealTemporary($parentReady);
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            $setupStore = $this->store(
                $primary,
                new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                new SequenceUuidGenerator([
                    '01941f29-7c00-7000-8000-000000000331',
                    '01941f29-7c00-7000-8000-000000000332',
                ]),
                schema: $schema,
            );
            $first = $setupStore->appendRequired($this->draft());
            $firstValue = $first->record()->toArray();

            foreach ([$admin, $primary, $probe] as $connection) {
                $connection->close();
            }
            $admin = $primary = $probe = null;

            $pid = pcntl_fork();
            if ($pid === -1) {
                self::fail('Unable to fork the close-before-append ordering fixture.');
            }
            if ($pid === 0) {
                $deadline = microtime(true) + 5.0;
                while (!is_file($parentReady) && microtime(true) < $deadline) {
                    usleep(10_000);
                }
                if (!is_file($parentReady)) {
                    file_put_contents($resultFile, json_encode(['ok' => false], JSON_THROW_ON_ERROR));
                    exit(1);
                }
                $child = null;
                $ok = false;
                $payload = ['ok' => false];
                try {
                    $child = $this->realConnection();
                    file_put_contents($ready, (string) $child->fetchOne('SELECT pg_backend_pid()'));
                    $childStore = $this->store(
                        $child,
                        new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                        new SequenceUuidGenerator([
                            '01941f29-7c00-7000-8000-000000000334',
                        ]),
                        schema: $schema,
                    );
                    $appended = $childStore->appendRequired($this->draft());
                    $appendedValue = $appended->record()->toArray();
                    $ok = $appendedValue['sequence'] === 2;
                    $payload = [
                        'ok' => $ok,
                        'sequence' => $appendedValue['sequence'],
                        'segmentId' => $appendedValue['segmentId'],
                        'previousRecordHash' => $appendedValue['previousRecordHash'],
                    ];
                } catch (\Throwable) {
                } finally {
                    if ($child instanceof Connection) {
                        try {
                            $child->close();
                        } catch (\Throwable) {
                        }
                    }
                    file_put_contents($resultFile, json_encode($payload, JSON_THROW_ON_ERROR));
                }
                exit($ok ? 0 : 1);
            }

            [$admin, $primary, $probe] = $this->realConnections();
            $primary->beginTransaction();
            $closed = $this->store(
                $primary,
                new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                new SequenceUuidGenerator([
                    '01941f29-7c00-7000-8000-000000000333',
                ]),
                schema: $schema,
            )->closeSegment('system:global', $firstValue['segmentId']);
            self::assertTrue($closed->changed());
            self::assertTrue($primary->isTransactionActive());
            self::assertSame(1, $primary->getTransactionNestingLevel());
            self::assertGreaterThan(
                0,
                (int) $probe->fetchOne(
                    "SELECT count(*) FROM pg_locks WHERE pid = :pid AND locktype = 'advisory' AND granted",
                    ['pid' => (int) $primary->fetchOne('SELECT pg_backend_pid()')],
                    ['pid' => ParameterType::INTEGER],
                ),
            );
            file_put_contents($parentReady, 'ready');
            $this->waitForRealAdvisoryLock($probe, $ready, $resultFile);
            $primary->commit();
            self::assertTrue($this->waitForRealChild($pid, $status));
            $childFinished = true;
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
            $childResult = json_decode((string) file_get_contents($resultFile), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($childResult);
            self::assertTrue($childResult['ok']);
            self::assertSame(2, $childResult['sequence']);
            self::assertSame($closed->successorSegmentId(), $childResult['segmentId']);
            self::assertSame($firstValue['recordHash'], $childResult['previousRecordHash']);

            $records = $this->realRecords($probe, $schema, 'system:global');
            self::assertCount(2, $records);
            self::assertSame('1', $records[0]['sequence']);
            self::assertSame('2', $records[1]['sequence']);
            self::assertSame($firstValue['recordHash'], $records[0]['record_hash']);
            self::assertSame($firstValue['recordHash'], $records[1]['previous_record_hash']);
            self::assertSame($closed->successorSegmentId(), $records[1]['segment_id']);
            $state = $this->realState($probe, $schema, 'system:global');
            self::assertSame($closed->successorSegmentId(), $state['active_segment_id']);
            self::assertSame('2', $state['last_sequence']);
            self::assertSame($records[1]['record_hash'], $state['head_record_hash']);
            $old = $this->realSegment($probe, $schema, 'system:global', $firstValue['segmentId']);
            self::assertSame('closed', $old['state']);
            self::assertSame('1', $old['final_sequence']);
            $successor = $this->realSegment($probe, $schema, 'system:global', $closed->successorSegmentId());
            self::assertSame('open', $successor['state']);
            self::assertSame('2', $successor['first_sequence']);
            self::assertSame('2', $successor['final_sequence']);
            self::assertSame($records[1]['record_hash'], $successor['head_record_hash']);
            self::assertSame($firstValue['recordHash'], $successor['predecessor_record_hash']);
        } finally {
            if (is_int($pid) && !$childFinished) {
                $this->terminateRealChild($pid, $status);
            }
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
            $this->unlinkRealTemporary($ready);
            $this->unlinkRealTemporary($resultFile);
            $this->unlinkRealTemporary($parentReady);
        }
    }

    public function testRealPostgreSqlFindIsTenantAwareAndDoesNotUseTransactions(): void
    {
        $admin = $primary = $probe = null;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            $primary->executeStatement("SET TIME ZONE 'America/Los_Angeles'");
            $probe->executeStatement("SET TIME ZONE 'Pacific/Kiritimati'");
            $store = $this->store(
                $primary,
                new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:05.123456Z')),
                new SequenceUuidGenerator([
                    '01941f29-7c00-7000-8000-000000000341',
                    '01941f29-7c00-7000-8000-000000000342',
                    '01941f29-7c00-7000-8000-000000000343',
                    '01941f29-7c00-7000-8000-000000000344',
                ]),
                schema: $schema,
            );
            $tenantA = new AuditTypedReference('account', 'tenant-find-a');
            $tenantB = new AuditTypedReference('account', 'tenant-find-b');
            $system = $store->appendRequired($this->draft());
            $tenant = $store->appendRequired($this->draftForTenant($tenantA));
            $systemStored = $system->record();
            $tenantStored = $tenant->record();
            $systemValue = $systemStored->toArray();
            $tenantValue = $tenantStored->toArray();
            $systemExpected = $this->unprotectForTest($systemStored, null, 'system:global');
            $tenantExpected = $this->unprotectForTest($tenantStored, $tenantA, $this->partition($tenantA));
            $events = [];
            $reader = $this->store(
                $probe,
                onUse: static function (string $event) use (&$events): void {
                    $events[] = $event;
                },
                schema: $schema,
            );

            self::assertFalse($probe->isTransactionActive());
            self::assertSame(0, $probe->getTransactionNestingLevel());
            $systemFound = $reader->find($systemValue['recordId'], null, 'system:global');
            self::assertTrue($systemFound->isFound());
            self::assertNotNull($systemFound->record());
            self::assertSame($systemExpected->bytes(), $systemFound->record()->bytes());
            self::assertSame($systemExpected->toArray(), $systemFound->record()->toArray());
            self::assertSame(['key'], $events);
            self::assertFalse($probe->isTransactionActive());
            self::assertSame(0, $probe->getTransactionNestingLevel());

            $events = [];
            $tenantFound = $reader->find($tenantValue['recordId'], $tenantA, $this->partition($tenantA));
            self::assertTrue($tenantFound->isFound());
            self::assertNotNull($tenantFound->record());
            self::assertSame($tenantExpected->bytes(), $tenantFound->record()->bytes());
            self::assertSame($tenantExpected->toArray(), $tenantFound->record()->toArray());
            self::assertSame(['key'], $events);
            self::assertFalse($probe->isTransactionActive());
            self::assertSame(0, $probe->getTransactionNestingLevel());

            foreach ([
                ['01941f29-7c00-7000-8000-000000000345', null, 'system:global'],
                ['01941f29-7c00-7000-8000-000000000346', $tenantA, $this->partition($tenantA)],
                [$tenantValue['recordId'], $tenantB, $this->partition($tenantB)],
            ] as [$recordId, $expectedTenant, $partition]) {
                $events = [];
                $notFound = $reader->find($recordId, $expectedTenant, $partition);
                self::assertFalse($notFound->isFound());
                self::assertNull($notFound->record());
                self::assertSame([], $events);
                self::assertFalse($probe->isTransactionActive());
                self::assertSame(0, $probe->getTransactionNestingLevel());
            }
        } finally {
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
        }
    }

    public function testRealPostgreSqlDurableBootstrapAndHighWaterAcrossSystemAndTenantPartitions(): void
    {
        $admin = $primary = $probe = null;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $clockCalls = 0;

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            $primary->executeStatement("SET TIME ZONE 'America/Los_Angeles'");
            $probe->executeStatement("SET TIME ZONE 'Pacific/Kiritimati'");
            $clock = new FixtureClock(static function (string $event) use (&$clockCalls): void {
                if ($event === 'clock') {
                    $clockCalls++;
                }
            });
            $uuids = new SequenceUuidGenerator([
                '01941f29-7c00-7000-8000-000000000231',
                '01941f29-7c00-7000-8000-000000000232',
            ]);
            $store = $this->store($primary, $clock, $uuids, schema: $schema);
            $system = $this->durableLogical('durableSystemIngress');
            $systemSnapshot = $system->toArray();
            $systemSnapshot['recordId'] = '01941f29-7c00-7000-8000-000000000233';
            $systemSnapshot['resource']['id'] = 'operation-system-second';
            $systemSnapshot['time']['occurredAt'] = '2026-08-19T00:00:01.000000Z';
            $systemSnapshot['time']['recordedAt'] = '2026-08-19T00:00:01.000000Z';
            $systemSecond = $this->canonical($systemSnapshot);
            $tenant = new AuditTypedReference('account', 'tenant-real-durable');
            $tenantFirst = $this->durableLogical('durableTenantIngress');
            $tenantFirstSnapshot = $tenantFirst->toArray();
            $tenantFirstSnapshot['tenant'] = $tenant->toArray();
            $tenantFirstSnapshot['time']['occurredAt'] = '2026-08-19T00:00:02.000000Z';
            $tenantFirstSnapshot['time']['recordedAt'] = '2026-08-19T00:00:02.000000Z';
            $tenantFirst = $this->canonical($tenantFirstSnapshot);
            $tenantSecondSnapshot = $tenantFirst->toArray();
            $tenantSecondSnapshot['recordId'] = '01941f29-7c00-7000-8000-000000000234';
            $tenantSecondSnapshot['resource']['id'] = 'operation-tenant-second';
            $tenantSecondSnapshot['time']['occurredAt'] = '2026-08-19T00:00:03.000000Z';
            $tenantSecondSnapshot['time']['recordedAt'] = '2026-08-19T00:00:03.000000Z';
            $tenantSecond = $this->canonical($tenantSecondSnapshot);

            foreach ([
                ['system:global', null, $system, $systemSecond, '2026-08-19T00:00:02.000000Z'],
                [$this->partition($tenant), $tenant, $tenantFirst, $tenantSecond, '2026-08-19T00:00:03.000000Z'],
            ] as [$partition, $currentTenant, $firstLogical, $secondLogical, $expectedHighWater]) {
                $first = $store->appendDurable($firstLogical);
                $second = $store->appendDurable($secondLogical);
                $firstValue = $first->record()->toArray();
                $secondValue = $second->record()->toArray();
                self::assertTrue($first->inserted());
                self::assertTrue($second->inserted());
                self::assertSame(1, $firstValue['sequence']);
                self::assertSame(2, $secondValue['sequence']);
                self::assertNull($firstValue['previousRecordHash']);
                self::assertSame($firstValue['recordHash'], $secondValue['previousRecordHash']);
                self::assertSame($firstValue['segmentId'], $secondValue['segmentId']);
                self::assertSame($firstLogical->toArray()['time']['recordedAt'], $firstValue['recordedAt']);
                self::assertSame($secondLogical->toArray()['time']['recordedAt'], $secondValue['recordedAt']);
                $records = $this->realExact17Records($probe, $schema, $partition);
                self::assertCount(2, $records);
                self::assertSame(
                    [
                        'schema_version',
                        'kind',
                        'record_id',
                        'partition_id',
                        'tenant_type',
                        'tenant_id',
                        'segment_id',
                        'sequence',
                        'previous_record_hash',
                        'content_hash',
                        'record_hash',
                        'recorded_at',
                        'event_category',
                        'protected_body',
                        'action_type',
                        'resource_type',
                        'resource_id',
                    ],
                    array_keys($records[0]),
                );
                foreach ([$firstValue, $secondValue] as $index => $value) {
                    self::assertSame($value['recordId'], $records[$index]['record_id']);
                    self::assertSame($value['partitionId'], $records[$index]['partition_id']);
                    self::assertSame($value['tenantBinding']['type'] ?? null, $records[$index]['tenant_type']);
                    self::assertSame($value['tenantBinding']['id'] ?? null, $records[$index]['tenant_id']);
                    self::assertSame($value['segmentId'], $records[$index]['segment_id']);
                    self::assertSame((string) $value['sequence'], $records[$index]['sequence']);
                    self::assertSame($value['previousRecordHash'], $records[$index]['previous_record_hash']);
                    self::assertSame($value['contentHash'], $records[$index]['content_hash']);
                    self::assertSame($value['recordHash'], $records[$index]['record_hash']);
                    self::assertSame($value['recordedAt'], $records[$index]['recorded_at']);
                    self::assertSame($value['eventCategory'], $records[$index]['event_category']);
                    self::assertSame(
                        $value['protectedBody'],
                        PostgreSqlBytea::string($records[$index]['protected_body']),
                    );
                }
                $state = $this->realState($probe, $schema, $partition);
                self::assertSame($expectedHighWater, $state['last_assigned_recorded_at']);
                self::assertSame('2', $state['last_sequence']);
                self::assertSame($secondValue['recordHash'], $state['head_record_hash']);
                self::assertSame('2', $state['state_version']);
                $segment = $this->realSegment($probe, $schema, $partition, $firstValue['segmentId']);
                self::assertSame('2', $segment['final_sequence']);
                self::assertSame($secondValue['recordHash'], $segment['head_record_hash']);
                self::assertSame($firstValue['recordedAt'], $segment['opened_at']);
                self::assertSame('2', $segment['state_version']);
            }
            self::assertSame(0, $clockCalls);
            self::assertSame(2, $uuids->calls);
        } finally {
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
        }
    }

    public function testRealPostgreSqlDurableRetryConflictsTamperAndCapacityPrecedence(): void
    {
        $admin = $primary = $probe = null;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $events = [];

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            $logical = $this->durableLogical('durableSystemIngress');
            $clock = new FixtureClock(static function (string $event) use (&$events): void {
                $events[] = $event;
            });
            $uuids = new SequenceUuidGenerator([
                '01941f29-7c00-7000-8000-000000000241',
            ], static function (string $event) use (&$events): void {
                $events[] = $event;
            });
            $store = $this->store(
                $primary,
                $clock,
                $uuids,
                onUse: static function (string $event) use (&$events): void {
                    $events[] = $event;
                },
                schema: $schema,
            );
            $first = $store->appendDurable($logical);
            $firstValue = $first->record()->toArray();
            $beforeRecords = $this->realExact17Records($probe, $schema, 'system:global');
            $beforeState = $this->realState($probe, $schema, 'system:global');
            $beforeSegment = $this->realSegment($probe, $schema, 'system:global', $firstValue['segmentId']);

            $equal = $store->appendDurable($logical);
            self::assertFalse($equal->inserted());
            self::assertSame($firstValue, $equal->record()->toArray());

            $differentSnapshot = $logical->toArray();
            $differentSnapshot['resource']['id'] = 'operation-different-real';
            try {
                $store->appendDurable($this->canonical($differentSnapshot));
                self::fail('durable authenticated different content was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IdempotencyConflict, $exception->failure());
                self::assertNull($exception->getPrevious());
            }

            $crossSnapshot = $this->durableLogical('durableTenantIngress')->toArray();
            $crossSnapshot['recordId'] = $firstValue['recordId'];
            $crossSnapshot['tenant'] = ['type' => 'account', 'id' => 'tenant-real-cross'];
            try {
                $store->appendDurable($this->canonical($crossSnapshot));
                self::fail('durable cross-partition ID was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IdempotencyConflict, $exception->failure());
                self::assertNull($exception->getPrevious());
            }

            $recordsTable = $schema->recordsTable();
            $admin->executeStatement(
                "UPDATE {$recordsTable} SET content_hash = :hash WHERE record_id = CAST(:record_id AS uuid)",
                ['hash' => str_repeat('b', 64), 'record_id' => $firstValue['recordId']],
                ['hash' => ParameterType::STRING, 'record_id' => ParameterType::STRING],
            );
            try {
                $store->appendDurable($logical);
                self::fail('durable content hash tampering was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
            $admin->executeStatement(
                "UPDATE {$recordsTable} SET content_hash = :hash WHERE record_id = CAST(:record_id AS uuid)",
                ['hash' => $firstValue['contentHash'], 'record_id' => $firstValue['recordId']],
                ['hash' => ParameterType::STRING, 'record_id' => ParameterType::STRING],
            );
            $admin->executeStatement(
                "UPDATE {$recordsTable} SET action_type = :action WHERE record_id = CAST(:record_id AS uuid)",
                ['action' => 'tampered.action', 'record_id' => $firstValue['recordId']],
                ['action' => ParameterType::STRING, 'record_id' => ParameterType::STRING],
            );
            try {
                $store->appendDurable($logical);
                self::fail('durable action index tampering was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
            $admin->executeStatement(
                "UPDATE {$recordsTable} SET action_type = :action WHERE record_id = CAST(:record_id AS uuid)",
                ['action' => $logical->toArray()['action']['type'], 'record_id' => $firstValue['recordId']],
                ['action' => ParameterType::STRING, 'record_id' => ParameterType::STRING],
            );

            $maximum = '9007199254740991';
            $admin->executeStatement(
                'UPDATE '
                . $schema->partitionStateTable()
                . ' SET state_version = CAST(:maximum AS bigint) '
                . 'WHERE partition_id = :partition_id',
                ['maximum' => $maximum, 'partition_id' => 'system:global'],
                ['maximum' => ParameterType::STRING, 'partition_id' => ParameterType::STRING],
            );
            $admin->executeStatement(
                'UPDATE '
                . $schema->segmentsTable()
                . ' SET state_version = CAST(:maximum AS bigint) '
                . 'WHERE partition_id = :partition_id AND segment_id = CAST(:segment_id AS uuid)',
                ['maximum' => $maximum, 'partition_id' => 'system:global', 'segment_id' => $firstValue['segmentId']],
                [
                    'maximum' => ParameterType::STRING,
                    'partition_id' => ParameterType::STRING,
                    'segment_id' => ParameterType::STRING,
                ],
            );
            $equalAtMaximum = $store->appendDurable($logical);
            self::assertFalse($equalAtMaximum->inserted());
            $absentSnapshot = $logical->toArray();
            $absentSnapshot['recordId'] = '01941f29-7c00-7000-8000-000000000242';
            try {
                $store->appendDurable($this->canonical($absentSnapshot));
                self::fail('durable absent ID bypassed MAX capacity');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::ResourceUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
            $afterRecords = $this->realExact17Records($probe, $schema, 'system:global');
            self::assertCount(count($beforeRecords), $afterRecords);
            foreach ($beforeRecords as $index => $beforeRecord) {
                $afterRecord = $afterRecords[$index];
                $beforeRecord['protected_body'] = PostgreSqlBytea::string($beforeRecord['protected_body']);
                $afterRecord['protected_body'] = PostgreSqlBytea::string($afterRecord['protected_body']);
                self::assertSame($beforeRecord, $afterRecord);
            }
            self::assertSame(
                $beforeState['head_record_hash'],
                $this->realState($probe, $schema, 'system:global')['head_record_hash'],
            );
            self::assertSame(
                $beforeSegment['head_record_hash'],
                $this->realSegment($probe, $schema, 'system:global', $firstValue['segmentId'])['head_record_hash'],
            );
            self::assertSame(1, $uuids->calls);
            self::assertSame(0, count(array_filter($events, static fn(string $event): bool => $event === 'clock')));
        } finally {
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
        }
    }

    public function testRealPostgreSqlDurableCallerOwnedLateFenceAbortsOnlyTheAttempt(): void
    {
        $admin = $primary = $probe = null;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            $clock = new FixtureClock();
            $uuids = new SequenceUuidGenerator(['01941f29-7c00-7000-8000-000000000251']);
            $store = $this->store($primary, $clock, $uuids, schema: $schema);
            $first = $store->appendDurable($this->durableLogical('durableSystemIngress'));
            $firstValue = $first->record()->toArray();
            $secondSnapshot = $this->durableLogical('durableSystemIngress')->toArray();
            $secondSnapshot['recordId'] = '01941f29-7c00-7000-8000-000000000252';
            $secondSnapshot['resource']['id'] = 'operation-late-fence';
            $second = $this->canonical($secondSnapshot);
            $this->installLateFence($admin, $schema);
            $primary->beginTransaction();
            try {
                $store->appendDurable($second);
                self::fail('durable caller-owned late fence was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
            self::assertTrue($primary->isTransactionActive());
            self::assertSame(1, $primary->getTransactionNestingLevel());
            try {
                $primary->fetchOne('SELECT 1');
                self::fail('durable caller root was not aborted');
            } catch (\Throwable) {
                self::assertTrue($primary->isTransactionActive());
            }
            $primary->rollBack();
            $this->dropLateFence($admin, $schema);
            self::assertCount(1, $this->realExact17Records($probe, $schema, 'system:global'));
            self::assertSame(
                $firstValue['recordHash'],
                $this->realState($probe, $schema, 'system:global')['head_record_hash'],
            );
            self::assertSame(
                $firstValue['recordHash'],
                $this->realSegment($probe, $schema, 'system:global', $firstValue['segmentId'])['head_record_hash'],
            );
            self::assertSame(1, $uuids->calls);
        } finally {
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
        }
    }

    public function testRealPostgreSqlDurableOwnedDeferredCommitFailureQuarantinesAndReconnects(): void
    {
        $admin = $primary = $probe = null;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            $clock = new FixtureClock();
            $uuids = new SequenceUuidGenerator(['01941f29-7c00-7000-8000-000000000261']);
            $store = $this->store($primary, $clock, $uuids, schema: $schema);
            $first = $store->appendDurable($this->durableLogical('durableSystemIngress'));
            $firstValue = $first->record()->toArray();
            $secondSnapshot = $this->durableLogical('durableSystemIngress')->toArray();
            $secondSnapshot['recordId'] = '01941f29-7c00-7000-8000-000000000262';
            $secondSnapshot['resource']['id'] = 'operation-deferred';
            $second = $this->canonical($secondSnapshot);
            $this->installDeferredFailure($admin, $schema);
            try {
                $store->appendDurable($second);
                self::fail('durable deferred commit failure was not raised');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
                $this->assertNoCanaryOnExceptionSurfaces($exception, self::DEFERRED_FAILURE_CANARY);
            }
            self::assertFalse($primary->isConnected());
            self::assertFalse($primary->isTransactionActive());
            self::assertSame(0, $primary->getTransactionNestingLevel());
            self::assertCount(1, $this->realExact17Records($probe, $schema, 'system:global'));
            self::assertSame(
                $firstValue['recordHash'],
                $this->realState($probe, $schema, 'system:global')['head_record_hash'],
            );
            $this->dropDeferredFailure($admin, $schema);
            $secondResult = $store->appendDurable($second);
            self::assertTrue($secondResult->inserted());
            self::assertSame(2, $secondResult->record()->toArray()['sequence']);
            self::assertSame($firstValue['recordHash'], $secondResult->record()->toArray()['previousRecordHash']);
            self::assertSame(1, $uuids->calls);
            self::assertCount(2, $this->realExact17Records($probe, $schema, 'system:global'));
        } finally {
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
        }
    }

    public function testRealPostgreSqlCallerOwnedLateFenceAbortsOnlyTheAttempt(): void
    {
        $admin = null;
        $primary = null;
        $probe = null;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            $clock = new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:00.123456Z'));
            $uuids = new SequenceUuidGenerator([
                '01941f29-7c00-7000-8000-000000000211',
                '01941f29-7c00-7000-8000-000000000212',
                '01941f29-7c00-7000-8000-000000000213',
            ]);
            $store = $this->store($primary, $clock, $uuids, schema: $schema);
            $first = $store->appendRequired($this->draft());
            $firstValue = $first->record()->toArray();
            $this->installLateFence($admin, $schema);

            $primary->beginTransaction();
            try {
                $store->appendRequired($this->draft());
                self::fail('caller-owned late fence was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
                self::assertSame('Audit store operation failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
            self::assertTrue($primary->isConnected());
            self::assertTrue($primary->isTransactionActive());
            self::assertSame(1, $primary->getTransactionNestingLevel());
            try {
                $primary->fetchOne('SELECT 1');
                self::fail('the caller root was not aborted by the final fence');
            } catch (\Throwable) {
                self::assertTrue($primary->isTransactionActive());
            }
            $primary->rollBack();
            self::assertFalse($primary->isTransactionActive());
            self::assertSame(0, $primary->getTransactionNestingLevel());
            $this->dropLateFence($admin, $schema);

            $records = $this->realRecords($probe, $schema, 'system:global');
            self::assertCount(1, $records);
            self::assertSame($firstValue['recordId'], $records[0]['record_id']);
            self::assertSame('1', $records[0]['sequence']);
            $state = $this->realState($probe, $schema, 'system:global');
            self::assertSame('1', $state['last_sequence']);
            self::assertSame($firstValue['recordHash'], $state['head_record_hash']);
            $segment = $this->realSegment($probe, $schema, 'system:global', $firstValue['segmentId']);
            self::assertSame('1', $segment['final_sequence']);
            self::assertSame($firstValue['recordHash'], $segment['head_record_hash']);
        } finally {
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
        }
    }

    public function testRealPostgreSqlOwnedDeferredCommitFailureQuarantinesAndReconnects(): void
    {
        $admin = null;
        $primary = null;
        $probe = null;
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            [$admin, $primary, $probe] = $this->realConnections();
            $this->createRealAuditSchema($admin, $schema);
            $clock = new FixtureClock(null, new \DateTimeImmutable('2026-08-30T00:00:00.123456Z'));
            $uuids = new SequenceUuidGenerator([
                '01941f29-7c00-7000-8000-000000000221',
                '01941f29-7c00-7000-8000-000000000222',
                '01941f29-7c00-7000-8000-000000000223',
                '01941f29-7c00-7000-8000-000000000224',
            ]);
            $store = $this->store($primary, $clock, $uuids, schema: $schema);
            $first = $store->appendRequired($this->draft());
            $firstValue = $first->record()->toArray();
            $this->installDeferredFailure($admin, $schema);

            try {
                $store->appendRequired($this->draft());
                self::fail('deferred commit failure was not raised');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
                self::assertSame('Audit store operation failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
                $this->assertNoCanaryOnExceptionSurfaces($exception, self::DEFERRED_FAILURE_CANARY);
            }
            self::assertFalse($primary->isConnected());
            self::assertFalse($primary->isTransactionActive());
            self::assertSame(0, $primary->getTransactionNestingLevel());

            $records = $this->realRecords($probe, $schema, 'system:global');
            self::assertCount(1, $records);
            self::assertSame($firstValue['recordId'], $records[0]['record_id']);
            self::assertSame('1', $records[0]['sequence']);
            $state = $this->realState($probe, $schema, 'system:global');
            self::assertSame('1', $state['last_sequence']);
            self::assertSame($firstValue['recordHash'], $state['head_record_hash']);
            $this->dropDeferredFailure($admin, $schema);

            $second = $store->appendRequired($this->draft());
            $secondValue = $second->record()->toArray();
            self::assertTrue($primary->isConnected());
            self::assertSame(2, $secondValue['sequence']);
            self::assertSame($firstValue['recordHash'], $secondValue['previousRecordHash']);
            self::assertSame(4, $uuids->calls);
            $records = $this->realRecords($probe, $schema, 'system:global');
            self::assertCount(2, $records);
            self::assertSame('2', $records[1]['sequence']);
            self::assertSame($secondValue['recordHash'], $records[1]['record_hash']);
            $state = $this->realState($probe, $schema, 'system:global');
            self::assertSame('2', $state['last_sequence']);
            self::assertSame($secondValue['recordHash'], $state['head_record_hash']);
        } finally {
            $this->cleanupRealAuditSchema($admin, $primary, $probe, $schema);
        }
    }

    public function testAppendRequiredBootstrapsOneSystemPartitionWithOrderedLocksAndCteFence(): void
    {
        $segmentId = '01941f29-7c00-7000-8000-000000000101';
        $recordId = '01941f29-7c00-7000-8000-000000000102';
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $events = [];
        $lockCalls = [];
        $write = [];
        $fetchOneCalls = 0;
        $connection
            ->expects(self::exactly(3))
            ->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $parameters, array $types) use (
                &$lockCalls,
                &$write,
                &$events,
                &$fetchOneCalls,
            ): string {
                $fetchOneCalls++;
                if (str_contains($sql, 'pg_advisory_xact_lock')) {
                    $lockCalls[] = [$sql, $parameters, $types];
                    $events[] = $fetchOneCalls === 1 ? 'partition-lock' : 'record-lock';
                    return '0';
                }
                $events[] = 'write';
                $write = [$sql, $parameters, $types];
                return '1';
            });
        $fetchAssociativeCalls = 0;
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnCallback(function () use (&$fetchAssociativeCalls, &$events): array|false {
                $fetchAssociativeCalls++;
                $events[] = $fetchAssociativeCalls === 1 ? 'state' : 'orphan';

                return $fetchAssociativeCalls === 1 ? false : ['segment_exists' => 0, 'record_exists' => 0];
            });
        $collisionQueries = [];
        $collisionCalls = 0;
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql, array $parameters, array $types) use (
                &$collisionQueries,
                &$collisionCalls,
                &$events,
            ): array {
                $collisionCalls++;
                $events[] = $collisionCalls === 1 ? 'segment-collision' : 'record-collision';
                $collisionQueries[] = [$sql, $parameters, $types];
                return [];
            });
        $dependencyEvents = static function (string $event) use (&$events): void {
            $events[] = $event;
        };
        $clock = new FixtureClock($dependencyEvents, new \DateTimeImmutable('2026-08-29T12:34:56.123456+09:00'));
        $uuids = new SequenceUuidGenerator([$segmentId, $recordId], $dependencyEvents);
        $store = $this->store($connection, $clock, $uuids, false, $dependencyEvents);

        $draft = $this->draft();
        $result = $store->appendRequired($draft);

        self::assertTrue($result->inserted());
        self::assertSame($recordId, $result->record()->toArray()['recordId']);
        self::assertSame('system:global', $result->record()->toArray()['partitionId']);
        self::assertSame(1, $result->record()->toArray()['sequence']);
        self::assertSame('2026-08-29T03:34:56.123456Z', $result->record()->toArray()['recordedAt']);
        self::assertCount(2, $lockCalls);
        self::assertSame(
            'SELECT pg_advisory_xact_lock(hashtextextended(:lock_key, 0))',
            $this->normalizeSql($lockCalls[0][0]),
        );
        self::assertSame(['lock_key' => 'blackops.audit.partition.v1:blackops:system:global'], $lockCalls[0][1]);
        self::assertSame(['lock_key' => 'blackops.audit.record.v1:blackops:' . $recordId], $lockCalls[1][1]);
        self::assertSame(ParameterType::STRING, $lockCalls[0][2]['lock_key']);
        self::assertSame($segmentId, $collisionQueries[0][1]['segment_id']);
        self::assertSame($recordId, $collisionQueries[1][1]['record_id']);
        self::assertSame(ParameterType::BINARY, $write[2]['protected_body']);
        self::assertSame(ParameterType::STRING, $write[2]['recorded_at']);
        self::assertSame(ParameterType::STRING, $write[2]['assigned_at']);
        self::assertSame(
            [
                'partition-lock',
                'state',
                'orphan',
                'clock',
                'segment-uuid',
                'record-uuid',
                'segment-collision',
                'record-lock',
                'record-collision',
                'key',
                'nonce',
                'write',
            ],
            $events,
        );
        $recordValue = $result->record()->toArray();
        self::assertSame(
            [
                'bootstrap' => 1,
                'partition_id' => 'system:global',
                'tenant_type' => null,
                'tenant_id' => null,
                'active_segment_id' => $segmentId,
                'segment_id' => $segmentId,
                'record_id' => $recordId,
                'schema_version' => 1,
                'kind' => 'audit_stored_record',
                'next_sequence' => 1,
                'previous_record_hash' => null,
                'content_hash' => $recordValue['contentHash'],
                'record_hash' => $recordValue['recordHash'],
                'recorded_at' => '2026-08-29T03:34:56.123456Z',
                'event_category' => 'access',
                'protected_body' => $recordValue['protectedBody'],
                'action_type' => $draft->action->type,
                'resource_type' => $draft->resource->type,
                'resource_id' => $draft->resource->id,
                'assigned_at' => '2026-08-29T03:34:56.123456Z',
                'state_version' => 0,
                'segment_version' => 0,
            ],
            $write[1],
        );
        self::assertSame(
            [
                'bootstrap' => ParameterType::INTEGER,
                'partition_id' => ParameterType::STRING,
                'tenant_type' => ParameterType::STRING,
                'tenant_id' => ParameterType::STRING,
                'active_segment_id' => ParameterType::STRING,
                'segment_id' => ParameterType::STRING,
                'record_id' => ParameterType::STRING,
                'schema_version' => ParameterType::INTEGER,
                'kind' => ParameterType::STRING,
                'next_sequence' => ParameterType::INTEGER,
                'previous_record_hash' => ParameterType::STRING,
                'content_hash' => ParameterType::STRING,
                'record_hash' => ParameterType::STRING,
                'recorded_at' => ParameterType::STRING,
                'event_category' => ParameterType::STRING,
                'protected_body' => ParameterType::BINARY,
                'action_type' => ParameterType::STRING,
                'resource_type' => ParameterType::STRING,
                'resource_id' => ParameterType::STRING,
                'assigned_at' => ParameterType::STRING,
                'state_version' => ParameterType::INTEGER,
                'segment_version' => ParameterType::INTEGER,
            ],
            $write[2],
        );
        self::assertStringContainsString('WITH new_segment AS', $write[0]);
        self::assertStringContainsString(
            "SELECT :partition_id, CAST(:segment_id AS uuid), 'open', 1, CAST(:next_sequence AS bigint)",
            $write[0],
        );
        self::assertStringContainsString(
            'last_sequence, head_record_hash, last_assigned_recorded_at, state_version',
            $write[0],
        );
        self::assertStringContainsString(
            'SELECT :partition_id, :tenant_type, :tenant_id, CAST(:active_segment_id AS uuid),',
            $write[0],
        );
        self::assertStringContainsString(
            'CAST(:next_sequence AS bigint), :record_hash, CAST(:assigned_at AS timestamptz), 1',
            $write[0],
        );
        $previousCteOffset = -1;
        foreach ([
            'new_segment AS',
            'new_state AS',
            'new_record AS',
            'advanced_segment AS',
            'advanced_state AS',
            'fence AS',
        ] as $cte) {
            $cteOffset = strpos($write[0], $cte);
            self::assertIsInt($cteOffset);
            self::assertGreaterThan($previousCteOffset, $cteOffset);
            $previousCteOffset = $cteOffset;
        }
        self::assertSame(1, $write[1]['bootstrap']);
        self::assertSame(1, $write[1]['next_sequence']);
        self::assertSame(0, $write[1]['state_version']);
        self::assertSame(0, $write[1]['segment_version']);
        self::assertStringContainsString('CAST(CASE WHEN', $write[0]);
        self::assertStringContainsString("ELSE 'audit_store_abort' END AS bigint)", $write[0]);
        $this->assertWriteCteInvariants($write[0]);
    }

    public function testAppendRequiredJoinsCallerRootAndLeavesCommitRollbackAndCloseToCaller(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(1);
        foreach (['beginTransaction', 'commit', 'rollBack', 'close'] as $method) {
            $connection->expects(self::never())->method($method);
        }
        $connection->expects(self::exactly(3))->method('fetchOne')->willReturnOnConsecutiveCalls('0', '0', '1');
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(false, ['segment_exists' => 0, 'record_exists' => 0]);
        $connection->expects(self::exactly(2))->method('fetchAllAssociative')->willReturn([]);
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::once())
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-08-29T12:34:56.123456Z'));
        $uuids = new SequenceUuidGenerator([
            '01941f29-7c00-7000-8000-000000000101',
            '01941f29-7c00-7000-8000-000000000102',
        ]);

        $result = $this->store($connection, $clock, $uuids)->appendRequired($this->draft());

        self::assertTrue($result->inserted());
    }

    public function testAppendRequiredAdvancesExistingSystemAndTenantPartitionsIndependently(): void
    {
        foreach ([
            ['systemGenesis', null, 'system:global', '01941f29-7c00-7000-8000-000000000101'],
            [
                'tenantGenesis',
                new AuditTypedReference('account', 'tenant-a'),
                $this->partition(new AuditTypedReference('account', 'tenant-a')),
                '01941f29-7c00-7000-8000-000000000102',
            ],
        ] as [$fixtureName, $tenant, $partition, $segmentId]) {
            $stored = $this->storedRecord($fixtureName);
            $value = $stored->toArray();
            $head = $value['recordHash'];
            $boundary = [
                'segment_id' => $segmentId,
                'sequence' => 1,
                'previous_record_hash' => null,
                'record_hash' => $head,
            ];
            $state = [
                'partition_id' => $partition,
                'tenant_type' => $tenant?->type,
                'tenant_id' => $tenant?->id,
                'active_segment_id' => $segmentId,
                'last_sequence' => 1,
                'head_record_hash' => $head,
                'last_assigned_recorded_at' => $value['recordedAt'],
                'state_version' => 1,
            ];
            $segment = [
                'partition_id' => $partition,
                'segment_id' => $segmentId,
                'state' => 'open',
                'first_sequence' => 1,
                'final_sequence' => 1,
                'head_record_hash' => $head,
                'predecessor_record_hash' => null,
                'opened_at' => $value['recordedAt'],
                'closed_at' => null,
                'successor_segment_id' => null,
                'close_fencing_token' => null,
                'state_version' => 1,
            ];
            $recordId = '01941f29-7c00-7000-8000-000000000109';
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('commit');
            $writes = [];
            $connection
                ->expects(self::exactly(3))
                ->method('fetchOne')
                ->willReturnCallback(function (string $sql, array $parameters, array $types) use (&$writes): string {
                    if (str_contains($sql, 'pg_advisory_xact_lock')) {
                        return '0';
                    }
                    $writes[] = [$sql, $parameters, $types];

                    return '1';
                });
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($state, $segment);
            $connection
                ->expects(self::exactly(3))
                ->method('fetchAllAssociative')
                ->willReturnOnConsecutiveCalls([$boundary], [$boundary], []);
            $clock = $this->createMock(ClockInterface::class);
            $clock
                ->expects(self::once())
                ->method('now')
                ->willReturn(new \DateTimeImmutable('2026-08-29T12:34:56.123456Z'));
            $uuids = new SequenceUuidGenerator([$recordId]);

            $result = $this->store($connection, $clock, $uuids)->appendRequired($this->draftForTenant($tenant));

            self::assertTrue($result->inserted());
            self::assertSame(1, $uuids->calls);
            $resultValue = $result->record()->toArray();
            self::assertSame(2, $resultValue['sequence']);
            self::assertSame($head, $resultValue['previousRecordHash']);
            self::assertSame($partition, $resultValue['partitionId']);
            self::assertSame($tenant?->toArray(), $resultValue['tenantBinding']);
            self::assertCount(1, $writes);
            self::assertSame(0, $writes[0][1]['bootstrap']);
            self::assertSame(2, $writes[0][1]['next_sequence']);
            self::assertSame(1, $writes[0][1]['state_version']);
            self::assertSame(1, $writes[0][1]['segment_version']);
            self::assertSame($partition, $writes[0][1]['partition_id']);
            self::assertSame($tenant?->type, $writes[0][1]['tenant_type']);
            self::assertSame($tenant?->id, $writes[0][1]['tenant_id']);
            self::assertSame($segmentId, $writes[0][1]['active_segment_id']);
            self::assertSame($segmentId, $writes[0][1]['segment_id']);
            self::assertSame($recordId, $writes[0][1]['record_id']);
            self::assertSame($head, $writes[0][1]['previous_record_hash']);
            self::assertSame(ParameterType::BINARY, $writes[0][2]['protected_body']);
            self::assertSame(ParameterType::INTEGER, $writes[0][2]['next_sequence']);
            self::assertSame(ParameterType::STRING, $writes[0][2]['recorded_at']);
            self::assertStringContainsString('state_version = state_version + 1', $writes[0][0]);
            self::assertStringContainsString('state_version = CAST(:segment_version AS bigint)', $writes[0][0]);
            self::assertStringContainsString('state_version = CAST(:state_version AS bigint)', $writes[0][0]);
            self::assertStringContainsString("ELSE 'audit_store_abort' END AS bigint", $writes[0][0]);
            $this->assertWriteCteInvariants($writes[0][0]);
        }
    }

    public function testAppendRequiredRejectsOppositeDeliveryBeforeTransactionAndDb(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $this->expectNoConnectionUseExceptAutoCommit($connection);
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');
        $draft = $this->draftWithDelivery(AuditDelivery::Durable);

        try {
            $this->store($connection, $clock, $uuids)->appendRequired($draft);
            self::fail('durable draft was accepted by required append');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testAppendRequiredRejectsEveryMissingStateOrphanShapeBeforeClockUuidAndWrite(): void
    {
        $probeQueries = [];
        foreach ([
            ['segment_exists' => 1, 'record_exists' => 0],
            ['segment_exists' => 0, 'record_exists' => 1],
            ['segment_exists' => 1, 'record_exists' => 1],
            ['segment_exists' => '01', 'record_exists' => 0],
            ['segment_exists' => 0, 'record_exists' => 0, 'unexpected' => 0],
        ] as $orphanRow) {
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $probeCalls = 0;
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAssociative')
                ->willReturnCallback(function (string $sql, array $parameters, array $types) use (
                    &$probeCalls,
                    &$probeQueries,
                    $orphanRow,
                ): array|false {
                    $probeCalls++;
                    if ($probeCalls === 1) {
                        return false;
                    }
                    $probeQueries[] = [$sql, $parameters, $types];

                    return $orphanRow;
                });
            foreach (['fetchAllAssociative', 'commit', 'close'] as $method) {
                $connection->expects(self::never())->method($method);
            }
            $connection->expects(self::once())->method('rollBack');
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids, true)->appendRequired($this->draft());
                self::fail('orphan state was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
        self::assertNotEmpty($probeQueries);
        [$probeSql, $probeParameters, $probeTypes] = $probeQueries[0];
        self::assertSame(
            'SELECT CASE WHEN EXISTS ( SELECT 1 FROM "blackops"."audit_segments" WHERE partition_id = :partition_id ) '
            . 'THEN 1 ELSE 0 END AS segment_exists, CASE WHEN EXISTS ( SELECT 1 FROM "blackops"."audit_records" '
            . 'WHERE partition_id = :partition_id ) THEN 1 ELSE 0 END AS record_exists',
            $this->normalizeSql($probeSql),
        );
        self::assertSame(['partition_id' => 'system:global'], $probeParameters);
        self::assertSame(['partition_id' => ParameterType::STRING], $probeTypes);
    }

    public function testAppendRequiredRejectsSegmentAndRecordUuidCollisionsBeforeFinalizerOrProtection(): void
    {
        foreach ([true, false] as $segmentCollision) {
            $segmentId = '01941f29-7c00-7000-8000-000000000101';
            $recordId = '01941f29-7c00-7000-8000-000000000102';
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection
                ->expects(self::exactly($segmentCollision ? 1 : 2))
                ->method('fetchOne')
                ->willReturnOnConsecutiveCalls(...array_fill(0, $segmentCollision ? 1 : 2, '0'));
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls(false, ['segment_exists' => 0, 'record_exists' => 0]);
            $connection
                ->expects(self::exactly($segmentCollision ? 1 : 2))
                ->method('fetchAllAssociative')
                ->willReturnOnConsecutiveCalls(
                    $segmentCollision ? [['segment_id' => $segmentId]] : [],
                    ...$segmentCollision ? [] : [[['record_id' => $recordId]]],
                );
            $connection->expects(self::once())->method('rollBack');
            foreach (['commit', 'close'] as $method) {
                $connection->expects(self::never())->method($method);
            }
            $clock = $this->createMock(ClockInterface::class);
            $clock
                ->expects(self::once())
                ->method('now')
                ->willReturn(new \DateTimeImmutable('2026-08-29T12:34:56.123456Z'));
            $uuids = new SequenceUuidGenerator([$segmentId, $recordId]);

            try {
                $this->store($connection, $clock, $uuids, true)->appendRequired($this->draft());
                self::fail('UUID collision was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::ResourceUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
                self::assertSame(2, $uuids->calls);
            }
        }
    }

    public function testAppendRequiredClampsEqualAndBackwardClockToPersistedHighWater(): void
    {
        foreach (['2026-08-19T00:00:00.000000Z', '2026-08-18T23:59:59.000000Z'] as $clockValue) {
            [$state, $segment, $boundary] = $this->existingAppendRows(
                'systemGenesis',
                null,
                '01941f29-7c00-7000-8000-000000000101',
            );
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('commit');
            $connection->expects(self::exactly(3))->method('fetchOne')->willReturnOnConsecutiveCalls('0', '0', '1');
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($state, $segment);
            $connection
                ->expects(self::exactly(3))
                ->method('fetchAllAssociative')
                ->willReturnOnConsecutiveCalls([$boundary], [$boundary], []);
            $clock = $this->createMock(ClockInterface::class);
            $clock
                ->expects(self::once())
                ->method('now')
                ->willReturn(new \DateTimeImmutable($clockValue));
            $uuids = new SequenceUuidGenerator(['01941f29-7c00-7000-8000-000000000109']);

            $result = $this->store($connection, $clock, $uuids)->appendRequired($this->draft());

            self::assertSame($state['last_assigned_recorded_at'], $result->record()->toArray()['recordedAt']);
            self::assertSame(1, $uuids->calls);
        }
    }

    public function testAppendRequiredAcceptsAnEmptyOpenSuccessorAndContinuesItsPredecessorChain(): void
    {
        $partition = $this->postgresFixture()['partitions'][0];
        $state = $partition['state'];
        $segment = $partition['segments'][1];
        $endpoint = $partition['records'][1];
        $endpoint = [
            'segment_id' => $endpoint['segment_id'],
            'sequence' => $endpoint['sequence'],
            'previous_record_hash' => $endpoint['previous_record_hash'],
            'record_hash' => $endpoint['record_hash'],
        ];
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $write = [];
        $connection
            ->expects(self::exactly(3))
            ->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $parameters, array $types) use (&$write): string {
                if (str_contains($sql, 'pg_advisory_xact_lock')) {
                    return '0';
                }
                $write = [$sql, $parameters, $types];

                return '1';
            });
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $segment);
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls([$endpoint], []);
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::once())
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-08-29T12:34:56.123456Z'));
        $recordId = '01941f29-7c00-7000-8000-000000000109';
        $uuids = new SequenceUuidGenerator([$recordId]);

        $result = $this->store($connection, $clock, $uuids)->appendRequired($this->draft());
        $value = $result->record()->toArray();

        self::assertSame(3, $value['sequence']);
        self::assertSame($state['head_record_hash'], $value['previousRecordHash']);
        self::assertSame($segment['segment_id'], $value['segmentId']);
        self::assertSame($recordId, $value['recordId']);
        self::assertSame(0, $write[1]['bootstrap']);
        self::assertSame(3, $write[1]['next_sequence']);
        self::assertSame(2, $write[1]['state_version']);
        self::assertSame(1, $write[1]['segment_version']);
        self::assertSame($segment['segment_id'], $write[1]['segment_id']);
        self::assertSame($state['head_record_hash'], $write[1]['previous_record_hash']);
        self::assertSame(ParameterType::BINARY, $write[2]['protected_body']);
        self::assertSame(ParameterType::INTEGER, $write[2]['next_sequence']);
        self::assertSame(ParameterType::INTEGER, $write[2]['state_version']);
        self::assertStringContainsString('state_version = state_version + 1', $write[0]);
        self::assertStringContainsString('AND state_version = CAST(:segment_version AS bigint)', $write[0]);
        self::assertStringContainsString('AND state_version = CAST(:state_version AS bigint)', $write[0]);
        self::assertStringContainsString("ELSE 'audit_store_abort' END AS bigint", $write[0]);
        $this->assertWriteCteInvariants($write[0]);
    }

    public function testAppendRequiredRejectsStateLastZeroWithNonGenesisSegmentBeforeCapacity(): void
    {
        [$state, $segment] = $this->existingAppendRows('systemGenesis', null, '01941f29-7c00-7000-8000-000000000101');
        $state['last_sequence'] = 0;
        $state['head_record_hash'] = null;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('fetchOne')->willReturn('0');
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $segment);
        $connection->expects(self::once())->method('rollBack');
        foreach (['fetchAllAssociative', 'commit', 'close'] as $method) {
            $connection->expects(self::never())->method($method);
        }
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        try {
            $this->store($connection, $clock, $uuids)->appendRequired($this->draft());
            self::fail('non-genesis segment was accepted with a zero state sequence');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testAppendRequiredRejectsEachGenesisSegmentInvariantMutationBeforeResources(): void
    {
        [$baselineState, $baselineSegment] = $this->genesisAppendRows();
        $variants = [
            'first sequence' => static function (array &$state, array &$segment): void {
                $segment['first_sequence'] = 2;
            },
            'final sequence' => static function (array &$state, array &$segment): void {
                $segment['final_sequence'] = 1;
            },
            'head hash' => static function (array &$state, array &$segment): void {
                $segment['head_record_hash'] = str_repeat('b', 64);
            },
            'predecessor hash' => static function (array &$state, array &$segment): void {
                $segment['predecessor_record_hash'] = str_repeat('b', 64);
            },
        ];

        foreach ($variants as $label => $mutate) {
            $state = $baselineState;
            $segment = $baselineSegment;
            $mutate($state, $segment);
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($state, $segment);
            $connection->expects(self::once())->method('rollBack');
            foreach (['fetchAllAssociative', 'commit', 'close'] as $method) {
                $connection->expects(self::never())->method($method);
            }
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids, true)->appendRequired($this->draft());
                self::fail($label . ' genesis mutation was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $label);
                self::assertNull($exception->getPrevious(), $label);
            }
        }
    }

    public function testAppendRequiredRejectsStandaloneGenesisStateLastMutationBeforeResources(): void
    {
        [$state] = $this->genesisAppendRows();
        $state['last_sequence'] = 1;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('fetchOne')->willReturn('0');
        $connection->expects(self::once())->method('fetchAssociative')->willReturn($state);
        $connection->expects(self::once())->method('rollBack');
        foreach (['fetchAllAssociative', 'commit', 'close'] as $method) {
            $connection->expects(self::never())->method($method);
        }
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        try {
            $this->store($connection, $clock, $uuids, true)->appendRequired($this->draft());
            self::fail('standalone genesis state last mutation was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testAppendRequiredRejectsMissingExactlyOneEndpointBeforeResources(): void
    {
        [$state, $segment] = $this->existingAppendRows('systemGenesis', null, '01941f29-7c00-7000-8000-000000000101');
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('fetchOne')->willReturn('0');
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $segment);
        $connection->expects(self::once())->method('fetchAllAssociative')->willReturn([]);
        $connection->expects(self::once())->method('rollBack');
        foreach (['commit', 'close'] as $method) {
            $connection->expects(self::never())->method($method);
        }
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        try {
            $this->store($connection, $clock, $uuids, true)->appendRequired($this->draft());
            self::fail('missing endpoint was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testAppendRequiredRejectsEmptySuccessorCrossRowDivergenceBeforeCapacity(): void
    {
        $partition = $this->postgresFixture()['partitions'][0];
        $state = $partition['state'];
        $segment = $partition['segments'][1];
        $endpoint = $partition['records'][1];
        $endpoint = [
            'segment_id' => $endpoint['segment_id'],
            'sequence' => $endpoint['sequence'],
            'previous_record_hash' => $endpoint['previous_record_hash'],
            'record_hash' => $endpoint['record_hash'],
        ];
        $variants = [
            'endpoint sequence' => static function (array &$state, array &$segment, array &$endpoint): void {
                $endpoint['sequence'] = 3;
            },
            'endpoint hash' => static function (array &$state, array &$segment, array &$endpoint): void {
                $endpoint['record_hash'] = str_repeat('b', 64);
            },
            'endpoint segment' => static function (array &$state, array &$segment, array &$endpoint): void {
                $endpoint['segment_id'] = $segment['segment_id'];
            },
            'successor first' => static function (array &$state, array &$segment, array &$endpoint): void {
                $segment['first_sequence'] = 2;
            },
            'successor predecessor' => static function (array &$state, array &$segment, array &$endpoint): void {
                $segment['predecessor_record_hash'] = str_repeat('b', 64);
            },
        ];
        foreach ($variants as $label => $mutate) {
            $currentState = $state;
            $currentSegment = $segment;
            $currentEndpoint = $endpoint;
            $mutate($currentState, $currentSegment, $currentEndpoint);
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($currentState, $currentSegment);
            $connection->expects(self::once())->method('fetchAllAssociative')->willReturn([$currentEndpoint]);
            $connection->expects(self::once())->method('rollBack');
            foreach (['commit', 'close'] as $method) {
                $connection->expects(self::never())->method($method);
            }
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids)->appendRequired($this->draft());
                self::fail($label . ' divergence was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $label);
                self::assertNull($exception->getPrevious(), $label);
            }
        }
    }

    public function testAppendRequiredRejectsFirstSequencePredecessorDivergenceBeforeResources(): void
    {
        $partition = $this->postgresFixture()['partitions'][0];
        $first = $partition['records'][0];
        $second = $partition['records'][1];
        $activeSegment = $partition['segments'][1]['segment_id'];
        $state = $partition['state'];
        $state['active_segment_id'] = $activeSegment;
        $state['last_sequence'] = 2;
        $state['head_record_hash'] = $second['record_hash'];
        $segment = $partition['segments'][1];
        $segment['first_sequence'] = 2;
        $segment['final_sequence'] = 2;
        $segment['head_record_hash'] = $second['record_hash'];
        $segment['predecessor_record_hash'] = $first['record_hash'];
        $endpoint = [
            'segment_id' => $activeSegment,
            'sequence' => 2,
            'previous_record_hash' => $first['record_hash'],
            'record_hash' => $second['record_hash'],
        ];
        $minimum = $endpoint;
        $predecessor = [
            'segment_id' => $first['segment_id'],
            'sequence' => 1,
            'previous_record_hash' => null,
            'record_hash' => $first['record_hash'],
        ];
        foreach (['sequence', 'record_hash', 'segment_id'] as $field) {
            $currentPredecessor = $predecessor;
            $currentPredecessor[$field] = match ($field) {
                'sequence' => 2,
                'record_hash' => str_repeat('b', 64),
                default => $activeSegment,
            };
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($state, $segment);
            $connection
                ->expects(self::exactly(3))
                ->method('fetchAllAssociative')
                ->willReturnOnConsecutiveCalls([$endpoint], [$minimum], [$currentPredecessor]);
            $connection->expects(self::once())->method('rollBack');
            foreach (['commit', 'close'] as $method) {
                $connection->expects(self::never())->method($method);
            }
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids)->appendRequired($this->draft());
                self::fail('predecessor ' . $field . ' divergence was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $field);
                self::assertNull($exception->getPrevious(), $field);
            }
        }
    }

    public function testAppendRequiredRejectsMaxSequenceAndVersionBeforeClockUuidOrWrite(): void
    {
        foreach (['last_sequence', 'state_version', 'segment_version'] as $capacity) {
            [$currentState, $currentSegment, $boundary] = $this->existingAppendRows(
                'systemGenesis',
                null,
                '01941f29-7c00-7000-8000-000000000101',
            );
            if ($capacity === 'last_sequence') {
                $currentState['last_sequence'] = 9007199254740991;
                $currentSegment['final_sequence'] = 9007199254740991;
                $boundary['sequence'] = 9007199254740991;
            }
            if ($capacity === 'state_version') {
                $currentState['state_version'] = 9007199254740991;
            }
            if ($capacity === 'segment_version') {
                $currentSegment['state_version'] = 9007199254740991;
            }
            $minimum = $boundary;
            if ($capacity === 'last_sequence') {
                $minimum['sequence'] = 1;
            }
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($currentState, $currentSegment);
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAllAssociative')
                ->willReturnOnConsecutiveCalls([$boundary], [$minimum]);
            $connection->expects(self::once())->method('rollBack');
            foreach (['commit', 'close'] as $method) {
                $connection->expects(self::never())->method($method);
            }
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids, true)->appendRequired($this->draft());
                self::fail('capacity exhaustion was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::ResourceUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }

        [$state, $segment, $boundary] = $this->existingAppendRows(
            'systemGenesis',
            null,
            '01941f29-7c00-7000-8000-000000000102',
        );
        $state['last_sequence'] = 9007199254740991;
        $state['head_record_hash'] = $boundary['record_hash'];
        $segment['segment_id'] = '01941f29-7c00-7000-8000-000000000102';
        $segment['first_sequence'] = 9007199254740991;
        $segment['final_sequence'] = null;
        $segment['head_record_hash'] = null;
        $segment['predecessor_record_hash'] = $state['head_record_hash'];
        $boundary['segment_id'] = '01941f29-7c00-7000-8000-000000000101';
        $boundary['sequence'] = 9007199254740991;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('fetchOne')->willReturn('0');
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $segment);
        $connection->expects(self::once())->method('fetchAllAssociative')->willReturn([$boundary]);
        $connection->expects(self::once())->method('rollBack');
        foreach (['commit', 'close'] as $method) {
            $connection->expects(self::never())->method($method);
        }
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');
        try {
            $this->store($connection, $clock, $uuids)->appendRequired($this->draft());
            self::fail('an invalid max empty successor was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        [$state, $segment, $boundary] = $this->existingAppendRows(
            'systemGenesis',
            null,
            '01941f29-7c00-7000-8000-000000000101',
        );
        $state['last_sequence'] = 9007199254740991;
        $state['state_version'] = 9007199254740991;
        $segment['final_sequence'] = 9007199254740991;
        $segment['state_version'] = 9007199254740991;
        $boundary['sequence'] = 9007199254740991;
        $minimum = $boundary;
        $minimum['sequence'] = 1;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('fetchOne')->willReturn('0');
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($state, $segment);
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls([$boundary], [$minimum]);
        $connection->expects(self::once())->method('rollBack');
        foreach (['commit', 'close'] as $method) {
            $connection->expects(self::never())->method($method);
        }
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');
        try {
            $this->store($connection, $clock, $uuids)->appendRequired($this->draft());
            self::fail('all maximum append capacities were accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::ResourceUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testAppendRequiredRejectsOneFieldBoundaryDivergenceBeforeClockUuidAndProtection(): void
    {
        $variants = [
            'state head' => ['state', 'head_record_hash'],
            'segment first' => ['segment', 'first_sequence'],
            'segment final' => ['segment', 'final_sequence'],
            'segment head' => ['segment', 'head_record_hash'],
            'segment predecessor' => ['segment', 'predecessor_record_hash'],
            'endpoint sequence' => ['endpoint', 'sequence'],
            'endpoint hash' => ['endpoint', 'record_hash'],
            'endpoint segment' => ['endpoint', 'segment_id'],
            'minimum sequence' => ['minimum', 'sequence'],
            'minimum segment' => ['minimum', 'segment_id'],
            'minimum previous' => ['minimum', 'previous_record_hash'],
        ];
        foreach ($variants as $label => [$target, $field]) {
            [$state, $segment, $boundary] = $this->existingAppendRows(
                'systemGenesis',
                null,
                '01941f29-7c00-7000-8000-000000000101',
            );
            $minimum = $boundary;
            $alternateHash = str_repeat('b', 64);
            $alternateSegment = '01941f29-7c00-7000-8000-000000000199';
            if ($target === 'state') {
                $state[$field] = $alternateHash;
            } elseif ($target === 'segment') {
                $segment[$field] = match ($field) {
                    'first_sequence' => 2,
                    'final_sequence' => 2,
                    default => $alternateHash,
                };
            } elseif ($target === 'endpoint') {
                $boundary[$field] = match ($field) {
                    'sequence' => 2,
                    'segment_id' => $alternateSegment,
                    default => $alternateHash,
                };
            } else {
                $minimum[$field] = match ($field) {
                    'sequence' => 2,
                    'segment_id' => $alternateSegment,
                    default => $alternateHash,
                };
            }
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection
                ->expects(self::exactly(2))
                ->method('fetchAssociative')
                ->willReturnOnConsecutiveCalls($state, $segment);
            $fetchAllCalls = 0;
            $connection
                ->expects(self::atLeastOnce())
                ->method('fetchAllAssociative')
                ->willReturnCallback(function () use (&$fetchAllCalls, $boundary, $minimum): array {
                    $fetchAllCalls++;

                    return $fetchAllCalls === 1 ? [$boundary] : [$minimum];
                });
            $connection->expects(self::once())->method('rollBack');
            foreach (['commit', 'close'] as $method) {
                $connection->expects(self::never())->method($method);
            }
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids, true)->appendRequired($this->draft());
                self::fail($label . ' divergence was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $label);
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testAppendRequiredRejectsStateTenantPartitionAndScalarGrammarBeforeClockUuidOrWrite(): void
    {
        [$state] = $this->existingAppendRows('systemGenesis', null, '01941f29-7c00-7000-8000-000000000101');
        $variants = [
            'partition' => ['partition_id', 'tenant:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
            'tenant type' => ['tenant_type', 'account'],
            'tenant id' => ['tenant_id', 'tenant-a'],
            'sequence leading zero' => ['last_sequence', '01'],
            'sequence overflow' => ['last_sequence', '9007199254740992'],
            'active segment' => ['active_segment_id', 'not-a-uuid'],
        ];
        foreach ($variants as $label => [$field, $value]) {
            $row = $state;
            $row[$field] = $value;
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
            $connection->expects(self::once())->method('beginTransaction');
            $connection->expects(self::once())->method('fetchOne')->willReturn('0');
            $connection->expects(self::once())->method('fetchAssociative')->willReturn($row);
            $connection->expects(self::once())->method('rollBack');
            foreach (['fetchAllAssociative', 'commit', 'close'] as $method) {
                $connection->expects(self::never())->method($method);
            }
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids, true)->appendRequired($this->draft());
                self::fail($label . ' state value was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $label);
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testAppendRequiredRejectsInvalidTransactionStatePairsBeforeSqlOrResources(): void
    {
        foreach ([[false, 1], [true, 0], [true, 2], [false, 2]] as [$active, $level]) {
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn($active);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn($level);
            $this->expectNoConnectionUseExceptTransactionState($connection);
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $uuids = $this->createMock(Uuidv7Generator::class);
            $uuids->expects(self::never())->method('generate');

            try {
                $this->store($connection, $clock, $uuids, true)->appendRequired($this->draft());
                self::fail('invalid transaction state was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testOwnedBeginFailureQuarantinesExactlyOnceWithoutOpeningResources(): void
    {
        $canary = 'begin-failure-canary-8d12';
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection
            ->expects(self::once())
            ->method('beginTransaction')
            ->willThrowException(new \RuntimeException($canary));
        $connection->expects(self::once())->method('close');
        $this->expectNoConnectionUseExceptBeginFailure($connection);
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        try {
            $this->store($connection, $clock, $uuids, true)->appendRequired($this->draft());
            self::fail('begin failure was swallowed');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
            self::assertSame('Audit store operation failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString($canary, $exception->getTraceAsString());
        }
    }

    public function testOwnedCommitFailureRollsBackAndQuarantinesExactlyOnce(): void
    {
        $connection = $this->bootstrapConnectionForAppend();
        $connection
            ->expects(self::once())
            ->method('commit')
            ->willThrowException(new \RuntimeException('commit-secret-42'));
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::once())->method('close');
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::once())
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-08-29T12:34:56.123456Z'));
        $uuids = new SequenceUuidGenerator([
            '01941f29-7c00-7000-8000-000000000101',
            '01941f29-7c00-7000-8000-000000000102',
        ]);

        try {
            $this->store($connection, $clock, $uuids)->appendRequired($this->draft());
            self::fail('commit failure was swallowed');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('commit-secret-42', $exception->getTraceAsString());
        }
    }

    public function testOwnedRollbackFailureQuarantinesExactlyOnceAndHidesTheWriteError(): void
    {
        $canary = 'write-secret-7e10';
        $connection = $this->bootstrapConnectionForAppend(static function (string $sql) use ($canary): string {
            if (str_contains($sql, 'WITH new_segment')) {
                throw new \RuntimeException($canary);
            }

            return '0';
        });
        $connection
            ->expects(self::once())
            ->method('rollBack')
            ->willThrowException(new \RuntimeException('rollback-secret-88'));
        $connection->expects(self::once())->method('close');
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::once())
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-08-29T12:34:56.123456Z'));
        $uuids = new SequenceUuidGenerator([
            '01941f29-7c00-7000-8000-000000000101',
            '01941f29-7c00-7000-8000-000000000102',
        ]);

        try {
            $this->store($connection, $clock, $uuids)->appendRequired($this->draft());
            self::fail('write failure was swallowed');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString($canary, $exception->getTraceAsString());
        }
    }

    public function testAppendRequiredRollsBackWhenTheFinalFenceDoesNotReturnOne(): void
    {
        $connection = $this->bootstrapConnectionForAppend(static fn(string $sql): string => '0');
        $connection->expects(self::once())->method('rollBack');
        foreach (['commit', 'close'] as $method) {
            $connection->expects(self::never())->method($method);
        }
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::once())
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-08-29T12:34:56.123456Z'));
        $uuids = new SequenceUuidGenerator([
            '01941f29-7c00-7000-8000-000000000101',
            '01941f29-7c00-7000-8000-000000000102',
        ]);

        try {
            $this->store($connection, $clock, $uuids)->appendRequired($this->draft());
            self::fail('a non-one fence result was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testFindReturnsNotFoundForAbsentSystemAndTenantRows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('isAutoCommit')->willReturn(true);
        $queries = [];
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql, array $parameters) use (&$queries): array {
                $queries[] = [$sql, $parameters];
                return [];
            });
        $store = $this->store($connection);
        $tenant = new AuditTypedReference('account', 'tenant-a');
        $partition = $this->partition($tenant);

        $system = $store->find(self::RECORD_ID, null, 'system:global');
        $tenantResult = $store->find(self::RECORD_ID, $tenant, $partition);

        self::assertFalse($system->isFound());
        self::assertNull($system->record());
        self::assertFalse($tenantResult->isFound());
        self::assertNull($tenantResult->record());
        self::assertSame($this->expectedFindSql(false), $this->normalizeSql($queries[0][0]));
        self::assertSame(
            [
                'record_id' => self::RECORD_ID,
                'partition_id' => 'system:global',
            ],
            $queries[0][1],
        );
        self::assertSame($this->expectedFindSql(true), $this->normalizeSql($queries[1][0]));
        self::assertSame(
            [
                'record_id' => self::RECORD_ID,
                'partition_id' => $partition,
                'tenant_type' => 'account',
                'tenant_id' => 'tenant-a',
            ],
            $queries[1][1],
        );
    }

    public function testFindTreatsCrossTenantRowsAsNotFoundBeforeHydration(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('fetchAllAssociative')->willReturn([]);
        $tenant = new AuditTypedReference('account', 'other-tenant');

        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');
        $result = $this->store($connection, $clock, $uuids, true)->find(
            self::RECORD_ID,
            $tenant,
            $this->partition($tenant),
        );

        self::assertFalse($result->isFound());
        self::assertNull($result->record());
    }

    public function testFindReturnsAFoundTenantRecordWithTheTenantPredicate(): void
    {
        $fixture = $this->fixture()['records']['tenantGenesis'];
        $tenant = new AuditTypedReference('account', 'tenant-a');
        $partition = $this->partition($tenant);
        $row = $this->row($this->storedRecord('tenantGenesis'), 'tenantGenesis');
        $captured = [];
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql, array $parameters) use (&$captured, $row): array {
                $captured = [$sql, $parameters];
                return [$row];
            });

        $result = $this->store($connection)->find($fixture['logical']['recordId'], $tenant, $partition);

        self::assertTrue($result->isFound());
        self::assertSame($fixture['completeBytes'], $result->record()?->bytes());
        self::assertSame($this->expectedFindSql(true), $this->normalizeSql($captured[0]));
        self::assertSame(
            [
                'record_id' => $fixture['logical']['recordId'],
                'partition_id' => $partition,
                'tenant_type' => 'account',
                'tenant_id' => 'tenant-a',
            ],
            $captured[1],
        );
    }

    public function testFindDoesNotUseTransactionControlClockOrUuid(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        foreach ([
            'isTransactionActive',
            'getTransactionNestingLevel',
            'setAutoCommit',
            'beginTransaction',
            'commit',
            'rollBack',
            'createSavepoint',
            'releaseSavepoint',
            'rollbackSavepoint',
            'close',
        ] as $method) {
            $connection->expects(self::never())->method($method);
        }
        $connection->expects(self::once())->method('fetchAllAssociative')->willReturn([]);
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $uuids = $this->createMock(Uuidv7Generator::class);
        $uuids->expects(self::never())->method('generate');

        $result = $this->store($connection, $clock, $uuids)->find(self::RECORD_ID, null, 'system:global');

        self::assertFalse($result->isFound());
    }

    public function testFindRejectsMalformedRecordAndPartitionInputsBeforeSql(): void
    {
        foreach ([
            ['not-a-uuid', null, 'system:global'],
            [self::RECORD_ID, null, 'system:global\n'],
            [self::RECORD_ID, new AuditTypedReference('account', 'tenant-a'), 'system:global'],
        ] as [$recordId, $tenant, $partition]) {
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::never())->method('fetchAllAssociative');
            $store = $this->store($connection);

            try {
                $store->find($recordId, $tenant, $partition);
                self::fail('malformed lookup input was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame('integrity_invalid', $exception->code());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testFindRejectsMalformedExactSeventeenColumnRowsAndColumnOrder(): void
    {
        $valid = $this->row($this->storedRecord());
        $variants = [
            array_diff_key($valid, ['kind' => true]),
            [...$valid, 'unexpected' => 'x'],
            array_reverse($valid, true),
            [...$valid, 'schema_version' => '01'],
        ];
        $wrongQueryType = $valid;
        $wrongQueryType['resource_id'] = 42;
        $variants[] = $wrongQueryType;
        $wrongPhysicalType = $valid;
        $wrongPhysicalType['sequence'] = 1.0;
        $variants[] = $wrongPhysicalType;
        foreach ($variants as $row) {
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('fetchAllAssociative')->willReturn([$row]);
            $store = $this->store($connection);

            try {
                $store->find(self::RECORD_ID, null, 'system:global');
                self::fail('malformed exact17 row was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame('integrity_invalid', $exception->code());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testFindRejectsMultipleRowsAsAnIntegrityFailure(): void
    {
        $row = $this->row($this->storedRecord());
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('fetchAllAssociative')->willReturn([$row, $row]);
        $store = $this->store($connection);

        try {
            $store->find(self::RECORD_ID, null, 'system:global');
            self::fail('multiple rows were accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testFindHydratesExactlyThePhysicalFourteenColumnsAndUnprotectsOnce(): void
    {
        $fixture = $this->fixture()['records']['systemGenesis'];
        $stored = $this->storedRecord();
        $row = $this->row($stored);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $captured = [];
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql, array $parameters) use (&$captured, $row): array {
                $captured = [$sql, $parameters];
                return [$row];
            });
        $store = $this->store($connection);

        $result = $store->find(self::RECORD_ID, null, 'system:global');

        self::assertTrue($result->isFound());
        self::assertSame($fixture['completeBytes'], $result->record()?->bytes());
        self::assertStringContainsString('record_id::text AS record_id', $captured[0]);
        self::assertStringContainsString('segment_id::text AS segment_id', $captured[0]);
        self::assertStringContainsString('to_char(recorded_at AT TIME ZONE', $captured[0]);
        self::assertSame(self::RECORD_ID, $captured[1]['record_id']);
        self::assertSame('system:global', $captured[1]['partition_id']);
    }

    public function testFindDetectsTenantPartitionMismatchAndQueryIndexMismatch(): void
    {
        $tenant = new AuditTypedReference('account', 'tenant-a');
        $tenantPartition = $this->partition($tenant);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::never())->method('fetchAllAssociative');
        $store = $this->store($connection);
        try {
            $store->find(self::RECORD_ID, $tenant, 'system:global');
            self::fail('tenant/partition mismatch was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame('integrity_invalid', $exception->code());
        }

        foreach (['action_type', 'resource_type', 'resource_id'] as $column) {
            $row = $this->row($this->storedRecord());
            $row[$column] = 'different-' . $column;
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
            $connection->expects(self::once())->method('fetchAllAssociative')->willReturn([$row]);
            $store = $this->store($connection);
            try {
                $store->find(self::RECORD_ID, null, 'system:global');
                self::fail('query index mismatch was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame('integrity_invalid', $exception->code());
                self::assertNull($exception->getPrevious());
            }
        }
        self::assertSame(71, strlen($tenantPartition));
    }

    public function testFindIndexMismatchDoesNotLeakAuthenticatedOrPhysicalValues(): void
    {
        $canary = 'physical-resource-canary-0f3a9e';
        $row = $this->row($this->storedRecord());
        $row['resource_id'] = $canary;
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturnCallback(static function () use (&$row): array {
                return [$row];
            });
        $store = $this->store($connection);

        try {
            $store->find(self::RECORD_ID, null, 'system:global');
            self::fail('query index mismatch was accepted');
        } catch (AuditStoreException $exception) {
            $row = [];
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertSame('Audit store operation failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            $this->assertNoCanaryOnExceptionSurfaces($exception, $canary);
        }
    }

    public function testFindMapsDatabaseErrorsToTheFixedSafeFailureWithoutLeakage(): void
    {
        $canary = 'postgres-secret-canary-1b7c9e';
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturnCallback(static function (): never {
                throw new \RuntimeException('postgres-secret-canary-1b7c9e');
            });
        $store = $this->store($connection);

        try {
            $store->find(self::RECORD_ID, null, 'system:global');
            self::fail('database failure was not mapped');
        } catch (AuditStoreException $exception) {
            self::assertSame('storage_unavailable', $exception->code());
            self::assertSame('Audit store operation failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            $this->assertNoCanaryOnExceptionSurfaces($exception, $canary);
        }
    }

    public function testDatabaseWriteAndProtectionFailuresHideCanariesOnEveryExceptionSurface(): void
    {
        $cases = [
            'database' => static function (self $test): array {
                $connection = $test->createMock(Connection::class);
                $connection->expects($test->once())->method('isAutoCommit')->willReturn(true);
                $connection->expects($test->once())->method('isTransactionActive')->willReturn(false);
                $connection->expects($test->once())->method('getTransactionNestingLevel')->willReturn(0);
                $connection->expects($test->once())->method('beginTransaction');
                $connection
                    ->expects($test->once())
                    ->method('fetchOne')
                    ->willReturnCallback(static function (): never {
                        throw new \RuntimeException('store-database-canary-a1b2c3');
                    });
                $connection->expects($test->once())->method('rollBack');
                $clock = $test->createMock(ClockInterface::class);
                $clock->expects($test->never())->method('now');
                $uuids = $test->createMock(Uuidv7Generator::class);
                $uuids->expects($test->never())->method('generate');

                return [$test->store($connection, $clock, $uuids), $test->draft()];
            },
            'write' => static function (self $test): array {
                $connection = $test->bootstrapConnectionForAppend(static function (string $sql): string {
                    if (str_contains($sql, 'WITH new_segment')) {
                        throw new \RuntimeException('store-write-canary-a1b2c3');
                    }

                    return '0';
                });
                $connection->expects($test->once())->method('rollBack');
                $clock = $test->createMock(ClockInterface::class);
                $clock
                    ->expects($test->once())
                    ->method('now')
                    ->willReturn(new \DateTimeImmutable('2026-08-29T12:34:56.123456Z'));
                $uuids = new SequenceUuidGenerator([
                    '01941f29-7c00-7000-8000-000000000101',
                    '01941f29-7c00-7000-8000-000000000102',
                ]);

                return [$test->store($connection, $clock, $uuids), $test->draft()];
            },
            'protection' => static function (self $test): array {
                $connection = $test->bootstrapConnectionForAppend(expectWrite: false);
                $connection->expects($test->once())->method('rollBack');
                $clock = $test->createMock(ClockInterface::class);
                $clock
                    ->expects($test->once())
                    ->method('now')
                    ->willReturn(new \DateTimeImmutable('2026-08-29T12:34:56.123456Z'));
                $uuids = new SequenceUuidGenerator([
                    '01941f29-7c00-7000-8000-000000000101',
                    '01941f29-7c00-7000-8000-000000000102',
                ]);

                return [
                    $test->store($connection, $clock, $uuids, 'store-protection-canary-a1b2c3'),
                    $test->draft(),
                ];
            },
        ];
        foreach ($cases as $kind => $factory) {
            $canary = 'store-' . $kind . '-canary-a1b2c3';
            [$store, $draft] = $factory($this);
            try {
                $store->appendRequired($draft);
                self::fail($kind . ' failure was swallowed');
            } catch (AuditStoreException $exception) {
                self::assertSame(
                    $kind === 'protection' ? 'resource_unavailable' : 'storage_unavailable',
                    $exception->code(),
                    $kind,
                );
                self::assertNull($exception->getPrevious(), $kind);
                $this->assertNoCanaryOnExceptionSurfaces($exception, $canary);
            }
        }
    }

    private function expectNoConnectionUse(Connection $connection): void
    {
        foreach ([
            'fetchAllAssociative',
            'fetchAssociative',
            'fetchOne',
            'fetchAllNumeric',
            'fetchAllKeyValue',
            'fetchAllAssociativeIndexed',
            'executeQuery',
            'executeStatement',
            'insert',
            'update',
            'delete',
            'prepare',
            'isTransactionActive',
            'getTransactionNestingLevel',
            'setAutoCommit',
            'beginTransaction',
            'commit',
            'rollBack',
            'createSavepoint',
            'releaseSavepoint',
            'rollbackSavepoint',
            'close',
        ] as $method) {
            $connection->expects(self::never())->method($method);
        }
    }

    private function expectNoConnectionUseExceptAutoCommit(Connection $connection): void
    {
        foreach ([
            'fetchAllAssociative',
            'fetchAssociative',
            'fetchOne',
            'fetchAllNumeric',
            'fetchAllKeyValue',
            'fetchAllAssociativeIndexed',
            'executeQuery',
            'executeStatement',
            'insert',
            'update',
            'delete',
            'prepare',
            'isTransactionActive',
            'getTransactionNestingLevel',
            'setAutoCommit',
            'beginTransaction',
            'commit',
            'rollBack',
            'createSavepoint',
            'releaseSavepoint',
            'rollbackSavepoint',
            'close',
        ] as $method) {
            $connection->expects(self::never())->method($method);
        }
    }

    private function expectNoConnectionUseExceptTransactionState(Connection $connection): void
    {
        foreach ([
            'fetchAllAssociative',
            'fetchAssociative',
            'fetchOne',
            'fetchAllNumeric',
            'fetchAllKeyValue',
            'fetchAllAssociativeIndexed',
            'executeQuery',
            'executeStatement',
            'insert',
            'update',
            'delete',
            'prepare',
            'setAutoCommit',
            'beginTransaction',
            'commit',
            'rollBack',
            'createSavepoint',
            'releaseSavepoint',
            'rollbackSavepoint',
            'close',
        ] as $method) {
            $connection->expects(self::never())->method($method);
        }
    }

    private function expectNoConnectionUseExceptBeginFailure(Connection $connection): void
    {
        foreach ([
            'fetchAllAssociative',
            'fetchAssociative',
            'fetchOne',
            'fetchAllNumeric',
            'fetchAllKeyValue',
            'fetchAllAssociativeIndexed',
            'executeQuery',
            'executeStatement',
            'insert',
            'update',
            'delete',
            'prepare',
            'setAutoCommit',
            'commit',
            'rollBack',
            'createSavepoint',
            'releaseSavepoint',
            'rollbackSavepoint',
        ] as $method) {
            $connection->expects(self::never())->method($method);
        }
    }

    private function bootstrapConnectionForAppend(?callable $fetchOne = null, bool $expectWrite = true): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects(self::once())->method('beginTransaction');
        if ($fetchOne === null) {
            $connection
                ->expects(self::exactly($expectWrite ? 3 : 2))
                ->method('fetchOne')
                ->willReturnOnConsecutiveCalls(...$expectWrite ? ['0', '0', '1'] : ['0', '0']);
        } else {
            $connection
                ->expects(self::exactly($expectWrite ? 3 : 2))
                ->method('fetchOne')
                ->willReturnCallback($fetchOne);
        }
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(false, ['segment_exists' => 0, 'record_exists' => 0]);
        $connection->expects(self::exactly(2))->method('fetchAllAssociative')->willReturn([]);

        return $connection;
    }

    private function normalizeSql(string $sql): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($sql));
        self::assertIsString($normalized);

        return $normalized;
    }

    private function assertWriteCteInvariants(string $sql): void
    {
        $normalized = $this->normalizeSql($sql);
        foreach ([
            '(SELECT count(*) FROM new_record) = 1',
            'CAST(:bootstrap AS integer) = 1 AND (SELECT count(*) FROM new_segment) = 1',
            'AND (SELECT count(*) FROM new_state) = 1',
            'AND (SELECT count(*) FROM advanced_segment) = 0',
            'AND (SELECT count(*) FROM advanced_state) = 0',
            'CAST(:bootstrap AS integer) = 0 AND (SELECT count(*) FROM new_segment) = 0',
            'AND (SELECT count(*) FROM new_state) = 0',
            'AND (SELECT count(*) FROM advanced_segment) = 1',
            'AND (SELECT count(*) FROM advanced_state) = 1',
        ] as $fragment) {
            self::assertSame(1, substr_count($normalized, $fragment), $fragment);
        }
        self::assertSame(3, substr_count($normalized, 'WHERE CAST(:bootstrap AS integer) = 1'));
        self::assertSame(3, substr_count($normalized, 'WHERE CAST(:bootstrap AS integer) = 0'));
        self::assertSame(1, substr_count(
            $normalized,
            "SELECT :partition_id, CAST(:segment_id AS uuid), 'open', 1, CAST(:next_sequence AS bigint), "
            . ':record_hash, NULL, CAST(:assigned_at AS timestamptz)',
        ));
        self::assertSame(1, substr_count(
            $normalized,
            'SELECT :partition_id, :tenant_type, :tenant_id, CAST(:active_segment_id AS uuid), '
            . 'CAST(:next_sequence AS bigint), :record_hash, CAST(:assigned_at AS timestamptz), 1',
        ));
        $segmentHeadMutation = str_replace(
            ':record_hash, NULL, CAST(:assigned_at AS timestamptz)',
            'NULL, NULL, CAST(:assigned_at AS timestamptz)',
            $normalized,
        );
        self::assertSame(0, substr_count(
            $segmentHeadMutation,
            "SELECT :partition_id, CAST(:segment_id AS uuid), 'open', 1, CAST(:next_sequence AS bigint), "
            . ':record_hash, NULL, CAST(:assigned_at AS timestamptz)',
        ));
        $stateHeadMutation = str_replace(
            'CAST(:next_sequence AS bigint), :record_hash, CAST(:assigned_at AS timestamptz), 1',
            'CAST(:next_sequence AS bigint), NULL, CAST(:assigned_at AS timestamptz), 1',
            $normalized,
        );
        self::assertSame(0, substr_count(
            $stateHeadMutation,
            'SELECT :partition_id, :tenant_type, :tenant_id, CAST(:active_segment_id AS uuid), '
            . 'CAST(:next_sequence AS bigint), :record_hash, CAST(:assigned_at AS timestamptz), 1',
        ));
        self::assertSame(
            [
                'CAST(:active_segment_id AS uuid)' => 1,
                'CAST(:assigned_at AS timestamptz)' => 3,
                'CAST(:bootstrap AS integer)' => 8,
                'CAST(:next_sequence AS bigint)' => 6,
                'CAST(:protected_body AS bytea)' => 2,
                'CAST(:record_id AS uuid)' => 2,
                'CAST(:recorded_at AS timestamptz)' => 2,
                'CAST(:schema_version AS smallint)' => 2,
                'CAST(:segment_id AS uuid)' => 4,
                'CAST(:segment_version AS bigint)' => 1,
                'CAST(:state_version AS bigint)' => 1,
            ],
            $this->castInventory($normalized),
        );
    }

    /** @return array<string,int> */
    private function castInventory(string $sql): array
    {
        preg_match_all('/\\bCAST\\s*\\(\\s*(:[a-z_]+)\\s+AS\\s+([a-z]+)\\s*\\)/i', $sql, $matches, PREG_SET_ORDER);
        $inventory = [];
        foreach ($matches as $match) {
            $cast = 'CAST(' . $match[1] . ' AS ' . strtolower($match[2]) . ')';
            $inventory[$cast] = ($inventory[$cast] ?? 0) + 1;
        }
        ksort($inventory);

        return $inventory;
    }

    private function safeVarExport(#[\SensitiveParameter] mixed $value): string
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

    private function assertNoCanaryOnExceptionSurfaces(
        #[\SensitiveParameter]
        AuditStoreException $exception,
        #[\SensitiveParameter]
        string $canary,
    ): void {
        ob_start();
        var_dump($exception);
        $dump = ob_get_clean();
        self::assertIsString($dump);
        try {
            $serialized = serialize($exception);
        } catch (\Throwable $serializationFailure) {
            $serialized = $serializationFailure->getMessage();
        }
        $trace = $exception->getTrace();
        self::assertIsArray($trace);
        $traceValues = [];
        array_walk_recursive($trace, static function (mixed $value) use (&$traceValues): void {
            if (is_scalar($value)) {
                $traceValues[] = (string) $value;
            }
        });
        $surfaces = [
            $exception->getMessage(),
            $exception->getPrevious()?->getMessage() ?? '',
            implode('|', $traceValues),
            $exception->getTraceAsString(),
            $dump,
            print_r($exception, true),
            $this->safeVarExport($exception),
            $serialized,
            json_encode($exception, JSON_THROW_ON_ERROR),
        ];
        foreach ($surfaces as $index => $surface) {
            self::assertStringNotContainsString(
                $canary,
                is_string($surface) ? $surface : $this->safeVarExport($surface),
                'surface ' . $index,
            );
        }
    }

    private function expectedFindSql(bool $tenant): string
    {
        $predicate = $tenant
            ? 'tenant_type = :tenant_type AND tenant_id = :tenant_id'
            : 'tenant_type IS NULL AND tenant_id IS NULL';

        return (
            'SELECT schema_version, kind, record_id::text AS record_id, partition_id, tenant_type, tenant_id, '
            . 'segment_id::text AS segment_id, sequence, previous_record_hash, content_hash, record_hash, '
            . "to_char(recorded_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS recorded_at, "
            . 'event_category, protected_body, action_type, resource_type, resource_id FROM "blackops"."audit_records" '
            . "WHERE record_id = :record_id AND partition_id = :partition_id AND {$predicate}"
        );
    }

    private function expectedSameRecordSql(): string
    {
        return (
            'SELECT schema_version, kind, record_id::text AS record_id, partition_id, tenant_type, tenant_id, '
            . 'segment_id::text AS segment_id, sequence, previous_record_hash, content_hash, record_hash, '
            . "to_char(recorded_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS recorded_at, "
            . 'event_category, protected_body, action_type, resource_type, resource_id FROM "blackops"."audit_records" '
            . 'WHERE record_id = CAST(:record_id AS uuid) AND partition_id = :partition_id'
        );
    }

    /**
     * @param array<string,mixed> $row
     * @mago-expect lint:excessive-parameter-list
     */
    private function store(
        Connection $connection,
        ?ClockInterface $clock = null,
        ?Uuidv7Generator $uuids = null,
        bool|string $keyFailure = false,
        ?\Closure $onUse = null,
        ?PostgreSqlAuditSchema $schema = null,
        ?NonceSource $nonces = null,
        ?\Closure $onDecrypt = null,
        ?AuditEventCatalog $catalog = null,
        ?string $keyId = null,
    ): PostgreSqlAuditRecordStore {
        $jcs = new AuditJcsCodec();
        $partitions = new AuditPartitionIdDeriver($jcs);
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalog ?? FrameworkAuditEventCatalog::catalog(),
        ]));
        $records = new AuditRecordCodec($validator, $jcs);

        return new PostgreSqlAuditRecordStore(
            $connection,
            $schema ?? new PostgreSqlAuditSchema(),
            $clock ?? new FixtureClock(),
            $uuids ?? new FixtureUuidGenerator(),
            $validator,
            new AuditRecordFinalizer($validator, $records),
            $partitions,
            $records,
            new AuditProtectedBodyCodec(
                new BopdBinaryEnvelopeCodec(),
                new FixtureAuditKeySource($keyFailure, $onUse, $onDecrypt, $keyId ?? 'test-key'),
                $nonces ?? new FixtureNonceSource($onUse),
                new AuditAssociatedData(),
                $records,
                new \BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec($records, $partitions, $jcs),
            ),
            new PostgreSqlAuditStoredRecordHydrator($partitions),
        );
    }

    /** @return array<string,mixed> */
    private function fixture(): array
    {
        $json = file_get_contents(__DIR__ . '/../../../../tests/Fixtures/Audit/audit-canonical-record-v1.json');
        self::assertIsString($json);
        $fixture = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);
        return $fixture;
    }

    /** @return array<string,mixed> */
    private function postgresFixture(): array
    {
        $json = file_get_contents(__DIR__ . '/../../../../tests/Fixtures/Audit/audit-postgresql-store-v1.json');
        self::assertIsString($json);
        $fixture = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);

        return $fixture;
    }

    /** @return array{0:Connection,1:Connection,2:Connection} */
    private function realConnections(): array
    {
        return [$this->realConnection(), $this->realConnection(), $this->realConnection()];
    }

    private function realConnection(): Connection
    {
        $parameters = [
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ];

        return DriverManager::getConnection($parameters);
    }

    private function observedRealConnection(): ObservedFailureConnection
    {
        $base = $this->realConnection();
        $connection = new ObservedFailureConnection($base->getParams(), $base->getDriver(), $base->getConfiguration());
        $base->close();

        return $connection;
    }

    private function observedOperationConnection(): ObservedOperationConnection
    {
        $base = $this->realConnection();
        $connection = new ObservedOperationConnection(
            $base->getParams(),
            $base->getDriver(),
            $base->getConfiguration(),
        );
        $base->close();

        return $connection;
    }

    private function realAdvisoryLockCount(Connection $probe, Connection $connection): int
    {
        $backendPid = $connection->fetchOne('SELECT pg_backend_pid()');

        return (int) $probe->fetchOne(
            "SELECT count(*) FROM pg_locks WHERE pid = :pid AND locktype = 'advisory' AND granted",
            ['pid' => (int) $backendPid],
            ['pid' => ParameterType::INTEGER],
        );
    }

    private function waitForRealAdvisoryLock(Connection $probe, string $ready, ?string $resultFile = null): void
    {
        $deadline = microtime(true) + 5.0;
        $lastWait = null;
        $lastPid = null;
        while (microtime(true) < $deadline) {
            $readyContents = is_file($ready) ? file_get_contents($ready) : '';
            $backendPid = trim(is_string($readyContents) ? $readyContents : '');
            if ($backendPid !== '') {
                $lastPid = $backendPid;
                $wait = $probe->fetchAssociative(
                    'SELECT wait_event_type, wait_event, state, query FROM pg_stat_activity WHERE pid = :pid',
                    ['pid' => (int) $backendPid],
                    ['pid' => ParameterType::INTEGER],
                );
                $lastWait = $wait;
                if (is_array($wait) && $wait['wait_event_type'] === 'Lock' && $wait['wait_event'] === 'advisory') {
                    return;
                }
            }
            usleep(10_000);
        }

        self::fail(
            'The child connection did not reach a PostgreSQL advisory-lock wait within the timeout: '
                . json_encode([
                    'pid' => $lastPid,
                    'wait' => $lastWait,
                    'result' => $resultFile !== null && is_file($resultFile) ? file_get_contents($resultFile) : null,
                ], JSON_THROW_ON_ERROR),
        );
    }

    /** @return array{wait_event_type:string,wait_event:string,state:string,query:string} */
    private function waitForRealDatabaseLock(Connection $probe, string $ready, ?string $resultFile = null): array
    {
        $deadline = microtime(true) + 5.0;
        $lastWait = null;
        $lastPid = null;
        while (microtime(true) < $deadline) {
            $readyContents = is_file($ready) ? file_get_contents($ready) : '';
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
            'The child connection did not reach a PostgreSQL lock wait within the timeout: '
                . json_encode([
                    'pid' => $lastPid,
                    'wait' => $lastWait,
                    'result' => $resultFile !== null && is_file($resultFile) ? file_get_contents($resultFile) : null,
                ], JSON_THROW_ON_ERROR),
        );
    }

    private function lockRealRecord(Connection $connection, PostgreSqlAuditSchema $schema, string $recordId): void
    {
        $connection->fetchOne(
            'SELECT pg_advisory_xact_lock(hashtextextended(:lock_key, 0))',
            ['lock_key' => 'blackops.audit.record.v1:' . $schema->schemaName() . ':' . $recordId],
            ['lock_key' => ParameterType::STRING],
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

    private function createRealAuditSchema(Connection $admin, PostgreSqlAuditSchema $schema): void
    {
        $identifier = $this->realSchemaIdentifier($schema);
        $admin->executeStatement('DROP SCHEMA IF EXISTS ' . $identifier . ' CASCADE');
        $admin->executeStatement('CREATE SCHEMA ' . $identifier);
        foreach ($schema->statements() as $statement) {
            $admin->executeStatement($statement);
        }
    }

    private function cleanupRealAuditSchema(
        ?Connection $admin,
        ?Connection $primary,
        ?Connection $probe,
        PostgreSqlAuditSchema $schema,
    ): void {
        if ($primary instanceof Connection) {
            try {
                if ($primary->isTransactionActive()) {
                    $primary->rollBack();
                }
            } catch (\Throwable) {
            }
        }
        if ($admin instanceof Connection) {
            try {
                $admin->executeStatement('DROP SCHEMA IF EXISTS ' . $this->realSchemaIdentifier($schema) . ' CASCADE');
            } catch (\Throwable) {
            }
        }
        foreach ([$primary, $probe, $admin] as $connection) {
            if (!$connection instanceof Connection) {
                continue;
            }
            try {
                $connection->close();
            } catch (\Throwable) {
            }
        }
    }

    private function installLateFence(Connection $admin, PostgreSqlAuditSchema $schema): void
    {
        $admin->executeStatement(
            'CREATE FUNCTION '
            . $this->realRoutineIdentifier($schema, self::LATE_FENCE_FUNCTION)
            . '() RETURNS trigger LANGUAGE plpgsql AS $fn$ BEGIN RETURN NULL; END; $fn$',
        );
        $admin->executeStatement(
            'CREATE TRIGGER "'
            . self::LATE_FENCE_TRIGGER
            . '" BEFORE UPDATE ON '
            . $schema->partitionStateTable()
            . ' FOR EACH ROW EXECUTE FUNCTION '
            . $this->realRoutineIdentifier($schema, self::LATE_FENCE_FUNCTION)
            . '()',
        );
    }

    private function dropLateFence(Connection $admin, PostgreSqlAuditSchema $schema): void
    {
        $admin->executeStatement(
            'DROP TRIGGER IF EXISTS "' . self::LATE_FENCE_TRIGGER . '" ON ' . $schema->partitionStateTable(),
        );
        $admin->executeStatement(
            'DROP FUNCTION IF EXISTS ' . $this->realRoutineIdentifier($schema, self::LATE_FENCE_FUNCTION) . '()',
        );
    }

    private function installDeferredFailure(Connection $admin, PostgreSqlAuditSchema $schema): void
    {
        $admin->executeStatement(
            'CREATE FUNCTION '
            . $this->realRoutineIdentifier($schema, self::DEFERRED_FAILURE_FUNCTION)
            . '() RETURNS trigger LANGUAGE plpgsql AS $fn$ BEGIN RAISE EXCEPTION '
            . "'"
            . self::DEFERRED_FAILURE_CANARY
            . "'; END; \$fn$",
        );
        $admin->executeStatement(
            'CREATE CONSTRAINT TRIGGER "'
            . self::DEFERRED_FAILURE_TRIGGER
            . '" AFTER INSERT ON '
            . $schema->recordsTable()
            . ' DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION '
            . $this->realRoutineIdentifier($schema, self::DEFERRED_FAILURE_FUNCTION)
            . '()',
        );
    }

    private function dropDeferredFailure(Connection $admin, PostgreSqlAuditSchema $schema): void
    {
        $admin->executeStatement(
            'DROP TRIGGER IF EXISTS "' . self::DEFERRED_FAILURE_TRIGGER . '" ON ' . $schema->recordsTable(),
        );
        $admin->executeStatement(
            'DROP FUNCTION IF EXISTS ' . $this->realRoutineIdentifier($schema, self::DEFERRED_FAILURE_FUNCTION) . '()',
        );
    }

    private function installDeferredSegmentFailure(Connection $admin, PostgreSqlAuditSchema $schema): void
    {
        $admin->executeStatement(
            'CREATE FUNCTION '
            . $this->realRoutineIdentifier($schema, self::DEFERRED_SEGMENT_FAILURE_FUNCTION)
            . '() RETURNS trigger LANGUAGE plpgsql AS $fn$ BEGIN RAISE EXCEPTION '
            . "'"
            . self::DEFERRED_SEGMENT_FAILURE_CANARY
            . "'; END; \$fn$",
        );
        $admin->executeStatement(
            'CREATE CONSTRAINT TRIGGER "'
            . self::DEFERRED_SEGMENT_FAILURE_TRIGGER
            . '" AFTER INSERT ON '
            . $schema->segmentsTable()
            . ' DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION '
            . $this->realRoutineIdentifier($schema, self::DEFERRED_SEGMENT_FAILURE_FUNCTION)
            . '()',
        );
    }

    private function dropDeferredSegmentFailure(Connection $admin, PostgreSqlAuditSchema $schema): void
    {
        $admin->executeStatement(
            'DROP TRIGGER IF EXISTS "' . self::DEFERRED_SEGMENT_FAILURE_TRIGGER . '" ON ' . $schema->segmentsTable(),
        );
        $admin->executeStatement(
            'DROP FUNCTION IF EXISTS '
            . $this->realRoutineIdentifier($schema, self::DEFERRED_SEGMENT_FAILURE_FUNCTION)
            . '()',
        );
    }

    private function installDeferredUniqueFailure(Connection $admin, PostgreSqlAuditSchema $schema): void
    {
        $admin->executeStatement(
            'CREATE FUNCTION '
            . $this->realRoutineIdentifier($schema, self::DEFERRED_UNIQUE_FAILURE_FUNCTION)
            . '() RETURNS trigger LANGUAGE plpgsql AS $fn$ BEGIN RAISE unique_violation USING MESSAGE = '
            . "'"
            . self::DEFERRED_UNIQUE_FAILURE_CANARY
            . "'; END; \$fn$",
        );
        $admin->executeStatement(
            'CREATE CONSTRAINT TRIGGER "'
            . self::DEFERRED_UNIQUE_FAILURE_TRIGGER
            . '" AFTER INSERT ON '
            . $schema->segmentsTable()
            . ' DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION '
            . $this->realRoutineIdentifier($schema, self::DEFERRED_UNIQUE_FAILURE_FUNCTION)
            . '()',
        );
    }

    private function dropDeferredUniqueFailure(Connection $admin, PostgreSqlAuditSchema $schema): void
    {
        $admin->executeStatement(
            'DROP TRIGGER IF EXISTS "' . self::DEFERRED_UNIQUE_FAILURE_TRIGGER . '" ON ' . $schema->segmentsTable(),
        );
        $admin->executeStatement(
            'DROP FUNCTION IF EXISTS '
            . $this->realRoutineIdentifier($schema, self::DEFERRED_UNIQUE_FAILURE_FUNCTION)
            . '()',
        );
    }

    private function forcedRealConnection(): ForcedAdvisoryTokenConnection
    {
        $base = $this->realConnection();
        $connection = new ForcedAdvisoryTokenConnection(
            $base->getParams(),
            $base->getDriver(),
            $base->getConfiguration(),
        );
        $base->close();

        return $connection;
    }

    /** @return list<string> */
    private function realThrowableCodes(\Throwable $failure): array
    {
        $codes = [];
        for ($current = $failure; $current !== null; $current = $current->getPrevious()) {
            $codes[] = (string) $current->getCode();
        }

        return $codes;
    }

    private function realSchemaIdentifier(PostgreSqlAuditSchema $schema): string
    {
        return '"' . $schema->schemaName() . '"';
    }

    private function realRoutineIdentifier(PostgreSqlAuditSchema $schema, string $routine): string
    {
        return $this->realSchemaIdentifier($schema) . '."' . $routine . '"';
    }

    /** @return array<string,mixed> */
    private function realState(Connection $probe, PostgreSqlAuditSchema $schema, string $partition): array
    {
        $row = $probe->fetchAssociative(
            'SELECT partition_id, tenant_type, tenant_id, active_segment_id::text AS active_segment_id, '
            . 'last_sequence::text AS last_sequence, head_record_hash, '
            . 'to_char(last_assigned_recorded_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') '
            . 'AS last_assigned_recorded_at, state_version::text AS state_version FROM '
            . $schema->partitionStateTable()
            . ' WHERE partition_id = :partition_id',
            ['partition_id' => $partition],
            ['partition_id' => ParameterType::STRING],
        );
        self::assertIsArray($row);

        return $row;
    }

    /** @return array<string,mixed> */
    private function realSegment(
        Connection $probe,
        PostgreSqlAuditSchema $schema,
        string $partition,
        string $segmentId,
    ): array {
        $row = $probe->fetchAssociative(
            'SELECT partition_id, segment_id::text AS segment_id, state, first_sequence::text AS first_sequence, '
            . 'final_sequence::text AS final_sequence, head_record_hash, predecessor_record_hash, '
            . 'to_char(opened_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS opened_at, '
            . 'CASE WHEN closed_at IS NULL THEN NULL ELSE to_char(closed_at AT TIME ZONE \'UTC\', '
            . '\'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') END AS closed_at, '
            . 'successor_segment_id::text AS successor_segment_id, close_fencing_token, '
            . 'state_version::text AS state_version FROM '
            . $schema->segmentsTable()
            . ' WHERE partition_id = :partition_id AND segment_id = :segment_id',
            ['partition_id' => $partition, 'segment_id' => $segmentId],
            ['partition_id' => ParameterType::STRING, 'segment_id' => ParameterType::STRING],
        );
        self::assertIsArray($row);

        return $row;
    }

    private function realSegmentCount(Connection $probe, PostgreSqlAuditSchema $schema, string $partition): int
    {
        return (int) $probe->fetchOne(
            'SELECT count(*) FROM ' . $schema->segmentsTable() . ' WHERE partition_id = :partition_id',
            ['partition_id' => $partition],
            ['partition_id' => ParameterType::STRING],
        );
    }

    /** @return array{state:int,segments:int,records:int} */
    private function realPartitionCounts(Connection $probe, PostgreSqlAuditSchema $schema, string $partition): array
    {
        return [
            'state' => (int) $probe->fetchOne(
                'SELECT count(*) FROM ' . $schema->partitionStateTable() . ' WHERE partition_id = :partition_id',
                ['partition_id' => $partition],
                ['partition_id' => ParameterType::STRING],
            ),
            'segments' => (int) $probe->fetchOne(
                'SELECT count(*) FROM ' . $schema->segmentsTable() . ' WHERE partition_id = :partition_id',
                ['partition_id' => $partition],
                ['partition_id' => ParameterType::STRING],
            ),
            'records' => (int) $probe->fetchOne(
                'SELECT count(*) FROM ' . $schema->recordsTable() . ' WHERE partition_id = :partition_id',
                ['partition_id' => $partition],
                ['partition_id' => ParameterType::STRING],
            ),
        ];
    }

    private function realSegmentIdCount(
        Connection $probe,
        PostgreSqlAuditSchema $schema,
        string $partition,
        string $segmentId,
    ): int {
        return (int) $probe->fetchOne(
            'SELECT count(*) FROM '
            . $schema->segmentsTable()
            . ' WHERE partition_id = :partition_id AND segment_id = :segment_id',
            ['partition_id' => $partition, 'segment_id' => $segmentId],
            ['partition_id' => ParameterType::STRING, 'segment_id' => ParameterType::STRING],
        );
    }

    /** @return list<array<string,mixed>> */
    private function realRecords(Connection $probe, PostgreSqlAuditSchema $schema, string $partition): array
    {
        $rows = $probe->fetchAllAssociative(
            'SELECT record_id::text AS record_id, partition_id, tenant_type, tenant_id, '
            . 'segment_id::text AS segment_id, sequence::text AS sequence, previous_record_hash, record_hash, '
            . 'to_char(recorded_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS recorded_at, '
            . 'protected_body FROM '
            . $schema->recordsTable()
            . ' WHERE partition_id = :partition_id ORDER BY sequence ASC',
            ['partition_id' => $partition],
            ['partition_id' => ParameterType::STRING],
        );
        self::assertIsArray($rows);

        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function realExact17Records(Connection $probe, PostgreSqlAuditSchema $schema, string $partition): array
    {
        $rows = $probe->fetchAllAssociative(
            'SELECT schema_version, kind, record_id::text AS record_id, partition_id, tenant_type, tenant_id, '
            . 'segment_id::text AS segment_id, sequence::text AS sequence, previous_record_hash, content_hash, '
            . 'record_hash, to_char(recorded_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') '
            . 'AS recorded_at, event_category, protected_body, action_type, resource_type, resource_id FROM '
            . $schema->recordsTable()
            . ' WHERE partition_id = :partition_id ORDER BY sequence ASC',
            ['partition_id' => $partition],
            ['partition_id' => ParameterType::STRING],
        );
        self::assertIsArray($rows);

        return $rows;
    }

    private function canonical(#[\SensitiveParameter] array $logical): CanonicalAuditLogicalRecord
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog]));
        $validated = $validator->validateLogical(new AuditLogicalRecordCandidate($logical));
        return new CanonicalAuditLogicalRecord($validated);
    }

    private function durableLogical(string $fixtureName): CanonicalAuditLogicalRecord
    {
        return $this->canonical($this->fixture()['ingress'][$fixtureName]['logical']);
    }

    /** @return array{0:AuditStoredRecord,1:CompleteAuditRecord} */
    private function protectedLogicalRecord(
        #[\SensitiveParameter]
        CanonicalAuditLogicalRecord $logical,
        int $sequence = 1,
        ?string $previousRecordHash = null,
    ): array {
        $jcs = new AuditJcsCodec();
        $partitions = new AuditPartitionIdDeriver($jcs);
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            FrameworkAuditEventCatalog::catalog(),
        ]));
        $records = new AuditRecordCodec($validator, $jcs);
        $snapshot = $logical->toArray();
        $tenant = $snapshot['tenant'] ?? null;
        $tenantReference = is_array($tenant) ? new AuditTypedReference($tenant['type'], $tenant['id']) : null;
        $partition = $partitions->derive($tenantReference);
        $complete = $records->complete(
            $logical,
            new AuditChainPosition($partition, '01941f29-7c00-7000-8000-000000000101', $sequence, $previousRecordHash),
        );
        $codec = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            new FixtureAuditKeySource(),
            new FixtureNonceSource(),
            new AuditAssociatedData(),
            $records,
            new \BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec($records, $partitions, $jcs),
        );

        return [$codec->protectRecord($complete), $complete];
    }

    private function storedRecord(string $fixtureName = 'systemGenesis'): AuditStoredRecord
    {
        $fixture = $this->fixture()['records'][$fixtureName];
        $jcs = new AuditJcsCodec();
        $partitions = new AuditPartitionIdDeriver($jcs);
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            FrameworkAuditEventCatalog::catalog(),
        ]));
        $records = new AuditRecordCodec($validator, $jcs);
        $complete = $records->complete(
            $this->canonical($fixture['logical']),
            new AuditChainPosition(...array_values($fixture['chain'])),
        );
        $codec = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            new FixtureAuditKeySource(),
            new FixtureNonceSource(),
            new AuditAssociatedData(),
            $records,
            new \BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec($records, $partitions, $jcs),
        );
        return $codec->protectRecord($complete);
    }

    private function unprotectForTest(
        AuditStoredRecord $stored,
        ?AuditTypedReference $tenant,
        string $partition,
        ?AuditEventCatalog $catalog = null,
    ): CompleteAuditRecord {
        $jcs = new AuditJcsCodec();
        $partitions = new AuditPartitionIdDeriver($jcs);
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            $catalog ?? FrameworkAuditEventCatalog::catalog(),
        ]));
        $records = new AuditRecordCodec($validator, $jcs);
        $codec = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            new FixtureAuditKeySource(),
            new FixtureNonceSource(),
            new AuditAssociatedData(),
            $records,
            new \BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec($records, $partitions, $jcs),
        );

        return $codec->unprotectRecord($stored, $tenant, $partition);
    }

    /** @return array<string,mixed> */
    private function row(#[\SensitiveParameter] AuditStoredRecord $stored, string $fixtureName = 'systemGenesis'): array
    {
        return $this->rowForLogical($stored, $this->canonical($this->fixture()['records'][$fixtureName]['logical']));
    }

    /** @return array<string,mixed> */
    private function rowForLogical(
        #[\SensitiveParameter]
        AuditStoredRecord $stored,
        #[\SensitiveParameter]
        CanonicalAuditLogicalRecord $logicalRecord,
    ): array {
        $value = $stored->toArray();
        $logical = $logicalRecord->toArray();
        $tenant = $value['tenantBinding'];
        $tenantType = is_array($tenant) ? $tenant['type'] ?? null : null;
        $tenantId = is_array($tenant) ? $tenant['id'] ?? null : null;
        return [
            'schema_version' => $value['schemaVersion'],
            'kind' => $value['kind'],
            'record_id' => $value['recordId'],
            'partition_id' => $value['partitionId'],
            'tenant_type' => $tenantType,
            'tenant_id' => $tenantId,
            'segment_id' => $value['segmentId'],
            'sequence' => $value['sequence'],
            'previous_record_hash' => $value['previousRecordHash'],
            'content_hash' => $value['contentHash'],
            'record_hash' => $value['recordHash'],
            'recorded_at' => $value['recordedAt'],
            'event_category' => $value['eventCategory'],
            'protected_body' => $value['protectedBody'],
            'action_type' => $logical['action']['type'],
            'resource_type' => $logical['resource']['type'],
            'resource_id' => $logical['resource']['id'],
        ];
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,mixed>} */
    private function appendRowsForStored(AuditStoredRecord $stored, ?AuditTypedReference $tenant): array
    {
        $value = $stored->toArray();
        $partition = $value['partitionId'];
        $segmentId = $value['segmentId'];
        $head = $value['recordHash'];
        $recordedAt = $value['recordedAt'];
        $boundary = [
            'segment_id' => $segmentId,
            'sequence' => $value['sequence'],
            'previous_record_hash' => $value['previousRecordHash'],
            'record_hash' => $head,
        ];
        $state = [
            'partition_id' => $partition,
            'tenant_type' => $tenant?->type,
            'tenant_id' => $tenant?->id,
            'active_segment_id' => $segmentId,
            'last_sequence' => $value['sequence'],
            'head_record_hash' => $head,
            'last_assigned_recorded_at' => $recordedAt,
            'state_version' => 1,
        ];
        $segment = [
            'partition_id' => $partition,
            'segment_id' => $segmentId,
            'state' => 'open',
            'first_sequence' => 1,
            'final_sequence' => 1,
            'head_record_hash' => $head,
            'predecessor_record_hash' => null,
            'opened_at' => $recordedAt,
            'closed_at' => null,
            'successor_segment_id' => null,
            'close_fencing_token' => null,
            'state_version' => 1,
        ];

        return [$state, $segment, $boundary];
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,mixed>} */
    private function existingAppendRows(string $fixtureName, ?AuditTypedReference $tenant, string $segmentId): array
    {
        $stored = $this->storedRecord($fixtureName);
        $value = $stored->toArray();
        $partition = $value['partitionId'];
        $head = $value['recordHash'];
        $boundary = [
            'segment_id' => $segmentId,
            'sequence' => 1,
            'previous_record_hash' => null,
            'record_hash' => $head,
        ];
        $state = [
            'partition_id' => $partition,
            'tenant_type' => $tenant?->type,
            'tenant_id' => $tenant?->id,
            'active_segment_id' => $segmentId,
            'last_sequence' => 1,
            'head_record_hash' => $head,
            'last_assigned_recorded_at' => $value['recordedAt'],
            'state_version' => 1,
        ];
        $segment = [
            'partition_id' => $partition,
            'segment_id' => $segmentId,
            'state' => 'open',
            'first_sequence' => 1,
            'final_sequence' => 1,
            'head_record_hash' => $head,
            'predecessor_record_hash' => null,
            'opened_at' => $value['recordedAt'],
            'closed_at' => null,
            'successor_segment_id' => null,
            'close_fencing_token' => null,
            'state_version' => 1,
        ];

        return [$state, $segment, $boundary];
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,mixed>,3:array<string,mixed>} */
    private function maximumAppendRowsForStored(AuditStoredRecord $stored, ?AuditTypedReference $tenant): array
    {
        [$state, $segment, $boundary] = $this->appendRowsForStored($stored, $tenant);
        $maximum = 9007199254740991;
        $state['last_sequence'] = $maximum;
        $state['state_version'] = $maximum;
        $segment['final_sequence'] = $maximum;
        $segment['head_record_hash'] = $boundary['record_hash'];
        $segment['state_version'] = $maximum;
        $boundary['sequence'] = $maximum;
        $boundary['previous_record_hash'] = str_repeat('d', 64);
        $minimum = $boundary;
        $minimum['sequence'] = 1;
        $minimum['previous_record_hash'] = null;
        $minimum['record_hash'] = str_repeat('c', 64);

        return [$state, $segment, $boundary, $minimum];
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,mixed>,3:array<string,mixed>} */
    private function maximumAppendRows(string $fixtureName, ?AuditTypedReference $tenant, string $segmentId): array
    {
        [$state, $segment, $boundary] = $this->existingAppendRows($fixtureName, $tenant, $segmentId);
        $maximum = 9007199254740991;
        $state['last_sequence'] = $maximum;
        $state['state_version'] = $maximum;
        $segment['final_sequence'] = $maximum;
        $segment['head_record_hash'] = $boundary['record_hash'];
        $segment['state_version'] = $maximum;
        $boundary['sequence'] = $maximum;
        $boundary['previous_record_hash'] = str_repeat('d', 64);
        $minimum = $boundary;
        $minimum['sequence'] = 1;
        $minimum['previous_record_hash'] = null;
        $minimum['record_hash'] = str_repeat('c', 64);

        return [$state, $segment, $boundary, $minimum];
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    private function genesisAppendRows(): array
    {
        $partition = $this->postgresFixture()['partitions'][0];
        $firstRecord = $partition['records'][0];
        $segmentId = '01941f29-7c00-7000-8000-000000000101';
        $state = [
            'partition_id' => $partition['partitionId'],
            'tenant_type' => null,
            'tenant_id' => null,
            'active_segment_id' => $segmentId,
            'last_sequence' => 0,
            'head_record_hash' => null,
            'last_assigned_recorded_at' => $firstRecord['recorded_at'],
            'state_version' => 1,
        ];
        $segment = [
            'partition_id' => $partition['partitionId'],
            'segment_id' => $segmentId,
            'state' => 'open',
            'first_sequence' => 1,
            'final_sequence' => null,
            'head_record_hash' => null,
            'predecessor_record_hash' => null,
            'opened_at' => $firstRecord['recorded_at'],
            'closed_at' => null,
            'successor_segment_id' => null,
            'close_fencing_token' => null,
            'state_version' => 1,
        ];

        return [$state, $segment];
    }

    private function partition(#[\SensitiveParameter] AuditTypedReference $tenant): string
    {
        return new AuditPartitionIdDeriver(new AuditJcsCodec())->derive($tenant);
    }

    /** @mago-expect lint:excessive-parameter-list */
    private function expectedCloseToken(
        string $partitionId,
        string $closedSegmentId,
        int $finalSequence,
        string $headHash,
        string $successorSegmentId,
        string $closedAt,
    ): string {
        $encodeString = static fn(string $value): string => 's:' . strlen($value) . ':' . $value;
        $encodeBytes = static fn(string $value): string => 'b:' . strlen($value) . ':' . $value;
        $preimage =
            $encodeString('blackops.audit.segment.close.v1')
            . $encodeString($partitionId)
            . $encodeString($closedSegmentId)
            . 'i:'
            . $finalSequence
            . $encodeBytes((string) hex2bin($headHash))
            . $encodeString($successorSegmentId)
            . $encodeString($closedAt);

        return 'aft:v1:' . hash('sha256', $preimage);
    }

    private function draft(): AuditRecordDraft
    {
        $definition = FrameworkAuditEventCatalog::catalog()->events[0];
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
                AuditDelivery::Required,
            ),
            null,
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

    private function maximalDraft(): AuditRecordDraft
    {
        $definition = FrameworkAuditEventCatalog::catalog()->events[0];
        $tuple = $definition->semanticTuples[0];
        $maximum = str_repeat('a', 256);

        return new AuditRecordDraft(
            new AuditRecordEvent(
                $definition->type,
                $definition->version,
                $definition->category,
                FrameworkAuditEventCatalog::catalog()->catalogHash,
            ),
            new AuditRecordAction($maximum, $tuple->actionType, $tuple->phase),
            new AuditRecordActor(
                new AuditTypedReference('user', $maximum),
                new AuditTypedReference('process', $maximum),
            ),
            new AuditRecordResource($tuple->resourceType, $maximum),
            new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
            new AuditRecordPolicy(
                $maximum,
                1,
                $tuple->policyDecision,
                $tuple->policyReasonCode,
                AuditDelivery::Required,
            ),
            null,
            '2026-08-19T00:00:00.000000Z',
            new AuditRecordSource(AuditSourceOwner::Framework, $maximum, AuditSourceRuntime::Cli, $maximum),
            AuditRecordCorrelation::fromArray([
                'operationId' => $maximum,
                'correlationId' => $maximum,
                'causationId' => $maximum,
                'attempt' => null,
                'traceId' => str_repeat('a', 32),
                'spanId' => str_repeat('a', 16),
                'sampled' => true,
            ]),
            new AuditRecordEvidence($definition->evidenceVersion),
        );
    }

    private function minimumBoundaryCatalog(): AuditEventCatalog
    {
        $event = new AuditEventDefinition(
            'a',
            1,
            1,
            AuditEventCategory::Access,
            ['a'],
            [new AuditSemanticTuple(
                'a',
                AuditActionPhase::Denied,
                'a',
                \BlackOps\Core\Audit\AuditOutcomeStatus::Denied,
                'a',
                \BlackOps\Core\Audit\AuditPolicyDecision::Deny,
                'a',
            )],
            [],
            13,
            AuditDelivery::Required,
        );

        return new AuditEventCatalogCodec()->catalog('a', 1, AuditSourceOwner::Application, [$event]);
    }

    private function maximumBoundaryCatalog(): AuditEventCatalog
    {
        $maximum = str_repeat('a', 128);
        $maximumInteger = \BlackOps\Core\Audit\AuditValidationException::MAX_SAFE_INTEGER;
        $fields = [];
        for ($index = 0; $index < 32; $index++) {
            $fields[] = new AuditEvidenceFieldDefinition(
                'field_' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                AuditEvidenceType::String,
                true,
                false,
                256,
                AuditEvidenceClassification::SafeCode,
            );
        }
        $event = new AuditEventDefinition(
            $maximum,
            $maximumInteger,
            $maximumInteger,
            AuditEventCategory::Configuration,
            [$maximum],
            [new AuditSemanticTuple(
                $maximum,
                AuditActionPhase::Succeeded,
                $maximum,
                \BlackOps\Core\Audit\AuditOutcomeStatus::Succeeded,
                $maximum,
                \BlackOps\Core\Audit\AuditPolicyDecision::NotApplicable,
                $maximum,
            )],
            $fields,
            8192,
            AuditDelivery::Required,
        );

        return new AuditEventCatalogCodec()->catalog(
            str_repeat('a', 128),
            $maximumInteger,
            AuditSourceOwner::Application,
            [$event],
        );
    }

    private function minimumBoundaryDraft(AuditEventCatalog $catalog): AuditRecordDraft
    {
        $definition = $catalog->events[0];
        $tuple = $definition->semanticTuples[0];

        return new AuditRecordDraft(
            new AuditRecordEvent($definition->type, $definition->version, $definition->category, $catalog->catalogHash),
            new AuditRecordAction('a', $tuple->actionType, $tuple->phase),
            new AuditRecordActor(null, new AuditTypedReference('a', 'a')),
            new AuditRecordResource($tuple->resourceType, 'a'),
            new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
            new AuditRecordPolicy('a', 1, $tuple->policyDecision, $tuple->policyReasonCode, $definition->delivery),
            null,
            '2026-08-19T00:00:00.000000Z',
            new AuditRecordSource(AuditSourceOwner::Application, 'a', AuditSourceRuntime::Cli, 'a'),
            AuditRecordCorrelation::fromArray([]),
            new AuditRecordEvidence($definition->evidenceVersion),
        );
    }

    private function maximumBoundaryDraft(AuditEventCatalog $catalog, AuditTypedReference $tenant): AuditRecordDraft
    {
        $definition = $catalog->events[0];
        $tuple = $definition->semanticTuples[0];
        $maximum = str_repeat('a', 256);
        $maximumInteger = \BlackOps\Core\Audit\AuditValidationException::MAX_SAFE_INTEGER;
        $evidence = [];
        foreach ($definition->evidenceFields as $field) {
            $evidence[$field->name] = $maximum;
        }
        $jcs = new AuditJcsCodec();
        foreach (array_reverse(array_keys($evidence)) as $fieldName) {
            while (
                strlen($jcs->canonicalJson(['version' => $definition->evidenceVersion, ...$evidence])) > 8192
                && strlen($evidence[$fieldName]) > 1
            ) {
                $evidence[$fieldName] = substr($evidence[$fieldName], 0, -1);
            }
        }
        self::assertSame(8192, strlen($jcs->canonicalJson(['version' => $definition->evidenceVersion, ...$evidence])));

        return new AuditRecordDraft(
            new AuditRecordEvent($definition->type, $definition->version, $definition->category, $catalog->catalogHash),
            new AuditRecordAction($maximum, $tuple->actionType, $tuple->phase),
            new AuditRecordActor(
                new AuditTypedReference($maximum, $maximum),
                new AuditTypedReference($maximum, $maximum),
            ),
            new AuditRecordResource($tuple->resourceType, $maximum),
            new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
            new AuditRecordPolicy(
                $maximum,
                $maximumInteger,
                $tuple->policyDecision,
                $tuple->policyReasonCode,
                $definition->delivery,
            ),
            $tenant,
            '2026-08-19T00:00:00.000000Z',
            new AuditRecordSource(AuditSourceOwner::Application, $maximum, AuditSourceRuntime::Scheduler, $maximum),
            AuditRecordCorrelation::fromArray([
                'operationId' => $maximum,
                'correlationId' => $maximum,
                'causationId' => $maximum,
                'attempt' => ['number' => 9007199254740991, 'kind' => 'initial'],
                'traceId' => str_repeat('a', 32),
                'spanId' => str_repeat('a', 16),
                'sampled' => true,
            ]),
            new AuditRecordEvidence($definition->evidenceVersion, $evidence),
        );
    }

    private function draftWithDelivery(AuditDelivery $delivery): AuditRecordDraft
    {
        $draft = $this->draft();

        return new AuditRecordDraft(
            $draft->event,
            $draft->action,
            $draft->actor,
            $draft->resource,
            $draft->outcome,
            new AuditRecordPolicy(
                $draft->policy->id,
                $draft->policy->version,
                $draft->policy->decision,
                $draft->policy->reasonCode,
                $delivery,
            ),
            $draft->tenant,
            $draft->occurredAt,
            $draft->source,
            $draft->correlation,
            $draft->evidence,
        );
    }

    private function draftForTenant(?AuditTypedReference $tenant): AuditRecordDraft
    {
        $draft = $this->draft();

        return new AuditRecordDraft(
            $draft->event,
            $draft->action,
            $draft->actor,
            $draft->resource,
            $draft->outcome,
            $draft->policy,
            $tenant,
            $draft->occurredAt,
            $draft->source,
            $draft->correlation,
            $draft->evidence,
        );
    }
}

final class ForcedAdvisoryTokenConnection extends Connection
{
    /** @mago-expect lint:no-literal-password */
    public const int TOKEN = 424242;

    /** @var list<string> */
    private array $lockKeyValues = [];

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        if (str_contains($query, 'hashtextextended(:lock_key, 0)')) {
            $lockKey = $params['lock_key'] ?? null;
            if (is_string($lockKey)) {
                $this->lockKeyValues[] = $lockKey;
            }
            $query = str_replace('hashtextextended(:lock_key, 0)', 'CAST(' . self::TOKEN . ' AS bigint)', $query);
        }

        return parent::fetchOne($query, $params, $types);
    }

    /** @return list<string> */
    public function lockKeys(): array
    {
        return $this->lockKeyValues;
    }
}

final class ObservedFailureConnection extends Connection
{
    /** @var list<string> */
    private array $observedCodes = [];

    /** @var list<string> */
    private array $observedStages = [];

    /** @var list<array<string,mixed>> */
    private array $writeTypeMaps = [];

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        if (str_contains($query, 'WITH new_segment')) {
            $this->writeTypeMaps[] = $types;
        }

        try {
            return parent::fetchOne($query, $params, $types);
        } catch (\Throwable $failure) {
            $codes = [];
            for ($current = $failure; $current !== null; $current = $current->getPrevious()) {
                $codes[] = (string) $current->getCode();
            }
            $this->observedCodes = array_values(array_unique(array_merge($this->observedCodes, $codes)));
            $this->observedStages[] = match (true) {
                str_contains($query, 'WITH new_segment') => 'write',
                str_contains($query, 'hashtextextended') => 'lock',
                default => 'other',
            };
            throw $failure;
        }
    }

    /** @return list<string> */
    public function failureCodes(): array
    {
        return $this->observedCodes;
    }

    /** @return list<string> */
    public function failureStages(): array
    {
        return $this->observedStages;
    }

    /** @return list<array<string,mixed>> */
    public function writeTypeMaps(): array
    {
        return $this->writeTypeMaps;
    }
}

/** @mago-expect lint:too-many-methods */
final class ObservedOperationConnection extends Connection
{
    private int $queries = 0;

    /** @var list<string> */
    private array $controls = [];

    /** @var list<bool> */
    private array $autoCommitChanges = [];

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        $this->queries++;

        return parent::fetchAssociative($query, $params, $types);
    }

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        $this->queries++;

        return parent::fetchAllAssociative($query, $params, $types);
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        $this->queries++;

        return parent::fetchOne($query, $params, $types);
    }

    public function beginTransaction(): void
    {
        $this->controls[] = 'beginTransaction';
        parent::beginTransaction();
    }

    public function commit(): void
    {
        $this->controls[] = 'commit';
        parent::commit();
    }

    public function rollBack(): void
    {
        $this->controls[] = 'rollBack';
        parent::rollBack();
    }

    public function createSavepoint(string $savepoint): void
    {
        $this->controls[] = 'createSavepoint';
        parent::createSavepoint($savepoint);
    }

    public function releaseSavepoint(string $savepoint): void
    {
        $this->controls[] = 'releaseSavepoint';
        parent::releaseSavepoint($savepoint);
    }

    public function rollbackSavepoint(string $savepoint): void
    {
        $this->controls[] = 'rollbackSavepoint';
        parent::rollbackSavepoint($savepoint);
    }

    public function setAutoCommit(bool $autoCommit): void
    {
        $this->autoCommitChanges[] = $autoCommit;
        $this->controls[] = 'setAutoCommit(' . ($autoCommit ? 'true' : 'false') . ')';
        parent::setAutoCommit($autoCommit);
    }

    public function close(): void
    {
        $this->controls[] = 'close';
        parent::close();
    }

    public function queryCount(): int
    {
        return $this->queries;
    }

    /** @return list<string> */
    public function controlCalls(): array
    {
        return $this->controls;
    }

    /** @return list<bool> */
    public function autoCommitCalls(): array
    {
        return $this->autoCommitChanges;
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
        $this->onUse?->__invoke('clock');

        return $this->value ?? new \DateTimeImmutable('2026-08-29T12:34:56.123456Z');
    }
}

final class FixtureUuidGenerator implements Uuidv7Generator
{
    public function generate(\DateTimeImmutable $time): string
    {
        return '01941f29-7c00-7000-8000-000000000099';
    }
}

final class SequenceUuidGenerator implements Uuidv7Generator
{
    public int $calls = 0;

    /** @param list<string> $ids */
    public function __construct(
        private array $ids,
        private ?\Closure $onUse = null,
    ) {}

    public function generate(\DateTimeImmutable $time): string
    {
        $this->calls++;
        $this->onUse?->__invoke($this->calls === 1 ? 'segment-uuid' : 'record-uuid');
        $id = array_shift($this->ids);
        if (!is_string($id)) {
            throw new \RuntimeException('UUID sequence exhausted.');
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

final class NulNonceSource implements NonceSource
{
    public function __construct(
        private ?\Closure $onUse = null,
    ) {}

    public function generate(): string
    {
        $this->onUse?->__invoke('nonce');

        return str_repeat('n', 11) . "\0" . str_repeat('n', 12);
    }
}

final class FixtureAuditKeySource implements AuditProtectionKeySource
{
    public function __construct(
        private bool|string $failure = false,
        private ?\Closure $onUse = null,
        private ?\Closure $onDecrypt = null,
        private string $keyId = 'test-key',
    ) {}

    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        $this->onUse?->__invoke('key');
        if ($this->failure !== false) {
            throw new \LogicException(is_string($this->failure) ? $this->failure : 'unexpected key source use');
        }

        return new StorageKey($this->keyId, str_repeat('k', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    }

    public function key(string $keyId, ?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        $this->onUse?->__invoke('key');
        if ($this->failure !== false) {
            throw new \LogicException(is_string($this->failure) ? $this->failure : 'unexpected key source use');
        }
        $this->onDecrypt?->__invoke('decrypt');

        return new StorageKey($keyId, str_repeat('k', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    }
}
