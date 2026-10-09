<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditClosedSegmentGraph;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditClosedSegmentQueries;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditClosedSegmentReader;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditClosedSegmentRecordProof;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Record\AuditChainPosition;
use BlackOps\Internal\Audit\Record\AuditLogicalRecordCandidate;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use BlackOps\Internal\Audit\Record\CompleteAuditRecord;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\StorageProtection\StorageKey;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class PostgreSqlAuditClosedSegmentReaderTest extends TestCase
{
    private const string SEGMENT_ID = '01941f29-7c00-7000-8000-000000000201';

    public function testTransactionRootMarkersAreRequiredBeforeAnyPartitionOrProtectedRead(): void
    {
        foreach ([
            [false, false, 0],
            [true, false, 1],
            [true, true, 2],
        ] as [$autoCommit, $active, $level]) {
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn($autoCommit);
            if ($autoCommit) {
                $connection->expects(self::once())->method('isTransactionActive')->willReturn($active);
                if ($active) {
                    $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn($level);
                }
            }
            $this->expectNoSql($connection);

            try {
                $this->reader($connection)->proveInCurrentTransaction(null, 'system:global', self::SEGMENT_ID);
                self::fail('invalid transaction state was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
                self::assertSame('Audit store operation failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testInvalidIdentityStopsBeforePartitionDerivationAndSql(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(1);
        $this->expectNoSql($connection, ['isAutoCommit', 'isTransactionActive', 'getTransactionNestingLevel']);

        try {
            $this->reader($connection)->proveInCurrentTransaction(null, 'tenant:not-a-partition', self::SEGMENT_ID);
            self::fail('invalid partition was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testWrongTenantBindingStopsBeforePartitionLockAndProtectedReads(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(1);
        $this->expectNoSql($connection, ['isAutoCommit', 'isTransactionActive', 'getTransactionNestingLevel']);

        try {
            $this->reader($connection)->proveInCurrentTransaction(
                new AuditTypedReference('account', 'tenant-a'),
                'system:global',
                self::SEGMENT_ID,
            );
            self::fail('wrong tenant binding was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testInvalidSegmentUuidIsRejectedWithAValueFreeFailure(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(1);
        $this->expectNoSql($connection, ['isAutoCommit', 'isTransactionActive', 'getTransactionNestingLevel']);

        try {
            $this->reader($connection)->proveInCurrentTransaction(
                null,
                'system:global',
                '01941f29-7c00-6000-8000-000000000201',
            );
            self::fail('non-v7 segment was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertSame('Audit store operation failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testGraphAcceptsPopulatedClosedTargetAndImmediateOpenSuccessor(): void
    {
        $targetRow = $this->segmentRow(
            self::SEGMENT_ID,
            'closed',
            1,
            1,
            str_repeat('a', 64),
            null,
            '2030-01-02T03:00:00.000000Z',
            '2030-01-02T03:01:00.000000Z',
            '01941f29-7c00-7000-8000-000000000202',
            2,
        );
        $state = PostgreSqlAuditClosedSegmentGraph::state(
            [
                'partition_id' => 'system:global',
                'tenant_type' => null,
                'tenant_id' => null,
                'active_segment_id' => '01941f29-7c00-7000-8000-000000000203',
                'last_sequence' => '1',
                'head_record_hash' => str_repeat('a', 64),
                'last_assigned_recorded_at' => '2030-01-02T03:01:00.000000Z',
                'state_version' => '3',
            ],
            null,
            'system:global',
        );
        $target = PostgreSqlAuditClosedSegmentGraph::target($targetRow, $state, 'system:global', self::SEGMENT_ID);
        self::assertSame('closed', $target['state']);
        self::assertSame(2, $target['stateVersion']);

        $successor = PostgreSqlAuditClosedSegmentGraph::successor(
            [$this->segmentRow(
                '01941f29-7c00-7000-8000-000000000202',
                'open',
                2,
                null,
                null,
                str_repeat('a', 64),
                '2030-01-02T03:01:00.000000Z',
                null,
                null,
                1,
            )],
            $target,
            'system:global',
            self::SEGMENT_ID,
        );
        self::assertSame(2, $successor['firstSequence']);
        self::assertSame(str_repeat('a', 64), $successor['predecessorRecordHash']);
        PostgreSqlAuditClosedSegmentGraph::endpoint(false, $target, null);
    }

    public function testReaderProvesARealClosedSegmentBeforeReturningTheCalculatedBoundary(): void
    {
        $protection = $this->protectedCodec();
        $complete = $this->completeRecord(1, null);
        $record = $this->storedRow($protection, $complete);
        $head = $complete->integrity()->recordHash;
        $target = $this->segmentRow(
            self::SEGMENT_ID,
            'closed',
            1,
            1,
            $head,
            null,
            '2030-01-02T03:00:00.000000Z',
            '2030-01-02T03:01:00.000000Z',
            '01941f29-7c00-7000-8000-000000000202',
            2,
        );
        $successor = $this->segmentRow(
            '01941f29-7c00-7000-8000-000000000202',
            'open',
            2,
            null,
            null,
            $head,
            '2030-01-02T03:01:00.000000Z',
            null,
            null,
            1,
        );
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(1);
        $connection->expects(self::exactly(2))->method('fetchOne')->willReturnOnConsecutiveCalls(null, false);
        $connection
            ->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls([
                'partition_id' => 'system:global',
                'tenant_type' => null,
                'tenant_id' => null,
                'active_segment_id' => '01941f29-7c00-7000-8000-000000000203',
                'last_sequence' => '1',
                'head_record_hash' => $head,
                'last_assigned_recorded_at' => '2030-01-02T03:01:00.000000Z',
                'state_version' => '3',
            ], $target);
        $connection
            ->expects(self::exactly(3))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls([$successor], [], [$record]);
        $result = new PostgreSqlAuditClosedSegmentReader(
            $connection,
            new PostgreSqlAuditSchema('blackops_audit'),
            new PostgreSqlAuditStoredRecordHydrator(new AuditPartitionIdDeriver(new AuditJcsCodec())),
            $protection,
            new AuditPartitionIdDeriver(new AuditJcsCodec()),
        )->proveInCurrentTransaction(null, 'system:global', self::SEGMENT_ID);

        self::assertSame(
            [
                'partitionId' => 'system:global',
                'segmentId' => self::SEGMENT_ID,
                'state' => 'closed',
                'stateVersion' => 2,
                'firstSequence' => 1,
                'lastSequence' => 1,
                'recordCount' => 1,
                'firstRecordHash' => $head,
                'chainHeadHash' => $head,
                'predecessorRecordHash' => null,
                'previousSegmentId' => null,
                'successorSegmentId' => '01941f29-7c00-7000-8000-000000000202',
                'closeFencingToken' => $target['close_fencing_token'],
                'closedAt' => '2030-01-02T03:01:00.000000Z',
            ],
            $result,
        );
    }

    public function testGraphRejectsVersionChronologyFenceNeighborAndEndpointMutations(): void
    {
        $targetRecord = $this->completeRecord(1, null, false, 'graph-target');
        $targetHead = $targetRecord->integrity()->recordHash;
        $forkRecord = $this->completeRecord(1, null, false, 'graph-fork');
        $forkHead = $forkRecord->integrity()->recordHash;
        $state = [
            'partitionId' => 'system:global',
            'tenantType' => null,
            'tenantId' => null,
            'activeSegmentId' => '01941f29-7c00-7000-8000-000000000203',
            'lastSequence' => 1,
            'headRecordHash' => $targetHead,
            'lastAssignedRecordedAt' => '2030-01-02T03:01:00.000000Z',
            'stateVersion' => 3,
        ];
        $target = $this->target($targetHead);
        $targetRow = $this->segmentRow(
            self::SEGMENT_ID,
            'closed',
            1,
            1,
            $targetHead,
            null,
            '2030-01-02T03:00:00.000000Z',
            '2030-01-02T03:01:00.000000Z',
            '01941f29-7c00-7000-8000-000000000202',
            2,
        );
        foreach ([
            'version1' => static function (array $row): array {
                $row['state_version'] = 1;
                return $row;
            },
            'closedBeforeOpen' => function (array $row): array {
                $row['closed_at'] = '2029-01-02T03:01:00.000000Z';
                return $this->withClosedRowToken($row);
            },
            'wrongToken' => static function (array $row): array {
                $row['close_fencing_token'] = 'aft:v1:' . str_repeat('b', 64);
                return $row;
            },
            'successorSubstitution' => function (array $row): array {
                $row['successor_segment_id'] = '01941f29-7c00-7000-8000-000000000299';
                return $this->withClosedRowToken($row);
            },
        ] as $case => $mutation) {
            if ($case === 'successorSubstitution') {
                $substituted = PostgreSqlAuditClosedSegmentGraph::target(
                    $mutation($targetRow),
                    $state,
                    'system:global',
                    self::SEGMENT_ID,
                );
                self::assertSame('01941f29-7c00-7000-8000-000000000299', $substituted['successorSegmentId']);
                continue;
            }
            $this->assertReaderFailure(
                static function () use ($mutation, $targetRow, $state): void {
                    PostgreSqlAuditClosedSegmentGraph::target(
                        $mutation($targetRow),
                        $state,
                        'system:global',
                        self::SEGMENT_ID,
                    );
                },
                $case,
            );
        }

        $successorRows = $this->segmentRow(
            '01941f29-7c00-7000-8000-000000000202',
            'open',
            2,
            null,
            null,
            $targetHead,
            '2030-01-02T03:01:00.000000Z',
            null,
            null,
            1,
        );
        foreach ([
            'successorSubstitution' => static function (array $row): array {
                $row['segment_id'] = '01941f29-7c00-7000-8000-000000000299';
                return $row;
            },
            'wrongFirst' => static function (array $row): array {
                $row['first_sequence'] = 3;
                return $row;
            },
            'wrongPredecessor' => static function (array $row) use ($forkHead): array {
                $row['predecessor_record_hash'] = $forkHead;
                return $row;
            },
            'neighborOpenedTooEarly' => static function (array $row): array {
                $row['opened_at'] = '2029-01-02T03:01:00.000000Z';
                return $row;
            },
        ] as $case => $mutation) {
            $this->assertReaderFailure(
                static function () use ($mutation, $successorRows, $target): void {
                    PostgreSqlAuditClosedSegmentGraph::successor(
                        [$mutation($successorRows)],
                        $target,
                        'system:global',
                        self::SEGMENT_ID,
                    );
                },
                $case,
            );
        }

        $closedSuccessorId = '01941f29-7c00-7000-8000-000000000202';
        $closedSuccessorRecord = $this->completeRecord(2, $targetHead, false, 'closed-successor');
        $closedSuccessor = $this->segmentRow(
            $closedSuccessorId,
            'closed',
            2,
            2,
            $closedSuccessorRecord->integrity()->recordHash,
            $targetHead,
            '2030-01-02T03:01:00.000000Z',
            '2030-01-02T03:02:00.000000Z',
            '01941f29-7c00-7000-8000-000000000299',
            2,
        );
        $closedTarget = $target;
        $closedTarget['successorSegmentId'] = $closedSuccessorId;
        $closedTarget['closeFencingToken'] = $this->closeToken($closedTarget);
        $acceptedClosedSuccessor = PostgreSqlAuditClosedSegmentGraph::successor(
            [$closedSuccessor],
            $closedTarget,
            'system:global',
            self::SEGMENT_ID,
        );
        self::assertSame('closed', $acceptedClosedSuccessor['state']);
        self::assertSame($closedSuccessorId, $acceptedClosedSuccessor['segmentId']);

        $closedNeighborBeforeOpen = $closedSuccessor;
        $closedNeighborBeforeOpen['closed_at'] = '2030-01-02T03:00:30.000000Z';
        $closedNeighborBeforeOpen = $this->withClosedRowToken($closedNeighborBeforeOpen);
        $this->assertReaderFailure(
            fn(): array => PostgreSqlAuditClosedSegmentGraph::successor(
                [$closedNeighborBeforeOpen],
                $closedTarget,
                'system:global',
                self::SEGMENT_ID,
            ),
            'closed-neighbor-before-open',
        );

        $gapRecord = $this->completeRecord(3, $targetHead, false, 'closed-gap');
        $overlapRecord = $this->completeRecord(1, null, false, 'closed-overlap');
        $forkSuccessor = $this->completeRecord(2, $forkHead, false, 'closed-fork');
        foreach ([
            'closedNeighborGap' => $this->segmentRow(
                $closedSuccessorId,
                'closed',
                3,
                3,
                $gapRecord->integrity()->recordHash,
                $targetHead,
                '2030-01-02T03:01:00.000000Z',
                '2030-01-02T03:02:00.000000Z',
                '01941f29-7c00-7000-8000-000000000299',
                2,
            ),
            'closedNeighborOverlap' => $this->segmentRow(
                $closedSuccessorId,
                'closed',
                1,
                1,
                $overlapRecord->integrity()->recordHash,
                null,
                '2030-01-02T03:01:00.000000Z',
                '2030-01-02T03:02:00.000000Z',
                '01941f29-7c00-7000-8000-000000000299',
                2,
            ),
            'closedNeighborFork' => $this->segmentRow(
                $closedSuccessorId,
                'closed',
                2,
                2,
                $forkSuccessor->integrity()->recordHash,
                $forkHead,
                '2030-01-02T03:01:00.000000Z',
                '2030-01-02T03:02:00.000000Z',
                '01941f29-7c00-7000-8000-000000000299',
                2,
            ),
        ] as $case => $row) {
            $this->assertReaderFailure(
                fn(): array => PostgreSqlAuditClosedSegmentGraph::successor(
                    [$row],
                    $closedTarget,
                    'system:global',
                    self::SEGMENT_ID,
                ),
                $case,
            );
        }

        $previousRecord = $this->completeRecord(1, null, false, 'graph-previous');
        $previousHash = $previousRecord->integrity()->recordHash;
        $targetWithPreviousRecord = $this->completeRecord(2, $previousHash, false, 'graph-target-with-previous');
        $previousRows = $this->segmentRow(
            '01941f29-7c00-7000-8000-000000000200',
            'closed',
            1,
            1,
            $previousHash,
            null,
            '2029-01-02T02:59:00.000000Z',
            '2030-01-02T03:00:00.000000Z',
            self::SEGMENT_ID,
            2,
        );
        $targetWithPrevious = $this->target($targetWithPreviousRecord->integrity()->recordHash, 2, $previousHash, 2);
        $acceptedPrevious = PostgreSqlAuditClosedSegmentGraph::previous(
            [$previousRows],
            $targetWithPrevious,
            'system:global',
            self::SEGMENT_ID,
        );
        self::assertNotNull($acceptedPrevious);
        self::assertSame($previousHash, $acceptedPrevious['headRecordHash']);
        PostgreSqlAuditClosedSegmentGraph::endpoint(
            [
                'segment_id' => $previousRows['segment_id'],
                'sequence' => 1,
                'previous_record_hash' => null,
                'record_hash' => $previousHash,
            ],
            $targetWithPrevious,
            $acceptedPrevious,
        );

        foreach ([
            'predecessorClosedAtMismatch' => function (array $row): array {
                $row['closed_at'] = '2029-01-02T03:00:01.000000Z';
                return $this->withClosedRowToken($row);
            },
            'predecessorVersion1' => static function (array $row): array {
                $row['state_version'] = 1;
                return $row;
            },
            'predecessorSuccessorMismatch' => function (array $row): array {
                $row['successor_segment_id'] = '01941f29-7c00-7000-8000-000000000299';
                return $this->withClosedRowToken($row);
            },
        ] as $case => $mutation) {
            $this->assertReaderFailure(
                static function () use ($mutation, $previousRows, $targetWithPrevious): void {
                    PostgreSqlAuditClosedSegmentGraph::previous(
                        [$mutation($previousRows)],
                        $targetWithPrevious,
                        'system:global',
                        self::SEGMENT_ID,
                    );
                },
                $case,
            );
        }

        foreach ([
            'wrongSegment' => ['segment_id' => '01941f29-7c00-7000-8000-000000000299'],
            'wrongSequence' => ['sequence' => 2],
            'wrongHash' => ['record_hash' => str_repeat('b', 64)],
        ] as $case => $changes) {
            $endpoint = [
                'segment_id' => '01941f29-7c00-7000-8000-000000000200',
                'sequence' => 1,
                'previous_record_hash' => null,
                'record_hash' => $previousHash,
                ...$changes,
            ];
            $this->assertReaderFailure(
                static function () use ($endpoint, $targetWithPrevious, $previousRows): void {
                    PostgreSqlAuditClosedSegmentGraph::endpoint(
                        $endpoint,
                        $targetWithPrevious,
                        $previousRows !== []
                            ? PostgreSqlAuditClosedSegmentGraph::previous(
                                [$previousRows],
                                $targetWithPrevious,
                                'system:global',
                                self::SEGMENT_ID,
                            )
                            : null,
                    );
                },
                $case,
            );
        }
    }

    public function testGraphAcceptsMaximumSafeFinalMinusOneAndRejectsMaximumSafeFinal(): void
    {
        $record = $this->completeRecord(1, null, false, 'capacity-boundary');
        $head = $record->integrity()->recordHash;
        $state = [
            'partitionId' => 'system:global',
            'tenantType' => null,
            'tenantId' => null,
            'activeSegmentId' => '01941f29-7c00-7000-8000-000000000203',
            'lastSequence' => 1,
            'headRecordHash' => $head,
            'lastAssignedRecordedAt' => '2030-01-02T03:01:00.000000Z',
            'stateVersion' => 3,
        ];
        $nearLimit = $this->segmentRow(
            self::SEGMENT_ID,
            'closed',
            1,
            9_007_199_254_740_990,
            $head,
            null,
            '2030-01-02T03:00:00.000000Z',
            '2030-01-02T03:01:00.000000Z',
            '01941f29-7c00-7000-8000-000000000202',
            2,
        );
        $accepted = PostgreSqlAuditClosedSegmentGraph::target($nearLimit, $state, 'system:global', self::SEGMENT_ID);
        self::assertSame(9_007_199_254_740_990, $accepted['finalSequence']);

        $atLimit = $this->segmentRow(
            self::SEGMENT_ID,
            'closed',
            1,
            9_007_199_254_740_991,
            $head,
            null,
            '2030-01-02T03:00:00.000000Z',
            '2030-01-02T03:01:00.000000Z',
            '01941f29-7c00-7000-8000-000000000202',
            2,
        );
        $this->assertProofFailure(
            fn(): array => PostgreSqlAuditClosedSegmentGraph::target(
                $atLimit,
                $state,
                'system:global',
                self::SEGMENT_ID,
            ),
            AuditStoreFailure::ResourceUnavailable,
            'final-sequence-at-maximum-safe-integer',
        );
    }

    /** @return array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} */
    private function target(
        string $head = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        int $final = 1,
        ?string $predecessor = null,
        int $first = 1,
    ): array {
        $segment = [
            'partitionId' => 'system:global',
            'segmentId' => self::SEGMENT_ID,
            'state' => 'closed',
            'firstSequence' => $first,
            'finalSequence' => $final,
            'headRecordHash' => $head,
            'predecessorRecordHash' => $predecessor,
            'openedAt' => '2030-01-02T03:00:00.000000Z',
            'closedAt' => '2030-01-02T03:01:00.000000Z',
            'successorSegmentId' => '01941f29-7c00-7000-8000-000000000202',
            'closeFencingToken' => '',
            'stateVersion' => 2,
        ];
        $segment['closeFencingToken'] = $this->closeToken($segment);
        return $segment;
    }

    /** @return array<string,mixed> */
    private function segmentRow(
        string $segmentId,
        string $state,
        int $first,
        ?int $final,
        ?string $head,
        ?string $predecessor,
        string $opened,
        ?string $closed,
        ?string $successor,
        int $version,
    ): array {
        $row = [
            'partition_id' => 'system:global',
            'segment_id' => $segmentId,
            'state' => $state,
            'first_sequence' => $first,
            'final_sequence' => $final,
            'head_record_hash' => $head,
            'predecessor_record_hash' => $predecessor,
            'opened_at' => $opened,
            'closed_at' => $closed,
            'successor_segment_id' => $successor,
            'close_fencing_token' => null,
            'state_version' => $version,
        ];
        if ($state !== 'open') {
            $normalized = [
                'partitionId' => 'system:global',
                'segmentId' => $segmentId,
                'state' => $state,
                'firstSequence' => $first,
                'finalSequence' => $final ?? 0,
                'headRecordHash' => $head ?? str_repeat('0', 64),
                'predecessorRecordHash' => $predecessor,
                'openedAt' => $opened,
                'closedAt' => $closed ?? $opened,
                'successorSegmentId' => $successor ?? '01941f29-7c00-7000-8000-000000000299',
                'closeFencingToken' => '',
                'stateVersion' => $version,
            ];
            $normalized['closeFencingToken'] = $this->closeToken($normalized);
            $row['close_fencing_token'] = $normalized['closeFencingToken'];
        }
        return $row;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function withClosedRowToken(array $row): array
    {
        $row['close_fencing_token'] = $this->closeToken([
            'partitionId' => $row['partition_id'],
            'segmentId' => $row['segment_id'],
            'finalSequence' => $row['final_sequence'],
            'headRecordHash' => $row['head_record_hash'],
            'successorSegmentId' => $row['successor_segment_id'],
            'closedAt' => $row['closed_at'],
        ]);

        return $row;
    }

    /** @param array{partitionId:string,segmentId:string,finalSequence:int,headRecordHash:string,successorSegmentId:string,closedAt:string} $segment */
    private function closeToken(array $segment): string
    {
        $head = hex2bin($segment['headRecordHash']);
        self::assertIsString($head);
        $domain = 'blackops.audit.segment.close.v1';
        $preimage =
            's:'
            . strlen($domain)
            . ':'
            . $domain
            . 's:'
            . strlen('system:global')
            . ':system:global'
            . 's:'
            . strlen($segment['segmentId'])
            . ':'
            . $segment['segmentId']
            . 'i:'
            . $segment['finalSequence']
            . 'b:'
            . strlen($head)
            . ':'
            . $head
            . 's:'
            . strlen($segment['successorSegmentId'])
            . ':'
            . $segment['successorSegmentId']
            . 's:'
            . strlen($segment['closedAt'])
            . ':'
            . $segment['closedAt'];
        return 'aft:v1:' . hash('sha256', $preimage);
    }

    private function protectedCodec(?AuditProtectionKeySource $keys = null): AuditProtectedBodyCodec
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog]));
        $records = new AuditRecordCodec($validator, new AuditJcsCodec());
        $ingress = new AuditIngressEnvelopeCodec(
            $records,
            new AuditPartitionIdDeriver(new AuditJcsCodec()),
            new AuditJcsCodec(),
        );
        $keys ??= new class implements AuditProtectionKeySource {
            public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
            {
                return new StorageKey('audit-record-key', str_repeat('r', 32));
            }

            public function key(
                string $keyId,
                ?AuditTypedReference $tenant,
                AuditProtectionPurpose $purpose,
            ): StorageKey {
                return new StorageKey($keyId, str_repeat('r', 32));
            }
        };
        return new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            $keys,
            new class implements NonceSource {
                private int $counter = 0;

                public function generate(): string
                {
                    ++$this->counter;
                    return str_repeat(chr($this->counter), 24);
                }
            },
            new AuditAssociatedData(),
            $records,
            $ingress,
        );
    }

    private function completeRecord(
        int $sequence,
        ?string $previous,
        bool $tenant = false,
        string $actionId = 'action-requested',
    ): CompleteAuditRecord {
        $fixture = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../Fixtures/Audit/audit-canonical-record-v1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $logical = $fixture['records'][$tenant ? 'tenantGenesis' : 'systemGenesis']['logical'];
        $logical['recordId'] = sprintf('01941f29-7c00-7000-8000-%012d', $sequence + 10);
        $logical['action']['id'] = $actionId;
        $catalog = FrameworkAuditEventCatalog::catalog();
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog]));
        $records = new AuditRecordCodec($validator, new AuditJcsCodec());
        $partition = $tenant
            ? 'tenant:59f58bfcb476fcc98fe5e95c6bf81a5f8125a721f27e53ed70ba7345ae64eae1'
            : 'system:global';
        return $records->complete(
            new CanonicalAuditLogicalRecord($validator->validateLogical(new AuditLogicalRecordCandidate($logical))),
            new AuditChainPosition($partition, self::SEGMENT_ID, $sequence, $previous),
        );
    }

    /** @return array<string,mixed> */
    private function storedRow(AuditProtectedBodyCodec $protection, CompleteAuditRecord $complete): array
    {
        $stored = $protection->protectRecord($complete)->toArray();
        $logical = $complete->logicalSnapshot();
        return [
            'schema_version' => $stored['schemaVersion'],
            'kind' => $stored['kind'],
            'record_id' => $stored['recordId'],
            'partition_id' => $stored['partitionId'],
            'tenant_type' => is_array($stored['tenantBinding']) ? $stored['tenantBinding']['type'] : null,
            'tenant_id' => is_array($stored['tenantBinding']) ? $stored['tenantBinding']['id'] : null,
            'segment_id' => $stored['segmentId'],
            'sequence' => $stored['sequence'],
            'previous_record_hash' => $stored['previousRecordHash'],
            'content_hash' => $stored['contentHash'],
            'record_hash' => $stored['recordHash'],
            'recorded_at' => $stored['recordedAt'],
            'event_category' => $stored['eventCategory'],
            'protected_body' => $stored['protectedBody'],
            'action_type' => $logical['action']['type'],
            'resource_type' => $logical['resource']['type'],
            'resource_id' => $logical['resource']['id'],
        ];
    }

    /** @return array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} */
    private function recordTarget(
        string $head,
        int $final = 1,
        ?string $predecessor = null,
        int $first = 1,
        string $partition = 'system:global',
    ): array {
        $target = $this->target($head, $final, $predecessor, $first);
        $target['partitionId'] = $partition;
        return $target;
    }

    /** @param list<array<string,mixed>> $rows @param array<string,mixed> $target */
    private function proveRows(array $rows, array $target, AuditProtectedBodyCodec $protection): array
    {
        $connection = $this->connectionForProof(false, $rows);
        $proof = new PostgreSqlAuditClosedSegmentRecordProof(
            new PostgreSqlAuditClosedSegmentQueries($connection, new PostgreSqlAuditSchema('blackops_audit')),
            new PostgreSqlAuditStoredRecordHydrator(new AuditPartitionIdDeriver(new AuditJcsCodec())),
            $protection,
        );
        return $proof->prove(null, 'system:global', self::SEGMENT_ID, $target);
    }

    /** @param list<array<string,mixed>> $rows */
    private function connectionForProof(bool $tenantMismatch, array $rows = []): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn($tenantMismatch ? 1 : false);
        if (!$tenantMismatch) {
            $connection->expects(self::once())->method('fetchAllAssociative')->willReturn($rows);
        } else {
            $connection->expects(self::never())->method('fetchAllAssociative');
        }
        return $connection;
    }

    /** @param callable():array<string,mixed> $operation */
    private function assertProofFailure(callable $operation, AuditStoreFailure $failure, string $case): void
    {
        try {
            $operation();
            self::fail($case . ' unexpectedly succeeded');
        } catch (AuditStoreException $exception) {
            self::assertSame($failure, $exception->failure(), $case);
            self::assertSame('Audit store operation failed.', $exception->getMessage(), $case);
            self::assertNull($exception->getPrevious(), $case);
        }
    }

    /** @param callable():array<string,mixed> $operation */
    private function assertProofFailureWithOwnedFrame(
        callable $operation,
        AuditStoreFailure $failure,
        string $case,
        string $canary,
    ): void {
        try {
            $operation();
            self::fail($case . ' unexpectedly succeeded');
        } catch (AuditStoreException $exception) {
            self::assertSame($failure, $exception->failure(), $case);
            self::assertSame('Audit store operation failed.', $exception->getMessage(), $case);
            self::assertNull($exception->getPrevious(), $case);
            self::assertStringNotContainsString($canary, $exception->getTraceAsString(), $case);
            self::assertStringNotContainsString($canary, (string) json_encode($exception, JSON_THROW_ON_ERROR), $case);
            $this->assertOwnedProofFrameIsSensitiveAndValueFree($exception, $canary);
        }
    }

    private function assertOwnedProofFrameIsSensitiveAndValueFree(AuditStoreException $exception, string $canary): void
    {
        $ownedFrames = array_values(array_filter(
            $exception->getTrace(),
            static fn(array $frame): bool => (
                ($frame['class'] ?? null) === PostgreSqlAuditClosedSegmentRecordProof::class
                && ($frame['function'] ?? null) === 'prove'
            ),
        ));
        self::assertCount(1, $ownedFrames);
        $args = $ownedFrames[0]['args'] ?? null;
        self::assertIsArray($args);
        self::assertCount(4, $args);
        foreach ($args as $argument) {
            self::assertInstanceOf(\SensitiveParameterValue::class, $argument);
        }
        $ownedTrace = print_r($ownedFrames, true) . var_export($ownedFrames, true);
        self::assertStringContainsString('SensitiveParameterValue', $ownedTrace);
        self::assertStringNotContainsString($canary, $ownedTrace);
        $ownedFramesSerializationFailure = null;
        try {
            serialize($ownedFrames);
        } catch (\Throwable $serializationFailure) {
            $ownedFramesSerializationFailure = $serializationFailure;
        }
        self::assertInstanceOf(\Throwable::class, $ownedFramesSerializationFailure);
        self::assertStringContainsString('SensitiveParameterValue', $ownedFramesSerializationFailure->getMessage());
    }

    /** @param callable():void $operation */
    private function assertReaderFailure(callable $operation, string $case): void
    {
        try {
            $operation();
            self::fail($case . ' unexpectedly succeeded');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $case);
            self::assertNull($exception->getPrevious(), $case);
        }
    }

    public function testQueriesBindExactNullableTenantPairsBeforeProtectedReads(): void
    {
        $calls = [];
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::exactly(2))
            ->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $parameters, array $types) use (&$calls): false {
                $calls[] = [$sql, $parameters, $types];
                return false;
            });
        $queries = new PostgreSqlAuditClosedSegmentQueries($connection, new PostgreSqlAuditSchema('blackops_audit'));
        $queries->tenantMismatch(null, 'system:global', self::SEGMENT_ID);
        $tenant = new AuditTypedReference('account', 'tenant-a');
        $queries->tenantMismatch(
            $tenant,
            'tenant:59f58bfcb476fcc98fe5e95c6bf81a5f8125a721f27e53ed70ba7345ae64eae1',
            self::SEGMENT_ID,
        );

        self::assertStringContainsString('IS DISTINCT FROM', $calls[0][0]);
        self::assertNull($calls[0][1]['tenant_type']);
        self::assertNull($calls[0][1]['tenant_id']);
        self::assertSame('account', $calls[1][1]['tenant_type']);
        self::assertSame('tenant-a', $calls[1][1]['tenant_id']);
    }

    public function testRecordProofUsesTheExactTenantPairAndMissingPairStopsBeforeKeyLookup(): void
    {
        $tenant = new AuditTypedReference('account', 'tenant-a');
        $partition = 'tenant:59f58bfcb476fcc98fe5e95c6bf81a5f8125a721f27e53ed70ba7345ae64eae1';
        $protection = $this->protectedCodec();
        $complete = $this->completeRecord(1, null, true);
        $row = $this->storedRow($protection, $complete);
        $connection = $this->connectionForProof(false, [$row]);
        $proof = new PostgreSqlAuditClosedSegmentRecordProof(
            new PostgreSqlAuditClosedSegmentQueries($connection, new PostgreSqlAuditSchema('blackops_audit')),
            new PostgreSqlAuditStoredRecordHydrator(new AuditPartitionIdDeriver(new AuditJcsCodec())),
            $protection,
        );
        $result = $proof->prove(
            $tenant,
            $partition,
            self::SEGMENT_ID,
            $this->recordTarget($complete->integrity()->recordHash, partition: $partition),
        );
        self::assertSame(1, $result['recordCount']);

        $unavailable = $this->protectedCodec(new class implements AuditProtectionKeySource {
            public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
            {
                throw AuditProtectionException::unavailable();
            }

            public function key(
                string $keyId,
                ?AuditTypedReference $tenant,
                AuditProtectionPurpose $purpose,
            ): StorageKey {
                throw AuditProtectionException::unavailable();
            }
        });
        $mismatchConnection = $this->connectionForProof(true);
        $mismatchProof = new PostgreSqlAuditClosedSegmentRecordProof(
            new PostgreSqlAuditClosedSegmentQueries($mismatchConnection, new PostgreSqlAuditSchema('blackops_audit')),
            new PostgreSqlAuditStoredRecordHydrator(new AuditPartitionIdDeriver(new AuditJcsCodec())),
            $unavailable,
        );
        $this->assertProofFailure(
            fn(): array => $mismatchProof->prove(
                null,
                'system:global',
                self::SEGMENT_ID,
                $this->recordTarget($complete->integrity()->recordHash),
            ),
            AuditStoreFailure::IntegrityInvalid,
            'missing-tenant-before-key',
        );
    }

    public function testRecordProofUsesRealHydrationAndAEADAndAcceptsOlderRecordedAt(): void
    {
        $protection = $this->protectedCodec();
        $complete = $this->completeRecord(1, null);
        $row = $this->storedRow($protection, $complete);
        $proof = $this->proveRows([$row], $this->recordTarget($complete->integrity()->recordHash), $protection);

        self::assertSame(1, $proof['recordCount']);
        self::assertSame($complete->integrity()->recordHash, $proof['firstRecordHash']);
        self::assertSame($complete->integrity()->recordHash, $proof['chainHeadHash']);
    }

    public function testRecordProofCoversCompletenessIndexAndChainEdgeMutations(): void
    {
        $protection = $this->protectedCodec();
        $first = $this->completeRecord(1, null);
        $firstRow = $this->storedRow($protection, $first);
        $second = $this->completeRecord(2, $first->integrity()->recordHash);
        $secondRow = $this->storedRow($protection, $second);
        $third = $this->completeRecord(3, $second->integrity()->recordHash);
        $thirdRow = $this->storedRow($protection, $third);
        $target = $this->recordTarget($first->integrity()->recordHash, 1);

        $validChain = $this->proveRows(
            [$firstRow, $secondRow],
            $this->recordTarget($second->integrity()->recordHash, 2),
            $protection,
        );
        self::assertSame(2, $validChain['recordCount']);
        self::assertSame($first->integrity()->recordHash, $validChain['firstRecordHash']);
        self::assertSame($second->integrity()->recordHash, $validChain['chainHeadHash']);

        $changedFirst = $this->completeRecord(1, null, false, 'action-changed');
        $changedFirstRow = $this->storedRow($protection, $changedFirst);
        $brokenSecond = $this->completeRecord(2, str_repeat('b', 64));
        $brokenSecondRow = $this->storedRow($protection, $brokenSecond);
        $forkFirst = $this->completeRecord(1, null, false, 'fork-predecessor');
        $forkSecond = $this->completeRecord(2, $forkFirst->integrity()->recordHash);
        $forkSecondRow = $this->storedRow($protection, $forkSecond);

        $cases = [
            'missing-first' => [
                [$secondRow, $thirdRow],
                $this->recordTarget($third->integrity()->recordHash, 3),
                AuditStoreFailure::IntegrityInvalid,
            ],
            'empty-page' => [[], $target, AuditStoreFailure::SegmentConflict],
            'missing-middle' => [
                [$firstRow, $thirdRow],
                $this->recordTarget($third->integrity()->recordHash, 3),
                AuditStoreFailure::IntegrityInvalid,
            ],
            'missing-last' => [
                [$firstRow, $secondRow],
                $this->recordTarget($third->integrity()->recordHash, 3),
                AuditStoreFailure::IntegrityInvalid,
            ],
            'extra-below' => [
                [$firstRow, $secondRow],
                $this->recordTarget($second->integrity()->recordHash, 2, $first->integrity()->recordHash, 2),
                AuditStoreFailure::IntegrityInvalid,
            ],
            'extra-above' => [[$firstRow, $secondRow], $target, AuditStoreFailure::IntegrityInvalid],
            'duplicate-sequence' => [
                [$firstRow, $firstRow],
                $this->recordTarget($first->integrity()->recordHash, 2),
                AuditStoreFailure::IntegrityInvalid,
            ],
            'reordered-page' => [
                [$secondRow, $firstRow],
                $this->recordTarget($second->integrity()->recordHash, 2),
                AuditStoreFailure::IntegrityInvalid,
            ],
            'broken-following-edge' => [
                [$firstRow, $brokenSecondRow],
                $this->recordTarget($brokenSecond->integrity()->recordHash, 2),
                AuditStoreFailure::IntegrityInvalid,
            ],
            'recomputed-local-body-following-edge' => [
                [$changedFirstRow, $secondRow],
                $this->recordTarget($second->integrity()->recordHash, 2),
                AuditStoreFailure::IntegrityInvalid,
            ],
            'wrong-predecessor' => [
                [$forkSecondRow],
                $this->recordTarget($forkSecond->integrity()->recordHash, 2, $first->integrity()->recordHash, 2),
                AuditStoreFailure::IntegrityInvalid,
            ],
        ];
        foreach ($cases as $case => [$rows, $caseTarget, $failure]) {
            $this->assertProofFailure(
                fn(): array => $this->proveRows($rows, $caseTarget, $protection),
                $failure,
                $case,
            );
        }

        $indexMutation = $firstRow;
        $indexMutation['action_type'] = 'access.operation_status.accessed';
        $this->assertProofFailure(
            fn(): array => $this->proveRows([$indexMutation], $target, $protection),
            AuditStoreFailure::IntegrityInvalid,
            'physical-index-triplet',
        );

        foreach ([
            'physical-action-type' => ['action_type' => 'access.operation_status.accessed'],
            'physical-resource-type' => ['resource_type' => 'different-resource'],
            'physical-resource-id' => ['resource_id' => 'different-resource-id'],
        ] as $case => $changes) {
            $physicalMutation = $firstRow;
            foreach ($changes as $field => $value) {
                $physicalMutation[$field] = $value;
            }
            $this->assertProofFailure(
                fn(): array => $this->proveRows([$physicalMutation], $target, $protection),
                AuditStoreFailure::IntegrityInvalid,
                $case,
            );
        }

        $staleHash = $firstRow;
        $staleHash['record_hash'] = str_repeat('b', 64);
        $this->assertProofFailure(
            fn(): array => $this->proveRows([$staleHash], $target, $protection),
            AuditStoreFailure::ResourceUnavailable,
            'stale-record-hash-with-old-body',
        );

        $headMutation = $this->recordTarget(str_repeat('b', 64));
        $this->assertProofFailure(
            fn(): array => $this->proveRows([$firstRow], $headMutation, $protection),
            AuditStoreFailure::IntegrityInvalid,
            'wrong-head',
        );
    }

    public function testRecordProofPairsUnavailableKeyAndCorruptBodyAndRejectsWrongTenantBeforeKey(): void
    {
        $writer = $this->protectedCodec();
        $complete = $this->completeRecord(1, null);
        $row = $this->storedRow($writer, $complete);
        $corruptBody = $row;
        $lastByte = strlen($corruptBody['protected_body']) - 1;
        $corruptBody['protected_body'] = substr_replace(
            $corruptBody['protected_body'],
            chr(ord($corruptBody['protected_body'][$lastByte]) ^ 1),
            $lastByte,
            1,
        );
        $this->assertProofFailure(
            fn(): array => $this->proveRows(
                [$corruptBody],
                $this->recordTarget($complete->integrity()->recordHash),
                $writer,
            ),
            AuditStoreFailure::ResourceUnavailable,
            'corrupt-protected-body',
        );

        $unavailable = $this->protectedCodec(new class implements AuditProtectionKeySource {
            public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
            {
                throw AuditProtectionException::unavailable();
            }

            public function key(
                string $keyId,
                ?AuditTypedReference $tenant,
                AuditProtectionPurpose $purpose,
            ): StorageKey {
                throw AuditProtectionException::unavailable();
            }
        });
        $this->assertProofFailure(
            fn(): array => $this->proveRows(
                [$row],
                $this->recordTarget($complete->integrity()->recordHash),
                $unavailable,
            ),
            AuditStoreFailure::ResourceUnavailable,
            'unavailable-key',
        );

        $tenantMismatch = $this->connectionForProof(true);
        $proof = new PostgreSqlAuditClosedSegmentRecordProof(
            new PostgreSqlAuditClosedSegmentQueries($tenantMismatch, new PostgreSqlAuditSchema('blackops_audit')),
            new PostgreSqlAuditStoredRecordHydrator(new AuditPartitionIdDeriver(new AuditJcsCodec())),
            $unavailable,
        );
        $this->assertProofFailure(
            fn(): array => $proof->prove(
                null,
                'system:global',
                self::SEGMENT_ID,
                $this->recordTarget($complete->integrity()->recordHash),
            ),
            AuditStoreFailure::IntegrityInvalid,
            'wrong-persisted-tenant-before-decrypt',
        );
    }

    public function testRecordProofRejectsTypedByteaAndTenantMetadataBeforeUnavailableKey(): void
    {
        $writer = $this->protectedCodec();
        $complete = $this->completeRecord(1, null, false, 'metadata-boundary');
        $row = $this->storedRow($writer, $complete);
        $unavailable = $this->protectedCodec(new class implements AuditProtectionKeySource {
            public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
            {
                throw AuditProtectionException::unavailable();
            }

            public function key(
                string $keyId,
                ?AuditTypedReference $tenant,
                AuditProtectionPurpose $purpose,
            ): StorageKey {
                throw AuditProtectionException::unavailable();
            }
        });
        $canary = 'reader-metadata-canary';
        $cases = [
            'typed-protected-body' => static function (array $candidate) use ($canary): array {
                $candidate['protected_body'] = ['canary' => $canary];
                return $candidate;
            },
            'partial-tenant-binding' => static function (array $candidate) use ($canary): array {
                $candidate['tenant_type'] = $canary;
                $candidate['tenant_id'] = null;
                return $candidate;
            },
            'cross-tenant-partition-binding' => static function (array $candidate) use ($canary): array {
                $candidate['partition_id'] = $canary;
                return $candidate;
            },
        ];
        $previousIgnoreArgs = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            foreach ($cases as $case => $mutation) {
                $this->assertProofFailureWithOwnedFrame(
                    fn(): array => $this->proveRows(
                        [$mutation($row)],
                        $this->recordTarget($complete->integrity()->recordHash),
                        $unavailable,
                    ),
                    AuditStoreFailure::IntegrityInvalid,
                    $case,
                    $canary,
                );
            }

            $corruptBytea = $row;
            $lastByte = strlen($corruptBytea['protected_body']) - 1;
            $corruptBytea['protected_body'] = substr_replace(
                $corruptBytea['protected_body'],
                chr(ord($corruptBytea['protected_body'][$lastByte]) ^ 1),
                $lastByte,
                1,
            );
            $this->assertProofFailureWithOwnedFrame(
                fn(): array => $this->proveRows(
                    [$corruptBytea],
                    $this->recordTarget($complete->integrity()->recordHash),
                    $writer,
                ),
                AuditStoreFailure::ResourceUnavailable,
                'corrupt-real-bytea-reaches-protection',
                $canary,
            );
        } finally {
            if ($previousIgnoreArgs !== false) {
                ini_set('zend.exception_ignore_args', $previousIgnoreArgs);
            }
        }
    }

    public function testProofFailureReconstructsAValueFreeExceptionAtTheFirstApplicableBoundary(): void
    {
        $previousIgnoreArgs = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        $protection = $this->protectedCodec();
        $complete = $this->completeRecord(1, null);
        $row = $this->storedRow($protection, $complete);
        $canary = 'record-index-canary';
        $row['action_type'] = $canary;
        try {
            try {
                $this->proveRows([$row], $this->recordTarget($complete->integrity()->recordHash), $protection);
                self::fail('canary index corruption was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
                self::assertSame('Audit store operation failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString($canary, $exception->getTraceAsString());
                try {
                    $serialized = serialize($exception);
                    self::assertStringNotContainsString($canary, $serialized);
                } catch (\Throwable $serializationFailure) {
                    self::assertSame(
                        "Serialization of 'SensitiveParameterValue' is not allowed",
                        $serializationFailure->getMessage(),
                    );
                }
                self::assertStringNotContainsString($canary, (string) json_encode($exception, JSON_THROW_ON_ERROR));
                $this->assertOwnedProofFrameIsSensitiveAndValueFree($exception, $canary);
            }
        } finally {
            if ($previousIgnoreArgs !== false) {
                ini_set('zend.exception_ignore_args', $previousIgnoreArgs);
            }
        }
    }

    private function reader(Connection $connection): PostgreSqlAuditClosedSegmentReader
    {
        $partitions = new AuditPartitionIdDeriver(new AuditJcsCodec());
        return new PostgreSqlAuditClosedSegmentReader(
            $connection,
            new PostgreSqlAuditSchema('blackops_audit'),
            new PostgreSqlAuditStoredRecordHydrator($partitions),
            $this->uninitializedProtection(),
            $partitions,
        );
    }

    private function uninitializedProtection(): AuditProtectedBodyCodec
    {
        /** @var AuditProtectedBodyCodec $protection */
        $protection = new \ReflectionClass(AuditProtectedBodyCodec::class)->newInstanceWithoutConstructor();
        return $protection;
    }

    /** @param list<string> $alreadyConfigured */
    private function expectNoSql(Connection $connection, array $alreadyConfigured = []): void
    {
        foreach ([
            'fetchOne',
            'fetchAssociative',
            'fetchAllAssociative',
            'executeStatement',
            'beginTransaction',
            'commit',
            'rollBack',
            'close',
        ] as $method) {
            if (in_array($method, $alreadyConfigured, true)) {
                continue;
            }
            $connection->expects(self::never())->method($method);
        }
    }
}
