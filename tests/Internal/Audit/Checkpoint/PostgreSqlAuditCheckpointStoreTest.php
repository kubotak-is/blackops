<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Artifact\AuditArtifactSigner;
use BlackOps\Internal\Audit\Artifact\AuditCheckpointCodec;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Checkpoint\AuditCheckpointPreparation;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointBinding;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointRows;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointSchema;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointStore;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointTransaction;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointTrustWorkflow;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\StorageProtection\StorageKey;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;

final class PostgreSqlAuditCheckpointStoreTest extends TestCase
{
    private const string SEGMENT_ID = '01941f29-7c00-7000-8000-000000000301';

    public function testIdentityPreconditionsRejectBeforeOwnedTransactionOrResources(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->expectNoConnectionUse($connection);
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::never())->method('generate');

        try {
            $this->store($connection, $clock, $ids)->prepare(
                null,
                'tenant:not-a-partition',
                self::SEGMENT_ID,
                'checkpoint-key',
                [],
            );
            self::fail('invalid partition was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertSame('Audit store operation failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testInvalidConnectionStateIsRejectedBeforeClockUuidOrSql(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(false);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $this->expectNoConnectionUse($connection, [
            'isAutoCommit',
            'isTransactionActive',
            'getTransactionNestingLevel',
        ]);
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::never())->method('generate');

        try {
            $this->store($connection, $clock, $ids)->prepare(
                null,
                'system:global',
                self::SEGMENT_ID,
                'checkpoint-key',
                [],
            );
            self::fail('caller transaction state was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testPersistRejectsRawCompleteBytesBeforeStartingItsRoot(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->expectNoConnectionUse($connection);
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::never())->method('generate');

        try {
            $this->store($connection, $clock, $ids)->persist(
                null,
                'system:global',
                self::SEGMENT_ID,
                '{"checkpointHash":"complete-bytes-canary"}\n',
                [],
            );
            self::fail('invalid complete bytes were accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertSame('Audit store operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('complete-bytes-canary', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testTransactionRootRejectsEveryCallerMarkerBeforeBeginning(): void
    {
        foreach ([
            'autocommit-off' => [false, false, 0],
            'inactive-nonzero' => [true, false, 2],
            'active-level-one' => [true, true, 1],
            'active-level-two' => [true, true, 2],
        ] as $case => [$autoCommit, $active, $level]) {
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn($autoCommit);
            $connection->expects(self::once())->method('isTransactionActive')->willReturn($active);
            $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn($level);
            $connection->expects(self::never())->method('beginTransaction');
            $connection->expects(self::never())->method('executeStatement');
            $connection->expects(self::never())->method('close');
            try {
                new PostgreSqlAuditCheckpointTransaction($connection)->beginRoot();
                self::fail($case . ' was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure(), $case);
                self::assertNull($exception->getPrevious(), $case);
            }
        }
    }

    public function testTransactionOwnsLiteralIsolationOrderAndMapsAbortWithoutLeakingCause(): void
    {
        $events = [];
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::exactly(2))
            ->method('isAutoCommit')
            ->willReturnCallback(static function () use (&$events): bool {
                $events[] = 'isAutoCommit';
                return true;
            });
        $connection
            ->expects(self::exactly(2))
            ->method('isTransactionActive')
            ->willReturnCallback(static function () use (&$events): bool {
                $events[] = 'isTransactionActive';
                return count(array_filter($events, static fn(string $event): bool => $event === 'isTransactionActive'))
                === 1
                    ? false
                    : true;
            });
        $connection
            ->expects(self::exactly(2))
            ->method('getTransactionNestingLevel')
            ->willReturnCallback(static function () use (&$events): int {
                $events[] = 'getTransactionNestingLevel';
                return count(array_filter(
                    $events,
                    static fn(string $event): bool => $event === 'getTransactionNestingLevel',
                )) === 1
                    ? 0
                    : 1;
            });
        $connection
            ->expects(self::once())
            ->method('beginTransaction')
            ->willReturnCallback(static function () use (&$events): void {
                $events[] = 'beginTransaction';
            });
        $connection
            ->expects(self::once())
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$events): int {
                $events[] = $sql;
                return 0;
            });
        $transaction = new PostgreSqlAuditCheckpointTransaction($connection);
        $transaction->beginRoot();
        self::assertSame(
            [
                'isAutoCommit',
                'isTransactionActive',
                'getTransactionNestingLevel',
                'beginTransaction',
                'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',
            ],
            $events,
        );

        $connection
            ->expects(self::once())
            ->method('rollBack')
            ->willReturnCallback(static function () use (&$events): void {
                $events[] = 'rollBack';
            });
        $failure = new RuntimeException('sql-canary');
        self::assertSame(
            AuditStoreFailure::IntegrityInvalid,
            $transaction->abort(new AuditStoreException(AuditStoreFailure::IntegrityInvalid)),
        );
        self::assertSame('rollBack', $events[array_key_last($events)]);
        self::assertNull($failure->getPrevious());
    }

    public function testTransactionIsolationCommitAndCleanupFailuresCloseExactlyOnce(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isAutoCommit')->willReturn(true);
        $connection->method('isTransactionActive')->willReturnOnConsecutiveCalls(false, true, true);
        $connection->method('getTransactionNestingLevel')->willReturnOnConsecutiveCalls(0, 1, 1);
        $connection->expects(self::once())->method('beginTransaction');
        $connection
            ->expects(self::once())
            ->method('executeStatement')
            ->willThrowException(new RuntimeException('isolation-canary'));
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::once())->method('close');
        try {
            new PostgreSqlAuditCheckpointTransaction($connection)->beginRoot();
            self::fail('isolation failure was hidden');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        $connection = $this->createMock(Connection::class);
        $connection->method('isAutoCommit')->willReturn(true);
        $connection->method('isTransactionActive')->willReturnOnConsecutiveCalls(false, true);
        $connection->method('getTransactionNestingLevel')->willReturnOnConsecutiveCalls(0, 1);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('executeStatement');
        $connection->expects(self::once())->method('commit')->willThrowException(new RuntimeException('commit-canary'));
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::once())->method('close');
        $transaction = new PostgreSqlAuditCheckpointTransaction($connection);
        $transaction->beginRoot();
        try {
            $transaction->commit();
            self::fail('commit failure was hidden');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        $connection = $this->createMock(Connection::class);
        $connection->method('isAutoCommit')->willReturn(true);
        $connection->method('isTransactionActive')->willReturnOnConsecutiveCalls(false, true, false);
        $connection->method('getTransactionNestingLevel')->willReturnOnConsecutiveCalls(0, 1, 0);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('executeStatement');
        $connection
            ->expects(self::once())
            ->method('rollBack')
            ->willThrowException(new RuntimeException('rollback-canary'));
        $connection->expects(self::once())->method('close')->willThrowException(new RuntimeException('close-canary'));
        $transaction = new PostgreSqlAuditCheckpointTransaction($connection);
        $transaction->beginRoot();
        self::assertSame(
            AuditStoreFailure::StorageUnavailable,
            $transaction->abort(new RuntimeException('operation-canary')),
        );
        self::assertSame(
            AuditStoreFailure::StorageUnavailable,
            $transaction->abort(new RuntimeException('second-canary')),
        );
    }

    public function testBindingUsesCanonicalBytesAndRejectsReSignedOrMismatchedCandidates(): void
    {
        $unsigned = $this->checkpointUnsigned();
        $reordered = array_reverse($unsigned, preserve_keys: true);
        $preparedBytes = AuditCheckpointCodec::signingInput($unsigned)['bytes'];
        self::assertSame($preparedBytes, PostgreSqlAuditCheckpointBinding::unsignedBytes($reordered));
        PostgreSqlAuditCheckpointBinding::persistUnsigned($unsigned, $reordered);

        foreach (['checkpointId', 'partitionId', 'segmentId', 'sealedAt', 'signingKeyRef'] as $field) {
            $candidate = $unsigned;
            $candidate[$field] = match ($field) {
                'checkpointId' => '01941f29-7c00-7000-8000-000000000999',
                'partitionId' => 'tenant:' . str_repeat('d', 64),
                'segmentId' => '01941f29-7c00-7000-8000-000000000999',
                'sealedAt' => '2030-01-02T03:04:00.000000Z',
                default => 'replacement-key',
            };
            $this->assertStoreFailure(
                static function () use ($candidate, $unsigned): void {
                    PostgreSqlAuditCheckpointBinding::persistUnsigned($candidate, $unsigned);
                },
                AuditStoreFailure::IntegrityInvalid,
                $field,
            );
        }

        $completeBytes = AuditCheckpointCodec::complete($unsigned, str_repeat("\0", SODIUM_CRYPTO_SIGN_BYTES));
        $complete = AuditCheckpointCodec::decode($completeBytes);
        $existing = [
            'checkpoint_id' => $complete['checkpointId'],
            'checkpoint_hash' => $complete['checkpointHash'],
            'complete_bytes' => $completeBytes,
        ];
        PostgreSqlAuditCheckpointBinding::completeReplay(
            $existing,
            $complete,
            $completeBytes,
            $complete['checkpointId'],
        );
        PostgreSqlAuditCheckpointBinding::sealedReplay($existing, $unsigned, $complete['checkpointId']);
        $this->assertStoreFailure(
            static function () use ($existing, $unsigned, $complete): void {
                $missing = $existing;
                unset($missing['complete_bytes']);
                PostgreSqlAuditCheckpointBinding::sealedReplay($missing, $unsigned, $complete['checkpointId']);
            },
            AuditStoreFailure::IntegrityInvalid,
            'sealed-missing-complete-row',
        );
        $this->assertStoreFailure(
            static function () use ($existing, $unsigned, $complete): void {
                $differentHash = $existing;
                $differentHash['checkpoint_hash'] = str_repeat('b', 64);
                PostgreSqlAuditCheckpointBinding::sealedReplay($differentHash, $unsigned, $complete['checkpointId']);
            },
            AuditStoreFailure::IntegrityInvalid,
            'sealed-different-complete-hash',
        );

        $differentBytes = AuditCheckpointCodec::complete($unsigned, str_repeat("\1", SODIUM_CRYPTO_SIGN_BYTES));
        $different = AuditCheckpointCodec::decode($differentBytes);
        $this->assertStoreFailure(
            static function () use ($existing, $different, $differentBytes, $complete): void {
                PostgreSqlAuditCheckpointBinding::completeReplay(
                    $existing,
                    $different,
                    $differentBytes,
                    $complete['checkpointId'],
                );
            },
            AuditStoreFailure::IntegrityInvalid,
            'same-id-different-complete-bytes',
        );

        $differentUnsigned = $unsigned;
        $differentUnsigned['sealedAt'] = '2030-01-02T03:03:30.000000Z';
        $differentSigningInput = AuditCheckpointCodec::signingInput($differentUnsigned);
        self::assertSame(new AuditJcsCodec()->canonicalJson($differentUnsigned), $differentSigningInput['bytes']);
        $this->assertStoreFailure(
            static function () use ($existing, $differentUnsigned, $complete): void {
                PostgreSqlAuditCheckpointBinding::sealedReplay(
                    $existing,
                    $differentUnsigned,
                    $complete['checkpointId'],
                );
            },
            AuditStoreFailure::IntegrityInvalid,
            'sealed-different-unsigned',
        );
    }

    public function testPreparationBindingRejectsPartitionSegmentTokenRangeHeadAndPredecessorChanges(): void
    {
        $unsigned = $this->checkpointUnsigned();
        $boundary = [
            'partitionId' => 'system:global',
            'segmentId' => self::SEGMENT_ID,
            'closedStateVersion' => 2,
            'closeFencingToken' => 'aft:v1:' . str_repeat('a', 64),
        ];
        $proof = [
            'partitionId' => 'system:global',
            'segmentId' => self::SEGMENT_ID,
            'state' => 'closed',
            'stateVersion' => 2,
            'firstSequence' => 1,
            'lastSequence' => 1,
            'recordCount' => 1,
            'firstRecordHash' => str_repeat('a', 64),
            'chainHeadHash' => str_repeat('a', 64),
            'predecessorRecordHash' => null,
            'previousSegmentId' => null,
            'successorSegmentId' => '01941f29-7c00-7000-8000-000000000302',
            'closeFencingToken' => $boundary['closeFencingToken'],
            'closedAt' => $unsigned['sealedAt'],
        ];
        PostgreSqlAuditCheckpointBinding::preparation($boundary, $proof, $unsigned, null);

        $boundaryMutations = [
            'boundary-partition' => static function (array $value): array {
                $value['partitionId'] = 'tenant:' . str_repeat('d', 64);
                return $value;
            },
            'boundary-segment' => static function (array $value): array {
                $value['segmentId'] = '01941f29-7c00-7000-8000-000000000399';
                return $value;
            },
            'boundary-token' => static function (array $value): array {
                $value['closeFencingToken'] = 'aft:v1:' . str_repeat('b', 64);
                return $value;
            },
        ];
        foreach ($boundaryMutations as $case => $mutation) {
            $this->assertStoreFailure(
                static function () use ($mutation, $boundary, $proof, $unsigned): void {
                    PostgreSqlAuditCheckpointBinding::preparation($mutation($boundary), $proof, $unsigned, null);
                },
                AuditStoreFailure::IntegrityInvalid,
                $case,
            );
        }

        $proofMutations = [
            'proof-range' => static function (array $value): array {
                $value['lastSequence'] = 2;
                $value['recordCount'] = 2;
                return $value;
            },
            'proof-head' => static function (array $value): array {
                $value['firstRecordHash'] = str_repeat('b', 64);
                $value['chainHeadHash'] = str_repeat('b', 64);
                return $value;
            },
        ];
        foreach ($proofMutations as $case => $mutation) {
            $this->assertStoreFailure(
                static function () use ($mutation, $boundary, $proof, $unsigned): void {
                    PostgreSqlAuditCheckpointBinding::preparation($boundary, $mutation($proof), $unsigned, null);
                },
                AuditStoreFailure::IntegrityInvalid,
                $case,
            );
        }

        $unsignedMutations = [
            'unsigned-sealed-at' => static function (array $value): array {
                $value['sealedAt'] = '2030-01-02T03:04:00.000000Z';
                return $value;
            },
        ];
        foreach ($unsignedMutations as $case => $mutation) {
            $this->assertStoreFailure(
                static function () use ($mutation, $boundary, $proof, $unsigned): void {
                    PostgreSqlAuditCheckpointBinding::preparation($boundary, $proof, $mutation($unsigned), null);
                },
                AuditStoreFailure::IntegrityInvalid,
                $case,
            );
        }

        $withPrevious = $unsigned;
        $withPrevious['previousCheckpointHash'] = str_repeat('c', 64);
        $previous = [
            'bytes' => 'previous-complete-bytes',
            'hash' => str_repeat('c', 64),
            'value' => [],
        ];
        PostgreSqlAuditCheckpointBinding::preparation($boundary, $proof, $withPrevious, $previous);
        $this->assertStoreFailure(
            static function () use ($boundary, $proof, $withPrevious, $previous): void {
                $previous['hash'] = str_repeat('d', 64);
                PostgreSqlAuditCheckpointBinding::preparation($boundary, $proof, $withPrevious, $previous);
            },
            AuditStoreFailure::IntegrityInvalid,
            'predecessor-checkpoint-hash',
        );
    }

    public function testVisibleGeneratedCheckpointIdCollisionIsObservedWithoutReplacementLookup(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->willReturn(1);
        $rows = new PostgreSqlAuditCheckpointRows(
            $connection,
            new PostgreSqlAuditCheckpointSchema(new PostgreSqlMigrationSchema('blackops_audit')),
            new PostgreSqlAuditSchema('blackops_audit'),
        );
        self::assertTrue($rows->checkpointIdExists('01941f29-7c00-7000-8000-000000000501'));

        $predecessorSql = null;
        $predecessorConnection = $this->createMock(Connection::class);
        $predecessorConnection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->willReturnCallback(static function (string $sql) use (&$predecessorSql): false {
                $predecessorSql = $sql;
                return false;
            });
        new PostgreSqlAuditCheckpointRows(
            $predecessorConnection,
            new PostgreSqlAuditCheckpointSchema(new PostgreSqlMigrationSchema('blackops_audit')),
            new PostgreSqlAuditSchema('blackops_audit'),
        )->predecessorSegment('system:global', '01941f29-7c00-7000-8000-000000000502');
        self::assertIsString($predecessorSql);
        self::assertStringContainsString('segment_id = CAST(:segment_id AS uuid)', $predecessorSql);
        self::assertStringNotContainsString('successor_segment_id = CAST(:segment_id AS uuid)', $predecessorSql);
    }

    public function testStoreEntryPreparesRetriesAndPersistsANonGenesisCheckpoint(): void
    {
        $fixture = $this->fixture();
        $canonical = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../Fixtures/Audit/audit-canonical-record-v1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($canonical);
        self::assertIsArray($canonical['records'] ?? null);
        $jcs = new AuditJcsCodec();
        $partitions = new AuditPartitionIdDeriver($jcs);
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            FrameworkAuditEventCatalog::catalog(),
        ]));
        $recordCodec = new AuditRecordCodec($validator, $jcs);
        $protection = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            new class implements AuditProtectionKeySource {
                public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
                {
                    return new StorageKey('test-key', str_repeat('k', 32));
                }

                public function key(
                    string $keyId,
                    ?AuditTypedReference $tenant,
                    AuditProtectionPurpose $purpose,
                ): StorageKey {
                    return new StorageKey($keyId, str_repeat('k', 32));
                }
            },
            new class implements NonceSource {
                private int $counter = 0;

                public function generate(): string
                {
                    return substr(hash('sha256', (string) ++$this->counter, true), 0, 24);
                }
            },
            new AuditAssociatedData(),
            $recordCodec,
            new AuditIngressEnvelopeCodec($recordCodec, $partitions, $jcs),
        );
        $records = $canonical['records'];
        self::assertIsArray($records['tenantGenesis'] ?? null);
        self::assertIsArray($records['tenantNonGenesis'] ?? null);
        self::assertIsString($records['tenantGenesis']['completeBytes'] ?? null);
        self::assertIsString($records['tenantNonGenesis']['completeBytes'] ?? null);
        $genesisRecord = $recordCodec->decode($records['tenantGenesis']['completeBytes']);
        $targetRecord = $recordCodec->decode($records['tenantNonGenesis']['completeBytes']);
        $storedRow = static function (array $stored, string $actionType): array {
            $tenant = $stored['tenantBinding'];
            return [
                'schema_version' => $stored['schemaVersion'],
                'kind' => $stored['kind'],
                'record_id' => $stored['recordId'],
                'partition_id' => $stored['partitionId'],
                'tenant_type' => is_array($tenant) ? $tenant['type'] : null,
                'tenant_id' => is_array($tenant) ? $tenant['id'] : null,
                'segment_id' => $stored['segmentId'],
                'sequence' => $stored['sequence'],
                'previous_record_hash' => $stored['previousRecordHash'],
                'content_hash' => $stored['contentHash'],
                'record_hash' => $stored['recordHash'],
                'recorded_at' => $stored['recordedAt'],
                'event_category' => $stored['eventCategory'],
                'protected_body' => $stored['protectedBody'],
                'action_type' => $actionType,
                'resource_type' => 'operation',
                'resource_id' => 'operation-1',
            ];
        };
        $genesisStored = $storedRow(
            $protection->protectRecord($genesisRecord)->toArray(),
            'access.operation_status.requested',
        );
        $targetStored = $storedRow(
            $protection->protectRecord($targetRecord)->toArray(),
            'access.operation_status.accessed',
        );

        $tenant = new AuditTypedReference('account', 'tenant-a');
        $partition = 'tenant:59f58bfcb476fcc98fe5e95c6bf81a5f8125a721f27e53ed70ba7345ae64eae1';
        $previousSegmentId = '01941f29-7c00-7000-8000-000000000101';
        $segmentId = '01941f29-7c00-7000-8000-000000000102';
        $successorSegmentId = '01941f29-7c00-7000-8000-000000000103';
        $previousClosedAt = '2030-01-02T03:02:00.000000Z';
        $closedAt = '2030-01-02T03:03:00.000000Z';
        $verifiedAt = '2030-01-02T03:04:05.123456Z';
        $genesisHash = $records['tenantGenesis']['recordHash'];
        $targetHash = $records['tenantNonGenesis']['recordHash'];
        self::assertIsString($genesisHash);
        self::assertIsString($targetHash);
        self::assertSame($genesisHash, $genesisStored['record_hash']);
        $closeToken = static function (
            string $partitionId,
            string $segmentId,
            int $finalSequence,
            string $headRecordHash,
            string $successorSegmentId,
            string $closedAt,
        ): string {
            $head = hex2bin($headRecordHash);
            if ($head === false) {
                throw new RuntimeException('invalid fixture hash');
            }
            $domain = 'blackops.audit.segment.close.v1';
            $preimage =
                's:'
                . strlen($domain)
                . ':'
                . $domain
                . 's:'
                . strlen($partitionId)
                . ':'
                . $partitionId
                . 's:'
                . strlen($segmentId)
                . ':'
                . $segmentId
                . 'i:'
                . $finalSequence
                . 'b:'
                . strlen($head)
                . ':'
                . $head
                . 's:'
                . strlen($successorSegmentId)
                . ':'
                . $successorSegmentId
                . 's:'
                . strlen($closedAt)
                . ':'
                . $closedAt;

            return 'aft:v1:' . hash('sha256', $preimage);
        };
        $previousToken = $closeToken($partition, $previousSegmentId, 1, $genesisHash, $segmentId, $previousClosedAt);
        $targetToken = $closeToken($partition, $segmentId, 2, $targetHash, $successorSegmentId, $closedAt);
        $stateRow = [
            'partition_id' => $partition,
            'tenant_type' => 'account',
            'tenant_id' => 'tenant-a',
            'active_segment_id' => $successorSegmentId,
            'last_sequence' => 2,
            'head_record_hash' => $targetHash,
            'last_assigned_recorded_at' => $closedAt,
            'state_version' => 2,
        ];
        $previousSegmentRow = [
            'partition_id' => $partition,
            'segment_id' => $previousSegmentId,
            'state' => 'closed',
            'first_sequence' => 1,
            'final_sequence' => 1,
            'head_record_hash' => $genesisHash,
            'predecessor_record_hash' => null,
            'opened_at' => '2030-01-02T03:01:00.000000Z',
            'closed_at' => $previousClosedAt,
            'successor_segment_id' => $segmentId,
            'close_fencing_token' => $previousToken,
            'state_version' => 2,
        ];
        $targetSegmentRow = [
            'partition_id' => $partition,
            'segment_id' => $segmentId,
            'state' => 'closed',
            'first_sequence' => 2,
            'final_sequence' => 2,
            'head_record_hash' => $targetHash,
            'predecessor_record_hash' => $genesisHash,
            'opened_at' => $previousClosedAt,
            'closed_at' => $closedAt,
            'successor_segment_id' => $successorSegmentId,
            'close_fencing_token' => $targetToken,
            'state_version' => 2,
        ];
        $successorSegmentRow = [
            'partition_id' => $partition,
            'segment_id' => $successorSegmentId,
            'state' => 'open',
            'first_sequence' => 3,
            'final_sequence' => null,
            'head_record_hash' => null,
            'predecessor_record_hash' => $targetHash,
            'opened_at' => $closedAt,
            'closed_at' => null,
            'successor_segment_id' => null,
            'close_fencing_token' => null,
            'state_version' => 1,
        ];
        $endpointRow = [
            'segment_id' => $previousSegmentId,
            'sequence' => 1,
            'previous_record_hash' => null,
            'record_hash' => $genesisHash,
        ];
        $seed = hex2bin((string) $fixture['seeds']['checkpoint']);
        self::assertIsString($seed);
        $previousUnsigned = [
            'schemaVersion' => 1,
            'kind' => 'audit_checkpoint',
            'checkpointId' => '01941f29-7c00-7000-8000-000000000901',
            'partitionId' => $partition,
            'segmentId' => $previousSegmentId,
            'firstSequence' => 1,
            'lastSequence' => 1,
            'recordCount' => 1,
            'firstRecordHash' => $genesisHash,
            'chainHeadHash' => $genesisHash,
            'previousCheckpointHash' => null,
            'sealedAt' => $previousClosedAt,
            'signedAt' => '2030-01-02T03:02:01.000000Z',
            'hashSuite' => 'audit-sha256-jcs-v1',
            'signatureAlgorithm' => 'ed25519-v1',
            'signingKeyRef' => 'checkpoint-key',
        ];
        $previousComplete = AuditArtifactSigner::checkpoint(
            $previousUnsigned,
            $seed,
            $fixture['trust']['snapshot'],
            $previousUnsigned['signedAt'],
        );
        $previousDecoded = AuditCheckpointCodec::decode($previousComplete);
        $fingerprint = $fixture['trust']['snapshot']['entries'][1]['publicKeyFingerprint'];
        $previousCheckpointRow = [
            'checkpoint_id' => $previousUnsigned['checkpointId'],
            'partition_id' => $partition,
            'segment_id' => $previousSegmentId,
            'closed_state_version' => '2',
            'close_fencing_token' => $previousToken,
            'unsigned_checkpoint' => AuditCheckpointCodec::signingInput($previousUnsigned)['bytes'],
            'signing_key_fingerprint' => $fingerprint,
            'checkpoint_hash' => $previousDecoded['checkpointHash'],
            'complete_bytes' => $previousComplete,
        ];
        $targetPreparationRow = false;
        $targetCompleteRow = false;
        $transactionActive = false;
        $events = [];
        $connection = $this->createStub(Connection::class);
        $connection->method('isAutoCommit')->willReturn(true);
        $connection
            ->method('isTransactionActive')
            ->willReturnCallback(static function () use (&$transactionActive): bool {
                return $transactionActive;
            });
        $connection
            ->method('getTransactionNestingLevel')
            ->willReturnCallback(static function () use (&$transactionActive): int {
                return $transactionActive ? 1 : 0;
            });
        $connection
            ->method('beginTransaction')
            ->willReturnCallback(static function () use (&$transactionActive, &$events): void {
                $transactionActive = true;
                $events[] = 'begin';
            });
        $connection
            ->method('commit')
            ->willReturnCallback(static function () use (&$transactionActive, &$events): void {
                $transactionActive = false;
                $events[] = 'commit';
            });
        $connection
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $parameters = []) use (
                &$targetPreparationRow,
                &$targetCompleteRow,
                &$events,
            ): int {
                if (str_starts_with($sql, 'SET TRANSACTION')) {
                    $events[] = 'repeatable-read';
                    return 0;
                }
                if (str_contains($sql, 'audit_checkpoints')) {
                    $targetCompleteRow = [
                        'checkpoint_id' => $parameters['checkpoint_id'],
                        'checkpoint_hash' => $parameters['checkpoint_hash'],
                        'complete_bytes' => $parameters['complete_bytes'],
                    ];
                    $events[] = 'insert-complete';
                    return 1;
                }
                if (str_contains($sql, 'audit_checkpoint_preparations')) {
                    $targetPreparationRow = [
                        'checkpoint_id' => $parameters['checkpoint_id'],
                        'partition_id' => $parameters['partition_id'],
                        'segment_id' => $parameters['segment_id'],
                        'closed_state_version' => $parameters['closed_state_version'],
                        'close_fencing_token' => $parameters['close_fencing_token'],
                        'unsigned_checkpoint' => $parameters['unsigned_checkpoint'],
                        'signing_key_fingerprint' => $parameters['signing_key_fingerprint'],
                    ];
                    $events[] = 'insert-preparation';
                    return 1;
                }
                return 0;
            });
        $connection
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql): int|false {
                if (str_contains($sql, 'pg_advisory_xact_lock')) {
                    return 1;
                }
                return false;
            });
        $connection
            ->method('fetchAssociative')
            ->willReturnCallback(static function (string $sql, array $parameters = []) use (
                $stateRow,
                $endpointRow,
                $previousSegmentRow,
                $targetSegmentRow,
                $previousCheckpointRow,
                &$targetPreparationRow,
                &$targetCompleteRow,
                $previousSegmentId,
            ): array|false {
                if (str_contains($sql, 'audit_partition_state')) {
                    return $stateRow;
                }
                if (array_key_exists('sequence', $parameters)) {
                    return $endpointRow;
                }
                if (str_contains($sql, 'unsigned_checkpoint')) {
                    if (($parameters['segment_id'] ?? null) === $previousSegmentId) {
                        return $previousCheckpointRow;
                    }
                    return $targetPreparationRow;
                }
                if (str_contains($sql, 'checkpoint_hash, complete_bytes')) {
                    return $targetCompleteRow;
                }
                if (str_contains($sql, 'audit_segments')) {
                    if (($parameters['segment_id'] ?? null) === $previousSegmentId) {
                        return $previousSegmentRow;
                    }
                    return $targetSegmentRow;
                }
                return false;
            });
        $connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(static function (string $sql, array $parameters = []) use (
                $previousSegmentRow,
                $successorSegmentRow,
                $targetStored,
            ): array {
                if (str_contains($sql, 'successor_segment_id =')) {
                    return [$previousSegmentRow];
                }
                if (str_contains($sql, 'audit_segments')) {
                    return [$successorSegmentRow];
                }
                if (str_contains($sql, 'audit_records')) {
                    return [$targetStored];
                }
                return [];
            });
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::exactly(2))->method('now')->willReturn(new DateTimeImmutable($verifiedAt));
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::once())->method('generate')->willReturn('01941f29-7c00-7000-8000-000000000902');
        $store = new PostgreSqlAuditCheckpointStore(
            $connection,
            new PostgreSqlAuditCheckpointSchema(new PostgreSqlMigrationSchema('blackops_audit')),
            new PostgreSqlAuditStoredRecordHydrator($partitions),
            $protection,
            $partitions,
            $clock,
            $ids,
        );
        $trust = $fixture['trust']['snapshot'];
        $preparation = $store->prepare($tenant, $partition, $segmentId, 'checkpoint-key', $trust);
        self::assertSame($segmentId, $preparation->unsigned()['segmentId']);
        self::assertSame($previousDecoded['checkpointHash'], $preparation->unsigned()['previousCheckpointHash']);
        self::assertSame(
            $preparation->unsigned(),
            $store->prepare($tenant, $partition, $segmentId, 'checkpoint-key', $trust)->unsigned(),
        );
        $complete = AuditArtifactSigner::checkpoint(
            $preparation->unsigned(),
            $seed,
            $trust,
            $preparation->unsigned()['signedAt'],
        );
        $store->persist($tenant, $partition, $segmentId, $complete, $trust);
        $store->persist($tenant, $partition, $segmentId, $complete, $trust);
        self::assertSame(4, count(array_filter($events, static fn(string $event): bool => $event === 'begin')));
        self::assertSame(4, count(array_filter($events, static fn(string $event): bool => $event === 'commit')));
        self::assertSame(
            1,
            count(array_filter($events, static fn(string $event): bool => $event === 'insert-preparation')),
        );
        self::assertSame(
            1,
            count(array_filter($events, static fn(string $event): bool => $event === 'insert-complete')),
        );
    }

    public function testStoreEntryRejectsTargetVersionAndChronologyBeforeTrustResources(): void
    {
        foreach ([
            'target-version-one' => static function (array $row): array {
                $row['state_version'] = 1;
                return $row;
            },
            'target-closed-before-open' => static function (array $row): array {
                $row['opened_at'] = '2030-01-02T03:04:00.000000Z';
                return $row;
            },
        ] as $case => $mutate) {
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $ids = $this->createMock(Uuidv7Generator::class);
            $ids->expects(self::never())->method('generate');
            $harness = $this->validStoreEntryFixture($clock, $ids);
            $target = $harness['targetSegment']();
            self::assertIsArray($target);
            $harness['setTargetSegment']($mutate($target));
            $beforeState = $harness['state']();
            $beforeTarget = $harness['targetSegment']();
            $beforeRecords = $harness['targetRecords']();

            $this->assertStoreFailure(
                fn(): mixed => $harness['store']->prepare(
                    $harness['tenant'],
                    $harness['partition'],
                    $harness['segmentId'],
                    'checkpoint-key',
                    $harness['trust'],
                ),
                AuditStoreFailure::IntegrityInvalid,
                $case,
            );
            $events = $harness['events']();
            self::assertNotContains('insert-preparation', $events, $case);
            self::assertNotContains('insert-complete', $events, $case);
            self::assertSame($beforeState, $harness['state'](), $case);
            self::assertSame($beforeTarget, $harness['targetSegment'](), $case);
            self::assertSame($beforeRecords, $harness['targetRecords'](), $case);
            self::assertSame([], $harness['unexpectedStatements'](), $case);
        }
    }

    public function testStoreEntryRejectsClosedNeighborAndPredecessorChronologyBeforeTrustResources(): void
    {
        foreach (['closed-neighbor-before-open', 'predecessor-close-before-target-open'] as $case) {
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::never())->method('now');
            $ids = $this->createMock(Uuidv7Generator::class);
            $ids->expects(self::never())->method('generate');
            $harness = $this->validStoreEntryFixture($clock, $ids);

            if ($case === 'closed-neighbor-before-open') {
                $successor = $harness['successorSegment']();
                $target = $harness['targetSegment']();
                self::assertIsArray($successor);
                self::assertIsArray($target);
                $successor['state'] = 'closed';
                $successor['final_sequence'] = 3;
                $successor['head_record_hash'] = str_repeat('d', 64);
                $successor['closed_at'] = '2030-01-02T03:02:30.000000Z';
                $successor['successor_segment_id'] = '01941f29-7c00-7000-8000-000000000104';
                $successor['close_fencing_token'] = $harness['closeToken'](
                    $harness['partition'],
                    $successor['segment_id'],
                    $successor['final_sequence'],
                    $successor['head_record_hash'],
                    $successor['successor_segment_id'],
                    $successor['closed_at'],
                );
                $successor['state_version'] = 2;
                $harness['setSuccessorSegment']($successor);
            } else {
                $target = $harness['targetSegment']();
                self::assertIsArray($target);
                $target['opened_at'] = '2030-01-02T03:02:30.000000Z';
                $harness['setTargetSegment']($target);
            }

            $beforeState = $harness['state']();
            $beforeTarget = $harness['targetSegment']();
            $beforeRecords = $harness['targetRecords']();
            $beforeSuccessor = $harness['successorSegment']();
            $this->assertStoreFailure(
                fn(): mixed => $harness['store']->prepare(
                    $harness['tenant'],
                    $harness['partition'],
                    $harness['segmentId'],
                    'checkpoint-key',
                    $harness['trust'],
                ),
                AuditStoreFailure::IntegrityInvalid,
                $case,
            );
            self::assertSame($beforeState, $harness['state'](), $case);
            self::assertSame($beforeTarget, $harness['targetSegment'](), $case);
            self::assertSame($beforeRecords, $harness['targetRecords'](), $case);
            self::assertSame($beforeSuccessor, $harness['successorSegment'](), $case);
            self::assertSame([], $harness['unexpectedStatements'](), $case);
            self::assertNotContains('insert-preparation', $harness['events'](), $case);
            self::assertNotContains('insert-complete', $harness['events'](), $case);
        }
    }

    public function testStoreEntryAcceptsIndependentRotatedTrustAndBindsExistingPreparationRetries(): void
    {
        $fixture = $this->fixture();
        $rotated = $this->trustWithCheckpointKey($fixture['trust']['snapshot'], 'checkpoint-key-rotated', "\x77");
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::exactly(2))
            ->method('now')
            ->willReturn(new DateTimeImmutable($fixture['times']['verifiedAt']));
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::once())->method('generate')->willReturn('01941f29-7c00-7000-8000-000000001001');
        $harness = $this->validStoreEntryFixture($clock, $ids);
        $preparation = $harness['store']->prepare(
            $harness['tenant'],
            $harness['partition'],
            $harness['segmentId'],
            $rotated['keyRef'],
            $rotated['snapshot'],
        );
        self::assertSame($rotated['keyRef'], $preparation->unsigned()['signingKeyRef']);
        self::assertSame(
            $rotated['snapshot']['entries'][2]['publicKeyFingerprint'],
            $preparation->signingKeyFingerprint(),
        );

        $this->assertStoreFailure(
            fn(): mixed => $harness['store']->prepare(
                $harness['tenant'],
                $harness['partition'],
                $harness['segmentId'],
                'checkpoint-key',
                $fixture['trust']['snapshot'],
            ),
            AuditStoreFailure::IdempotencyConflict,
            'retry-changed-key-ref',
        );
        $complete = AuditArtifactSigner::checkpoint(
            $preparation->unsigned(),
            $rotated['seed'],
            $rotated['snapshot'],
            $preparation->unsigned()['signedAt'],
        );
        $harness['store']->persist(
            $harness['tenant'],
            $harness['partition'],
            $harness['segmentId'],
            $complete,
            $rotated['snapshot'],
        );
        $events = $harness['events']();
        self::assertSame(
            1,
            count(array_filter($events, static fn(string $event): bool => $event === 'insert-preparation')),
        );
        self::assertSame(
            1,
            count(array_filter($events, static fn(string $event): bool => $event === 'insert-complete')),
        );
        self::assertSame([], $harness['unexpectedStatements']());

        $changedMaterial = $this->trustWithCheckpointKey($rotated['snapshot'], $rotated['keyRef'], "\x44");
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::exactly(2))
            ->method('now')
            ->willReturn(new DateTimeImmutable($fixture['times']['verifiedAt']));
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::once())->method('generate')->willReturn('01941f29-7c00-7000-8000-000000001002');
        $changedHarness = $this->validStoreEntryFixture($clock, $ids);
        $changedPreparation = $changedHarness['store']->prepare(
            $changedHarness['tenant'],
            $changedHarness['partition'],
            $changedHarness['segmentId'],
            $rotated['keyRef'],
            $rotated['snapshot'],
        );
        $changedComplete = AuditArtifactSigner::checkpoint(
            $changedPreparation->unsigned(),
            $rotated['seed'],
            $rotated['snapshot'],
            $changedPreparation->unsigned()['signedAt'],
        );
        $beforeState = $changedHarness['state']();
        $beforeTarget = $changedHarness['targetSegment']();
        $beforeRecords = $changedHarness['targetRecords']();
        $this->assertStoreFailure(
            fn(): mixed => $changedHarness['store']->persist(
                $changedHarness['tenant'],
                $changedHarness['partition'],
                $changedHarness['segmentId'],
                $changedComplete,
                $changedMaterial['snapshot'],
            ),
            AuditStoreFailure::IntegrityInvalid,
            'retry-same-key-ref-changed-material',
        );
        self::assertFalse($changedHarness['targetComplete']());
        self::assertNotFalse($changedHarness['targetPreparation']());
        self::assertSame($beforeState, $changedHarness['state']());
        self::assertSame($beforeTarget, $changedHarness['targetSegment']());
        self::assertSame($beforeRecords, $changedHarness['targetRecords']());
        self::assertSame([], $changedHarness['unexpectedStatements']());
    }

    public function testStoreEntryRejectsAValidDifferentSealedReplayBodyWithoutReplacingStoredBytes(): void
    {
        $fixture = $this->fixture();
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::exactly(2))
            ->method('now')
            ->willReturn(new DateTimeImmutable($fixture['times']['verifiedAt']));
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::once())->method('generate')->willReturn('01941f29-7c00-7000-8000-000000001003');
        $harness = $this->validStoreEntryFixture($clock, $ids);
        $preparation = $harness['store']->prepare(
            $harness['tenant'],
            $harness['partition'],
            $harness['segmentId'],
            'checkpoint-key',
            $harness['trust'],
        );
        $complete = AuditArtifactSigner::checkpoint(
            $preparation->unsigned(),
            $harness['seed'],
            $harness['trust'],
            $preparation->unsigned()['signedAt'],
        );
        $harness['store']->persist(
            $harness['tenant'],
            $harness['partition'],
            $harness['segmentId'],
            $complete,
            $harness['trust'],
        );
        $sealed = $harness['targetSegment']();
        self::assertIsArray($sealed);
        $sealed['state'] = 'sealed';
        $sealed['state_version'] = 3;
        $harness['setTargetSegment']($sealed);
        $beforeState = $harness['state']();
        $beforeTarget = $harness['targetSegment']();
        $beforeRecords = $harness['targetRecords']();

        $differentUnsigned = $preparation->unsigned();
        $differentUnsigned['sealedAt'] = '2030-01-02T03:03:30.000000Z';
        $signingInput = AuditCheckpointCodec::signingInput($differentUnsigned);
        self::assertSame(new AuditJcsCodec()->canonicalJson($differentUnsigned), $signingInput['bytes']);
        $differentComplete = AuditArtifactSigner::checkpoint(
            $differentUnsigned,
            $harness['seed'],
            $harness['trust'],
            $differentUnsigned['signedAt'],
        );
        self::assertNotSame($complete, $differentComplete);
        $this->assertStoreFailure(
            fn(): mixed => $harness['store']->persist(
                $harness['tenant'],
                $harness['partition'],
                $harness['segmentId'],
                $differentComplete,
                $harness['trust'],
            ),
            AuditStoreFailure::IntegrityInvalid,
            'sealed-valid-different-body',
        );
        $differentReplayBytes = AuditCheckpointCodec::complete($preparation->unsigned(), str_repeat(
            "\1",
            SODIUM_CRYPTO_SIGN_BYTES,
        ));
        self::assertNotSame($complete, $differentReplayBytes);
        $this->assertStoreFailure(
            fn(): mixed => $harness['store']->persist(
                $harness['tenant'],
                $harness['partition'],
                $harness['segmentId'],
                $differentReplayBytes,
                $harness['trust'],
            ),
            AuditStoreFailure::IntegrityInvalid,
            'sealed-different-complete-bytes',
        );
        $stored = $harness['targetComplete']();
        self::assertIsArray($stored);
        self::assertSame($complete, $stored['complete_bytes']);
        self::assertSame(AuditCheckpointCodec::decode($complete)['checkpointHash'], $stored['checkpoint_hash']);
        self::assertSame($beforeState, $harness['state']());
        self::assertSame($beforeTarget, $harness['targetSegment']());
        self::assertSame($beforeRecords, $harness['targetRecords']());
        $events = $harness['events']();
        self::assertSame(
            1,
            count(array_filter($events, static fn(string $event): bool => $event === 'insert-complete')),
        );
        self::assertSame([], $harness['unexpectedStatements']());
    }

    public function testStoreEntryCanaryFailuresRemainFiniteAndProtectOwnedFrames(): void
    {
        $fixture = $this->fixture();
        $trust = $this->trustWithCheckpointKey($fixture['trust']['snapshot'], 'checkpoint-key-store-canary', "\x55");
        $trust['snapshot']['ref'] = 'store-snapshot-canary';
        $trust['snapshot'] = $this->withTrustHash($trust['snapshot']);
        $canary = 'store-entry-canary';

        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::never())->method('generate');
        $sqlHarness = $this->validStoreEntryFixture($clock, $ids);
        $sqlHarness['setFetchOneFailure'](new RuntimeException($canary));
        $this->assertStoreCanaryFailure(
            fn(): mixed => $sqlHarness['store']->prepare(
                $sqlHarness['tenant'],
                $sqlHarness['partition'],
                $sqlHarness['segmentId'],
                $trust['keyRef'],
                $trust['snapshot'],
            ),
            AuditStoreFailure::StorageUnavailable,
            [$canary, $trust['keyRef'], $trust['snapshot']['ref']],
            'sql-canary',
        );
        self::assertSame([], $sqlHarness['unexpectedStatements']());

        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::once())->method('now')->willThrowException(new RuntimeException($canary));
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::never())->method('generate');
        $clockHarness = $this->validStoreEntryFixture($clock, $ids);
        $this->assertStoreCanaryFailure(
            fn(): mixed => $clockHarness['store']->prepare(
                $clockHarness['tenant'],
                $clockHarness['partition'],
                $clockHarness['segmentId'],
                $trust['keyRef'],
                $trust['snapshot'],
            ),
            AuditStoreFailure::ResourceUnavailable,
            [$canary, $trust['keyRef'], $trust['snapshot']['ref']],
            'clock-canary',
        );
        self::assertSame([], $clockHarness['unexpectedStatements']());

        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::once())
            ->method('now')
            ->willReturn(new DateTimeImmutable($fixture['times']['verifiedAt']));
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::once())->method('generate')->willThrowException(new RuntimeException($canary));
        $uuidHarness = $this->validStoreEntryFixture($clock, $ids);
        $this->assertStoreCanaryFailure(
            fn(): mixed => $uuidHarness['store']->prepare(
                $uuidHarness['tenant'],
                $uuidHarness['partition'],
                $uuidHarness['segmentId'],
                $trust['keyRef'],
                $trust['snapshot'],
            ),
            AuditStoreFailure::ResourceUnavailable,
            [$canary, $trust['keyRef'], $trust['snapshot']['ref']],
            'uuid-canary',
        );
        self::assertSame([], $uuidHarness['unexpectedStatements']());
    }

    public function testStoreEntryMapsAClockYearOutsideCanonicalTimestampGrammarBeforeUuid(): void
    {
        $fixture = $this->fixture();
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::once())
            ->method('now')
            ->willReturn(new DateTimeImmutable('+10000-01-02T03:04:05.123456Z'));
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::never())->method('generate');
        $harness = $this->validStoreEntryFixture($clock, $ids);
        $beforeState = $harness['state']();
        $beforeTarget = $harness['targetSegment']();
        $beforeRecords = $harness['targetRecords']();
        $this->assertStoreFailure(
            fn(): mixed => $harness['store']->prepare(
                $harness['tenant'],
                $harness['partition'],
                $harness['segmentId'],
                'checkpoint-key',
                $harness['trust'],
            ),
            AuditStoreFailure::ResourceUnavailable,
            'clock-noncanonical-year',
        );
        self::assertFalse($harness['targetPreparation']());
        self::assertNotContains('insert-preparation', $harness['events']());
        self::assertNotContains('insert-complete', $harness['events']());
        self::assertSame($beforeState, $harness['state']());
        self::assertSame($beforeTarget, $harness['targetSegment']());
        self::assertSame($beforeRecords, $harness['targetRecords']());
        self::assertSame([], $harness['unexpectedStatements']());
    }

    public function testStoreEntryRejectsMissingPredecessorBeforeTrustResourcesOrInsert(): void
    {
        $fixture = $this->fixture();
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::never())->method('generate');
        $harness = $this->validStoreEntryFixture($clock, $ids);
        $harness['setPreviousCheckpoint'](false);
        $beforeState = $harness['state']();
        $beforeTarget = $harness['targetSegment']();
        $beforeRecords = $harness['targetRecords']();
        $this->assertStoreFailure(
            fn(): mixed => $harness['store']->prepare(
                $harness['tenant'],
                $harness['partition'],
                $harness['segmentId'],
                'checkpoint-key',
                $harness['trust'],
            ),
            AuditStoreFailure::IntegrityInvalid,
            'missing-predecessor-complete-row',
        );
        self::assertNotContains('insert-preparation', $harness['events']());
        self::assertNotContains('insert-complete', $harness['events']());
        self::assertSame($beforeState, $harness['state']());
        self::assertSame($beforeTarget, $harness['targetSegment']());
        self::assertSame($beforeRecords, $harness['targetRecords']());
        self::assertSame([], $harness['unexpectedStatements']());
    }

    public function testStoreEntryPreservesValidMultiRecordRangeWhenFirstOrHeadHashChanges(): void
    {
        foreach (['first-hash' => 'firstRecordHash', 'head-hash' => 'chainHeadHash'] as $case => $field) {
            $fixture = $this->fixture();
            $clock = $this->createMock(ClockInterface::class);
            $clock
                ->expects(self::exactly(1))
                ->method('now')
                ->willReturn(new DateTimeImmutable($fixture['times']['verifiedAt']));
            $ids = $this->createMock(Uuidv7Generator::class);
            $ids
                ->expects(self::once())
                ->method('generate')
                ->willReturn(
                    $case === 'first-hash'
                        ? '01941f29-7c00-7000-8000-000000001004'
                        : '01941f29-7c00-7000-8000-000000001005',
                );
            $harness = $this->validStoreEntryFixture($clock, $ids, true);
            $preparation = $harness['store']->prepare(
                $harness['tenant'],
                $harness['partition'],
                $harness['segmentId'],
                'checkpoint-key',
                $harness['trust'],
            );
            $beforePreparation = $harness['targetPreparation']();
            self::assertIsArray($beforePreparation, $case);
            $beforeState = $harness['state']();
            $beforeTarget = $harness['targetSegment']();
            $beforeRecords = $harness['targetRecords']();
            $unsigned = $preparation->unsigned();
            $unsigned[$field] = str_repeat($field === 'firstRecordHash' ? 'b' : 'c', 64);
            $candidateBytes = AuditCheckpointCodec::signingInput($unsigned)['bytes'];
            self::assertSame(new AuditJcsCodec()->canonicalJson($unsigned), $candidateBytes, $case);
            $seed = $harness['seed'];
            $complete = AuditArtifactSigner::checkpoint($unsigned, $seed, $harness['trust'], $unsigned['signedAt']);
            $this->assertStoreFailure(
                fn(): mixed => $harness['store']->persist(
                    $harness['tenant'],
                    $harness['partition'],
                    $harness['segmentId'],
                    $complete,
                    $harness['trust'],
                ),
                AuditStoreFailure::IntegrityInvalid,
                $case . '-candidate-mismatch',
            );
            self::assertFalse($harness['targetComplete']());
            self::assertSame($beforePreparation, $harness['targetPreparation']());
            self::assertSame($beforeState, $harness['state']());
            self::assertSame($beforeTarget, $harness['targetSegment']());
            self::assertSame($beforeRecords, $harness['targetRecords']());
            self::assertNotContains('insert-complete', $harness['events']());
            self::assertSame([], $harness['unexpectedStatements'](), $case . '-candidate-mismatch');

            $storedPreparation = $beforePreparation;
            $storedPreparation['unsigned_checkpoint'] = $candidateBytes;
            $harness['setTargetPreparation']($storedPreparation);
            self::assertSame($candidateBytes, $storedPreparation['unsigned_checkpoint'], $case);
            $this->assertStoreFailure(
                fn(): mixed => $harness['store']->persist(
                    $harness['tenant'],
                    $harness['partition'],
                    $harness['segmentId'],
                    $complete,
                    $harness['trust'],
                ),
                AuditStoreFailure::IntegrityInvalid,
                $case . '-proof-binding',
            );
            self::assertFalse($harness['targetComplete']());
            self::assertSame($storedPreparation, $harness['targetPreparation']());
            self::assertSame($beforeState, $harness['state']());
            self::assertSame($beforeTarget, $harness['targetSegment']());
            self::assertSame($beforeRecords, $harness['targetRecords']());
            self::assertNotContains('insert-complete', $harness['events']());
            self::assertSame([], $harness['unexpectedStatements'](), $case);
        }
    }

    public function testStoreEntryRejectsSealedVersionAdvanceWithoutACompleteRow(): void
    {
        $fixture = $this->fixture();
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::once())
            ->method('now')
            ->willReturn(new DateTimeImmutable($fixture['times']['verifiedAt']));
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::once())->method('generate')->willReturn('01941f29-7c00-7000-8000-000000001006');
        $harness = $this->validStoreEntryFixture($clock, $ids);
        $preparation = $harness['store']->prepare(
            $harness['tenant'],
            $harness['partition'],
            $harness['segmentId'],
            'checkpoint-key',
            $harness['trust'],
        );
        $complete = AuditArtifactSigner::checkpoint(
            $preparation->unsigned(),
            $harness['seed'],
            $harness['trust'],
            $preparation->unsigned()['signedAt'],
        );
        $sealed = $harness['targetSegment']();
        self::assertIsArray($sealed);
        $sealed['state'] = 'sealed';
        $sealed['state_version'] = 3;
        $harness['setTargetSegment']($sealed);
        $beforeState = $harness['state']();
        $beforeTarget = $harness['targetSegment']();
        $beforeRecords = $harness['targetRecords']();
        $beforePreparation = $harness['targetPreparation']();
        $this->assertStoreFailure(
            fn(): mixed => $harness['store']->persist(
                $harness['tenant'],
                $harness['partition'],
                $harness['segmentId'],
                $complete,
                $harness['trust'],
            ),
            AuditStoreFailure::IntegrityInvalid,
            'sealed-version-without-complete-row',
        );
        self::assertFalse($harness['targetComplete']());
        self::assertSame($beforePreparation, $harness['targetPreparation']());
        self::assertSame($beforeState, $harness['state']());
        self::assertSame($beforeTarget, $harness['targetSegment']());
        self::assertSame($beforeRecords, $harness['targetRecords']());
        self::assertNotContains('insert-complete', $harness['events']());
        self::assertSame([], $harness['unexpectedStatements']());
    }

    public function testStoreEntryRejectsSealedPreparationRetryWithoutCompleteRow(): void
    {
        $fixture = $this->fixture();
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::once())
            ->method('now')
            ->willReturn(new DateTimeImmutable($fixture['times']['verifiedAt']));
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::once())->method('generate')->willReturn('01941f29-7c00-7000-8000-000000001012');
        $harness = $this->validStoreEntryFixture($clock, $ids);
        $preparation = $harness['store']->prepare(
            $harness['tenant'],
            $harness['partition'],
            $harness['segmentId'],
            'checkpoint-key',
            $harness['trust'],
        );
        $sealed = $harness['targetSegment']();
        self::assertIsArray($sealed);
        $sealed['state'] = 'sealed';
        $sealed['state_version'] = 3;
        $harness['setTargetSegment']($sealed);
        $beforeState = $harness['state']();
        $beforeTarget = $harness['targetSegment']();
        $beforeRecords = $harness['targetRecords']();
        $beforePreparation = $harness['targetPreparation']();

        $replayClock = $this->createMock(ClockInterface::class);
        $replayClock->expects(self::never())->method('now');
        $replayIds = $this->createMock(Uuidv7Generator::class);
        $replayIds->expects(self::never())->method('generate');
        $replayStore = $harness['storeWithResources']($replayClock, $replayIds);
        $this->assertStoreFailure(
            fn(): mixed => $replayStore->prepare(
                $harness['tenant'],
                $harness['partition'],
                $harness['segmentId'],
                $preparation->unsigned()['signingKeyRef'],
                ['unusable' => 'current-snapshot'],
            ),
            AuditStoreFailure::IntegrityInvalid,
            'prepare-sealed-without-complete-row',
        );
        self::assertSame($beforePreparation, $harness['targetPreparation']());
        self::assertFalse($harness['targetComplete']());
        self::assertSame($beforeState, $harness['state']());
        self::assertSame($beforeTarget, $harness['targetSegment']());
        self::assertSame($beforeRecords, $harness['targetRecords']());
        self::assertSame([], $harness['unexpectedStatements']());
        self::assertSame(
            1,
            count(array_filter(
                $harness['events'](),
                static fn(string $event): bool => $event === 'insert-preparation',
            )),
        );
        self::assertNotContains('insert-complete', $harness['events']());
    }

    public function testStoreEntryReplaysSealedPreparationWithoutFreshResourcesOrSnapshot(): void
    {
        $fixture = $this->fixture();
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::exactly(2))
            ->method('now')
            ->willReturn(new DateTimeImmutable($fixture['times']['verifiedAt']));
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::once())->method('generate')->willReturn('01941f29-7c00-7000-8000-000000001013');
        $harness = $this->validStoreEntryFixture($clock, $ids);
        $preparation = $harness['store']->prepare(
            $harness['tenant'],
            $harness['partition'],
            $harness['segmentId'],
            'checkpoint-key',
            $harness['trust'],
        );
        $complete = AuditArtifactSigner::checkpoint(
            $preparation->unsigned(),
            $harness['seed'],
            $harness['trust'],
            $preparation->unsigned()['signedAt'],
        );
        $harness['store']->persist(
            $harness['tenant'],
            $harness['partition'],
            $harness['segmentId'],
            $complete,
            $harness['trust'],
        );
        $sealed = $harness['targetSegment']();
        self::assertIsArray($sealed);
        $sealed['state'] = 'sealed';
        $sealed['state_version'] = 3;
        $harness['setTargetSegment']($sealed);
        $beforeState = $harness['state']();
        $beforeTarget = $harness['targetSegment']();
        $beforeRecords = $harness['targetRecords']();
        $beforePreparation = $harness['targetPreparation']();
        $beforeComplete = $harness['targetComplete']();

        $replayClock = $this->createMock(ClockInterface::class);
        $replayClock->expects(self::never())->method('now');
        $replayIds = $this->createMock(Uuidv7Generator::class);
        $replayIds->expects(self::never())->method('generate');
        $replayStore = $harness['storeWithResources']($replayClock, $replayIds);
        $replayed = $replayStore->prepare(
            $harness['tenant'],
            $harness['partition'],
            $harness['segmentId'],
            $preparation->unsigned()['signingKeyRef'],
            ['unusable' => 'current-snapshot'],
        );
        self::assertSame($preparation->unsigned(), $replayed->unsigned());
        self::assertSame($beforePreparation, $harness['targetPreparation']());
        self::assertSame($beforeComplete, $harness['targetComplete']());
        self::assertSame($beforeState, $harness['state']());
        self::assertSame($beforeTarget, $harness['targetSegment']());
        self::assertSame($beforeRecords, $harness['targetRecords']());
        self::assertSame([], $harness['unexpectedStatements']());
        self::assertSame(
            1,
            count(array_filter(
                $harness['events'](),
                static fn(string $event): bool => $event === 'insert-preparation',
            )),
        );
        self::assertSame(
            1,
            count(array_filter($harness['events'](), static fn(string $event): bool => $event === 'insert-complete')),
        );
    }

    public function testStoreEntryReplaysCompleteRowWithoutFreshResourcesOrSnapshot(): void
    {
        $fixture = $this->fixture();
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::exactly(2))
            ->method('now')
            ->willReturn(new DateTimeImmutable($fixture['times']['verifiedAt']));
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::once())->method('generate')->willReturn('01941f29-7c00-7000-8000-000000001014');
        $harness = $this->validStoreEntryFixture($clock, $ids);
        $preparation = $harness['store']->prepare(
            $harness['tenant'],
            $harness['partition'],
            $harness['segmentId'],
            'checkpoint-key',
            $harness['trust'],
        );
        $complete = AuditArtifactSigner::checkpoint(
            $preparation->unsigned(),
            $harness['seed'],
            $harness['trust'],
            $preparation->unsigned()['signedAt'],
        );
        $harness['store']->persist(
            $harness['tenant'],
            $harness['partition'],
            $harness['segmentId'],
            $complete,
            $harness['trust'],
        );
        $beforeState = $harness['state']();
        $beforeTarget = $harness['targetSegment']();
        $beforeRecords = $harness['targetRecords']();
        $beforePreparation = $harness['targetPreparation']();
        $beforeComplete = $harness['targetComplete']();

        $replayClock = $this->createMock(ClockInterface::class);
        $replayClock->expects(self::never())->method('now');
        $replayIds = $this->createMock(Uuidv7Generator::class);
        $replayIds->expects(self::never())->method('generate');
        $harness['storeWithResources']($replayClock, $replayIds)->persist(
            $harness['tenant'],
            $harness['partition'],
            $harness['segmentId'],
            $complete,
            ['unusable' => 'current-snapshot'],
        );
        self::assertSame($beforeState, $harness['state']());
        self::assertSame($beforeTarget, $harness['targetSegment']());
        self::assertSame($beforeRecords, $harness['targetRecords']());
        self::assertSame($beforePreparation, $harness['targetPreparation']());
        self::assertSame($beforeComplete, $harness['targetComplete']());
        self::assertSame([], $harness['unexpectedStatements']());
        self::assertSame(
            1,
            count(array_filter($harness['events'](), static fn(string $event): bool => $event === 'insert-complete')),
        );
    }

    public function testStoreEntryRejectsValidTrustRotationStatesBeforeCompleteInsert(): void
    {
        $fixture = $this->fixture();
        foreach (['expired', 'revoked', 'unique-rotation'] as $case) {
            $clock = $this->createMock(ClockInterface::class);
            $clock
                ->expects(self::exactly(2))
                ->method('now')
                ->willReturn(new DateTimeImmutable($fixture['times']['verifiedAt']));
            $ids = $this->createMock(Uuidv7Generator::class);
            $ids
                ->expects(self::once())
                ->method('generate')
                ->willReturn(match ($case) {
                    'expired' => '01941f29-7c00-7000-8000-000000001007',
                    'revoked' => '01941f29-7c00-7000-8000-000000001008',
                    default => '01941f29-7c00-7000-8000-000000001009',
                });
            $harness = $this->validStoreEntryFixture($clock, $ids);
            $preparation = $harness['store']->prepare(
                $harness['tenant'],
                $harness['partition'],
                $harness['segmentId'],
                'checkpoint-key',
                $harness['trust'],
            );
            $complete = AuditArtifactSigner::checkpoint(
                $preparation->unsigned(),
                $harness['seed'],
                $harness['trust'],
                $preparation->unsigned()['signedAt'],
            );
            $beforePreparation = $harness['targetPreparation']();
            $beforeState = $harness['state']();
            $beforeTarget = $harness['targetSegment']();
            $beforeRecords = $harness['targetRecords']();
            if ($case === 'unique-rotation') {
                $changed = $this->trustWithCheckpointKey($harness['trust'], 'checkpoint-key', "\x66");
                $trust = $changed['snapshot'];
            } else {
                $trust = $harness['trust'];
                if ($case === 'expired') {
                    $trust['entries'][1]['notAfter'] = $fixture['times']['verifiedAt'];
                } else {
                    $trust['entries'][1]['status'] = 'revoked';
                    $trust['entries'][1]['revokedAt'] = $fixture['times']['observedAt'];
                }
                $trust = $this->withTrustHash($trust);
            }
            $this->assertStoreFailure(
                fn(): mixed => $harness['store']->persist(
                    $harness['tenant'],
                    $harness['partition'],
                    $harness['segmentId'],
                    $complete,
                    $trust,
                ),
                AuditStoreFailure::IntegrityInvalid,
                'first-persist-' . $case,
            );
            self::assertFalse($harness['targetComplete']());
            self::assertSame($beforePreparation, $harness['targetPreparation']());
            self::assertSame($beforeState, $harness['state'](), $case);
            self::assertSame($beforeTarget, $harness['targetSegment'](), $case);
            self::assertSame($beforeRecords, $harness['targetRecords'](), $case);
            self::assertNotContains('insert-complete', $harness['events']());
            self::assertSame([], $harness['unexpectedStatements'](), $case);
        }
    }

    public function testStoreEntryRejectsOtherwiseValidCompleteBytesWithActualLfBeforeResources(): void
    {
        $fixture = $this->fixture();
        $unsigned = $fixture['checkpoints']['systemOne']['unsigned'];
        $unsigned['checkpointId'] = '01941f29-7c00-7000-8000-000000001011';
        $unsigned['segmentId'] = self::SEGMENT_ID;
        $seed = hex2bin((string) $fixture['seeds']['checkpoint']);
        self::assertIsString($seed);
        $complete = AuditArtifactSigner::checkpoint(
            $unsigned,
            $seed,
            $fixture['trust']['snapshot'],
            $fixture['times']['verifiedAt'],
        );
        $connection = $this->createMock(Connection::class);
        $this->expectNoConnectionUse($connection);
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::never())->method('generate');
        $this->assertStoreFailure(
            fn(): mixed => $this->store($connection, $clock, $ids)->persist(
                null,
                'system:global',
                self::SEGMENT_ID,
                $complete . "\n",
                $fixture['trust']['snapshot'],
            ),
            AuditStoreFailure::IntegrityInvalid,
            'complete-bytes-actual-lf',
        );
    }

    public function testStoreEntryUsesRealF1BytesForValidPreparationBinding(): void
    {
        $fixture = $this->fixture();
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::exactly(1))
            ->method('now')
            ->willReturn(new DateTimeImmutable($fixture['times']['verifiedAt']));
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::once())->method('generate')->willReturn('01941f29-7c00-7000-8000-000000001010');
        $harness = $this->validStoreEntryFixture($clock, $ids, true);
        $preparation = $harness['store']->prepare(
            $harness['tenant'],
            $harness['partition'],
            $harness['segmentId'],
            'checkpoint-key',
            $harness['trust'],
        );
        self::assertSame(1, $preparation->unsigned()['firstSequence']);
        self::assertSame(2, $preparation->unsigned()['lastSequence']);
        self::assertSame(2, $preparation->unsigned()['recordCount']);
        $complete = AuditArtifactSigner::checkpoint(
            $preparation->unsigned(),
            $harness['seed'],
            $harness['trust'],
            $preparation->unsigned()['signedAt'],
        );
        $decoded = AuditCheckpointCodec::decode($complete);
        unset($decoded['checkpointHash'], $decoded['signature']);
        self::assertSame(
            new AuditJcsCodec()->canonicalJson($preparation->unsigned()),
            AuditCheckpointCodec::signingInput($decoded)['bytes'],
        );
        self::assertSame([], $harness['unexpectedStatements']());
    }

    public function testTrustWorkflowUsesOneClockAndUuidAndRejectsCurrentTrustFailures(): void
    {
        $fixture = $this->fixture();
        $proof = [
            'partitionId' => 'system:global',
            'segmentId' => '01941f29-7c00-7000-8000-000000000101',
            'state' => 'closed',
            'stateVersion' => 2,
            'firstSequence' => 1,
            'lastSequence' => 1,
            'recordCount' => 1,
            'firstRecordHash' => $fixture['recordEndpoints']['systemGenesis'],
            'chainHeadHash' => $fixture['recordEndpoints']['systemGenesis'],
            'predecessorRecordHash' => null,
            'previousSegmentId' => null,
            'successorSegmentId' => '01941f29-7c00-7000-8000-000000000102',
            'closeFencingToken' => 'aft:v1:' . str_repeat('a', 64),
            'closedAt' => $fixture['times']['sealedAt'],
        ];
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::once())
            ->method('now')
            ->willReturn(new DateTimeImmutable($fixture['times']['verifiedAt']));
        $ids = $this->createMock(Uuidv7Generator::class);
        $ids->expects(self::once())->method('generate')->willReturn('01941f29-7c00-7000-8000-000000000999');
        $created = new PostgreSqlAuditCheckpointTrustWorkflow($clock, $ids)->newPreparation(
            $proof,
            null,
            $fixture['trust']['keyRefs']['checkpoint'],
            $fixture['trust']['snapshot'],
        );
        self::assertSame('01941f29-7c00-7000-8000-000000000999', $created['unsigned']['checkpointId']);
        self::assertSame(
            $fixture['trust']['snapshot']['entries'][1]['publicKeyFingerprint'],
            $created['signingKeyFingerprint'],
        );

        $equalClock = $this->createMock(ClockInterface::class);
        $equalClock
            ->expects(self::once())
            ->method('now')
            ->willReturn(new DateTimeImmutable($fixture['times']['sealedAt']));
        $equalIds = $this->createMock(Uuidv7Generator::class);
        $equalIds->expects(self::once())->method('generate')->willReturn('01941f29-7c00-7000-8000-000000000998');
        self::assertSame(
            '01941f29-7c00-7000-8000-000000000998',
            new PostgreSqlAuditCheckpointTrustWorkflow($equalClock, $equalIds)->newPreparation(
                $proof,
                null,
                $fixture['trust']['keyRefs']['checkpoint'],
                $fixture['trust']['snapshot'],
            )['unsigned']['checkpointId'],
        );

        foreach (['expired', 'revoked', 'duplicate-material', 'wrong-role', 'malformed-material', 'alias'] as $case) {
            $snapshot = $fixture['trust']['snapshot'];
            $keyRef = $fixture['trust']['keyRefs']['checkpoint'];
            if ($case === 'expired') {
                $snapshot['entries'][1]['notAfter'] = $fixture['times']['verifiedAt'];
            } elseif ($case === 'revoked') {
                $snapshot['entries'][1]['status'] = 'revoked';
                $snapshot['entries'][1]['revokedAt'] = $fixture['times']['observedAt'];
            } else {
                if ($case === 'duplicate-material') {
                    $snapshot['entries'][1]['publicKey'] = $snapshot['entries'][0]['publicKey'];
                    $snapshot['entries'][1]['publicKeyFingerprint'] = hash('sha256', sodium_base642bin(
                        $snapshot['entries'][0]['publicKey'],
                        SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
                    ));
                } elseif ($case === 'wrong-role') {
                    $snapshot['entries'][1]['role'] = 'anchor';
                } elseif ($case === 'malformed-material') {
                    $snapshot['entries'][1]['publicKey'] = 'material-canary';
                } else {
                    $keyRef = 'checkpoint-key-alias';
                }
            }
            $snapshot = $this->withTrustHash($snapshot);
            $badClock = $this->createMock(ClockInterface::class);
            $badClock
                ->expects(self::once())
                ->method('now')
                ->willReturn(new DateTimeImmutable($fixture['times']['verifiedAt']));
            $badIds = $this->createMock(Uuidv7Generator::class);
            $badIds->expects(self::never())->method('generate');
            try {
                new PostgreSqlAuditCheckpointTrustWorkflow($badClock, $badIds)->newPreparation(
                    $proof,
                    null,
                    $keyRef,
                    $snapshot,
                );
                self::fail($case . ' trust state was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $case);
                self::assertNull($exception->getPrevious(), $case);
            }
        }
    }

    public function testTrustWorkflowMapsClockBoundaryAndMalformedUuidToResourceUnavailable(): void
    {
        $fixture = $this->fixture();
        $proof = [
            'partitionId' => 'system:global',
            'segmentId' => '01941f29-7c00-7000-8000-000000000101',
            'state' => 'closed',
            'stateVersion' => 2,
            'firstSequence' => 1,
            'lastSequence' => 1,
            'recordCount' => 1,
            'firstRecordHash' => $fixture['recordEndpoints']['systemGenesis'],
            'chainHeadHash' => $fixture['recordEndpoints']['systemGenesis'],
            'predecessorRecordHash' => null,
            'previousSegmentId' => null,
            'successorSegmentId' => '01941f29-7c00-7000-8000-000000000102',
            'closeFencingToken' => 'aft:v1:' . str_repeat('a', 64),
            'closedAt' => $fixture['times']['sealedAt'],
        ];
        foreach ([
            'clock-before-close' => new DateTimeImmutable('2030-01-02T03:02:59.000000Z'),
            'malformed-id' => new DateTimeImmutable($fixture['times']['verifiedAt']),
            'uuid-throw' => new DateTimeImmutable($fixture['times']['verifiedAt']),
        ] as $case => $time) {
            $clock = $this->createMock(ClockInterface::class);
            $clock->expects(self::once())->method('now')->willReturn($time);
            $ids = $this->createMock(Uuidv7Generator::class);
            if ($case === 'malformed-id') {
                $ids->expects(self::once())->method('generate')->willReturn('not-a-v7');
            } elseif ($case === 'uuid-throw') {
                $ids
                    ->expects(self::once())
                    ->method('generate')
                    ->willThrowException(new RuntimeException('uuid-canary'));
            } else {
                $ids->expects(self::never())->method('generate');
            }
            try {
                new PostgreSqlAuditCheckpointTrustWorkflow($clock, $ids)->newPreparation(
                    $proof,
                    null,
                    $fixture['trust']['keyRefs']['checkpoint'],
                    $fixture['trust']['snapshot'],
                );
                self::fail($case . ' was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::ResourceUnavailable, $exception->failure(), $case);
                self::assertNull($exception->getPrevious(), $case);
            }
        }

        $throwingClock = $this->createMock(ClockInterface::class);
        $throwingClock->expects(self::once())->method('now')->willThrowException(new RuntimeException('clock-canary'));
        $throwingIds = $this->createMock(Uuidv7Generator::class);
        $throwingIds->expects(self::never())->method('generate');
        try {
            new PostgreSqlAuditCheckpointTrustWorkflow($throwingClock, $throwingIds)->newPreparation(
                $proof,
                null,
                $fixture['trust']['keyRefs']['checkpoint'],
                $fixture['trust']['snapshot'],
            );
            self::fail('clock-throw was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::ResourceUnavailable, $exception->failure(), 'clock-throw');
            self::assertNull($exception->getPrevious(), 'clock-throw');
        }
    }

    public function testPersistenceTrustVerifiesRealF1BytesAndDeniesExpiredRevokedOrRotatedMaterial(): void
    {
        $fixture = $this->fixture();
        $unsigned = $fixture['checkpoints']['systemOne']['unsigned'];
        $unsigned['checkpointId'] = '01941f29-7c00-7000-8000-000000000777';
        $seed = hex2bin($fixture['seeds']['checkpoint']);
        self::assertIsString($seed);
        $completeBytes = AuditArtifactSigner::checkpoint(
            $unsigned,
            $seed,
            $fixture['trust']['snapshot'],
            $fixture['times']['signedAt'],
        );
        $preparation = AuditCheckpointPreparation::fromRow([
            'checkpoint_id' => $unsigned['checkpointId'],
            'partition_id' => $unsigned['partitionId'],
            'segment_id' => $unsigned['segmentId'],
            'closed_state_version' => '2',
            'close_fencing_token' => 'aft:v1:' . str_repeat('a', 64),
            'unsigned_checkpoint' => AuditCheckpointCodec::signingInput($unsigned)['bytes'],
            'signing_key_fingerprint' => $fixture['trust']['snapshot']['entries'][1]['publicKeyFingerprint'],
        ]);
        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects(self::once())
            ->method('now')
            ->willReturn(new DateTimeImmutable($fixture['times']['verifiedAt']));
        new PostgreSqlAuditCheckpointTrustWorkflow(
            $clock,
            $this->createStub(Uuidv7Generator::class),
        )->verifyPersistence($preparation, null, $completeBytes, $fixture['trust']['snapshot']);

        foreach (['expired', 'revoked', 'duplicate-material'] as $case) {
            $snapshot = $fixture['trust']['snapshot'];
            if ($case === 'expired') {
                $snapshot['entries'][1]['notAfter'] = $fixture['times']['verifiedAt'];
            } elseif ($case === 'revoked') {
                $snapshot['entries'][1]['status'] = 'revoked';
                $snapshot['entries'][1]['revokedAt'] = $fixture['times']['observedAt'];
            } else {
                $snapshot['entries'][1]['publicKey'] = $snapshot['entries'][0]['publicKey'];
                $snapshot['entries'][1]['publicKeyFingerprint'] = hash('sha256', sodium_base642bin(
                    $snapshot['entries'][0]['publicKey'],
                    SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
                ));
            }
            $snapshot = $this->withTrustHash($snapshot);
            $badClock = $this->createMock(ClockInterface::class);
            $badClock
                ->expects(self::once())
                ->method('now')
                ->willReturn(new DateTimeImmutable($fixture['times']['verifiedAt']));
            try {
                new PostgreSqlAuditCheckpointTrustWorkflow(
                    $badClock,
                    $this->createStub(Uuidv7Generator::class),
                )->verifyPersistence($preparation, null, $completeBytes, $snapshot);
                self::fail($case . ' material was accepted at first persistence');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure(), $case);
                self::assertNull($exception->getPrevious(), $case);
            }
        }
    }

    /** @return array<string,mixed> */
    private function checkpointUnsigned(): array
    {
        return [
            'schemaVersion' => 1,
            'kind' => 'audit_checkpoint',
            'checkpointId' => '01941f29-7c00-7000-8000-000000000501',
            'partitionId' => 'system:global',
            'segmentId' => self::SEGMENT_ID,
            'firstSequence' => 1,
            'lastSequence' => 1,
            'recordCount' => 1,
            'firstRecordHash' => str_repeat('a', 64),
            'chainHeadHash' => str_repeat('a', 64),
            'previousCheckpointHash' => null,
            'sealedAt' => '2030-01-02T03:03:00.000000Z',
            'signedAt' => '2030-01-02T03:04:00.000000Z',
            'hashSuite' => 'audit-sha256-jcs-v1',
            'signatureAlgorithm' => 'ed25519-v1',
            'signingKeyRef' => 'checkpoint-key',
        ];
    }

    /** @return array<string,mixed> */
    private function fixture(): array
    {
        $value = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../Fixtures/Audit/audit-signed-artifacts-v1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($value);
        return $value;
    }

    /** @param array<string,mixed> $snapshot @return array<string,mixed> */
    private function withTrustHash(array $snapshot): array
    {
        $body = $snapshot;
        unset($body['hash']);
        $bodyBytes = new AuditJcsCodec()->canonicalJson($body);
        $domain = 'blackops.audit.trust-store-snapshot.v1';
        $snapshot['hash'] = hash(
            'sha256',
            's:' . strlen($domain) . ':' . $domain . 'b:' . strlen($bodyBytes) . ':' . $bodyBytes,
        );
        return $snapshot;
    }

    /** @return array{snapshot:array<string,mixed>,seed:string,keyRef:string} */
    private function trustWithCheckpointKey(array $snapshot, string $keyRef, string $seedByte): array
    {
        self::assertSame(1, strlen($seedByte));
        $seed = str_repeat($seedByte, SODIUM_CRYPTO_SIGN_SEEDBYTES);
        $keyPair = sodium_crypto_sign_seed_keypair($seed);
        try {
            $publicKey = sodium_crypto_sign_publickey($keyPair);
            $entry = $snapshot['entries'][0];
            self::assertIsArray($entry);
            $entry['keyRef'] = $keyRef;
            $entry['role'] = 'checkpoint';
            $entry['publicKey'] = sodium_bin2base64($publicKey, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            $entry['publicKeyFingerprint'] = hash('sha256', $publicKey);
            $entries = array_values(array_filter(
                $snapshot['entries'],
                static fn(mixed $value): bool => is_array($value) && ($value['keyRef'] ?? null) !== $keyRef,
            ));
            $entries[] = $entry;
            usort($entries, static fn(array $left, array $right): int => strcmp($left['keyRef'], $right['keyRef']));
            $snapshot['entries'] = $entries;
            $snapshot = $this->withTrustHash($snapshot);
        } finally {
            sodium_memzero($publicKey);
            sodium_memzero($keyPair);
        }

        return ['snapshot' => $snapshot, 'seed' => $seed, 'keyRef' => $keyRef];
    }

    /** @param callable():void $operation */
    private function assertStoreFailure(callable $operation, AuditStoreFailure $expected, string $case): void
    {
        try {
            $operation();
            self::fail($case . ' unexpectedly succeeded');
        } catch (AuditStoreException $exception) {
            self::assertSame($expected, $exception->failure(), $case);
            self::assertSame('Audit store operation failed.', $exception->getMessage(), $case);
            self::assertNull($exception->getPrevious(), $case);
        }
    }

    /** @param list<string> $canaries */
    private function assertStoreCanaryFailure(
        callable $operation,
        AuditStoreFailure $expected,
        array $canaries,
        string $case,
    ): void {
        foreach ($canaries as $marker) {
            self::assertIsString($marker, $case);
            self::assertNotSame('', $marker, $case);
        }
        $previousIgnoreArgs = ini_get('zend.exception_ignore_args');
        self::assertIsString($previousIgnoreArgs, $case);
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'), $case);
        try {
            try {
                $operation();
                self::fail($case . ' unexpectedly succeeded');
            } catch (AuditStoreException $exception) {
                self::assertSame($expected, $exception->failure(), $case);
                self::assertSame('Audit store operation failed.', $exception->getMessage(), $case);
                self::assertNull($exception->getPrevious(), $case);
                $traceText = $exception->getTraceAsString();
                foreach ($canaries as $marker) {
                    self::assertStringNotContainsString($marker, $traceText, $case);
                }
                $json = (string) json_encode($exception, JSON_THROW_ON_ERROR);
                foreach ($canaries as $marker) {
                    self::assertStringNotContainsString($marker, $json, $case);
                }
                $frames = array_values(array_filter(
                    $exception->getTrace(),
                    static fn(array $frame): bool => (
                        ($frame['class'] ?? null) === PostgreSqlAuditCheckpointStore::class
                        && ($frame['function'] ?? null) === 'prepare'
                    ),
                ));
                self::assertCount(1, $frames, $case);
                $arguments = $frames[0]['args'] ?? null;
                self::assertIsArray($arguments, $case);
                self::assertCount(5, $arguments, $case);
                foreach ($arguments as $argument) {
                    self::assertInstanceOf(\SensitiveParameterValue::class, $argument, $case);
                }
                $ownedTrace = print_r($frames, true) . var_export($frames, true);
                self::assertStringContainsString('SensitiveParameterValue', $ownedTrace, $case);
                foreach ($canaries as $marker) {
                    self::assertStringNotContainsString($marker, $ownedTrace, $case);
                }
                $framesSerializationFailure = null;
                try {
                    serialize($frames);
                } catch (\Throwable $serializationFailure) {
                    $framesSerializationFailure = $serializationFailure;
                }
                self::assertInstanceOf(\Throwable::class, $framesSerializationFailure, $case);
                self::assertStringContainsString(
                    'SensitiveParameterValue',
                    $framesSerializationFailure->getMessage(),
                    $case,
                );
                foreach ($canaries as $marker) {
                    self::assertStringNotContainsString($marker, $framesSerializationFailure->getMessage(), $case);
                }
                $serializedException = null;
                $exceptionSerializationFailure = null;
                try {
                    $serializedException = serialize($exception);
                } catch (\Throwable $serializationFailure) {
                    $exceptionSerializationFailure = $serializationFailure;
                }
                if ($exceptionSerializationFailure === null) {
                    self::assertIsString($serializedException, $case);
                    foreach ($canaries as $marker) {
                        self::assertStringNotContainsString($marker, $serializedException, $case);
                    }
                } else {
                    self::assertStringContainsString(
                        'SensitiveParameterValue',
                        $exceptionSerializationFailure->getMessage(),
                        $case,
                    );
                    foreach ($canaries as $marker) {
                        self::assertStringNotContainsString(
                            $marker,
                            $exceptionSerializationFailure->getMessage(),
                            $case,
                        );
                    }
                }
            }
        } finally {
            self::assertNotFalse(ini_set('zend.exception_ignore_args', $previousIgnoreArgs), $case);
        }
    }

    private function store(
        Connection $connection,
        ClockInterface $clock,
        Uuidv7Generator $ids,
    ): PostgreSqlAuditCheckpointStore {
        $partitions = new AuditPartitionIdDeriver(new AuditJcsCodec());
        return new PostgreSqlAuditCheckpointStore(
            $connection,
            new PostgreSqlAuditCheckpointSchema(new PostgreSqlMigrationSchema('blackops_audit')),
            new PostgreSqlAuditStoredRecordHydrator($partitions),
            $this->uninitializedProtection(),
            $partitions,
            $clock,
            $ids,
        );
    }

    /** @return array<string,mixed> */
    private function validStoreEntryFixture(
        ClockInterface $clock,
        Uuidv7Generator $ids,
        bool $multiRecord = false,
    ): array {
        $fixture = $this->fixture();
        $canonical = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../Fixtures/Audit/audit-canonical-record-v1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($canonical);
        self::assertIsArray($canonical['records'] ?? null);
        $jcs = new AuditJcsCodec();
        $partitions = new AuditPartitionIdDeriver($jcs);
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            FrameworkAuditEventCatalog::catalog(),
        ]));
        $recordCodec = new AuditRecordCodec($validator, $jcs);
        $protection = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            new class implements AuditProtectionKeySource {
                public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
                {
                    return new StorageKey('test-key', str_repeat('k', 32));
                }

                public function key(
                    string $keyId,
                    ?AuditTypedReference $tenant,
                    AuditProtectionPurpose $purpose,
                ): StorageKey {
                    return new StorageKey($keyId, str_repeat('k', 32));
                }
            },
            new class implements NonceSource {
                private int $counter = 0;

                public function generate(): string
                {
                    return substr(hash('sha256', (string) ++$this->counter, true), 0, 24);
                }
            },
            new AuditAssociatedData(),
            $recordCodec,
            new AuditIngressEnvelopeCodec($recordCodec, $partitions, $jcs),
        );
        $records = $canonical['records'];
        self::assertIsArray($records['tenantGenesis'] ?? null);
        self::assertIsArray($records['tenantNonGenesis'] ?? null);
        self::assertIsString($records['tenantGenesis']['completeBytes'] ?? null);
        self::assertIsString($records['tenantNonGenesis']['completeBytes'] ?? null);
        $genesisRecord = $recordCodec->decode($records['tenantGenesis']['completeBytes']);
        $targetRecord = $recordCodec->decode($records['tenantNonGenesis']['completeBytes']);
        $storedRow = static function (array $stored, string $actionType): array {
            $tenant = $stored['tenantBinding'];
            return [
                'schema_version' => $stored['schemaVersion'],
                'kind' => $stored['kind'],
                'record_id' => $stored['recordId'],
                'partition_id' => $stored['partitionId'],
                'tenant_type' => is_array($tenant) ? $tenant['type'] : null,
                'tenant_id' => is_array($tenant) ? $tenant['id'] : null,
                'segment_id' => $stored['segmentId'],
                'sequence' => $stored['sequence'],
                'previous_record_hash' => $stored['previousRecordHash'],
                'content_hash' => $stored['contentHash'],
                'record_hash' => $stored['recordHash'],
                'recorded_at' => $stored['recordedAt'],
                'event_category' => $stored['eventCategory'],
                'protected_body' => $stored['protectedBody'],
                'action_type' => $actionType,
                'resource_type' => 'operation',
                'resource_id' => 'operation-1',
            ];
        };
        $genesisStored = $storedRow(
            $protection->protectRecord($genesisRecord)->toArray(),
            'access.operation_status.requested',
        );
        $targetStored = $storedRow(
            $protection->protectRecord($targetRecord)->toArray(),
            'access.operation_status.accessed',
        );

        $tenant = new AuditTypedReference('account', 'tenant-a');
        $partition = 'tenant:59f58bfcb476fcc98fe5e95c6bf81a5f8125a721f27e53ed70ba7345ae64eae1';
        $previousSegmentId = '01941f29-7c00-7000-8000-000000000101';
        $segmentId = '01941f29-7c00-7000-8000-000000000102';
        $successorSegmentId = '01941f29-7c00-7000-8000-000000000103';
        $previousClosedAt = '2030-01-02T03:02:00.000000Z';
        $closedAt = '2030-01-02T03:03:00.000000Z';
        $genesisHash = $records['tenantGenesis']['recordHash'];
        $targetHash = $records['tenantNonGenesis']['recordHash'];
        self::assertIsString($genesisHash);
        self::assertIsString($targetHash);
        $closeToken = static function (
            string $partitionId,
            string $segmentId,
            int $finalSequence,
            string $headRecordHash,
            string $successorSegmentId,
            string $closedAt,
        ): string {
            $head = hex2bin($headRecordHash);
            if ($head === false) {
                throw new RuntimeException('invalid fixture hash');
            }
            $domain = 'blackops.audit.segment.close.v1';
            $preimage =
                's:'
                . strlen($domain)
                . ':'
                . $domain
                . 's:'
                . strlen($partitionId)
                . ':'
                . $partitionId
                . 's:'
                . strlen($segmentId)
                . ':'
                . $segmentId
                . 'i:'
                . $finalSequence
                . 'b:'
                . strlen($head)
                . ':'
                . $head
                . 's:'
                . strlen($successorSegmentId)
                . ':'
                . $successorSegmentId
                . 's:'
                . strlen($closedAt)
                . ':'
                . $closedAt;

            return 'aft:v1:' . hash('sha256', $preimage);
        };
        $previousToken = $closeToken($partition, $previousSegmentId, 1, $genesisHash, $segmentId, $previousClosedAt);
        $targetToken = $closeToken($partition, $segmentId, 2, $targetHash, $successorSegmentId, $closedAt);
        $stateRow = [
            'partition_id' => $partition,
            'tenant_type' => 'account',
            'tenant_id' => 'tenant-a',
            'active_segment_id' => $successorSegmentId,
            'last_sequence' => 2,
            'head_record_hash' => $targetHash,
            'last_assigned_recorded_at' => $closedAt,
            'state_version' => 2,
        ];
        $previousSegmentRow = [
            'partition_id' => $partition,
            'segment_id' => $previousSegmentId,
            'state' => 'closed',
            'first_sequence' => 1,
            'final_sequence' => 1,
            'head_record_hash' => $genesisHash,
            'predecessor_record_hash' => null,
            'opened_at' => '2030-01-02T03:01:00.000000Z',
            'closed_at' => $previousClosedAt,
            'successor_segment_id' => $segmentId,
            'close_fencing_token' => $previousToken,
            'state_version' => 2,
        ];
        $targetSegmentRow = [
            'partition_id' => $partition,
            'segment_id' => $segmentId,
            'state' => 'closed',
            'first_sequence' => $multiRecord ? 1 : 2,
            'final_sequence' => 2,
            'head_record_hash' => $targetHash,
            'predecessor_record_hash' => $multiRecord ? null : $genesisHash,
            'opened_at' => $multiRecord ? '2030-01-02T03:01:00.000000Z' : $previousClosedAt,
            'closed_at' => $closedAt,
            'successor_segment_id' => $successorSegmentId,
            'close_fencing_token' => $targetToken,
            'state_version' => 2,
        ];
        $successorSegmentRow = [
            'partition_id' => $partition,
            'segment_id' => $successorSegmentId,
            'state' => 'open',
            'first_sequence' => 3,
            'final_sequence' => null,
            'head_record_hash' => null,
            'predecessor_record_hash' => $targetHash,
            'opened_at' => $closedAt,
            'closed_at' => null,
            'successor_segment_id' => null,
            'close_fencing_token' => null,
            'state_version' => 1,
        ];
        $endpointRow = [
            'segment_id' => $previousSegmentId,
            'sequence' => 1,
            'previous_record_hash' => null,
            'record_hash' => $genesisHash,
        ];
        $seed = hex2bin((string) $fixture['seeds']['checkpoint']);
        self::assertIsString($seed);
        $previousUnsigned = [
            'schemaVersion' => 1,
            'kind' => 'audit_checkpoint',
            'checkpointId' => '01941f29-7c00-7000-8000-000000000901',
            'partitionId' => $partition,
            'segmentId' => $previousSegmentId,
            'firstSequence' => 1,
            'lastSequence' => 1,
            'recordCount' => 1,
            'firstRecordHash' => $genesisHash,
            'chainHeadHash' => $genesisHash,
            'previousCheckpointHash' => null,
            'sealedAt' => $previousClosedAt,
            'signedAt' => '2030-01-02T03:02:01.000000Z',
            'hashSuite' => 'audit-sha256-jcs-v1',
            'signatureAlgorithm' => 'ed25519-v1',
            'signingKeyRef' => 'checkpoint-key',
        ];
        $previousComplete = AuditArtifactSigner::checkpoint(
            $previousUnsigned,
            $seed,
            $fixture['trust']['snapshot'],
            $previousUnsigned['signedAt'],
        );
        $previousDecoded = AuditCheckpointCodec::decode($previousComplete);
        $previousCheckpointRow = [
            'checkpoint_id' => $previousUnsigned['checkpointId'],
            'partition_id' => $partition,
            'segment_id' => $previousSegmentId,
            'closed_state_version' => '2',
            'close_fencing_token' => $previousToken,
            'unsigned_checkpoint' => AuditCheckpointCodec::signingInput($previousUnsigned)['bytes'],
            'signing_key_fingerprint' => $fixture['trust']['snapshot']['entries'][1]['publicKeyFingerprint'],
            'checkpoint_hash' => $previousDecoded['checkpointHash'],
            'complete_bytes' => $previousComplete,
        ];
        $targetRecords = $multiRecord ? [$genesisStored, $targetStored] : [$targetStored];
        $targetPreparationRow = false;
        $targetCompleteRow = false;
        $checkpointIdExists = false;
        $transactionActive = false;
        $fetchOneFailure = null;
        $fetchAssociativeFailure = null;
        $fetchAllFailure = null;
        $executeFailure = null;
        $events = [];
        $unexpectedStatements = [];
        $connection = $this->createStub(Connection::class);
        $connection->method('isAutoCommit')->willReturn(true);
        $connection
            ->method('isTransactionActive')
            ->willReturnCallback(static function () use (&$transactionActive): bool {
                return $transactionActive;
            });
        $connection
            ->method('getTransactionNestingLevel')
            ->willReturnCallback(static function () use (&$transactionActive): int {
                return $transactionActive ? 1 : 0;
            });
        $connection
            ->method('beginTransaction')
            ->willReturnCallback(static function () use (&$transactionActive, &$events): void {
                $transactionActive = true;
                $events[] = 'begin';
            });
        $connection
            ->method('commit')
            ->willReturnCallback(static function () use (&$transactionActive, &$events): void {
                $transactionActive = false;
                $events[] = 'commit';
            });
        $connection
            ->method('rollBack')
            ->willReturnCallback(static function () use (&$transactionActive, &$events): void {
                $transactionActive = false;
                $events[] = 'rollback';
            });
        $connection
            ->method('close')
            ->willReturnCallback(static function () use (&$events): void {
                $events[] = 'close';
            });
        $connection
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $parameters = []) use (
                &$targetPreparationRow,
                &$targetCompleteRow,
                &$events,
                &$executeFailure,
                &$unexpectedStatements,
            ): int {
                if ($executeFailure instanceof \Throwable) {
                    throw $executeFailure;
                }
                if (str_starts_with($sql, 'SET TRANSACTION')) {
                    $events[] = 'repeatable-read';
                    return 0;
                }
                if (str_contains($sql, 'audit_checkpoints')) {
                    $targetCompleteRow = [
                        'checkpoint_id' => $parameters['checkpoint_id'],
                        'checkpoint_hash' => $parameters['checkpoint_hash'],
                        'complete_bytes' => $parameters['complete_bytes'],
                    ];
                    $events[] = 'insert-complete';
                    return 1;
                }
                if (str_contains($sql, 'audit_checkpoint_preparations')) {
                    $targetPreparationRow = [
                        'checkpoint_id' => $parameters['checkpoint_id'],
                        'partition_id' => $parameters['partition_id'],
                        'segment_id' => $parameters['segment_id'],
                        'closed_state_version' => $parameters['closed_state_version'],
                        'close_fencing_token' => $parameters['close_fencing_token'],
                        'unsigned_checkpoint' => $parameters['unsigned_checkpoint'],
                        'signing_key_fingerprint' => $parameters['signing_key_fingerprint'],
                    ];
                    $events[] = 'insert-preparation';
                    return 1;
                }
                $unexpectedStatements[] = $sql;
                return 0;
            });
        $connection
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql) use (
                &$fetchOneFailure,
                &$checkpointIdExists,
            ): int|false {
                if ($fetchOneFailure instanceof \Throwable) {
                    throw $fetchOneFailure;
                }
                if (str_contains($sql, 'pg_advisory_xact_lock')) {
                    return 1;
                }
                return $checkpointIdExists ? 1 : false;
            });
        $connection
            ->method('fetchAssociative')
            ->willReturnCallback(static function (string $sql, array $parameters = []) use (
                &$fetchAssociativeFailure,
                &$stateRow,
                &$targetSegmentRow,
                &$previousCheckpointRow,
                &$targetPreparationRow,
                &$targetCompleteRow,
                $endpointRow,
                $previousSegmentRow,
                $previousSegmentId,
            ): array|false {
                if ($fetchAssociativeFailure instanceof \Throwable) {
                    throw $fetchAssociativeFailure;
                }
                if (str_contains($sql, 'audit_partition_state')) {
                    return $stateRow;
                }
                if (array_key_exists('sequence', $parameters)) {
                    return $endpointRow;
                }
                if (str_contains($sql, 'unsigned_checkpoint')) {
                    if (($parameters['segment_id'] ?? null) === $previousSegmentId) {
                        return $previousCheckpointRow;
                    }
                    return $targetPreparationRow;
                }
                if (str_contains($sql, 'checkpoint_hash, complete_bytes')) {
                    return $targetCompleteRow;
                }
                if (str_contains($sql, 'audit_segments')) {
                    if (($parameters['segment_id'] ?? null) === $previousSegmentId) {
                        return $previousSegmentRow;
                    }
                    return $targetSegmentRow;
                }
                return false;
            });
        $connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(static function (string $sql) use (
                &$fetchAllFailure,
                &$targetSegmentRow,
                $previousSegmentRow,
                &$successorSegmentRow,
                $targetRecords,
            ): array {
                if ($fetchAllFailure instanceof \Throwable) {
                    throw $fetchAllFailure;
                }
                if (str_contains($sql, 'successor_segment_id =')) {
                    return $targetSegmentRow['first_sequence'] === 1 ? [] : [$previousSegmentRow];
                }
                if (str_contains($sql, 'audit_segments')) {
                    return [$successorSegmentRow];
                }
                if (str_contains($sql, 'audit_records')) {
                    return $targetRecords;
                }
                return [];
            });
        $store = new PostgreSqlAuditCheckpointStore(
            $connection,
            new PostgreSqlAuditCheckpointSchema(new PostgreSqlMigrationSchema('blackops_audit')),
            new PostgreSqlAuditStoredRecordHydrator($partitions),
            $protection,
            $partitions,
            $clock,
            $ids,
        );
        $storeWithResources = static function (ClockInterface $freshClock, Uuidv7Generator $freshIds) use (
            $connection,
            $partitions,
            $protection,
        ): PostgreSqlAuditCheckpointStore {
            return new PostgreSqlAuditCheckpointStore(
                $connection,
                new PostgreSqlAuditCheckpointSchema(new PostgreSqlMigrationSchema('blackops_audit')),
                new PostgreSqlAuditStoredRecordHydrator($partitions),
                $protection,
                $partitions,
                $freshClock,
                $freshIds,
            );
        };

        return [
            'fixture' => $fixture,
            'connection' => $connection,
            'store' => $store,
            'tenant' => $tenant,
            'partition' => $partition,
            'segmentId' => $segmentId,
            'seed' => $seed,
            'trust' => $fixture['trust']['snapshot'],
            'setState' => static function (array $value) use (&$stateRow): void {
                $stateRow = $value;
            },
            'state' => static function () use (&$stateRow): array {
                return $stateRow;
            },
            'setTargetSegment' => static function (array $value) use (&$targetSegmentRow): void {
                $targetSegmentRow = $value;
            },
            'targetSegment' => static function () use (&$targetSegmentRow): array {
                return $targetSegmentRow;
            },
            'setSuccessorSegment' => static function (array $value) use (&$successorSegmentRow): void {
                $successorSegmentRow = $value;
            },
            'successorSegment' => static function () use (&$successorSegmentRow): array {
                return $successorSegmentRow;
            },
            'setPreviousCheckpoint' => static function (array|false $value) use (&$previousCheckpointRow): void {
                $previousCheckpointRow = $value;
            },
            'setTargetPreparation' => static function (array|false $value) use (&$targetPreparationRow): void {
                $targetPreparationRow = $value;
            },
            'setTargetComplete' => static function (array|false $value) use (&$targetCompleteRow): void {
                $targetCompleteRow = $value;
            },
            'setCheckpointIdExists' => static function (bool $value) use (&$checkpointIdExists): void {
                $checkpointIdExists = $value;
            },
            'setFetchOneFailure' => static function (?\Throwable $value) use (&$fetchOneFailure): void {
                $fetchOneFailure = $value;
            },
            'setFetchAssociativeFailure' => static function (?\Throwable $value) use (&$fetchAssociativeFailure): void {
                $fetchAssociativeFailure = $value;
            },
            'setFetchAllFailure' => static function (?\Throwable $value) use (&$fetchAllFailure): void {
                $fetchAllFailure = $value;
            },
            'setExecuteFailure' => static function (?\Throwable $value) use (&$executeFailure): void {
                $executeFailure = $value;
            },
            'events' => static function () use (&$events): array {
                return $events;
            },
            'targetPreparation' => static function () use (&$targetPreparationRow): array|false {
                return $targetPreparationRow;
            },
            'targetComplete' => static function () use (&$targetCompleteRow): array|false {
                return $targetCompleteRow;
            },
            'targetRecords' => static function () use ($targetRecords): array {
                return $targetRecords;
            },
            'unexpectedStatements' => static function () use (&$unexpectedStatements): array {
                return $unexpectedStatements;
            },
            'closeToken' => $closeToken,
            'storeWithResources' => $storeWithResources,
        ];
    }

    private function uninitializedProtection(): AuditProtectedBodyCodec
    {
        /** @var AuditProtectedBodyCodec $protection */
        $protection = new \ReflectionClass(AuditProtectedBodyCodec::class)->newInstanceWithoutConstructor();
        return $protection;
    }

    /** @param list<string> $alreadyConfigured */
    private function expectNoConnectionUse(Connection $connection, array $alreadyConfigured = []): void
    {
        foreach ([
            'isAutoCommit',
            'isTransactionActive',
            'getTransactionNestingLevel',
            'beginTransaction',
            'executeStatement',
            'fetchOne',
            'fetchAssociative',
            'fetchAllAssociative',
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
