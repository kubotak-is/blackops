<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditDelivery;
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
use BlackOps\Internal\Audit\Artifact\AuditArtifactSigner;
use BlackOps\Internal\Audit\Artifact\AuditArtifactVerifier;
use BlackOps\Internal\Audit\Artifact\AuditCheckpointCodec;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointSchema;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointStore;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Migration\DatabaseMigrationRunner;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Direction;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class PostgreSqlAuditCheckpointStorePostgreSqlTest extends TestCase
{
    private const string SCHEMA = 'blackops_audit_checkpoint_f2';

    private const string ALT_SCHEMA = 'blackops_audit_checkpoint_f2_alt';

    private const string CHECKPOINT_KEY = 'checkpoint-key';

    /** @var array<int,true> */
    private array $reapedChildren = [];

    /** @var list<string> */
    private array $cleanupFailures = [];

    /** @var list<int> */
    private array $observedBackendPids = [];

    protected function tearDown(): void
    {
        $failures = $this->cleanupFailures;
        $this->cleanupFailures = [];
        if ($failures !== []) {
            $message = 'owned native resource cleanup failed: ' . implode('; ', $failures);
            if ($this->status()->isSuccess()) {
                self::fail($message);
            }
            if (!$this->writeCleanupDiagnostic($message)) {
                fwrite(STDERR, 'native cleanup diagnostic persistence failed: ' . $message . PHP_EOL);
            }
        }

        parent::tearDown();
    }

    public function testNativeSystemAndTenantChainsPersistExactF1BytesAcrossPageBoundaryAndRetry(): void
    {
        $admin = $primary = $probe = null;
        try {
            [$admin, $primary, $probe] = $this->connections();
            $this->migrate($primary);
            $clock = $this->clock('2030-01-02T03:04:05.123456Z');
            $records = $this->recordStore($primary, $clock, $this->uuidGenerator(1));
            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];
            $seed = hex2bin((string) $fixture['seeds']['checkpoint']);
            self::assertIsString($seed);

            $system = $records->appendRequired($this->draft(null, 1));
            $systemRecord = $system->record()->toArray();
            $systemPartition = 'system:global';
            $systemSegment = $systemRecord['segmentId'];
            $systemClose = $records->closeSegment($systemPartition, $systemSegment);
            self::assertTrue($systemClose->changed());
            self::assertSame(1, $systemClose->firstSequence());
            self::assertSame(1, $systemClose->finalSequence());
            $systemSegmentRow = $this->segment($probe, $systemPartition, $systemSegment);
            self::assertSame(
                $this->expectedCloseToken(
                    $systemPartition,
                    $systemSegment,
                    1,
                    $systemRecord['recordHash'],
                    $systemClose->successorSegmentId(),
                    $systemClose->closedAt(),
                ),
                $systemSegmentRow['close_fencing_token'],
            );

            $checkpointIds = $this->uuidGenerator(900);
            $checkpoints = $this->checkpointStore($primary, $clock, $checkpointIds);
            $systemPreparation = $checkpoints->prepare(
                null,
                $systemPartition,
                $systemSegment,
                self::CHECKPOINT_KEY,
                $trust,
            );
            $systemUnsigned = $systemPreparation->unsigned();
            self::assertSame(1, $systemUnsigned['firstSequence']);
            self::assertSame(1, $systemUnsigned['lastSequence']);
            self::assertSame(1, $systemUnsigned['recordCount']);
            self::assertSame(2, $systemPreparation->boundary()['closedStateVersion']);
            self::assertSame(
                $systemSegmentRow['close_fencing_token'],
                $systemPreparation->boundary()['closeFencingToken'],
            );
            $systemComplete = AuditArtifactSigner::checkpoint(
                $systemUnsigned,
                $seed,
                $trust,
                $systemUnsigned['signedAt'],
            );
            AuditArtifactVerifier::checkpoint($systemComplete, $trust, $systemUnsigned['signedAt']);
            $checkpoints->persist(null, $systemPartition, $systemSegment, $systemComplete, $trust);
            $checkpoints->persist(null, $systemPartition, $systemSegment, $systemComplete, $trust);
            $systemStored = $this->checkpointRow($probe, $systemUnsigned['checkpointId']);
            self::assertSame($systemComplete, PostgreSqlBytea::string($systemStored['complete_bytes']));
            self::assertSame(
                AuditCheckpointCodec::decode($systemComplete)['checkpointHash'],
                $systemStored['checkpoint_hash'],
            );
            $conflictingUnsigned = $systemUnsigned;
            $conflictingUnsigned['sealedAt'] = '2030-01-02T03:04:05.000000Z';
            $conflictingComplete = AuditArtifactSigner::checkpoint(
                $conflictingUnsigned,
                $seed,
                $trust,
                $conflictingUnsigned['signedAt'],
            );
            $conflictRejected = false;
            try {
                $checkpoints->persist(null, $systemPartition, $systemSegment, $conflictingComplete, $trust);
            } catch (AuditStoreException $exception) {
                $conflictRejected = true;
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            }
            self::assertTrue($conflictRejected, 'a different signed body for the same checkpoint was accepted');
            $systemStoredAfterConflict = $this->checkpointRow($probe, $systemUnsigned['checkpointId']);
            self::assertSame($systemComplete, PostgreSqlBytea::string($systemStoredAfterConflict['complete_bytes']));
            self::assertSame($systemStored['checkpoint_hash'], $systemStoredAfterConflict['checkpoint_hash']);

            $tenant = new AuditTypedReference('account', 'f2-native-tenant');
            $tenantPartition = $this->partition($tenant);
            $firstTenantRecord = null;
            $firstTenantSegment = null;
            $lastTenantRecord = null;
            for ($index = 1; $index <= 42; ++$index) {
                $appended = $records->appendRequired($this->draft($tenant, $index));
                $value = $appended->record()->toArray();
                $firstTenantRecord ??= $value;
                $firstTenantSegment ??= $value['segmentId'];
                $lastTenantRecord = $value;
            }
            self::assertIsArray($firstTenantRecord);
            self::assertIsArray($lastTenantRecord);
            self::assertSame($tenantPartition, $firstTenantRecord['partitionId']);
            self::assertSame($firstTenantSegment, $lastTenantRecord['segmentId']);
            self::assertSame(1, $firstTenantRecord['sequence']);
            self::assertSame(42, $lastTenantRecord['sequence']);
            $tenantClose = $records->closeSegment($tenantPartition, $firstTenantSegment);
            $firstTenantSegmentRow = $this->segment($probe, $tenantPartition, $firstTenantSegment);
            self::assertSame(
                $this->expectedCloseToken(
                    $tenantPartition,
                    $firstTenantSegment,
                    42,
                    $lastTenantRecord['recordHash'],
                    $tenantClose->successorSegmentId(),
                    $tenantClose->closedAt(),
                ),
                $firstTenantSegmentRow['close_fencing_token'],
            );

            $firstTenantPreparation = $checkpoints->prepare(
                $tenant,
                $tenantPartition,
                $firstTenantSegment,
                self::CHECKPOINT_KEY,
                $trust,
            );
            $firstTenantUnsigned = $firstTenantPreparation->unsigned();
            self::assertSame(1, $firstTenantUnsigned['firstSequence']);
            self::assertSame(42, $firstTenantUnsigned['lastSequence']);
            self::assertSame(42, $firstTenantUnsigned['recordCount']);
            $firstTenantComplete = AuditArtifactSigner::checkpoint(
                $firstTenantUnsigned,
                $seed,
                $trust,
                $firstTenantUnsigned['signedAt'],
            );
            $checkpoints->persist($tenant, $tenantPartition, $firstTenantSegment, $firstTenantComplete, $trust);

            $secondTenantSegment = null;
            $secondFirst = null;
            $secondLast = null;
            for ($index = 43; $index <= 50; ++$index) {
                $appended = $records->appendRequired($this->draft($tenant, $index));
                $value = $appended->record()->toArray();
                $secondTenantSegment ??= $value['segmentId'];
                $secondFirst ??= $value;
                $secondLast = $value;
            }
            self::assertIsArray($secondFirst);
            self::assertIsArray($secondLast);
            self::assertNotSame($firstTenantSegment, $secondTenantSegment);
            self::assertSame(43, $secondFirst['sequence']);
            self::assertSame(50, $secondLast['sequence']);
            $secondTenantClose = $records->closeSegment($tenantPartition, $secondTenantSegment);
            $secondTenantSegmentRow = $this->segment($probe, $tenantPartition, $secondTenantSegment);
            self::assertSame(
                $this->expectedCloseToken(
                    $tenantPartition,
                    $secondTenantSegment,
                    50,
                    $secondLast['recordHash'],
                    $secondTenantClose->successorSegmentId(),
                    $secondTenantClose->closedAt(),
                ),
                $secondTenantSegmentRow['close_fencing_token'],
            );
            $secondTenantPreparation = $checkpoints->prepare(
                $tenant,
                $tenantPartition,
                $secondTenantSegment,
                self::CHECKPOINT_KEY,
                $trust,
            );
            $secondTenantUnsigned = $secondTenantPreparation->unsigned();
            self::assertSame(43, $secondTenantUnsigned['firstSequence']);
            self::assertSame(50, $secondTenantUnsigned['lastSequence']);
            self::assertSame(8, $secondTenantUnsigned['recordCount']);
            self::assertSame(
                AuditCheckpointCodec::decode($firstTenantComplete)['checkpointHash'],
                $secondTenantUnsigned['previousCheckpointHash'],
            );
            $secondTenantComplete = AuditArtifactSigner::checkpoint(
                $secondTenantUnsigned,
                $seed,
                $trust,
                $secondTenantUnsigned['signedAt'],
            );
            AuditArtifactVerifier::checkpoint($secondTenantComplete, $trust, $secondTenantUnsigned['signedAt']);
            $checkpoints->persist($tenant, $tenantPartition, $secondTenantSegment, $secondTenantComplete, $trust);
            $secondStored = $this->checkpointRow($probe, $secondTenantUnsigned['checkpointId']);
            self::assertSame($secondTenantComplete, PostgreSqlBytea::string($secondStored['complete_bytes']));
            self::assertSame(3, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));
            self::assertSame(3, (int) $this->rowCount($probe, 'audit_checkpoints'));
        } finally {
            $this->cleanup($admin, $primary, $probe);
        }
    }

    public function testNativeRepeatableReadRootRejectsWrongTenantAndRecoversAfterCommitResponseLoss(): void
    {
        $admin = $primary = $probe = $recoveryConnection = null;
        $observed = $lossy = null;
        try {
            [$admin, $primary, $probe] = $this->connections();
            $this->migrate($primary);
            $defaultIsolation = strtolower((string) $primary->fetchOne('SHOW default_transaction_isolation'));
            $clock = $this->clock('2030-01-02T03:04:05.123456Z');
            $records = $this->recordStore($primary, $clock, $this->uuidGenerator(2000));
            $tenant = new AuditTypedReference('account', 'f2-native-ordering');
            $appended = $records->appendRequired($this->draft($tenant, 1));
            $value = $appended->record()->toArray();
            $records->closeSegment($value['partitionId'], $value['segmentId']);
            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];
            $ids = $this->uuidGenerator(2100);
            $observedBase = $this->connection();
            $observed = new class($observedBase->getParams(), $observedBase->getDriver()) extends Connection {
                public bool $rootBegan = false;

                public int $rootSequence = 0;

                /** @var list<string> */
                public array $statements = [];

                /** @var list<string> */
                public array $queries = [];

                /** @var list<string> */
                public array $rootTrace = [];

                public ?string $isolationDuringRoot = null;

                private bool $recordingRoot = false;

                public function beginTransaction(): void
                {
                    parent::beginTransaction();
                    ++$this->rootSequence;
                    $this->rootBegan = true;
                    $this->recordingRoot = true;
                    $this->rootTrace = ['begin'];
                    $this->isolationDuringRoot = null;
                }

                public function executeStatement(string $sql, array $params = [], array $types = []): int|string
                {
                    $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $sql) ?? $sql));
                    if ($this->recordingRoot) {
                        $this->statements[] = $normalized;
                        $this->rootTrace[] = 'execute:' . $normalized;
                    }
                    $result = parent::executeStatement($sql, $params, $types);
                    if (str_contains(strtoupper($sql), 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')) {
                        $this->isolationDuringRoot = strtolower((string) $this->fetchOne('SHOW transaction_isolation'));
                    }

                    return $result;
                }

                public function fetchOne(string $query, array $params = [], array $types = []): mixed
                {
                    $this->queries[] = $query;
                    if ($this->recordingRoot) {
                        $this->rootTrace[] = 'fetch:' . strtoupper(trim(preg_replace('/\s+/', ' ', $query) ?? $query));
                    }

                    return parent::fetchOne($query, $params, $types);
                }

                public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
                {
                    $this->queries[] = $query;
                    if ($this->recordingRoot) {
                        $this->rootTrace[] = 'fetch:' . strtoupper(trim(preg_replace('/\s+/', ' ', $query) ?? $query));
                    }

                    return parent::fetchAssociative($query, $params, $types);
                }

                public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
                {
                    $this->queries[] = $query;
                    if ($this->recordingRoot) {
                        $this->rootTrace[] = 'fetch:' . strtoupper(trim(preg_replace('/\s+/', ' ', $query) ?? $query));
                    }

                    return parent::fetchAllAssociative($query, $params, $types);
                }

                public function commit(): void
                {
                    try {
                        parent::commit();
                    } finally {
                        $this->recordingRoot = false;
                    }
                }

                public function rollBack(): void
                {
                    try {
                        parent::rollBack();
                    } finally {
                        $this->recordingRoot = false;
                    }
                }
            };
            $observedBase->close();
            $observedDefaultIsolation = strtolower((string) $observed->fetchOne('SHOW default_transaction_isolation'));
            $store = $this->checkpointStore($observed, $clock, $ids);

            $queryCountBeforeUnknown = count($observed->queries);
            try {
                $store->prepare(null, 'system:global', $value['segmentId'], self::CHECKPOINT_KEY, $trust);
                self::fail('an unknown system-partition target was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::SegmentConflict, $exception->failure());
            }
            self::assertFalse($observed->isTransactionActive());
            self::assertSame(
                $defaultIsolation,
                strtolower((string) $primary->fetchOne('SHOW default_transaction_isolation')),
            );
            self::assertNoProtectedBodyQueries(array_slice($observed->queries, $queryCountBeforeUnknown));
            self::assertSame(0, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));

            $queryCountBeforeMismatch = count($observed->queries);
            try {
                $store->prepare($tenant, 'system:global', $value['segmentId'], self::CHECKPOINT_KEY, $trust);
                self::fail('a tenant and system partition mismatch was accepted');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            }
            self::assertFalse($observed->isTransactionActive());
            self::assertNoProtectedBodyQueries(array_slice($observed->queries, $queryCountBeforeMismatch));
            self::assertSame(0, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));
            $rootSequenceBeforeValid = $observed->rootSequence;
            $preparation = $store->prepare(
                $tenant,
                $value['partitionId'],
                $value['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            self::assertSame($rootSequenceBeforeValid + 1, $observed->rootSequence);
            self::assertFalse($observed->isTransactionActive());
            self::assertSame('begin', $observed->rootTrace[0] ?? null);
            self::assertSame(
                'execute:SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',
                $observed->rootTrace[1] ?? null,
            );
            self::assertStringStartsWith('fetch:SELECT PG_ADVISORY_XACT_LOCK', $observed->rootTrace[3] ?? '');
            self::assertSame('repeatable read', $observed->isolationDuringRoot);
            self::assertSame(
                $observedDefaultIsolation,
                strtolower((string) $observed->fetchOne('SHOW default_transaction_isolation')),
            );
            $seed = hex2bin((string) $fixture['seeds']['checkpoint']);
            self::assertIsString($seed);
            $complete = AuditArtifactSigner::checkpoint(
                $preparation->unsigned(),
                $seed,
                $trust,
                $preparation->unsigned()['signedAt'],
            );
            $base = $this->connection();
            $lossy = new class($base->getParams(), $base->getDriver()) extends Connection {
                public int $closeCalls = 0;

                public function commit(): void
                {
                    parent::commit();
                    throw new \RuntimeException('commit response lost after durable checkpoint commit');
                }

                public function close(): void
                {
                    ++$this->closeCalls;
                    parent::close();
                }
            };
            $base->close();
            $lossyStore = $this->checkpointStore($lossy, $clock, $this->uuidGenerator(2200));
            try {
                $lossyStore->persist($tenant, $value['partitionId'], $value['segmentId'], $complete, $trust);
                self::fail('commit response loss was reported as success');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
            }
            self::assertFalse($lossy->isConnected());
            self::assertFalse($lossy->isTransactionActive());
            self::assertSame(0, $lossy->getTransactionNestingLevel());
            $this->assertStoreFailure(
                fn(): mixed => $lossyStore->persist(
                    $tenant,
                    $value['partitionId'],
                    $value['segmentId'],
                    $complete,
                    $trust,
                ),
                AuditStoreFailure::StorageUnavailable,
            );
            self::assertSame(1, $lossy->closeCalls);
            $recoveryConnection = $this->connection();
            $recovery = $this->checkpointStore($recoveryConnection, $clock, $this->uuidGenerator(2300));
            $recovery->persist($tenant, $value['partitionId'], $value['segmentId'], $complete, $trust);
            self::assertSame(1, (int) $this->rowCount($recoveryConnection, 'audit_checkpoints'));
            self::assertSame(
                $complete,
                PostgreSqlBytea::string(
                    $this->checkpointRow(
                        $recoveryConnection,
                        $preparation->unsigned()['checkpointId'],
                    )['complete_bytes'],
                ),
            );
            self::assertSame(
                $defaultIsolation,
                strtolower((string) $primary->fetchOne('SHOW default_transaction_isolation')),
            );
        } finally {
            $extraConnections = [];
            foreach ([$recoveryConnection, $observed, $lossy] as $extra) {
                if ($extra instanceof Connection) {
                    $extraConnections[] = $extra;
                }
            }
            $this->cleanup($admin, $primary, $probe, ...$extraConnections);
        }
    }

    public function testNativeConfiguredSchemaDoesNotCrossWireTheAltSchema(): void
    {
        $admin = $primary = $probe = null;
        try {
            [$admin, $primary, $probe] = $this->connections();
            $this->migrate($primary, self::SCHEMA);
            $this->migrate($admin, self::ALT_SCHEMA);
            $clock = $this->clock('2030-01-02T03:04:05.123456Z');
            $records = $this->recordStore($primary, $clock, $this->uuidGenerator(2500), self::SCHEMA);
            $appended = $records->appendRequired($this->draft(null, 1))->record()->toArray();
            $records->closeSegment('system:global', $appended['segmentId']);
            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];
            $alt = $this->checkpointStore($primary, $clock, $this->uuidGenerator(2600), self::ALT_SCHEMA);

            try {
                $alt->prepare(null, 'system:global', $appended['segmentId'], self::CHECKPOINT_KEY, $trust);
                self::fail('the alternate schema read the primary schema segment');
            } catch (AuditStoreException $exception) {
                self::assertSame(AuditStoreFailure::SegmentConflict, $exception->failure());
            }

            $primaryStore = $this->checkpointStore($primary, $clock, $this->uuidGenerator(2700), self::SCHEMA);
            $preparation = $primaryStore->prepare(
                null,
                'system:global',
                $appended['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            self::assertSame($appended['segmentId'], $preparation->boundary()['segmentId']);
        } finally {
            $this->cleanup($admin, $primary, $probe);
        }
    }

    public function testNativeExpiredFirstPersistRejectsAndSealedExactRetryRemainsIdempotent(): void
    {
        $admin = $primary = $probe = null;
        try {
            [$admin, $primary, $probe] = $this->connections();
            $this->migrate($primary);
            $validClock = $this->clock('2030-01-02T03:04:05.123456Z');
            $expiredClock = $this->clock('2030-01-02T05:00:00.000000Z');
            $records = $this->recordStore($primary, $validClock, $this->uuidGenerator(2800));
            $appended = $records->appendRequired($this->draft(null, 1))->record()->toArray();
            $records->closeSegment('system:global', $appended['segmentId']);
            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];
            $seed = hex2bin((string) $fixture['seeds']['checkpoint']);
            self::assertIsString($seed);
            $preparationStore = $this->checkpointStore($primary, $validClock, $this->uuidGenerator(2900));
            $preparation = $preparationStore->prepare(
                null,
                'system:global',
                $appended['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            $unsigned = $preparation->unsigned();
            $complete = AuditArtifactSigner::checkpoint($unsigned, $seed, $trust, $unsigned['signedAt']);
            AuditArtifactVerifier::checkpoint($complete, $trust, $unsigned['signedAt']);

            $expiredStore = $this->checkpointStore($probe, $expiredClock, $this->uuidGenerator(3000));
            $expiredRejected = false;
            try {
                $expiredStore->persist(null, 'system:global', $appended['segmentId'], $complete, $trust);
                self::fail('expired checkpoint trust was accepted for a first complete insert');
            } catch (AuditStoreException $exception) {
                $expiredRejected = true;
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            }
            self::assertTrue($expiredRejected);
            self::assertFalse($probe->isTransactionActive());
            self::assertSame(0, (int) $this->rowCount($probe, 'audit_checkpoints'));

            $validStore = $this->checkpointStore($probe, $validClock, $this->uuidGenerator(3100));
            $validStore->persist(null, 'system:global', $appended['segmentId'], $complete, $trust);
            self::assertSame(1, (int) $this->rowCount($probe, 'audit_checkpoints'));
            $closed = $this->segment($probe, 'system:global', $appended['segmentId']);
            self::assertSame('closed', $closed['state']);

            $probe->executeStatement(
                'UPDATE "'
                . self::SCHEMA
                . '"."audit_segments" '
                . 'SET state = \'sealed\', state_version = :state_version '
                . 'WHERE partition_id = :partition_id AND segment_id = CAST(:segment_id AS uuid)',
                [
                    'state_version' => (int) $closed['state_version'],
                    'partition_id' => 'system:global',
                    'segment_id' => $appended['segmentId'],
                ],
                [
                    'state_version' => ParameterType::INTEGER,
                    'partition_id' => ParameterType::STRING,
                    'segment_id' => ParameterType::STRING,
                ],
            );
            $sameVersionSealed = $this->segment($probe, 'system:global', $appended['segmentId']);
            self::assertSame('sealed', $sameVersionSealed['state']);
            self::assertSame((int) $closed['state_version'], (int) $sameVersionSealed['state_version']);
            $sameVersionStore = $this->checkpointStore($probe, $expiredClock, $this->uuidGenerator(3150));
            $this->assertStoreFailure(
                fn(): mixed => $sameVersionStore->prepare(
                    null,
                    'system:global',
                    $appended['segmentId'],
                    self::CHECKPOINT_KEY,
                    $trust,
                ),
                AuditStoreFailure::IntegrityInvalid,
            );
            $this->assertStoreFailure(
                fn(): mixed => $sameVersionStore->persist(
                    null,
                    'system:global',
                    $appended['segmentId'],
                    $complete,
                    $trust,
                ),
                AuditStoreFailure::IntegrityInvalid,
            );
            self::assertSame($sameVersionSealed, $this->segment($probe, 'system:global', $appended['segmentId']));
            self::assertSame(1, (int) $this->rowCount($probe, 'audit_checkpoints'));

            $probe->executeStatement(
                'UPDATE "'
                . self::SCHEMA
                . '"."audit_segments" '
                . 'SET state = \'sealed\', state_version = state_version + 1 '
                . 'WHERE partition_id = :partition_id AND segment_id = CAST(:segment_id AS uuid)',
                ['partition_id' => 'system:global', 'segment_id' => $appended['segmentId']],
                ['partition_id' => ParameterType::STRING, 'segment_id' => ParameterType::STRING],
            );
            $sealed = $this->segment($probe, 'system:global', $appended['segmentId']);
            self::assertSame('sealed', $sealed['state']);
            self::assertSame((int) $closed['state_version'] + 1, (int) $sealed['state_version']);
            self::assertSame($closed['close_fencing_token'], $sealed['close_fencing_token']);

            $retryStore = $this->checkpointStore($primary, $expiredClock, $this->uuidGenerator(3200));
            $retry = $retryStore->prepare(null, 'system:global', $appended['segmentId'], self::CHECKPOINT_KEY, $trust);
            self::assertSame($unsigned, $retry->unsigned());
            $retryStore->persist(null, 'system:global', $appended['segmentId'], $complete, $trust);
            self::assertSame(1, (int) $this->rowCount($probe, 'audit_checkpoints'));
            self::assertSame($sealed, $this->segment($probe, 'system:global', $appended['segmentId']));
            $storedRetry = $this->checkpointRow($probe, $unsigned['checkpointId']);
            self::assertSame($complete, PostgreSqlBytea::string($storedRetry['complete_bytes']));
            self::assertFalse($primary->isTransactionActive());
        } finally {
            $this->cleanup($admin, $primary, $probe);
        }
    }

    public function testNativePersistRejectsResignedSealedAtTamperAndOriginalBytesRecover(): void
    {
        $admin = $primary = $probe = null;
        try {
            [$admin, $primary, $probe] = $this->connections();
            $this->migrate($primary);
            $clock = $this->clock('2030-01-02T03:04:05.123456Z');
            $records = $this->recordStore($primary, $clock, $this->uuidGenerator(3210));
            $appended = $records->appendRequired($this->draft(null, 1))->record()->toArray();
            $records->closeSegment('system:global', $appended['segmentId']);
            $segmentBefore = $this->segment($probe, 'system:global', $appended['segmentId']);
            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];
            $seed = hex2bin((string) $fixture['seeds']['checkpoint']);
            self::assertIsString($seed);
            $preparationStore = $this->checkpointStore($primary, $clock, $this->uuidGenerator(3220));
            $preparation = $preparationStore->prepare(
                null,
                'system:global',
                $appended['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            $unsigned = $preparation->unsigned();
            $complete = AuditArtifactSigner::checkpoint($unsigned, $seed, $trust, $unsigned['signedAt']);
            $originalBytes = PostgreSqlBytea::string(
                $this->preparationRow($probe, $unsigned['checkpointId'])['unsigned_checkpoint'],
            );
            $tamperedUnsigned = $unsigned;
            $tamperedUnsigned['sealedAt'] = '2030-01-02T03:02:01.000000Z';
            $tamperedInput = AuditCheckpointCodec::signingInput($tamperedUnsigned);
            $tamperedComplete = AuditArtifactSigner::checkpoint(
                $tamperedUnsigned,
                $seed,
                $trust,
                $tamperedUnsigned['signedAt'],
            );
            AuditArtifactVerifier::checkpoint($tamperedComplete, $trust, $tamperedUnsigned['signedAt']);
            $this->updatePreparationUnsigned($probe, $unsigned['checkpointId'], $tamperedInput['bytes']);

            $tamperedStore = $this->checkpointStore($primary, $clock, $this->uuidGenerator(3230));
            $this->assertStoreFailure(
                fn(): mixed => $tamperedStore->persist(
                    null,
                    'system:global',
                    $appended['segmentId'],
                    $tamperedComplete,
                    $trust,
                ),
                AuditStoreFailure::IntegrityInvalid,
            );
            self::assertSame(0, (int) $this->rowCount($probe, 'audit_checkpoints'));
            self::assertSame($segmentBefore, $this->segment($probe, 'system:global', $appended['segmentId']));
            self::assertSame(
                $tamperedInput['bytes'],
                PostgreSqlBytea::string(
                    $this->preparationRow($probe, $unsigned['checkpointId'])['unsigned_checkpoint'],
                ),
            );

            $this->updatePreparationUnsigned($probe, $unsigned['checkpointId'], $originalBytes);
            $recovery = $this->checkpointStore($probe, $clock, $this->uuidGenerator(3240));
            $recovery->persist(null, 'system:global', $appended['segmentId'], $complete, $trust);
            self::assertSame(
                $complete,
                PostgreSqlBytea::string($this->checkpointRow($probe, $unsigned['checkpointId'])['complete_bytes']),
            );
        } finally {
            $this->cleanup($admin, $primary, $probe);
        }
    }

    public function testNativePreviousCheckpointTokenMutationRejectsAndSealedPredecessorRetrySucceeds(): void
    {
        $admin = $primary = $probe = null;
        try {
            [$admin, $primary, $probe] = $this->connections();
            $this->migrate($primary);
            $clock = $this->clock('2030-01-02T03:04:05.123456Z');
            $records = $this->recordStore($primary, $clock, $this->uuidGenerator(3250));
            $first = $records->appendRequired($this->draft(null, 1))->record()->toArray();
            $records->closeSegment('system:global', $first['segmentId']);
            $second = $records->appendRequired($this->draft(null, 2))->record()->toArray();
            $records->closeSegment('system:global', $second['segmentId']);
            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];
            $seed = hex2bin((string) $fixture['seeds']['checkpoint']);
            self::assertIsString($seed);
            $checkpoints = $this->checkpointStore($primary, $clock, $this->uuidGenerator(3270));
            $firstPreparation = $checkpoints->prepare(
                null,
                'system:global',
                $first['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            $firstUnsigned = $firstPreparation->unsigned();
            $firstComplete = AuditArtifactSigner::checkpoint($firstUnsigned, $seed, $trust, $firstUnsigned['signedAt']);
            $checkpoints->persist(null, 'system:global', $first['segmentId'], $firstComplete, $trust);
            $firstSegment = $this->segment($probe, 'system:global', $first['segmentId']);
            $originalToken = (string) $firstSegment['close_fencing_token'];
            $alternateToken = 'aft:v1:' . str_repeat('e', 64);
            self::assertNotSame($originalToken, $alternateToken);
            $this->updatePreparationToken($probe, $firstUnsigned['checkpointId'], $alternateToken);

            $this->assertStoreFailure(
                fn(): mixed => $checkpoints->prepare(
                    null,
                    'system:global',
                    $second['segmentId'],
                    self::CHECKPOINT_KEY,
                    $trust,
                ),
                AuditStoreFailure::IntegrityInvalid,
            );
            self::assertSame(1, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));

            $this->updatePreparationToken($probe, $firstUnsigned['checkpointId'], $originalToken);
            $secondPreparation = $checkpoints->prepare(
                null,
                'system:global',
                $second['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            self::assertSame($second['segmentId'], $secondPreparation->boundary()['segmentId']);
            $probe->executeStatement(
                'UPDATE "'
                . self::SCHEMA
                . '"."audit_segments" '
                . 'SET state = \'sealed\', state_version = state_version + 1 '
                . 'WHERE partition_id = :partition_id AND segment_id = CAST(:segment_id AS uuid)',
                ['partition_id' => 'system:global', 'segment_id' => $first['segmentId']],
                ['partition_id' => ParameterType::STRING, 'segment_id' => ParameterType::STRING],
            );
            $sealedRetry = $checkpoints->prepare(
                null,
                'system:global',
                $second['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            self::assertSame($secondPreparation->unsigned(), $sealedRetry->unsigned());
        } finally {
            $this->cleanup($admin, $primary, $probe);
        }
    }

    public function testNativeEndpointPhysicalSegmentSubstitutionRejectsAndRestoredChainSucceeds(): void
    {
        $admin = $primary = $probe = null;
        try {
            [$admin, $primary, $probe] = $this->connections();
            $this->migrate($primary);
            $clock = $this->clock('2030-01-02T03:04:05.123456Z');
            $records = $this->recordStore($primary, $clock, $this->uuidGenerator(3280));
            $first = $records->appendRequired($this->draft(null, 1))->record()->toArray();
            $records->closeSegment('system:global', $first['segmentId']);
            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];
            $seed = hex2bin((string) $fixture['seeds']['checkpoint']);
            self::assertIsString($seed);
            $checkpoints = $this->checkpointStore($primary, $clock, $this->uuidGenerator(3290));
            $firstPreparation = $checkpoints->prepare(
                null,
                'system:global',
                $first['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            $firstUnsigned = $firstPreparation->unsigned();
            $firstComplete = AuditArtifactSigner::checkpoint($firstUnsigned, $seed, $trust, $firstUnsigned['signedAt']);
            $checkpoints->persist(null, 'system:global', $first['segmentId'], $firstComplete, $trust);
            $second = $records->appendRequired($this->draft(null, 2))->record()->toArray();
            $records->closeSegment('system:global', $second['segmentId']);
            $secondSegment = $this->segment($probe, 'system:global', $second['segmentId']);
            $successor = (string) $secondSegment['successor_segment_id'];
            $this->updateRecordSegment($probe, 'system:global', $first['recordId'], $successor);

            $this->assertStoreFailure(
                fn(): mixed => $checkpoints->prepare(
                    null,
                    'system:global',
                    $second['segmentId'],
                    self::CHECKPOINT_KEY,
                    $trust,
                ),
                AuditStoreFailure::IntegrityInvalid,
            );
            self::assertSame(1, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));
            $this->updateRecordSegment($probe, 'system:global', $first['recordId'], $first['segmentId']);
            $restored = $checkpoints->prepare(
                null,
                'system:global',
                $second['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            self::assertSame($second['segmentId'], $restored->boundary()['segmentId']);
        } finally {
            $this->cleanup($admin, $primary, $probe);
        }
    }

    public function testNativeMalformedGeneratedIdAndClosedVersionOneAreDistinctFailures(): void
    {
        $admin = $primary = $probe = null;
        try {
            [$admin, $primary, $probe] = $this->connections();
            $this->migrate($primary);
            $clock = $this->clock('2030-01-02T03:04:05.123456Z');
            $records = $this->recordStore($primary, $clock, $this->uuidGenerator(3310));
            $malformed = $records->appendRequired($this->draft(null, 1))->record()->toArray();
            $records->closeSegment('system:global', $malformed['segmentId']);
            $versionOne = $records->appendRequired($this->draft(null, 2))->record()->toArray();
            $records->closeSegment('system:global', $versionOne['segmentId']);
            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];
            $malformedStore = $this->checkpointStore($primary, $clock, $this->malformedUuidGenerator());
            $this->assertStoreFailure(
                fn(): mixed => $malformedStore->prepare(
                    null,
                    'system:global',
                    $malformed['segmentId'],
                    self::CHECKPOINT_KEY,
                    $trust,
                ),
                AuditStoreFailure::ResourceUnavailable,
            );
            self::assertSame(0, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));
            $probe->executeStatement(
                'UPDATE "'
                . self::SCHEMA
                . '"."audit_segments" SET state_version = 1 '
                . 'WHERE partition_id = :partition_id AND segment_id = CAST(:segment_id AS uuid)',
                ['partition_id' => 'system:global', 'segment_id' => $versionOne['segmentId']],
                ['partition_id' => ParameterType::STRING, 'segment_id' => ParameterType::STRING],
            );
            $versionOneStore = $this->checkpointStore($primary, $clock, $this->uuidGenerator(3320));
            $this->assertStoreFailure(
                fn(): mixed => $versionOneStore->prepare(
                    null,
                    'system:global',
                    $versionOne['segmentId'],
                    self::CHECKPOINT_KEY,
                    $trust,
                ),
                AuditStoreFailure::IntegrityInvalid,
            );
            self::assertSame(0, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));
        } finally {
            $this->cleanup($admin, $primary, $probe);
        }
    }

    public function testNativeInsertRollbackCleanupLeavesNoRowsAndFreshRootRecovers(): void
    {
        $admin = $primary = $probe = null;
        $firstRecoveryConnection = $secondRecoveryConnection = null;
        $beforeFunction = 'f2_checkpoint_fail_before_fn';
        $beforeTrigger = 'f2_checkpoint_fail_before_trigger';
        $afterFunction = 'f2_checkpoint_fail_after_fn';
        $afterTrigger = 'f2_checkpoint_fail_after_trigger';
        try {
            [$admin, $primary, $probe] = $this->connections();
            $this->migrate($primary);
            $clock = $this->clock('2030-01-02T03:04:05.123456Z');
            $records = $this->recordStore($primary, $clock, $this->uuidGenerator(3300));
            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];
            $seed = hex2bin((string) $fixture['seeds']['checkpoint']);
            self::assertIsString($seed);

            $first = $records->appendRequired($this->draft(null, 1))->record()->toArray();
            $records->closeSegment('system:global', $first['segmentId']);
            $firstSegmentBefore = $this->segment($probe, 'system:global', $first['segmentId']);
            $this->installInsertFailureTrigger($admin, $beforeFunction, $beforeTrigger, 'BEFORE');
            $beforeStore = $this->checkpointStore($primary, $clock, $this->uuidGenerator(3400));
            $beforeRejected = false;
            try {
                $beforeStore->prepare(null, 'system:global', $first['segmentId'], self::CHECKPOINT_KEY, $trust);
                self::fail('a native before-insert failure was accepted');
            } catch (AuditStoreException $exception) {
                $beforeRejected = true;
                self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
            }
            self::assertTrue($beforeRejected);
            self::assertFalse($primary->isTransactionActive());
            self::assertSame(0, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));
            self::assertSame($firstSegmentBefore, $this->segment($probe, 'system:global', $first['segmentId']));
            $this->removeInsertFailureTrigger($admin, $beforeFunction, $beforeTrigger);

            $firstRecoveryConnection = $this->connection();
            $fresh = $this->checkpointStore($firstRecoveryConnection, $clock, $this->uuidGenerator(3500));
            $firstPreparation = $fresh->prepare(
                null,
                'system:global',
                $first['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            $firstUnsigned = $firstPreparation->unsigned();
            $firstComplete = AuditArtifactSigner::checkpoint($firstUnsigned, $seed, $trust, $firstUnsigned['signedAt']);
            $fresh->persist(null, 'system:global', $first['segmentId'], $firstComplete, $trust);

            $second = $records->appendRequired($this->draft(null, 2))->record()->toArray();
            $records->closeSegment('system:global', $second['segmentId']);
            $secondSegmentBefore = $this->segment($probe, 'system:global', $second['segmentId']);
            $this->installInsertFailureTrigger($admin, $afterFunction, $afterTrigger, 'AFTER');
            $afterStore = $this->checkpointStore($primary, $clock, $this->uuidGenerator(3600));
            $afterRejected = false;
            try {
                $afterStore->prepare(null, 'system:global', $second['segmentId'], self::CHECKPOINT_KEY, $trust);
                self::fail('a native after-insert failure was accepted');
            } catch (AuditStoreException $exception) {
                $afterRejected = true;
                self::assertSame(AuditStoreFailure::StorageUnavailable, $exception->failure());
            }
            self::assertTrue($afterRejected);
            self::assertFalse($primary->isTransactionActive());
            self::assertSame(1, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));
            self::assertSame($secondSegmentBefore, $this->segment($probe, 'system:global', $second['segmentId']));
            $this->removeInsertFailureTrigger($admin, $afterFunction, $afterTrigger);

            $secondRecoveryConnection = $this->connection();
            $secondRecovery = $this->checkpointStore($secondRecoveryConnection, $clock, $this->uuidGenerator(3700));
            $secondPreparation = $secondRecovery->prepare(
                null,
                'system:global',
                $second['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            self::assertSame($second['segmentId'], $secondPreparation->boundary()['segmentId']);
            self::assertSame(2, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));
        } finally {
            $extraConnections = [];
            foreach ([$firstRecoveryConnection, $secondRecoveryConnection] as $extra) {
                if ($extra instanceof Connection) {
                    $extraConnections[] = $extra;
                }
            }
            $this->releaseOwnedConnectionsBeforeDdl($primary, $probe, ...$extraConnections);
            if ($admin instanceof Connection) {
                $this->attemptCleanup('remove before preparation trigger', function () use (
                    $admin,
                    $beforeFunction,
                    $beforeTrigger,
                ): void {
                    $this->removeInsertFailureTrigger($admin, $beforeFunction, $beforeTrigger);
                });
                $this->attemptCleanup('remove after preparation trigger', function () use (
                    $admin,
                    $afterFunction,
                    $afterTrigger,
                ): void {
                    $this->removeInsertFailureTrigger($admin, $afterFunction, $afterTrigger);
                });
            }
            $this->cleanup($admin, $primary, $probe, ...$extraConnections);
        }
    }

    public function testNativeOwnedBeginDeferredCommitAndRollbackFailuresQuarantineAndRecover(): void
    {
        $admin = $primary = $probe = null;
        $beginFailure = $preBeginFailure = $rollbackFailure = $isolationFailure = null;
        $secondRecoveryConnection = $thirdRecoveryConnection = null;
        $deferred = null;
        $deferredFunction = 'f2_checkpoint_deferred_fail_fn';
        $deferredTrigger = 'f2_checkpoint_deferred_fail_trigger';
        $beforeFunction = 'f2_checkpoint_rollback_fail_fn';
        $beforeTrigger = 'f2_checkpoint_rollback_fail_trigger';
        try {
            [$admin, $primary, $probe] = $this->connections();
            $this->migrate($primary);
            $clock = $this->clock('2030-01-02T03:04:05.123456Z');
            $records = $this->recordStore($primary, $clock, $this->uuidGenerator(3720));
            $first = $records->appendRequired($this->draft(null, 1))->record()->toArray();
            $records->closeSegment('system:global', $first['segmentId']);
            $deferredTenant = new AuditTypedReference('account', 'f2-p7-deferred');
            $deferredPartition = $this->partition($deferredTenant);
            $second = $records->appendRequired($this->draft($deferredTenant, 1))->record()->toArray();
            $records->closeSegment($deferredPartition, $second['segmentId']);
            $rollbackTenant = new AuditTypedReference('account', 'f2-p7-rollback');
            $rollbackPartition = $this->partition($rollbackTenant);
            $third = $records->appendRequired($this->draft($rollbackTenant, 1))->record()->toArray();
            $records->closeSegment($rollbackPartition, $third['segmentId']);
            $segmentsBefore = [
                'first' => $this->segment($probe, 'system:global', $first['segmentId']),
                'second' => $this->segment($probe, $deferredPartition, $second['segmentId']),
                'third' => $this->segment($probe, $rollbackPartition, $third['segmentId']),
            ];
            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];

            $isolationBase = $this->connection();
            $isolationFailure = new class($isolationBase->getParams(), $isolationBase->getDriver()) extends Connection {
                public bool $isolationAttempted = false;

                public ?string $isolationSql = null;

                public int $closeCalls = 0;

                public function executeStatement(string $sql, array $params = [], array $types = []): int|string
                {
                    if (str_contains(strtoupper($sql), 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')) {
                        $this->isolationAttempted = true;
                        $this->isolationSql = strtoupper(trim(preg_replace('/\s+/', ' ', $sql) ?? $sql));

                        return parent::executeStatement(
                            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ BROKEN',
                            $params,
                            $types,
                        );
                    }

                    return parent::executeStatement($sql, $params, $types);
                }

                public function close(): void
                {
                    ++$this->closeCalls;
                    parent::close();
                    throw new \RuntimeException('f2 native close response lost after isolation failure');
                }
            };
            $isolationBase->close();
            $isolationStore = $this->checkpointStore($isolationFailure, $clock, $this->uuidGenerator(3725));
            $this->assertStoreFailure(
                fn(): mixed => $isolationStore->prepare(
                    null,
                    'system:global',
                    $first['segmentId'],
                    self::CHECKPOINT_KEY,
                    $trust,
                ),
                AuditStoreFailure::StorageUnavailable,
            );
            self::assertTrue($isolationFailure->isolationAttempted);
            self::assertSame('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ', $isolationFailure->isolationSql);
            self::assertSame(1, $isolationFailure->closeCalls);
            self::assertFalse($isolationFailure->isConnected());
            self::assertFalse($isolationFailure->isTransactionActive());
            self::assertSame(0, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));
            $this->assertStoreFailure(
                fn(): mixed => $isolationStore->prepare(
                    null,
                    'system:global',
                    $first['segmentId'],
                    self::CHECKPOINT_KEY,
                    $trust,
                ),
                AuditStoreFailure::StorageUnavailable,
            );
            self::assertSame(1, $isolationFailure->closeCalls);

            $beginBase = $this->connection();
            $beginFailure = new class($beginBase->getParams(), $beginBase->getDriver()) extends Connection {
                public bool $nativeBeginReturned = false;

                public int $nativeNestingAtResponseLoss = 0;

                public function beginTransaction(): void
                {
                    parent::beginTransaction();
                    $this->nativeBeginReturned = true;
                    $this->nativeNestingAtResponseLoss = $this->getTransactionNestingLevel();
                    throw new \RuntimeException('f2 native begin response lost');
                }
            };
            $beginBase->close();
            $beginStore = $this->checkpointStore($beginFailure, $clock, $this->uuidGenerator(3730));
            $this->assertStoreFailure(
                fn(): mixed => $beginStore->prepare(
                    null,
                    'system:global',
                    $first['segmentId'],
                    self::CHECKPOINT_KEY,
                    $trust,
                ),
                AuditStoreFailure::StorageUnavailable,
            );
            self::assertTrue($beginFailure->nativeBeginReturned);
            self::assertSame(1, $beginFailure->nativeNestingAtResponseLoss);
            self::assertFalse($beginFailure->isConnected());
            self::assertFalse($beginFailure->isTransactionActive());
            self::assertSame(0, $beginFailure->getTransactionNestingLevel());
            self::assertSame(0, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));
            self::assertSame($segmentsBefore['first'], $this->segment($probe, 'system:global', $first['segmentId']));

            $preBeginBase = $this->connection();
            $preBeginFailure = new class($preBeginBase->getParams(), $preBeginBase->getDriver()) extends Connection {
                public function beginTransaction(): void
                {
                    throw new \RuntimeException('f2 native begin rejected before driver call');
                }
            };
            $preBeginBase->close();
            $preBeginStore = $this->checkpointStore($preBeginFailure, $clock, $this->uuidGenerator(3735));
            $this->assertStoreFailure(
                fn(): mixed => $preBeginStore->prepare(
                    null,
                    'system:global',
                    $first['segmentId'],
                    self::CHECKPOINT_KEY,
                    $trust,
                ),
                AuditStoreFailure::StorageUnavailable,
            );
            self::assertFalse($preBeginFailure->isConnected());

            $this->installDeferredFailureTrigger($admin, $deferredFunction, $deferredTrigger);
            $deferredBase = $this->connection();
            $deferred = new class($deferredBase->getParams(), $deferredBase->getDriver()) extends Connection {
                public ?string $failureSqlState = null;

                public ?string $failureMessage = null;

                public function commit(): void
                {
                    try {
                        parent::commit();
                    } catch (DriverException $exception) {
                        $this->failureSqlState = $exception->getSQLState();
                        $this->failureMessage = $exception->getMessage();
                        throw $exception;
                    }
                }
            };
            $deferredBase->close();
            $deferredStore = $this->checkpointStore($deferred, $clock, $this->uuidGenerator(3740));
            $this->assertStoreFailure(
                fn(): mixed => $deferredStore->prepare(
                    $deferredTenant,
                    $deferredPartition,
                    $second['segmentId'],
                    self::CHECKPOINT_KEY,
                    $trust,
                ),
                AuditStoreFailure::StorageUnavailable,
            );
            self::assertSame('P0001', $deferred->failureSqlState);
            self::assertIsString($deferred->failureMessage);
            self::assertStringContainsString('deferred commit gate', $deferred->failureMessage);
            self::assertFalse($deferred->isConnected());
            self::assertFalse($deferred->isTransactionActive());
            self::assertSame(0, $deferred->getTransactionNestingLevel());
            self::assertSame(0, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));
            self::assertSame($segmentsBefore['second'], $this->segment(
                $probe,
                $deferredPartition,
                $second['segmentId'],
            ));
            $this->removeDeferredFailureTrigger($admin, $deferredFunction, $deferredTrigger);
            $secondRecoveryConnection = $this->connection();
            $secondRecovery = $this->checkpointStore($secondRecoveryConnection, $clock, $this->uuidGenerator(3750));
            $secondPreparation = $secondRecovery->prepare(
                $deferredTenant,
                $deferredPartition,
                $second['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            self::assertSame($second['segmentId'], $secondPreparation->boundary()['segmentId']);

            $this->installInsertFailureTrigger($admin, $beforeFunction, $beforeTrigger, 'BEFORE');
            $rollbackBase = $this->connection();
            $rollbackFailure = new class($rollbackBase->getParams(), $rollbackBase->getDriver()) extends Connection {
                public ?string $failureSqlState = null;

                public ?string $failureMessage = null;

                public function executeStatement(string $sql, array $params = [], array $types = []): int|string
                {
                    try {
                        return parent::executeStatement($sql, $params, $types);
                    } catch (DriverException $exception) {
                        $this->failureSqlState = $exception->getSQLState();
                        $this->failureMessage = $exception->getMessage();
                        throw $exception;
                    }
                }

                public function rollBack(): void
                {
                    throw new \RuntimeException('f2 native rollback failure');
                }
            };
            $rollbackBase->close();
            $rollbackStore = $this->checkpointStore($rollbackFailure, $clock, $this->uuidGenerator(3760));
            $this->assertStoreFailure(
                fn(): mixed => $rollbackStore->prepare(
                    $rollbackTenant,
                    $rollbackPartition,
                    $third['segmentId'],
                    self::CHECKPOINT_KEY,
                    $trust,
                ),
                AuditStoreFailure::StorageUnavailable,
            );
            self::assertSame('P0001', $rollbackFailure->failureSqlState);
            self::assertIsString($rollbackFailure->failureMessage);
            self::assertStringContainsString('rollback gate', $rollbackFailure->failureMessage);
            self::assertFalse($rollbackFailure->isConnected());
            self::assertFalse($rollbackFailure->isTransactionActive());
            self::assertSame(0, $rollbackFailure->getTransactionNestingLevel());
            self::assertSame(1, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));
            self::assertSame($segmentsBefore['third'], $this->segment($probe, $rollbackPartition, $third['segmentId']));
            $this->removeInsertFailureTrigger($admin, $beforeFunction, $beforeTrigger);
            $thirdRecoveryConnection = $this->connection();
            $thirdRecovery = $this->checkpointStore($thirdRecoveryConnection, $clock, $this->uuidGenerator(3770));
            $thirdPreparation = $thirdRecovery->prepare(
                $rollbackTenant,
                $rollbackPartition,
                $third['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            self::assertSame($third['segmentId'], $thirdPreparation->boundary()['segmentId']);
            self::assertSame(2, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));
        } finally {
            $extraConnections = [];
            foreach ([
                $beginFailure,
                $preBeginFailure,
                $deferred,
                $rollbackFailure,
                $isolationFailure,
                $secondRecoveryConnection,
                $thirdRecoveryConnection,
            ] as $extra) {
                if ($extra instanceof Connection) {
                    $extraConnections[] = $extra;
                }
            }
            $this->releaseOwnedConnectionsBeforeDdl($primary, $probe, ...$extraConnections);
            if ($admin instanceof Connection) {
                $this->attemptCleanup('remove deferred P7 trigger', function () use (
                    $admin,
                    $deferredFunction,
                    $deferredTrigger,
                ): void {
                    $this->removeDeferredFailureTrigger($admin, $deferredFunction, $deferredTrigger);
                });
                $this->attemptCleanup('remove rollback P7 trigger', function () use (
                    $admin,
                    $beforeFunction,
                    $beforeTrigger,
                ): void {
                    $this->removeInsertFailureTrigger($admin, $beforeFunction, $beforeTrigger);
                });
            }
            $this->cleanup($admin, $primary, $probe, ...$extraConnections);
        }
    }

    public function testNativeGeneratedCheckpointIdCollisionAcrossPartitionsIsIntegrityFailure(): void
    {
        $admin = $primary = $probe = null;
        try {
            [$admin, $primary, $probe] = $this->connections();
            $this->migrate($primary);
            $clock = $this->clock('2030-01-02T03:04:05.123456Z');
            $records = $this->recordStore($primary, $clock, $this->uuidGenerator(3800));
            $system = $records->appendRequired($this->draft(null, 1))->record()->toArray();
            $records->closeSegment('system:global', $system['segmentId']);
            $tenant = new AuditTypedReference('account', 'f2-native-collision-tenant');
            $tenantPartition = $this->partition($tenant);
            $tenantRecord = $records->appendRequired($this->draft($tenant, 2))->record()->toArray();
            $records->closeSegment($tenantPartition, $tenantRecord['segmentId']);
            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];
            $collisionId = '01941f29-7c00-7000-8000-000000000777';
            $checkpoints = $this->checkpointStore($primary, $clock, $this->fixedUuidGenerator($collisionId));
            $systemPreparation = $checkpoints->prepare(
                null,
                'system:global',
                $system['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            self::assertSame($collisionId, $systemPreparation->unsigned()['checkpointId']);
            $collisionRejected = false;
            try {
                $checkpoints->prepare(
                    $tenant,
                    $tenantPartition,
                    $tenantRecord['segmentId'],
                    self::CHECKPOINT_KEY,
                    $trust,
                );
                self::fail('a cross-partition generated checkpoint ID collision was accepted');
            } catch (AuditStoreException $exception) {
                $collisionRejected = true;
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            }
            self::assertTrue($collisionRejected);
            self::assertFalse($primary->isTransactionActive());
            self::assertSame(1, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));
            self::assertSame($collisionId, $this->preparationRow($probe, $collisionId)['checkpoint_id']);
            self::assertSame($system['segmentId'], $systemPreparation->boundary()['segmentId']);
        } finally {
            $this->cleanup($admin, $primary, $probe);
        }
    }

    public function testNativeRepeatableReadRaceReturnsStorageUnavailableAndFreshRootRecoversWinner(): void
    {
        $admin = $primary = $probe = $recoveryConnection = null;
        $winnerPid = $loserPid = null;
        $winnerStatus = $loserStatus = 0;
        $gateHeld = false;
        $gatePid = null;
        $gateFunction = 'f2_checkpoint_race_gate_fn';
        $gateTrigger = 'f2_checkpoint_race_gate_trigger';
        $raceLock = 'f2-native-preparation-race';
        $winnerFile = tempnam(sys_get_temp_dir(), 'f2-winner-');
        $loserFile = tempnam(sys_get_temp_dir(), 'f2-loser-');
        $winnerBackendFile = tempnam(sys_get_temp_dir(), 'f2-winner-backend-');
        $loserBackendFile = tempnam(sys_get_temp_dir(), 'f2-loser-backend-');
        $winnerStartFile = $this->startBarrier('f2-winner-start-');
        $loserStartFile = $this->startBarrier('f2-loser-start-');
        self::assertIsString($winnerFile);
        self::assertIsString($loserFile);
        self::assertIsString($winnerBackendFile);
        self::assertIsString($loserBackendFile);
        try {
            [$admin, $primary, $probe] = $this->connections();
            $this->migrate($primary);
            $clock = $this->clock('2030-01-02T03:04:05.123456Z');
            $records = $this->recordStore($primary, $clock, $this->uuidGenerator(4000));
            $appended = $records->appendRequired($this->draft(null, 1));
            $value = $appended->record()->toArray();
            $records->closeSegment('system:global', $value['segmentId']);
            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];
            $this->installRaceGate($admin, $gateFunction, $gateTrigger, 'audit_checkpoint_preparations', $raceLock);
            $this->closeConnectionsBeforeFork($admin, $primary, $probe);
            $records = $appended = null;
            $admin = $primary = $probe = null;

            $winnerPid = $this->forkNativeChild(
                null,
                null,
                null,
                $winnerBackendFile,
                $winnerFile,
                function (Connection $child) use ($clock, $value, $trust): array {
                    $store = $this->checkpointStore($child, $clock, $this->uuidGenerator(4100));
                    $preparation = $store->prepare(
                        null,
                        'system:global',
                        $value['segmentId'],
                        self::CHECKPOINT_KEY,
                        $trust,
                    );

                    return [
                        'status' => 'ok',
                        'unsigned' => $preparation->unsigned(),
                    ];
                },
                $winnerStartFile,
            );

            $loserPid = $this->forkNativeChild(
                null,
                null,
                null,
                $loserBackendFile,
                $loserFile,
                function (Connection $child) use ($clock, $value, $trust): array {
                    try {
                        $store = $this->checkpointStore($child, $clock, $this->uuidGenerator(4200));
                        $store->prepare(null, 'system:global', $value['segmentId'], self::CHECKPOINT_KEY, $trust);

                        return ['status' => 'unexpected-success'];
                    } catch (AuditStoreException $exception) {
                        return [
                            'status' => 'error',
                            'failure' => $exception->failure()->value,
                            'connected' => $child->isConnected(),
                        ];
                    }
                },
                $loserStartFile,
            );

            $admin = $this->connection();
            $probe = $this->connection();
            $gatePid = $this->acquireRaceGate($admin, $raceLock);
            $gateHeld = true;
            $this->signalStartBarrier($winnerStartFile);
            $winnerObservation = $this->waitForRaceWinnerAtGate($probe, $winnerBackendFile, $raceLock, $gatePid);
            $this->assertRaceWinnerBackendIdentity($winnerObservation['pid'], $winnerBackendFile);
            $this->signalStartBarrier($loserStartFile);
            $observation = $this->waitForRaceParticipants(
                $probe,
                $winnerBackendFile,
                $loserBackendFile,
                $raceLock,
                gatePid: $gatePid,
                loserLockKey: $raceLock,
            );
            $this->assertRaceBackendPairIdentity(
                $observation['winner'],
                $observation['loser'],
                $winnerBackendFile,
                $loserBackendFile,
            );
            self::assertTrue($observation['winner_blocked_by_gate']);
            $this->releaseRaceGate($admin, $raceLock);
            $gateHeld = false;

            self::assertTrue($this->waitForChild($winnerPid, $winnerStatus), 'winner did not finish');
            self::assertTrue($this->waitForChild($loserPid, $loserStatus), 'loser did not finish');
            self::assertSame(0, $winnerStatus);
            self::assertSame(0, $loserStatus);
            $winner = json_decode((string) file_get_contents($winnerFile), true, flags: JSON_THROW_ON_ERROR);
            $loser = json_decode((string) file_get_contents($loserFile), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('ok', $winner['status']);
            self::assertSame('storage_unavailable', $loser['failure']);
            self::assertFalse($loser['connected']);
            self::assertSame(1, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));

            $recoveryConnection = $this->connection();
            $recovery = $this->checkpointStore($recoveryConnection, $clock, $this->uuidGenerator(4300));
            $stored = $this->preparationRow($recoveryConnection, $winner['unsigned']['checkpointId']);
            $storedUnsigned = json_decode(
                PostgreSqlBytea::string($stored['unsigned_checkpoint']),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            $storedSegment = $this->segment($recoveryConnection, 'system:global', $value['segmentId']);
            self::assertSame('system:global', $storedUnsigned['partitionId']);
            self::assertSame($value['segmentId'], $storedUnsigned['segmentId']);
            self::assertSame(1, $storedUnsigned['firstSequence']);
            self::assertSame(1, $storedUnsigned['lastSequence']);
            self::assertSame($storedSegment['head_record_hash'], $storedUnsigned['chainHeadHash']);
            self::assertSame($storedSegment['head_record_hash'], $storedUnsigned['firstRecordHash']);
            self::assertSame($winner['unsigned'], $storedUnsigned);
            $recovered = $recovery->prepare(null, 'system:global', $value['segmentId'], self::CHECKPOINT_KEY, $trust);
            self::assertSame($storedUnsigned, $recovered->unsigned());
            self::assertFalse($recoveryConnection->isTransactionActive());
        } finally {
            $admin = $this->reconnectAdminForCleanup($admin);
            if ($gateHeld && $admin instanceof Connection) {
                $this->attemptCleanup('release preparation race gate', function () use ($admin, $raceLock): void {
                    $this->releaseRaceGate($admin, $raceLock);
                });
            }
            if (is_int($winnerPid) && $winnerPid > 0) {
                $this->terminateChild($winnerPid, $winnerStatus);
            }
            if (is_int($loserPid) && $loserPid > 0) {
                $this->terminateChild($loserPid, $loserStatus);
            }
            $extraConnections = [];
            if ($recoveryConnection instanceof Connection) {
                $extraConnections[] = $recoveryConnection;
            }
            $this->releaseOwnedConnectionsBeforeDdl($primary, $probe, ...$extraConnections);
            if ($admin instanceof Connection) {
                $this->attemptCleanup('remove preparation race gate', function () use (
                    $admin,
                    $gateFunction,
                    $gateTrigger,
                ): void {
                    $this->removeRaceGate($admin, $gateFunction, $gateTrigger);
                });
            }
            $this->cleanup($admin, $primary, $probe, ...$extraConnections);
            foreach ([
                $winnerFile,
                $loserFile,
                $winnerBackendFile,
                $loserBackendFile,
                $winnerStartFile,
                $loserStartFile,
            ] as $file) {
                if (is_string($file)) {
                    $this->removeOwnedFile('remove native race file', $file);
                }
            }
        }
    }

    public function testNativePersistSameBytesRaceQuarantinesLoserAndRecoversExactWinner(): void
    {
        $admin = $primary = $probe = $recoveryConnection = null;
        $winnerPid = $loserPid = null;
        $winnerStatus = $loserStatus = 0;
        $gateHeld = false;
        $gatePid = null;
        $gateFunction = 'f2_checkpoint_persist_race_gate_fn';
        $gateTrigger = 'f2_checkpoint_persist_race_gate_trigger';
        $raceLock = 'f2-native-persist-same-bytes-race';
        $winnerFile = tempnam(sys_get_temp_dir(), 'f2-persist-winner-');
        $loserFile = tempnam(sys_get_temp_dir(), 'f2-persist-loser-');
        $winnerBackendFile = tempnam(sys_get_temp_dir(), 'f2-persist-winner-backend-');
        $loserBackendFile = tempnam(sys_get_temp_dir(), 'f2-persist-loser-backend-');
        $winnerStartFile = $this->startBarrier('f2-persist-winner-start-');
        $loserStartFile = $this->startBarrier('f2-persist-loser-start-');
        self::assertIsString($winnerFile);
        self::assertIsString($loserFile);
        self::assertIsString($winnerBackendFile);
        self::assertIsString($loserBackendFile);
        try {
            [$admin, $primary, $probe] = $this->connections();
            $this->migrate($primary);
            $clock = $this->clock('2030-01-02T03:04:05.123456Z');
            $records = $this->recordStore($primary, $clock, $this->uuidGenerator(4350));
            $appended = $records->appendRequired($this->draft(null, 1))->record()->toArray();
            $records->closeSegment('system:global', $appended['segmentId']);
            $segmentBefore = $this->segment($probe, 'system:global', $appended['segmentId']);
            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];
            $seed = hex2bin((string) $fixture['seeds']['checkpoint']);
            self::assertIsString($seed);
            $checkpoints = $this->checkpointStore($primary, $clock, $this->uuidGenerator(4360));
            $preparation = $checkpoints->prepare(
                null,
                'system:global',
                $appended['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            $unsigned = $preparation->unsigned();
            $complete = AuditArtifactSigner::checkpoint($unsigned, $seed, $trust, $unsigned['signedAt']);
            AuditArtifactVerifier::checkpoint($complete, $trust, $unsigned['signedAt']);
            $this->installRaceGate($admin, $gateFunction, $gateTrigger, 'audit_checkpoints', $raceLock);
            $this->closeConnectionsBeforeFork($admin, $primary, $probe);
            $records = $checkpoints = null;
            $admin = $primary = $probe = null;

            $winnerPid = $this->forkNativeChild(
                null,
                null,
                null,
                $winnerBackendFile,
                $winnerFile,
                function (Connection $child) use ($clock, $appended, $complete, $trust): array {
                    $store = $this->checkpointStore($child, $clock, $this->uuidGenerator(4370));
                    $store->persist(null, 'system:global', $appended['segmentId'], $complete, $trust);

                    return ['status' => 'ok'];
                },
                $winnerStartFile,
            );
            $loserPid = $this->forkNativeChild(
                null,
                null,
                null,
                $loserBackendFile,
                $loserFile,
                function (Connection $child) use ($clock, $appended, $complete, $trust): array {
                    try {
                        $store = $this->checkpointStore($child, $clock, $this->uuidGenerator(4380));
                        $store->persist(null, 'system:global', $appended['segmentId'], $complete, $trust);

                        return ['status' => 'unexpected-success'];
                    } catch (AuditStoreException $exception) {
                        return [
                            'status' => 'error',
                            'failure' => $exception->failure()->value,
                            'connected' => $child->isConnected(),
                        ];
                    }
                },
                $loserStartFile,
            );

            $admin = $this->connection();
            $probe = $this->connection();
            $gatePid = $this->acquireRaceGate($admin, $raceLock);
            $gateHeld = true;
            $this->signalStartBarrier($winnerStartFile);
            $winnerObservation = $this->waitForRaceWinnerAtGate($probe, $winnerBackendFile, $raceLock, $gatePid);
            $this->assertRaceWinnerBackendIdentity($winnerObservation['pid'], $winnerBackendFile);
            $this->signalStartBarrier($loserStartFile);
            $observation = $this->waitForRaceParticipants(
                $probe,
                $winnerBackendFile,
                $loserBackendFile,
                $raceLock,
                gatePid: $gatePid,
                loserLockKey: $raceLock,
            );
            $this->assertRaceBackendPairIdentity(
                $observation['winner'],
                $observation['loser'],
                $winnerBackendFile,
                $loserBackendFile,
            );
            self::assertTrue($observation['winner_blocked_by_gate']);
            $this->releaseRaceGate($admin, $raceLock);
            $gateHeld = false;

            self::assertTrue($this->waitForChild($winnerPid, $winnerStatus), 'persist winner did not finish');
            self::assertTrue($this->waitForChild($loserPid, $loserStatus), 'persist loser did not finish');
            self::assertSame(0, $winnerStatus);
            self::assertSame(0, $loserStatus);
            $winner = json_decode((string) file_get_contents($winnerFile), true, flags: JSON_THROW_ON_ERROR);
            $loser = json_decode((string) file_get_contents($loserFile), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('ok', $winner['status']);
            self::assertSame('storage_unavailable', $loser['failure']);
            self::assertFalse($loser['connected']);
            self::assertSame(1, (int) $this->rowCount($probe, 'audit_checkpoints'));
            self::assertSame($segmentBefore, $this->segment($probe, 'system:global', $appended['segmentId']));

            $recoveryConnection = $this->connection();
            $stored = $this->checkpointRow($recoveryConnection, $unsigned['checkpointId']);
            self::assertSame($complete, PostgreSqlBytea::string($stored['complete_bytes']));
            self::assertSame(AuditCheckpointCodec::decode($complete)['checkpointHash'], $stored['checkpoint_hash']);
            $recovery = $this->checkpointStore($recoveryConnection, $clock, $this->uuidGenerator(4390));
            $recovery->persist(null, 'system:global', $appended['segmentId'], $complete, $trust);
            self::assertSame(
                $complete,
                PostgreSqlBytea::string(
                    $this->checkpointRow($recoveryConnection, $unsigned['checkpointId'])['complete_bytes'],
                ),
            );
        } finally {
            $admin = $this->reconnectAdminForCleanup($admin);
            if ($gateHeld && $admin instanceof Connection) {
                $this->attemptCleanup('release same-byte persist race gate', function () use ($admin, $raceLock): void {
                    $this->releaseRaceGate($admin, $raceLock);
                });
            }
            if (is_int($winnerPid) && $winnerPid > 0) {
                $this->terminateChild($winnerPid, $winnerStatus);
            }
            if (is_int($loserPid) && $loserPid > 0) {
                $this->terminateChild($loserPid, $loserStatus);
            }
            $extraConnections = [];
            if ($recoveryConnection instanceof Connection) {
                $extraConnections[] = $recoveryConnection;
            }
            $this->releaseOwnedConnectionsBeforeDdl($primary, $probe, ...$extraConnections);
            if ($admin instanceof Connection) {
                $this->attemptCleanup('remove same-byte persist race gate', function () use (
                    $admin,
                    $gateFunction,
                    $gateTrigger,
                ): void {
                    $this->removeRaceGate($admin, $gateFunction, $gateTrigger, 'audit_checkpoints');
                });
            }
            $this->cleanup($admin, $primary, $probe, ...$extraConnections);
            foreach ([
                $winnerFile,
                $loserFile,
                $winnerBackendFile,
                $loserBackendFile,
                $winnerStartFile,
                $loserStartFile,
            ] as $file) {
                if (is_string($file)) {
                    $this->removeOwnedFile('remove native persist race file', $file);
                }
            }
        }
    }

    public function testNativePersistConflictingBytesRaceRejectsOneAndPreservesWinner(): void
    {
        $admin = $primary = $probe = $recoveryConnection = $productConnection = null;
        $winnerPid = $loserPid = $productPid = null;
        $winnerStatus = $loserStatus = $productStatus = 0;
        $gateHeld = false;
        $partitionGateHeld = false;
        $gatePid = null;
        $partitionGatePid = null;
        $raceLock = 'f2-native-persist-conflicting-bytes-race';
        $partitionLock = 'blackops.audit.partition.v1:' . self::SCHEMA . ':system:global';
        $winnerFile = tempnam(sys_get_temp_dir(), 'f2-conflict-winner-');
        $loserFile = tempnam(sys_get_temp_dir(), 'f2-conflict-loser-');
        $winnerBackendFile = tempnam(sys_get_temp_dir(), 'f2-conflict-winner-backend-');
        $loserBackendFile = tempnam(sys_get_temp_dir(), 'f2-conflict-loser-backend-');
        $productFile = tempnam(sys_get_temp_dir(), 'f2-conflict-product-');
        $productBackendFile = tempnam(sys_get_temp_dir(), 'f2-conflict-product-backend-');
        $winnerStartFile = $this->startBarrier('f2-conflict-winner-start-');
        $loserStartFile = $this->startBarrier('f2-conflict-loser-start-');
        $productStartFile = $this->startBarrier('f2-conflict-product-start-');
        self::assertIsString($winnerFile);
        self::assertIsString($loserFile);
        self::assertIsString($winnerBackendFile);
        self::assertIsString($loserBackendFile);
        self::assertIsString($productFile);
        self::assertIsString($productBackendFile);
        try {
            [$admin, $primary, $probe] = $this->connections();
            $this->migrate($primary);
            $clock = $this->clock('2030-01-02T03:04:05.123456Z');
            $records = $this->recordStore($primary, $clock, $this->uuidGenerator(4450));
            $appended = $records->appendRequired($this->draft(null, 1))->record()->toArray();
            $records->closeSegment('system:global', $appended['segmentId']);
            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];
            $seed = hex2bin((string) $fixture['seeds']['checkpoint']);
            self::assertIsString($seed);
            $checkpoints = $this->checkpointStore($primary, $clock, $this->uuidGenerator(4460));
            $preparation = $checkpoints->prepare(
                null,
                'system:global',
                $appended['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            $unsigned = $preparation->unsigned();
            $winnerComplete = AuditArtifactSigner::checkpoint($unsigned, $seed, $trust, $unsigned['signedAt']);
            $conflictingUnsigned = $unsigned;
            $conflictingUnsigned['sealedAt'] = '2030-01-02T03:04:05.000000Z';
            $conflictingComplete = AuditArtifactSigner::checkpoint(
                $conflictingUnsigned,
                $seed,
                $trust,
                $conflictingUnsigned['signedAt'],
            );
            self::assertNotSame($winnerComplete, $conflictingComplete);
            AuditArtifactVerifier::checkpoint($winnerComplete, $trust, $unsigned['signedAt']);
            AuditArtifactVerifier::checkpoint($conflictingComplete, $trust, $conflictingUnsigned['signedAt']);
            $winnerHash = AuditCheckpointCodec::decode($winnerComplete)['checkpointHash'];
            $conflictingHash = AuditCheckpointCodec::decode($conflictingComplete)['checkpointHash'];
            self::assertNotSame($winnerHash, $conflictingHash);

            $insert = static function (Connection $child, string $completeBytes): void {
                $complete = AuditCheckpointCodec::decode($completeBytes);
                $child->executeStatement(
                    'INSERT INTO "'
                    . self::SCHEMA
                    . '"."audit_checkpoints" '
                    . '(checkpoint_id, checkpoint_hash, complete_bytes) '
                    . 'VALUES (:checkpoint_id, :checkpoint_hash, :complete_bytes)',
                    [
                        'checkpoint_id' => $complete['checkpointId'],
                        'checkpoint_hash' => $complete['checkpointHash'],
                        'complete_bytes' => $completeBytes,
                    ],
                    [
                        'checkpoint_id' => ParameterType::STRING,
                        'checkpoint_hash' => ParameterType::STRING,
                        'complete_bytes' => ParameterType::BINARY,
                    ],
                );
            };

            $this->closeConnectionsBeforeFork($admin, $primary, $probe);
            $records = $checkpoints = null;
            $admin = $primary = $probe = null;

            $winnerPid = $this->forkNativeChild(
                null,
                null,
                null,
                $winnerBackendFile,
                $winnerFile,
                function (Connection $child) use ($insert, $winnerComplete, $raceLock): array {
                    $child->beginTransaction();
                    $insert($child, $winnerComplete);
                    $child->executeStatement(
                        'SELECT pg_advisory_xact_lock(hashtextextended(:lock_key, 0))',
                        ['lock_key' => $raceLock],
                        ['lock_key' => ParameterType::STRING],
                    );
                    $child->commit();

                    return ['status' => 'ok'];
                },
                $winnerStartFile,
            );
            $loserPid = $this->forkNativeChild(
                null,
                null,
                null,
                $loserBackendFile,
                $loserFile,
                function (Connection $child) use ($insert, $conflictingComplete): array {
                    try {
                        $insert($child, $conflictingComplete);

                        return ['status' => 'unexpected-success'];
                    } catch (DriverException $exception) {
                        return [
                            'status' => 'error',
                            'failure' => 'unique_constraint',
                            'sqlState' => $exception->getSQLState(),
                            'connected' => $child->isConnected(),
                        ];
                    }
                },
                $loserStartFile,
            );
            $productPid = $this->forkNativeChild(
                null,
                null,
                null,
                $productBackendFile,
                $productFile,
                function (Connection $child) use ($clock, $appended, $conflictingComplete, $trust): array {
                    try {
                        $store = $this->checkpointStore($child, $clock, $this->uuidGenerator(4480));
                        $store->persist(null, 'system:global', $appended['segmentId'], $conflictingComplete, $trust);

                        return ['status' => 'unexpected-success'];
                    } catch (AuditStoreException $exception) {
                        return [
                            'status' => 'error',
                            'failure' => $exception->failure()->value,
                            'connected' => $child->isConnected(),
                            'transactionActive' => $child->isTransactionActive(),
                            'nesting' => $child->getTransactionNestingLevel(),
                        ];
                    }
                },
                $productStartFile,
            );
            $admin = $this->connection();
            $probe = $this->connection();
            $gatePid = $this->acquireRaceGate($admin, $raceLock);
            $gateHeld = true;
            $partitionGatePid = $this->acquireRaceGate($admin, $partitionLock);
            $partitionGateHeld = true;
            $this->signalStartBarrier($winnerStartFile);
            $winnerObservation = $this->waitForRaceWinnerAtGate($probe, $winnerBackendFile, $raceLock, $gatePid);
            $winnerBackendPid = $this->assertRaceWinnerBackendIdentity($winnerObservation['pid'], $winnerBackendFile);
            $this->signalStartBarrier($loserStartFile);
            $observation = $this->waitForRaceParticipants(
                $probe,
                $winnerBackendFile,
                $loserBackendFile,
                $raceLock,
                'transactionid',
                false,
                $gatePid,
            );
            $this->assertRaceBackendPairIdentity(
                $observation['winner'],
                $observation['loser'],
                $winnerBackendFile,
                $loserBackendFile,
            );
            self::assertTrue($observation['winner_blocked_by_gate']);
            self::assertTrue($observation['loser_blocked_by_winner']);
            $this->signalStartBarrier($productStartFile);
            $productBackendPid = $this->waitForBackendPid($productBackendFile);
            self::assertGreaterThan(0, $productBackendPid);
            self::assertNotSame($winnerBackendPid, $productBackendPid);
            self::assertFalse($admin->isTransactionActive());
            $partitionObservation = $this->waitForBackendBlockedBy(
                $admin,
                $productBackendFile,
                $partitionGatePid,
                'advisory',
                'SELECT PG_ADVISORY_XACT_LOCK',
            );
            self::assertSame($productBackendPid, $partitionObservation['pid']);
            $this->releaseRaceGate($admin, $partitionLock);
            $partitionGateHeld = false;
            $preparationObservation = $this->waitForBackendBlockedBy(
                $admin,
                $productBackendFile,
                $winnerBackendPid,
                'transactionid',
                'FROM "' . self::SCHEMA . '"."audit_checkpoint_preparations"',
            );
            self::assertSame($productBackendPid, $preparationObservation['pid']);
            $this->releaseRaceGate($admin, $raceLock);
            $gateHeld = false;

            self::assertTrue($this->waitForChild($winnerPid, $winnerStatus), 'conflicting winner did not finish');
            self::assertTrue($this->waitForChild($loserPid, $loserStatus), 'conflicting loser did not finish');
            self::assertTrue(
                $this->waitForChild($productPid, $productStatus),
                'conflicting Product request did not finish',
            );
            self::assertSame(0, $winnerStatus);
            self::assertSame(0, $loserStatus);
            self::assertSame(0, $productStatus);
            $winner = json_decode((string) file_get_contents($winnerFile), true, flags: JSON_THROW_ON_ERROR);
            $loser = json_decode((string) file_get_contents($loserFile), true, flags: JSON_THROW_ON_ERROR);
            $product = json_decode((string) file_get_contents($productFile), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('ok', $winner['status']);
            self::assertSame('error', $loser['status']);
            self::assertSame('unique_constraint', $loser['failure']);
            self::assertSame('23505', $loser['sqlState']);
            self::assertSame('error', $product['status']);
            self::assertSame('integrity_invalid', $product['failure']);
            self::assertTrue($product['connected']);
            self::assertFalse($product['transactionActive']);
            self::assertSame(0, $product['nesting']);
            self::assertSame(1, (int) $this->rowCount($probe, 'audit_checkpoints'));
            $stored = $this->checkpointRow($probe, $unsigned['checkpointId']);
            self::assertSame($winnerComplete, PostgreSqlBytea::string($stored['complete_bytes']));
            self::assertSame($winnerHash, $stored['checkpoint_hash']);

            $recoveryConnection = $this->connection();
            $recoveredRow = $this->checkpointRow($recoveryConnection, $unsigned['checkpointId']);
            self::assertSame($winnerComplete, PostgreSqlBytea::string($recoveredRow['complete_bytes']));
            self::assertSame($winnerHash, $recoveredRow['checkpoint_hash']);

            $productConnection = $this->connection();
            $productStore = $this->checkpointStore($productConnection, $clock, $this->uuidGenerator(4470));
            $this->assertStoreFailure(
                fn(): mixed => $productStore->persist(
                    null,
                    'system:global',
                    $appended['segmentId'],
                    $conflictingComplete,
                    $trust,
                ),
                AuditStoreFailure::IntegrityInvalid,
            );
            $productStore->persist(null, 'system:global', $appended['segmentId'], $winnerComplete, $trust);
            $productRow = $this->checkpointRow($productConnection, $unsigned['checkpointId']);
            self::assertSame($winnerComplete, PostgreSqlBytea::string($productRow['complete_bytes']));
            self::assertSame($winnerHash, $productRow['checkpoint_hash']);
        } finally {
            $admin = $this->reconnectAdminForCleanup($admin);
            if ($gateHeld && $admin instanceof Connection) {
                $this->attemptCleanup('release conflicting-byte persist race gate', function () use (
                    $admin,
                    $raceLock,
                ): void {
                    $this->releaseRaceGate($admin, $raceLock);
                });
            }
            if ($partitionGateHeld && $admin instanceof Connection) {
                $this->attemptCleanup('release conflicting-byte partition gate', function () use (
                    $admin,
                    $partitionLock,
                ): void {
                    $this->releaseRaceGate($admin, $partitionLock);
                });
            }
            if (is_int($winnerPid) && $winnerPid > 0) {
                $this->terminateChild($winnerPid, $winnerStatus);
            }
            if (is_int($loserPid) && $loserPid > 0) {
                $this->terminateChild($loserPid, $loserStatus);
            }
            if (is_int($productPid) && $productPid > 0) {
                $this->terminateChild($productPid, $productStatus);
            }
            $extraConnections = [];
            foreach ([$recoveryConnection, $productConnection] as $extra) {
                if ($extra instanceof Connection) {
                    $extraConnections[] = $extra;
                }
            }
            $this->releaseOwnedConnectionsBeforeDdl($primary, $probe, ...$extraConnections);
            $this->cleanup($admin, $primary, $probe, ...$extraConnections);
            foreach ([
                $winnerFile,
                $loserFile,
                $productFile,
                $winnerBackendFile,
                $loserBackendFile,
                $productBackendFile,
                $winnerStartFile,
                $loserStartFile,
                $productStartFile,
            ] as $file) {
                if (is_string($file)) {
                    $this->removeOwnedFile('remove conflicting-byte race file', $file);
                }
            }
        }
    }

    public function testNativeProofFenceSerializesSuccessorAppendAfterCheckpointCommit(): void
    {
        $admin = $primary = $probe = null;
        $proofPid = $appendPid = null;
        $proofStatus = $appendStatus = 0;
        $gateHeld = false;
        $gatePid = null;
        $gateFunction = 'f2_checkpoint_append_race_gate_fn';
        $gateTrigger = 'f2_checkpoint_append_race_gate_trigger';
        $raceLock = 'f2-native-successor-append-race';
        $proofFile = tempnam(sys_get_temp_dir(), 'f2-append-proof-');
        $appendFile = tempnam(sys_get_temp_dir(), 'f2-append-result-');
        $proofBackendFile = tempnam(sys_get_temp_dir(), 'f2-append-proof-backend-');
        $appendBackendFile = tempnam(sys_get_temp_dir(), 'f2-append-result-backend-');
        $proofStartFile = $this->startBarrier('f2-append-proof-start-');
        $appendStartFile = $this->startBarrier('f2-append-start-');
        self::assertIsString($proofFile);
        self::assertIsString($appendFile);
        self::assertIsString($proofBackendFile);
        self::assertIsString($appendBackendFile);
        try {
            [$admin, $primary, $probe] = $this->connections();
            $this->migrate($primary);
            $clock = $this->clock('2030-01-02T03:04:05.123456Z');
            $records = $this->recordStore($primary, $clock, $this->uuidGenerator(4400));
            $appended = $records->appendRequired($this->draft(null, 1))->record()->toArray();
            $records->closeSegment('system:global', $appended['segmentId']);
            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];
            $this->installRaceGate($admin, $gateFunction, $gateTrigger, 'audit_checkpoint_preparations', $raceLock);
            $this->closeConnectionsBeforeFork($admin, $primary, $probe);
            $records = null;
            $admin = $primary = $probe = null;

            $proofPid = $this->forkNativeChild(
                null,
                null,
                null,
                $proofBackendFile,
                $proofFile,
                function (Connection $child) use ($clock, $appended, $trust): array {
                    $store = $this->checkpointStore($child, $clock, $this->uuidGenerator(4410));
                    $preparation = $store->prepare(
                        null,
                        'system:global',
                        $appended['segmentId'],
                        self::CHECKPOINT_KEY,
                        $trust,
                    );

                    return [
                        'status' => 'ok',
                        'unsigned' => $preparation->unsigned(),
                    ];
                },
                $proofStartFile,
            );

            $appendPid = $this->forkNativeChild(
                null,
                null,
                null,
                $appendBackendFile,
                $appendFile,
                function (Connection $child) use ($clock): array {
                    $childRecords = $this->recordStore($child, $clock, $this->uuidGenerator(4420));
                    $next = $childRecords->appendRequired($this->draft(null, 2))->record()->toArray();

                    return [
                        'status' => 'ok',
                        'segmentId' => $next['segmentId'],
                        'sequence' => $next['sequence'],
                    ];
                },
                $appendStartFile,
            );

            $admin = $this->connection();
            $probe = $this->connection();
            $gatePid = $this->acquireRaceGate($admin, $raceLock);
            $gateHeld = true;
            $this->signalStartBarrier($proofStartFile);
            $winnerObservation = $this->waitForRaceWinnerAtGate($probe, $proofBackendFile, $raceLock, $gatePid);
            $this->assertRaceWinnerBackendIdentity($winnerObservation['pid'], $proofBackendFile);
            $this->signalStartBarrier($appendStartFile);
            $observation = $this->waitForRaceParticipants(
                $probe,
                $proofBackendFile,
                $appendBackendFile,
                $raceLock,
                'advisory',
                false,
                $gatePid,
                'blackops.audit.partition.v1:' . self::SCHEMA . ':system:global',
            );
            $this->assertRaceBackendPairIdentity(
                $observation['winner'],
                $observation['loser'],
                $proofBackendFile,
                $appendBackendFile,
            );
            self::assertTrue($observation['winner_blocked_by_gate']);
            self::assertTrue($observation['loser_blocked_by_winner']);
            $this->releaseRaceGate($admin, $raceLock);
            $gateHeld = false;

            self::assertTrue($this->waitForChild($proofPid, $proofStatus), 'proof process did not finish');
            self::assertTrue($this->waitForChild($appendPid, $appendStatus), 'successor append did not finish');
            self::assertSame(0, $proofStatus);
            self::assertSame(0, $appendStatus);
            $proof = json_decode((string) file_get_contents($proofFile), true, flags: JSON_THROW_ON_ERROR);
            $append = json_decode((string) file_get_contents($appendFile), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('ok', $proof['status']);
            self::assertSame('ok', $append['status']);
            $closed = $this->segment($probe, 'system:global', $appended['segmentId']);
            self::assertSame($append['segmentId'], $closed['successor_segment_id']);
            self::assertSame(2, (int) $append['sequence']);
            self::assertSame(
                1,
                (int) $probe->fetchOne(
                    'SELECT count(*) FROM "'
                    . self::SCHEMA
                    . '"."audit_records" '
                    . 'WHERE partition_id = :partition_id AND segment_id = CAST(:segment_id AS uuid)',
                    ['partition_id' => 'system:global', 'segment_id' => $append['segmentId']],
                    ['partition_id' => ParameterType::STRING, 'segment_id' => ParameterType::STRING],
                ),
            );
        } finally {
            $admin = $this->reconnectAdminForCleanup($admin);
            if ($gateHeld && $admin instanceof Connection) {
                $this->attemptCleanup('release successor append race gate', function () use ($admin, $raceLock): void {
                    $this->releaseRaceGate($admin, $raceLock);
                });
            }
            if (is_int($proofPid) && $proofPid > 0) {
                $this->terminateChild($proofPid, $proofStatus);
            }
            if (is_int($appendPid) && $appendPid > 0) {
                $this->terminateChild($appendPid, $appendStatus);
            }
            $this->releaseOwnedConnectionsBeforeDdl($primary, $probe);
            if ($admin instanceof Connection) {
                $this->attemptCleanup('remove successor append race gate', function () use (
                    $admin,
                    $gateFunction,
                    $gateTrigger,
                ): void {
                    $this->removeRaceGate($admin, $gateFunction, $gateTrigger);
                });
            }
            $this->cleanup($admin, $primary, $probe);
            foreach ([
                $proofFile,
                $appendFile,
                $proofBackendFile,
                $appendBackendFile,
                $proofStartFile,
                $appendStartFile,
            ] as $file) {
                if (is_string($file)) {
                    $this->removeOwnedFile('remove successor append race file', $file);
                }
            }
        }
    }

    public function testNativeProofAcceptsPopulatedOpenAndThenClosedSuccessor(): void
    {
        $admin = $primary = $probe = null;
        try {
            [$admin, $primary, $probe] = $this->connections();
            $this->migrate($primary);
            $clock = $this->clock('2030-01-02T03:04:05.123456Z');
            $records = $this->recordStore($primary, $clock, $this->uuidGenerator(4430));
            $first = $records->appendRequired($this->draft(null, 1))->record()->toArray();
            $records->closeSegment('system:global', $first['segmentId']);
            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];
            $checkpoints = $this->checkpointStore($primary, $clock, $this->uuidGenerator(4440));
            $preparation = $checkpoints->prepare(
                null,
                'system:global',
                $first['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            $unsigned = $preparation->unsigned();
            $firstSegment = $this->segment($probe, 'system:global', $first['segmentId']);
            $successorId = (string) $firstSegment['successor_segment_id'];
            $next = $records->appendRequired($this->draft(null, 2))->record()->toArray();
            self::assertSame($successorId, $next['segmentId']);
            $populatedRetry = $checkpoints->prepare(
                null,
                'system:global',
                $first['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            self::assertSame($unsigned, $populatedRetry->unsigned());
            $records->closeSegment('system:global', $successorId);
            $closedRetry = $checkpoints->prepare(
                null,
                'system:global',
                $first['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            self::assertSame($unsigned, $closedRetry->unsigned());
        } finally {
            $this->cleanup($admin, $primary, $probe);
        }
    }

    public function testNativeInvalidSchemaIsRejectedBeforeCheckpointSql(): void
    {
        $connection = $this->connection();
        $rejected = false;
        try {
            new PostgreSqlAuditCheckpointSchema(new PostgreSqlMigrationSchema('blackops-audit-invalid'));
        } catch (\InvalidArgumentException) {
            $rejected = true;
        } finally {
            $connection->close();
        }
        self::assertTrue($rejected);
        self::assertFalse($connection->isConnected());
    }

    public function testNativeCheckpointDdlInventoryAndUuidV4CheckpointIdRejection(): void
    {
        $admin = $connection = $probe = null;
        try {
            [$admin, $connection, $probe] = $this->connections();
            $this->migrate($connection);
            $this->executeBoundedCleanupDdl($admin, 'DROP SCHEMA IF EXISTS "' . self::SCHEMA . '" CASCADE');
            $this->migratePredecessorThenReservedVersion($admin, self::SCHEMA);
            self::assertSame(
                [
                    [
                        'ordinal_position' => 1,
                        'column_name' => 'checkpoint_id',
                        'data_type' => 'uuid',
                        'udt_name' => 'uuid',
                        'is_nullable' => 'NO',
                        'character_maximum_length' => null,
                        'column_default' => null,
                    ],
                    [
                        'ordinal_position' => 2,
                        'column_name' => 'partition_id',
                        'data_type' => 'character varying',
                        'udt_name' => 'varchar',
                        'is_nullable' => 'NO',
                        'character_maximum_length' => 71,
                        'column_default' => null,
                    ],
                    [
                        'ordinal_position' => 3,
                        'column_name' => 'segment_id',
                        'data_type' => 'uuid',
                        'udt_name' => 'uuid',
                        'is_nullable' => 'NO',
                        'character_maximum_length' => null,
                        'column_default' => null,
                    ],
                    [
                        'ordinal_position' => 4,
                        'column_name' => 'closed_state_version',
                        'data_type' => 'bigint',
                        'udt_name' => 'int8',
                        'is_nullable' => 'NO',
                        'character_maximum_length' => null,
                        'column_default' => null,
                    ],
                    [
                        'ordinal_position' => 5,
                        'column_name' => 'close_fencing_token',
                        'data_type' => 'character varying',
                        'udt_name' => 'varchar',
                        'is_nullable' => 'NO',
                        'character_maximum_length' => 71,
                        'column_default' => null,
                    ],
                    [
                        'ordinal_position' => 6,
                        'column_name' => 'unsigned_checkpoint',
                        'data_type' => 'bytea',
                        'udt_name' => 'bytea',
                        'is_nullable' => 'NO',
                        'character_maximum_length' => null,
                        'column_default' => null,
                    ],
                    [
                        'ordinal_position' => 7,
                        'column_name' => 'signing_key_fingerprint',
                        'data_type' => 'character',
                        'udt_name' => 'bpchar',
                        'is_nullable' => 'NO',
                        'character_maximum_length' => 64,
                        'column_default' => null,
                    ],
                ],
                $this->columns($probe, 'audit_checkpoint_preparations'),
            );
            self::assertSame(
                [
                    [
                        'ordinal_position' => 1,
                        'column_name' => 'checkpoint_id',
                        'data_type' => 'uuid',
                        'udt_name' => 'uuid',
                        'is_nullable' => 'NO',
                        'character_maximum_length' => null,
                        'column_default' => null,
                    ],
                    [
                        'ordinal_position' => 2,
                        'column_name' => 'checkpoint_hash',
                        'data_type' => 'character',
                        'udt_name' => 'bpchar',
                        'is_nullable' => 'NO',
                        'character_maximum_length' => 64,
                        'column_default' => null,
                    ],
                    [
                        'ordinal_position' => 3,
                        'column_name' => 'complete_bytes',
                        'data_type' => 'bytea',
                        'udt_name' => 'bytea',
                        'is_nullable' => 'NO',
                        'character_maximum_length' => null,
                        'column_default' => null,
                    ],
                ],
                $this->columns($probe, 'audit_checkpoints'),
            );
            self::assertSame(
                [
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'index_name' => 'audit_checkpoint_preparations_pk',
                        'definition' => 'create unique index audit_checkpoint_preparations_pk on {schema}.audit_checkpoint_preparations (checkpoint_id)',
                    ],
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'index_name' => 'audit_checkpoint_preparations_segment_uq',
                        'definition' => 'create unique index audit_checkpoint_preparations_segment_uq on {schema}.audit_checkpoint_preparations (segment_id)',
                    ],
                    [
                        'table_name' => 'audit_checkpoints',
                        'index_name' => 'audit_checkpoints_hash_uq',
                        'definition' => 'create unique index audit_checkpoints_hash_uq on {schema}.audit_checkpoints (checkpoint_hash)',
                    ],
                    [
                        'table_name' => 'audit_checkpoints',
                        'index_name' => 'audit_checkpoints_pk',
                        'definition' => 'create unique index audit_checkpoints_pk on {schema}.audit_checkpoints (checkpoint_id)',
                    ],
                ],
                $this->indexes($probe),
            );
            self::assertSame(
                [
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'constraint_name' => 'audit_checkpoint_preparations_bytes_check',
                        'contype' => 'c',
                        'definition' => 'check (octet_length(unsigned_checkpoint) >= 1 and octet_length(unsigned_checkpoint) <= 1048575)',
                    ],
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'constraint_name' => 'audit_checkpoint_preparations_checkpoint_id_not_null',
                        'contype' => 'n',
                        'definition' => 'not null checkpoint_id',
                    ],
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'constraint_name' => 'audit_checkpoint_preparations_close_fencing_token_not_null',
                        'contype' => 'n',
                        'definition' => 'not null close_fencing_token',
                    ],
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'constraint_name' => 'audit_checkpoint_preparations_closed_state_version_not_null',
                        'contype' => 'n',
                        'definition' => 'not null closed_state_version',
                    ],
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'constraint_name' => 'audit_checkpoint_preparations_identity_check',
                        'contype' => 'c',
                        'definition' => "check (partition_id::text ~ '^(system:global|tenant:[0-9a-f]{64})$'::text and checkpoint_id::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'::text and segment_id::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'::text)",
                    ],
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'constraint_name' => 'audit_checkpoint_preparations_key_check',
                        'contype' => 'c',
                        'definition' => "check (signing_key_fingerprint ~ '^[0-9a-f]{64}$'::text)",
                    ],
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'constraint_name' => 'audit_checkpoint_preparations_partition_id_not_null',
                        'contype' => 'n',
                        'definition' => 'not null partition_id',
                    ],
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'constraint_name' => 'audit_checkpoint_preparations_pk',
                        'contype' => 'p',
                        'definition' => 'primary key (checkpoint_id)',
                    ],
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'constraint_name' => 'audit_checkpoint_preparations_segment_fk',
                        'contype' => 'f',
                        'definition' => 'foreign key (partition_id, segment_id) references {schema}.audit_segments(partition_id, segment_id)',
                    ],
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'constraint_name' => 'audit_checkpoint_preparations_segment_id_not_null',
                        'contype' => 'n',
                        'definition' => 'not null segment_id',
                    ],
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'constraint_name' => 'audit_checkpoint_preparations_segment_uq',
                        'contype' => 'u',
                        'definition' => 'unique (segment_id)',
                    ],
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'constraint_name' => 'audit_checkpoint_preparations_signing_key_fingerprint_not_null',
                        'contype' => 'n',
                        'definition' => 'not null signing_key_fingerprint',
                    ],
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'constraint_name' => 'audit_checkpoint_preparations_token_check',
                        'contype' => 'c',
                        'definition' => "check (close_fencing_token::text ~ '^aft:v1:[0-9a-f]{64}$'::text)",
                    ],
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'constraint_name' => 'audit_checkpoint_preparations_unsigned_checkpoint_not_null',
                        'contype' => 'n',
                        'definition' => 'not null unsigned_checkpoint',
                    ],
                    [
                        'table_name' => 'audit_checkpoint_preparations',
                        'constraint_name' => 'audit_checkpoint_preparations_version_check',
                        'contype' => 'c',
                        'definition' => "check (closed_state_version >= 2 and closed_state_version <= '9007199254740990'::bigint)",
                    ],
                    [
                        'table_name' => 'audit_checkpoints',
                        'constraint_name' => 'audit_checkpoints_bytes_check',
                        'contype' => 'c',
                        'definition' => 'check (octet_length(complete_bytes) >= 1 and octet_length(complete_bytes) <= 1048575)',
                    ],
                    [
                        'table_name' => 'audit_checkpoints',
                        'constraint_name' => 'audit_checkpoints_checkpoint_hash_not_null',
                        'contype' => 'n',
                        'definition' => 'not null checkpoint_hash',
                    ],
                    [
                        'table_name' => 'audit_checkpoints',
                        'constraint_name' => 'audit_checkpoints_checkpoint_id_not_null',
                        'contype' => 'n',
                        'definition' => 'not null checkpoint_id',
                    ],
                    [
                        'table_name' => 'audit_checkpoints',
                        'constraint_name' => 'audit_checkpoints_complete_bytes_not_null',
                        'contype' => 'n',
                        'definition' => 'not null complete_bytes',
                    ],
                    [
                        'table_name' => 'audit_checkpoints',
                        'constraint_name' => 'audit_checkpoints_hash_check',
                        'contype' => 'c',
                        'definition' => "check (checkpoint_hash ~ '^[0-9a-f]{64}$'::text)",
                    ],
                    [
                        'table_name' => 'audit_checkpoints',
                        'constraint_name' => 'audit_checkpoints_hash_uq',
                        'contype' => 'u',
                        'definition' => 'unique (checkpoint_hash)',
                    ],
                    [
                        'table_name' => 'audit_checkpoints',
                        'constraint_name' => 'audit_checkpoints_pk',
                        'contype' => 'p',
                        'definition' => 'primary key (checkpoint_id)',
                    ],
                    [
                        'table_name' => 'audit_checkpoints',
                        'constraint_name' => 'audit_checkpoints_preparation_fk',
                        'contype' => 'f',
                        'definition' => 'foreign key (checkpoint_id) references {schema}.audit_checkpoint_preparations(checkpoint_id)',
                    ],
                ],
                $this->constraints($probe),
            );

            $records = $this->recordStore(
                $connection,
                $this->clock('2030-01-02T03:04:05.123456Z'),
                $this->uuidGenerator(3000),
            );
            $appended = $records->appendRequired($this->draft(null, 1));
            $value = $appended->record()->toArray();
            $close = $records->closeSegment('system:global', $value['segmentId']);
            $segment = $this->segment($probe, 'system:global', $value['segmentId']);
            $this->assertDatabaseConstraintRejects(
                $probe,
                'audit_checkpoint_preparations_identity_check',
                '23514',
                function () use ($probe, $value, $segment): void {
                    $probe->executeStatement(
                        'INSERT INTO "'
                        . self::SCHEMA
                        . '"."audit_checkpoint_preparations" '
                        . '(checkpoint_id, partition_id, segment_id, closed_state_version, close_fencing_token, unsigned_checkpoint, signing_key_fingerprint) '
                        . 'VALUES (:checkpoint_id, :partition_id, :segment_id, :closed_state_version, :close_fencing_token, :unsigned_checkpoint, :signing_key_fingerprint)',
                        [
                            'checkpoint_id' => '550e8400-e29b-41d4-a716-446655440000',
                            'partition_id' => 'system:global',
                            'segment_id' => $value['segmentId'],
                            'closed_state_version' => 2,
                            'close_fencing_token' => $segment['close_fencing_token'],
                            'unsigned_checkpoint' => 'x',
                            'signing_key_fingerprint' => str_repeat('0', 64),
                        ],
                        [
                            'checkpoint_id' => ParameterType::STRING,
                            'partition_id' => ParameterType::STRING,
                            'segment_id' => ParameterType::STRING,
                            'closed_state_version' => ParameterType::INTEGER,
                            'close_fencing_token' => ParameterType::STRING,
                            'unsigned_checkpoint' => ParameterType::BINARY,
                            'signing_key_fingerprint' => ParameterType::STRING,
                        ],
                    );
                },
            );

            $this->assertPreparationConstraintRejects(
                $probe,
                '01941f29-7c00-7000-8000-000000000002',
                'system:global',
                $value['segmentId'],
                1,
                $segment['close_fencing_token'],
                'x',
                str_repeat('0', 64),
                'audit_checkpoint_preparations_version_check',
            );
            $this->assertPreparationConstraintRejects(
                $probe,
                '01941f29-7c00-7000-8000-000000000003',
                'system:global',
                $value['segmentId'],
                9_007_199_254_740_991,
                $segment['close_fencing_token'],
                'x',
                str_repeat('0', 64),
                'audit_checkpoint_preparations_version_check',
            );
            $this->assertPreparationConstraintRejects(
                $probe,
                '01941f29-7c00-7000-8000-000000000004',
                'partition:invalid',
                $value['segmentId'],
                2,
                $segment['close_fencing_token'],
                'x',
                str_repeat('0', 64),
                'audit_checkpoint_preparations_identity_check',
            );
            $this->assertPreparationConstraintRejects(
                $probe,
                '01941f29-7c00-7000-8000-000000000005',
                'system:global',
                '550e8400-e29b-41d4-a716-446655440000',
                2,
                $segment['close_fencing_token'],
                'x',
                str_repeat('0', 64),
                'audit_checkpoint_preparations_identity_check',
            );
            $this->assertPreparationConstraintRejects(
                $probe,
                '01941f29-7c00-7000-8000-000000000006',
                'system:global',
                $value['segmentId'],
                2,
                'aft:v1:invalid',
                'x',
                str_repeat('0', 64),
                'audit_checkpoint_preparations_token_check',
            );
            $this->assertPreparationConstraintRejects(
                $probe,
                '01941f29-7c00-7000-8000-000000000007',
                'system:global',
                $value['segmentId'],
                2,
                $segment['close_fencing_token'],
                str_repeat('x', 1_048_576),
                str_repeat('0', 64),
                'audit_checkpoint_preparations_bytes_check',
            );
            $this->assertPreparationConstraintRejects(
                $probe,
                '01941f29-7c00-7000-8000-000000000008',
                'system:global',
                $value['segmentId'],
                2,
                $segment['close_fencing_token'],
                'x',
                str_repeat('g', 64),
                'audit_checkpoint_preparations_key_check',
            );
            $this->assertPreparationConstraintRejects(
                $probe,
                '01941f29-7c00-7000-8000-000000000009',
                'system:global',
                '01941f29-7c00-7000-8000-000000000999',
                2,
                $segment['close_fencing_token'],
                'x',
                str_repeat('0', 64),
                'audit_checkpoint_preparations_segment_fk',
                '23503',
            );
            self::assertSame(0, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));
            self::assertSame($close->closeFencingToken(), $segment['close_fencing_token']);

            $fixture = $this->fixture();
            $trust = $fixture['trust']['snapshot'];
            $seed = hex2bin((string) $fixture['seeds']['checkpoint']);
            self::assertIsString($seed);
            $checkpoints = $this->checkpointStore(
                $connection,
                $this->clock('2030-01-02T03:04:05.123456Z'),
                $this->uuidGenerator(5000),
            );
            $preparation = $checkpoints->prepare(
                null,
                'system:global',
                $value['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            $firstUnsigned = $preparation->unsigned();
            $firstComplete = AuditArtifactSigner::checkpoint($firstUnsigned, $seed, $trust, $firstUnsigned['signedAt']);
            $checkpoints->persist(null, 'system:global', $value['segmentId'], $firstComplete, $trust);
            $this->assertPreparationConstraintRejects(
                $probe,
                '01941f29-7c00-7000-8000-000000000010',
                'system:global',
                $value['segmentId'],
                2,
                $segment['close_fencing_token'],
                'x',
                str_repeat('0', 64),
                'audit_checkpoint_preparations_segment_uq',
                '23505',
            );
            $second = $records->appendRequired($this->draft(null, 2))->record()->toArray();
            $records->closeSegment('system:global', $second['segmentId']);
            $secondPreparation = $checkpoints->prepare(
                null,
                'system:global',
                $second['segmentId'],
                self::CHECKPOINT_KEY,
                $trust,
            );
            $secondUnsigned = $secondPreparation->unsigned();
            $firstCheckpointHash = AuditCheckpointCodec::decode($firstComplete)['checkpointHash'];
            $this->assertCheckpointConstraintRejects(
                $probe,
                $firstUnsigned['checkpointId'],
                $firstCheckpointHash,
                $firstComplete,
                'audit_checkpoints_pk',
                '23505',
            );
            $this->assertCheckpointConstraintRejects(
                $probe,
                $secondUnsigned['checkpointId'],
                $firstCheckpointHash,
                $firstComplete,
                'audit_checkpoints_hash_uq',
                '23505',
            );
            $this->assertCheckpointConstraintRejects(
                $probe,
                $secondUnsigned['checkpointId'],
                str_repeat('g', 64),
                'x',
                'audit_checkpoints_hash_check',
            );
            $this->assertCheckpointConstraintRejects(
                $probe,
                $secondUnsigned['checkpointId'],
                str_repeat('0', 64),
                str_repeat('x', 1_048_576),
                'audit_checkpoints_bytes_check',
            );
            $this->assertCheckpointConstraintRejects(
                $probe,
                '01941f29-7c00-7000-8000-000000000998',
                str_repeat('0', 64),
                'x',
                'audit_checkpoints_preparation_fk',
                '23503',
            );
            self::assertSame(2, (int) $this->rowCount($probe, 'audit_checkpoint_preparations'));
            self::assertSame(1, (int) $this->rowCount($probe, 'audit_checkpoints'));
        } finally {
            $this->cleanup($admin, $connection, $probe);
        }
    }

    /** @return array{0:Connection,1:Connection,2:Connection} */
    private function connections(): array
    {
        return [$this->connection(), $this->connection(), $this->connection()];
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

    private function migrate(Connection $connection, string $schema = self::SCHEMA): void
    {
        new DatabaseMigrationRunner($connection, $schema)->migrate();
    }

    private function migratePredecessorThenReservedVersion(Connection $connection, string $schema): void
    {
        $connection->executeStatement('CREATE SCHEMA IF NOT EXISTS "' . $schema . '"');
        $runner = new DatabaseMigrationRunner($connection, $schema);
        $metadata = $runner->dependencyFactory()->getMetadataStorage();
        $metadata->ensureInitialized();
        $available = $runner->dependencyFactory()->getMigrationPlanCalculator()->getMigrations()->getItems();
        self::assertCount(14, $available);
        $predecessor = array_slice($available, 0, -1);
        $versions = array_map(static fn(object $migration): object => $migration->getVersion(), $predecessor);
        $plan = $runner
            ->dependencyFactory()
            ->getMigrationPlanCalculator()
            ->getPlanForVersions($versions, Direction::UP);
        $runner
            ->dependencyFactory()
            ->getMigrator()
            ->migrate(
                $plan,
                new MigratorConfiguration()
                    ->setAllOrNothing(true)
                    ->setNoMigrationException(true),
            );
        $upgrade = new DatabaseMigrationRunner($connection, $schema)->migrate();
        self::assertSame(1, $upgrade->migrations);
        self::assertSame(14, (int) $connection->fetchOne('SELECT count(*) FROM "' . $schema . '"."schema_migrations"'));
        self::assertSame(
            2,
            (int) $connection->fetchOne(
                'SELECT count(*) FROM information_schema.tables WHERE table_schema = :schema '
                . 'AND table_name IN (:preparations, :checkpoints)',
                [
                    'schema' => $schema,
                    'preparations' => 'audit_checkpoint_preparations',
                    'checkpoints' => 'audit_checkpoints',
                ],
                [
                    'schema' => ParameterType::STRING,
                    'preparations' => ParameterType::STRING,
                    'checkpoints' => ParameterType::STRING,
                ],
            ),
        );
    }

    private function recordStore(
        Connection $connection,
        ClockInterface $clock,
        Uuidv7Generator $ids,
        string $schema = self::SCHEMA,
    ): PostgreSqlAuditRecordStore {
        $jcs = new AuditJcsCodec();
        $partitions = new AuditPartitionIdDeriver($jcs);
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            FrameworkAuditEventCatalog::catalog(),
        ]));
        $records = new AuditRecordCodec($validator, $jcs);

        return new PostgreSqlAuditRecordStore(
            $connection,
            new PostgreSqlAuditSchema($schema),
            $clock,
            $ids,
            $validator,
            new AuditRecordFinalizer($validator, $records),
            $partitions,
            $records,
            $this->protection($records, $partitions, $jcs),
            new PostgreSqlAuditStoredRecordHydrator($partitions),
        );
    }

    private function checkpointStore(
        Connection $connection,
        ClockInterface $clock,
        Uuidv7Generator $ids,
        string $schema = self::SCHEMA,
    ): PostgreSqlAuditCheckpointStore {
        $jcs = new AuditJcsCodec();
        $partitions = new AuditPartitionIdDeriver($jcs);
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([
            FrameworkAuditEventCatalog::catalog(),
        ]));
        $records = new AuditRecordCodec($validator, $jcs);

        return new PostgreSqlAuditCheckpointStore(
            $connection,
            new PostgreSqlAuditCheckpointSchema(new PostgreSqlMigrationSchema($schema)),
            new PostgreSqlAuditStoredRecordHydrator($partitions),
            $this->protection($records, $partitions, $jcs),
            $partitions,
            $clock,
            $ids,
        );
    }

    private function protection(
        AuditRecordCodec $records,
        AuditPartitionIdDeriver $partitions,
        AuditJcsCodec $jcs,
    ): AuditProtectedBodyCodec {
        return new AuditProtectedBodyCodec(
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
            $records,
            new AuditIngressEnvelopeCodec($records, $partitions, $jcs),
        );
    }

    private function clock(string $value): ClockInterface
    {
        return new class($value) implements ClockInterface {
            public function __construct(
                private string $value,
            ) {}

            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable($this->value);
            }
        };
    }

    private function uuidGenerator(int $start): Uuidv7Generator
    {
        return new class($start) implements Uuidv7Generator {
            public function __construct(
                private int $next,
            ) {}

            public function generate(\DateTimeImmutable $time): string
            {
                return sprintf('01941f29-7c00-7000-8000-%012x', $this->next++);
            }
        };
    }

    private function fixedUuidGenerator(string $id): Uuidv7Generator
    {
        return new class($id) implements Uuidv7Generator {
            public function __construct(
                private string $id,
            ) {}

            public function generate(\DateTimeImmutable $time): string
            {
                return $this->id;
            }
        };
    }

    private function malformedUuidGenerator(): Uuidv7Generator
    {
        return new class implements Uuidv7Generator {
            public function generate(\DateTimeImmutable $time): string
            {
                return 'not-a-uuid';
            }
        };
    }

    private function draft(?AuditTypedReference $tenant, int $index): AuditRecordDraft
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $definition = $catalog->events[0];
        $tuple = $definition->semanticTuples[0];
        return new AuditRecordDraft(
            new AuditRecordEvent($definition->type, $definition->version, $definition->category, $catalog->catalogHash),
            new AuditRecordAction('f2-native-action-' . $index, $tuple->actionType, $tuple->phase),
            new AuditRecordActor(
                new AuditTypedReference('user', 'f2-native-actor'),
                new AuditTypedReference('process', 'blackops-test'),
            ),
            new AuditRecordResource($tuple->resourceType, 'f2-native-resource-' . $index),
            new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
            new AuditRecordPolicy(
                'f2-native-policy',
                1,
                $tuple->policyDecision,
                $tuple->policyReasonCode,
                AuditDelivery::Required,
            ),
            $tenant,
            '2030-01-02T03:02:00.000000Z',
            new AuditRecordSource(AuditSourceOwner::Framework, 'f2-native', AuditSourceRuntime::Cli, 'f2-test'),
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

    private function partition(AuditTypedReference $tenant): string
    {
        return new AuditPartitionIdDeriver(new AuditJcsCodec())->derive($tenant);
    }

    /** @return array<string,mixed> */
    private function fixture(): array
    {
        $json = file_get_contents(__DIR__ . '/../../../Fixtures/Audit/audit-signed-artifacts-v1.json');
        self::assertIsString($json);
        $fixture = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);
        return $fixture;
    }

    /** @return array<string,mixed> */
    private function segment(Connection $connection, string $partition, string $segment): array
    {
        $row = $connection->fetchAssociative(
            'SELECT partition_id, segment_id::text AS segment_id, state, first_sequence, final_sequence, '
            . 'head_record_hash, closed_at, successor_segment_id::text AS successor_segment_id, '
            . 'close_fencing_token, state_version FROM "'
            . self::SCHEMA
            . '"."audit_segments" '
            . 'WHERE partition_id = :partition_id AND segment_id = :segment_id',
            ['partition_id' => $partition, 'segment_id' => $segment],
            ['partition_id' => ParameterType::STRING, 'segment_id' => ParameterType::STRING],
        );
        self::assertIsArray($row);
        return $row;
    }

    /** @return array<string,mixed> */
    private function checkpointRow(Connection $connection, string $checkpointId): array
    {
        $row = $connection->fetchAssociative(
            'SELECT checkpoint_id::text AS checkpoint_id, checkpoint_hash, complete_bytes '
            . 'FROM "'
            . self::SCHEMA
            . '"."audit_checkpoints" WHERE checkpoint_id = :checkpoint_id',
            ['checkpoint_id' => $checkpointId],
            ['checkpoint_id' => ParameterType::STRING],
        );
        self::assertIsArray($row);
        return $row;
    }

    /** @return array<string,mixed> */
    private function preparationRow(Connection $connection, string $checkpointId): array
    {
        $row = $connection->fetchAssociative(
            'SELECT checkpoint_id::text AS checkpoint_id, partition_id, segment_id::text AS segment_id, '
            . 'closed_state_version, close_fencing_token, unsigned_checkpoint, signing_key_fingerprint '
            . 'FROM "'
            . self::SCHEMA
            . '"."audit_checkpoint_preparations" '
            . 'WHERE checkpoint_id = :checkpoint_id',
            ['checkpoint_id' => $checkpointId],
            ['checkpoint_id' => ParameterType::STRING],
        );
        self::assertIsArray($row);
        return $row;
    }

    private function updatePreparationUnsigned(Connection $connection, string $checkpointId, string $bytes): void
    {
        $connection->executeStatement(
            'UPDATE "'
            . self::SCHEMA
            . '"."audit_checkpoint_preparations" '
            . 'SET unsigned_checkpoint = :unsigned_checkpoint WHERE checkpoint_id = CAST(:checkpoint_id AS uuid)',
            ['checkpoint_id' => $checkpointId, 'unsigned_checkpoint' => $bytes],
            ['checkpoint_id' => ParameterType::STRING, 'unsigned_checkpoint' => ParameterType::BINARY],
        );
    }

    private function updatePreparationToken(Connection $connection, string $checkpointId, string $token): void
    {
        $connection->executeStatement(
            'UPDATE "'
            . self::SCHEMA
            . '"."audit_checkpoint_preparations" '
            . 'SET close_fencing_token = :close_fencing_token WHERE checkpoint_id = CAST(:checkpoint_id AS uuid)',
            ['checkpoint_id' => $checkpointId, 'close_fencing_token' => $token],
            ['checkpoint_id' => ParameterType::STRING, 'close_fencing_token' => ParameterType::STRING],
        );
    }

    private function updateRecordSegment(
        Connection $connection,
        string $partition,
        string $recordId,
        string $segmentId,
    ): void {
        $connection->executeStatement(
            'UPDATE "'
            . self::SCHEMA
            . '"."audit_records" '
            . 'SET segment_id = CAST(:segment_id AS uuid) '
            . 'WHERE partition_id = :partition_id AND record_id = CAST(:record_id AS uuid)',
            ['partition_id' => $partition, 'record_id' => $recordId, 'segment_id' => $segmentId],
            [
                'partition_id' => ParameterType::STRING,
                'record_id' => ParameterType::STRING,
                'segment_id' => ParameterType::STRING,
            ],
        );
    }

    private function rowCount(Connection $connection, string $table): int
    {
        return (int) $connection->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '"."' . $table . '"');
    }

    private function assertStoreFailure(callable $operation, AuditStoreFailure $expected): void
    {
        $failure = null;
        try {
            $operation();
        } catch (AuditStoreException $exception) {
            $failure = $exception->failure();
        }
        self::assertSame($expected, $failure);
    }

    /** @param list<string> $queries */
    private static function assertNoProtectedBodyQueries(array $queries): void
    {
        foreach ($queries as $query) {
            self::assertStringNotContainsString('protected_body', strtolower($query));
        }
    }

    private function assertPreparationConstraintRejects(
        Connection $connection,
        string $checkpointId,
        string $partitionId,
        string $segmentId,
        int $closedStateVersion,
        string $closeFencingToken,
        string $unsignedCheckpoint,
        string $signingKeyFingerprint,
        string $expectedConstraint,
        string $expectedSqlState = '23514',
    ): void {
        $this->assertDatabaseConstraintRejects(
            $connection,
            $expectedConstraint,
            $expectedSqlState,
            function () use (
                $connection,
                $checkpointId,
                $partitionId,
                $segmentId,
                $closedStateVersion,
                $closeFencingToken,
                $unsignedCheckpoint,
                $signingKeyFingerprint,
            ): void {
                $connection->executeStatement(
                    'INSERT INTO "'
                    . self::SCHEMA
                    . '"."audit_checkpoint_preparations" '
                    . '(checkpoint_id, partition_id, segment_id, closed_state_version, close_fencing_token, unsigned_checkpoint, signing_key_fingerprint) '
                    . 'VALUES (:checkpoint_id, :partition_id, :segment_id, :closed_state_version, :close_fencing_token, :unsigned_checkpoint, :signing_key_fingerprint)',
                    [
                        'checkpoint_id' => $checkpointId,
                        'partition_id' => $partitionId,
                        'segment_id' => $segmentId,
                        'closed_state_version' => $closedStateVersion,
                        'close_fencing_token' => $closeFencingToken,
                        'unsigned_checkpoint' => $unsignedCheckpoint,
                        'signing_key_fingerprint' => $signingKeyFingerprint,
                    ],
                    [
                        'checkpoint_id' => ParameterType::STRING,
                        'partition_id' => ParameterType::STRING,
                        'segment_id' => ParameterType::STRING,
                        'closed_state_version' => ParameterType::INTEGER,
                        'close_fencing_token' => ParameterType::STRING,
                        'unsigned_checkpoint' => ParameterType::BINARY,
                        'signing_key_fingerprint' => ParameterType::STRING,
                    ],
                );
            },
        );
    }

    private function assertCheckpointConstraintRejects(
        Connection $connection,
        string $checkpointId,
        string $checkpointHash,
        string $completeBytes,
        string $expectedConstraint,
        string $expectedSqlState = '23514',
    ): void {
        $this->assertDatabaseConstraintRejects(
            $connection,
            $expectedConstraint,
            $expectedSqlState,
            function () use ($connection, $checkpointId, $checkpointHash, $completeBytes): void {
                $connection->executeStatement(
                    'INSERT INTO "'
                    . self::SCHEMA
                    . '"."audit_checkpoints" '
                    . '(checkpoint_id, checkpoint_hash, complete_bytes) '
                    . 'VALUES (:checkpoint_id, :checkpoint_hash, :complete_bytes)',
                    [
                        'checkpoint_id' => $checkpointId,
                        'checkpoint_hash' => $checkpointHash,
                        'complete_bytes' => $completeBytes,
                    ],
                    [
                        'checkpoint_id' => ParameterType::STRING,
                        'checkpoint_hash' => ParameterType::STRING,
                        'complete_bytes' => ParameterType::BINARY,
                    ],
                );
            },
        );
    }

    private function assertDatabaseConstraintRejects(
        Connection $connection,
        string $expectedConstraint,
        string $expectedSqlState,
        callable $operation,
    ): void {
        $exception = null;
        try {
            $operation();
        } catch (DriverException $failure) {
            $exception = $failure;
        }
        self::assertInstanceOf(DriverException::class, $exception);
        self::assertSame($expectedSqlState, $exception->getSQLState());
        self::assertStringContainsString($expectedConstraint, $exception->getMessage());
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    }

    /** @return list<array{ordinal_position:int,column_name:string,data_type:string,udt_name:string,is_nullable:string,character_maximum_length:int|null,column_default:string|null}> */
    private function columns(Connection $connection, string $table): array
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT ordinal_position, column_name, data_type, udt_name, is_nullable, '
            . 'character_maximum_length, column_default '
            . 'FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table '
            . 'ORDER BY ordinal_position',
            ['schema' => self::SCHEMA, 'table' => $table],
            ['schema' => ParameterType::STRING, 'table' => ParameterType::STRING],
        );
        self::assertIsArray($rows);
        return array_map(static fn(array $row): array => [
            'ordinal_position' => (int) $row['ordinal_position'],
            'column_name' => (string) $row['column_name'],
            'data_type' => (string) $row['data_type'],
            'udt_name' => (string) $row['udt_name'],
            'is_nullable' => (string) $row['is_nullable'],
            'character_maximum_length' => $row['character_maximum_length'] === null
                ? null
                : (int) $row['character_maximum_length'],
            'column_default' => $row['column_default'] === null ? null : (string) $row['column_default'],
        ], $rows);
    }

    /** @return list<array{table_name:string,index_name:string,definition:string}> */
    private function indexes(Connection $connection): array
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT tablename AS table_name, indexname AS index_name, indexdef AS definition '
            . 'FROM pg_indexes WHERE schemaname = :schema AND tablename IN (:tables) '
            . 'ORDER BY tablename, indexname',
            ['schema' => self::SCHEMA, 'tables' => ['audit_checkpoint_preparations', 'audit_checkpoints']],
            ['tables' => ArrayParameterType::STRING],
        );
        return array_map(fn(array $row): array => [
            'table_name' => (string) $row['table_name'],
            'index_name' => (string) $row['index_name'],
            'definition' => $this->normalizeCatalogIndex((string) $row['definition']),
        ], $rows);
    }

    /** @return list<array{table_name:string,constraint_name:string,contype:string,definition:string}> */
    private function constraints(Connection $connection): array
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT r.relname AS table_name, c.conname AS constraint_name, c.contype, '
            . 'pg_get_constraintdef(c.oid, true) AS definition FROM pg_constraint AS c '
            . 'JOIN pg_namespace AS n ON n.oid = c.connamespace '
            . 'JOIN pg_class AS r ON r.oid = c.conrelid '
            . 'WHERE n.nspname = :schema '
            . 'AND r.relname IN (:tables) ORDER BY r.relname, c.conname',
            ['schema' => self::SCHEMA, 'tables' => ['audit_checkpoint_preparations', 'audit_checkpoints']],
            ['tables' => ArrayParameterType::STRING],
        );
        return array_map(fn(array $row): array => [
            'table_name' => (string) $row['table_name'],
            'constraint_name' => (string) $row['constraint_name'],
            'contype' => (string) $row['contype'],
            'definition' => $this->normalizeCatalogSql((string) $row['definition']),
        ], $rows);
    }

    private function normalizeCatalogSql(string $sql): string
    {
        $normalized = preg_replace('/\\s+/', ' ', trim(strtolower($sql)));
        self::assertIsString($normalized);

        return str_replace(['"' . self::SCHEMA . '".', self::SCHEMA . '.'], ['{schema}.', '{schema}.'], $normalized);
    }

    private function normalizeCatalogIndex(string $sql): string
    {
        return str_replace(' using btree', '', $this->normalizeCatalogSql($sql));
    }

    private function installInsertFailureTrigger(
        Connection $connection,
        string $function,
        string $trigger,
        string $timing,
    ): void {
        if (!in_array($timing, ['BEFORE', 'AFTER'], strict: true)) {
            self::fail('unsupported native rollback trigger timing');
        }
        $connection->executeStatement(
            'CREATE OR REPLACE FUNCTION "'
            . self::SCHEMA
            . '"."'
            . $function
            . '"() '
            . 'RETURNS trigger LANGUAGE plpgsql AS $f$ BEGIN RAISE EXCEPTION SQLSTATE \'P0001\' USING MESSAGE = \'f2 native rollback gate\'; END; $f$',
        );
        $connection->executeStatement(
            'CREATE TRIGGER "'
            . $trigger
            . '" '
            . $timing
            . ' INSERT ON "'
            . self::SCHEMA
            . '"."audit_checkpoint_preparations" FOR EACH ROW EXECUTE FUNCTION "'
            . self::SCHEMA
            . '"."'
            . $function
            . '"()',
        );
    }

    private function removeInsertFailureTrigger(Connection $connection, string $function, string $trigger): void
    {
        $this->executeBoundedCleanupDdl(
            $connection,
            'DROP TRIGGER IF EXISTS "' . $trigger . '" ON "' . self::SCHEMA . '"."audit_checkpoint_preparations"',
        );
        $this->executeBoundedCleanupDdl(
            $connection,
            'DROP FUNCTION IF EXISTS "' . self::SCHEMA . '"."' . $function . '"()',
        );
    }

    private function installDeferredFailureTrigger(Connection $connection, string $function, string $trigger): void
    {
        $connection->executeStatement(
            'CREATE OR REPLACE FUNCTION "'
            . self::SCHEMA
            . '"."'
            . $function
            . '"() '
            . 'RETURNS trigger LANGUAGE plpgsql AS $f$ BEGIN RAISE EXCEPTION SQLSTATE \'P0001\' USING MESSAGE = \'f2 native deferred commit gate\'; END; $f$',
        );
        $connection->executeStatement(
            'CREATE CONSTRAINT TRIGGER "'
            . $trigger
            . '" AFTER INSERT ON "'
            . self::SCHEMA
            . '"."audit_checkpoint_preparations" DEFERRABLE INITIALLY DEFERRED '
            . 'FOR EACH ROW EXECUTE FUNCTION "'
            . self::SCHEMA
            . '"."'
            . $function
            . '"()',
        );
    }

    private function removeDeferredFailureTrigger(Connection $connection, string $function, string $trigger): void
    {
        $this->executeBoundedCleanupDdl(
            $connection,
            'DROP TRIGGER IF EXISTS "' . $trigger . '" ON "' . self::SCHEMA . '"."audit_checkpoint_preparations"',
        );
        $this->executeBoundedCleanupDdl(
            $connection,
            'DROP FUNCTION IF EXISTS "' . self::SCHEMA . '"."' . $function . '"()',
        );
    }

    private function expectedCloseToken(
        string $partition,
        string $segment,
        int $finalSequence,
        string $head,
        string $successor,
        string $closedAt,
    ): string {
        $string = static fn(string $value): string => 's:' . strlen($value) . ':' . $value;
        $bytes = static fn(string $value): string => 'b:' . strlen($value) . ':' . $value;
        $headBytes = hex2bin($head);
        self::assertIsString($headBytes);
        return 'aft:v1:'
        . hash(
            'sha256',
            $string('blackops.audit.segment.close.v1')
                . $string($partition)
                . $string($segment)
                . 'i:'
                . $finalSequence
                . $bytes($headBytes)
                . $string($successor)
                . $string($closedAt),
        );
    }

    private function installRaceGate(
        Connection $connection,
        string $function,
        string $trigger,
        string $table = 'audit_checkpoint_preparations',
        string $lockKey = 'f2-native-race-gate',
    ): void {
        $quotedLockKey = str_replace("'", "''", $lockKey);
        $connection->executeStatement(
            'CREATE OR REPLACE FUNCTION "'
            . self::SCHEMA
            . '"."'
            . $function
            . '"() '
            . 'RETURNS trigger LANGUAGE plpgsql AS $f$ BEGIN '
            . 'PERFORM pg_advisory_xact_lock(hashtextextended(\''
            . $quotedLockKey
            . '\', 0)); '
            . 'RETURN NEW; END; $f$',
        );
        $connection->executeStatement(
            'CREATE TRIGGER "'
            . $trigger
            . '" BEFORE INSERT ON "'
            . self::SCHEMA
            . '"."'
            . $table
            . '" FOR EACH ROW EXECUTE FUNCTION "'
            . self::SCHEMA
            . '"."'
            . $function
            . '"()',
        );
    }

    private function acquireRaceGate(Connection $connection, string $lockKey): int
    {
        $connection->executeStatement(
            'SELECT pg_advisory_lock(hashtextextended(:lock_key, 0))',
            ['lock_key' => $lockKey],
            ['lock_key' => ParameterType::STRING],
        );
        $pid = (int) $connection->fetchOne('SELECT pg_backend_pid()');
        self::assertSame(1, $this->advisoryLockCount($connection, $pid, $lockKey, true));

        return $pid;
    }

    private function releaseRaceGate(Connection $connection, string $lockKey): void
    {
        $unlocked = $connection->fetchOne(
            'SELECT pg_advisory_unlock(hashtextextended(:lock_key, 0))',
            ['lock_key' => $lockKey],
            ['lock_key' => ParameterType::STRING],
        );
        self::assertTrue((bool) $unlocked, 'the parent did not own the native race gate');
    }

    private function removeRaceGate(
        Connection $connection,
        string $function,
        string $trigger,
        string $table = 'audit_checkpoint_preparations',
    ): void {
        $this->executeBoundedCleanupDdl(
            $connection,
            'DROP TRIGGER IF EXISTS "' . $trigger . '" ON "' . self::SCHEMA . '"."' . $table . '"',
        );
        $this->executeBoundedCleanupDdl(
            $connection,
            'DROP FUNCTION IF EXISTS "' . self::SCHEMA . '"."' . $function . '"()',
        );
    }

    private function executeBoundedCleanupDdl(Connection $connection, string $sql): void
    {
        $connection->executeStatement("SET lock_timeout = '2s'");
        try {
            $connection->executeStatement($sql);
        } finally {
            $connection->executeStatement("SET lock_timeout = '0'");
        }
    }

    /** @return array{winner:int,loser:int,winner_query:string,loser_query:string,winner_blocked_by_gate:bool,loser_blocked_by_winner:bool} */
    private function waitForRaceParticipants(
        Connection $probe,
        string $winnerBackendFile,
        string $loserBackendFile,
        string $lockKey,
        string $loserWaitEvent = 'advisory',
        bool $requireSnapshot = true,
        ?int $gatePid = null,
        ?string $loserLockKey = null,
    ): array {
        $winnerPid = $this->waitForBackendPid($winnerBackendFile);
        $loserPid = $this->waitForBackendPid($loserBackendFile);
        $deadline = microtime(true) + 8.0;
        while (microtime(true) < $deadline) {
            $activity = $probe->fetchAllAssociative(
                'SELECT pid, state, wait_event_type, wait_event, backend_xmin, query '
                . 'FROM pg_stat_activity WHERE pid IN (:winner_pid, :loser_pid)',
                ['winner_pid' => $winnerPid, 'loser_pid' => $loserPid],
                ['winner_pid' => ParameterType::INTEGER, 'loser_pid' => ParameterType::INTEGER],
            );
            $byPid = [];
            foreach ($activity as $row) {
                $byPid[(int) $row['pid']] = $row;
            }
            $winner = $byPid[$winnerPid] ?? null;
            $loser = $byPid[$loserPid] ?? null;
            if (is_array($winner) && is_array($loser)) {
                $winnerQuery = (string) $winner['query'];
                $loserQuery = (string) $loser['query'];
                $winnerWaiting =
                    $winner['state'] === 'active'
                    && $winner['wait_event_type'] === 'Lock'
                    && $winner['wait_event'] === 'advisory'
                    && ($winner['backend_xmin'] !== null || !$requireSnapshot)
                    && $this->advisoryLockCount($probe, $winnerPid, $lockKey, false) === 1
                    && ($gatePid === null || $this->blockedByBackend($probe, $winnerPid, $gatePid));
                $loserBlockedByWinner = $this->blockedByBackend($probe, $loserPid, $winnerPid);
                $loserHasExactAdvisoryWait =
                    $loserLockKey !== null && $this->advisoryLockCount($probe, $loserPid, $loserLockKey, false) === 1;
                $loserWaiting =
                    $loser['state'] === 'active'
                    && $loser['wait_event_type'] === 'Lock'
                    && $loser['wait_event'] === $loserWaitEvent
                    && ($loser['backend_xmin'] !== null || !$requireSnapshot)
                    && (
                        $loserHasExactAdvisoryWait
                            ? $gatePid === null
                            || $this->blockedByBackend($probe, $loserPid, $gatePid)
                            || $loserBlockedByWinner
                            : $loserBlockedByWinner
                    );
                if ($winnerWaiting && $loserWaiting) {
                    return [
                        'winner' => $winnerPid,
                        'loser' => $loserPid,
                        'winner_query' => $winnerQuery,
                        'loser_query' => $loserQuery,
                        'winner_blocked_by_gate' =>
                            $gatePid === null || $this->blockedByBackend($probe, $winnerPid, $gatePid),
                        'loser_blocked_by_winner' => $loserBlockedByWinner,
                    ];
                }
            }
            usleep(10_000);
        }
        self::fail('native race participants did not reach the exact expected gate/block waits');
    }

    /** @return array{pid:int,query:string} */
    private function waitForRaceWinnerAtGate(
        Connection $probe,
        string $winnerBackendFile,
        string $lockKey,
        int $gatePid,
    ): array {
        $winnerPid = $this->waitForBackendPid($winnerBackendFile);
        $deadline = microtime(true) + 8.0;
        while (microtime(true) < $deadline) {
            $activity = $probe->fetchAssociative(
                'SELECT pid, state, wait_event_type, wait_event, backend_xmin, query '
                . 'FROM pg_stat_activity WHERE pid = :pid',
                ['pid' => $winnerPid],
                ['pid' => ParameterType::INTEGER],
            );
            if (
                $activity !== false
                && $activity['state'] === 'active'
                && $activity['wait_event_type'] === 'Lock'
                && $activity['wait_event'] === 'advisory'
                && $this->advisoryLockCount($probe, $winnerPid, $lockKey, false) === 1
                && $this->blockedByBackend($probe, $winnerPid, $gatePid)
            ) {
                return ['pid' => $winnerPid, 'query' => (string) $activity['query']];
            }
            usleep(10_000);
        }
        self::fail('native race winner did not wait on the exact held parent gate');
    }

    private function advisoryLockCount(Connection $probe, int $pid, string $lockKey, bool $granted): int
    {
        return (int) $probe->fetchOne(
            'WITH lock_hash AS (SELECT hashtextextended(:lock_key, 0) AS value) '
            . 'SELECT count(*) FROM pg_locks, lock_hash '
            . 'WHERE pg_locks.pid = :pid AND pg_locks.locktype = \'advisory\' '
            . 'AND pg_locks.granted = '
            . ($granted ? 'TRUE' : 'FALSE')
            . ' '
            . 'AND pg_locks.classid::bigint = ((lock_hash.value >> 32) & 4294967295) '
            . 'AND pg_locks.objid::bigint = (lock_hash.value & 4294967295)',
            ['lock_key' => $lockKey, 'pid' => $pid],
            ['lock_key' => ParameterType::STRING, 'pid' => ParameterType::INTEGER],
        );
    }

    private function blockedByBackend(Connection $probe, int $pid, int $blockerPid): bool
    {
        return (int) $probe->fetchOne(
            'SELECT count(*) FROM unnest(pg_blocking_pids(:pid)) AS blocker(pid) ' . 'WHERE blocker.pid = :blocker_pid',
            ['pid' => $pid, 'blocker_pid' => $blockerPid],
            ['pid' => ParameterType::INTEGER, 'blocker_pid' => ParameterType::INTEGER],
        ) === 1;
    }

    private function assertRaceWinnerBackendIdentity(int $observedPid, string $backendFile): int
    {
        $backendPid = $this->waitForBackendPid($backendFile);
        self::assertGreaterThan(0, $backendPid);
        self::assertSame($backendPid, $observedPid);

        return $backendPid;
    }

    private function assertRaceBackendPairIdentity(
        int $observedWinner,
        int $observedLoser,
        string $winnerBackendFile,
        string $loserBackendFile,
    ): void {
        $winnerBackendPid = $this->waitForBackendPid($winnerBackendFile);
        $loserBackendPid = $this->waitForBackendPid($loserBackendFile);
        self::assertGreaterThan(0, $winnerBackendPid);
        self::assertGreaterThan(0, $loserBackendPid);
        self::assertNotSame($winnerBackendPid, $loserBackendPid);
        self::assertSame($winnerBackendPid, $observedWinner);
        self::assertSame($loserBackendPid, $observedLoser);
    }

    /** @return array{pid:int,query:string} */
    private function waitForBackendBlockedBy(
        Connection $probe,
        string $backendFile,
        int $blockerPid,
        string $waitEvent,
        string $queryFragment,
    ): array {
        $backendPid = $this->waitForBackendPid($backendFile);
        $needle = strtoupper(trim(preg_replace('/\s+/', ' ', $queryFragment) ?? $queryFragment));
        $deadline = microtime(true) + 8.0;
        while (microtime(true) < $deadline) {
            $activity = $probe->fetchAssociative(
                'SELECT pid, state, wait_event_type, wait_event, query ' . 'FROM pg_stat_activity WHERE pid = :pid',
                ['pid' => $backendPid],
                ['pid' => ParameterType::INTEGER],
            );
            if ($activity !== false) {
                $rawQuery = (string) $activity['query'];
                $query = strtoupper(trim(preg_replace('/\s+/', ' ', $rawQuery) ?? $rawQuery));
                if (
                    $activity['state'] === 'active'
                    && $activity['wait_event_type'] === 'Lock'
                    && $activity['wait_event'] === $waitEvent
                    && $this->blockedByBackend($probe, $backendPid, $blockerPid)
                    && str_contains($query, $needle)
                ) {
                    return ['pid' => $backendPid, 'query' => $query];
                }
            }
            usleep(10_000);
        }
        self::fail('native backend did not reach the expected blocker and query');
    }

    private function waitForBackendPid(string $backendFile): int
    {
        $deadline = microtime(true) + 8.0;
        while (microtime(true) < $deadline) {
            if (is_file($backendFile)) {
                $value = trim((string) file_get_contents($backendFile));
                if (preg_match('/^[1-9][0-9]*$/', $value) === 1) {
                    $pid = (int) $value;
                    if (!in_array($pid, $this->observedBackendPids, true)) {
                        $this->observedBackendPids[] = $pid;
                    }
                    return $pid;
                }
            }
            usleep(10_000);
        }
        self::fail('native child did not publish its PostgreSQL backend PID');
    }

    private function forkNativeChild(
        ?Connection $admin,
        ?Connection $primary,
        ?Connection $probe,
        string $backendFile,
        string $resultFile,
        callable $work,
        ?string $startFile = null,
    ): int {
        $pid = pcntl_fork();
        if ($pid === -1) {
            self::fail('native child process could not be forked');
        }
        if ($pid === 0) {
            $child = null;
            $result = ['status' => 'error', 'failure' => 'child-not-started'];
            $exitCode = 1;
            $this->discardForkInheritedConnections($admin, $primary, $probe);
            try {
                $child = $this->connection();
                $backendPid = (string) $child->fetchOne('SELECT pg_backend_pid()');
                if (file_put_contents($backendFile, $backendPid, LOCK_EX) === false) {
                    throw new \RuntimeException('native child backend PID could not be published');
                }
                if ($startFile !== null) {
                    $this->waitForStartBarrier($startFile);
                }
                $result = $work($child);
                $exitCode = 0;
            } catch (AuditStoreException $exception) {
                $result = [
                    'status' => 'error',
                    'failure' => $exception->failure()->value,
                ];
            } catch (\Throwable $exception) {
                $result = [
                    'status' => 'error',
                    'failure' => 'unexpected',
                    'exception' => $exception::class,
                ];
            }
            $this->finishForkChild($child, $resultFile, $result, $exitCode);
        }

        unset($this->reapedChildren[$pid]);
        return $pid;
    }

    private function discardForkInheritedConnections(?Connection ...$connections): void
    {
        // Callers close every parent session before forking and pass nulls here.
        // The child creates exactly one fresh session after the fork.
        unset($connections);
    }

    private function waitForStartBarrier(string $startFile): void
    {
        $deadline = microtime(true) + 8.0;
        while (microtime(true) < $deadline) {
            if (is_file($startFile)) {
                return;
            }
            usleep(10_000);
        }
        throw new \RuntimeException('native child start barrier timed out');
    }

    private function signalStartBarrier(string $startFile): void
    {
        if (file_put_contents($startFile, 'start', LOCK_EX) === false) {
            self::fail('native child start barrier could not be signalled');
        }
    }

    private function startBarrier(string $prefix): string
    {
        $file = tempnam(sys_get_temp_dir(), $prefix);
        self::assertIsString($file);
        if (!unlink($file)) {
            self::fail('native child start barrier could not be initialized');
        }

        return $file;
    }

    private function closeConnectionsBeforeFork(?Connection ...$connections): void
    {
        foreach ($connections as $connection) {
            if (!$connection instanceof Connection || !$connection->isConnected()) {
                continue;
            }
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $connection->close();
            self::assertFalse($connection->isConnected());
        }
    }

    /** @param array<string,mixed> $result */
    private function finishForkChild(?Connection $child, string $resultFile, array $result, int $exitCode): never
    {
        $cleanupFailures = [];
        if ($child instanceof Connection) {
            try {
                if ($child->isTransactionActive()) {
                    $child->rollBack();
                }
            } catch (\Throwable $exception) {
                $cleanupFailures[] = 'child-rollback:' . $exception::class;
            }
            try {
                $child->close();
            } catch (\Throwable $exception) {
                $cleanupFailures[] = 'child-close:' . $exception::class;
            }
        }
        if ($cleanupFailures !== []) {
            $result['cleanup_failures'] = $cleanupFailures;
            $exitCode = 1;
        }
        if (file_put_contents($resultFile, json_encode($result, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            $exitCode = 1;
        }
        exit($exitCode);
    }

    private function waitForChild(int $pid, int &$status): bool
    {
        if (isset($this->reapedChildren[$pid])) {
            return true;
        }
        $deadline = microtime(true) + 8.0;
        do {
            $waited = pcntl_waitpid($pid, $status, WNOHANG);
            if ($waited === $pid) {
                $this->reapedChildren[$pid] = true;
                return true;
            }
            if ($waited === -1) {
                $this->recordCleanupFailure('wait child ' . $pid . ' returned no owned child');
                return false;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);
        $this->terminateChild($pid, $status);
        return false;
    }

    private function terminateChild(int $pid, int &$status): void
    {
        if (isset($this->reapedChildren[$pid])) {
            return;
        }
        if (posix_kill($pid, SIGKILL)) {
            $waited = pcntl_waitpid($pid, $status);
            if ($waited === $pid) {
                $this->reapedChildren[$pid] = true;
            } else {
                $this->recordCleanupFailure('killed child ' . $pid . ' was not reaped');
            }
            return;
        }
        $waited = pcntl_waitpid($pid, $status, WNOHANG);
        if ($waited === $pid) {
            $this->reapedChildren[$pid] = true;
        } elseif ($waited === -1) {
            $this->recordCleanupFailure('owned child ' . $pid . ' could not be reaped');
        } elseif ($waited === 0) {
            $this->recordCleanupFailure('owned child ' . $pid . ' remained running after kill failure');
        }
    }

    private function attemptCleanup(string $label, callable $operation): void
    {
        try {
            $operation();
        } catch (\Throwable $exception) {
            $this->recordCleanupFailure($label . ':' . $exception::class);
        }
    }

    private function recordCleanupFailure(string $failure): void
    {
        $this->cleanupFailures[] = $failure;
        if (!$this->writeCleanupDiagnostic($failure)) {
            fwrite(STDERR, 'native cleanup diagnostic persistence failed: ' . $failure . PHP_EOL);
        }
    }

    private function writeCleanupDiagnostic(string $message): bool
    {
        $path = dirname(__DIR__, 4) . '/.phpunit.cache/f2/native-cleanup-failures.log';
        $directory = dirname($path);
        if (!is_dir($directory)) {
            return false;
        }
        $written = file_put_contents($path, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            return false;
        }

        return true;
    }

    private function removeOwnedFile(string $label, string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        if (!unlink($file)) {
            $this->recordCleanupFailure($label . ':unlink-failed');
        }
    }

    private function reconnectAdminForCleanup(?Connection $admin): ?Connection
    {
        if ($admin instanceof Connection) {
            try {
                if ($admin->isConnected()) {
                    return $admin;
                }
            } catch (\Throwable $exception) {
                $this->recordCleanupFailure('inspect cleanup admin:' . $exception::class);
            }
        }
        try {
            return $this->connection();
        } catch (\Throwable $exception) {
            $this->recordCleanupFailure('reconnect cleanup admin:' . $exception::class);

            return null;
        }
    }

    private function releaseOwnedConnectionsBeforeDdl(?Connection ...$connections): void
    {
        $seen = [];
        foreach ($connections as $index => $connection) {
            if (!$connection instanceof Connection) {
                continue;
            }
            $objectId = spl_object_id($connection);
            if (isset($seen[$objectId])) {
                continue;
            }
            $seen[$objectId] = true;
            if (!$connection->isConnected()) {
                continue;
            }
            $this->attemptCleanup('read pre-DDL backend ' . $index, function () use ($connection): void {
                $backendPid = (int) $connection->fetchOne('SELECT pg_backend_pid()');
                if (!in_array($backendPid, $this->observedBackendPids, true)) {
                    $this->observedBackendPids[] = $backendPid;
                }
            });
            if ($connection->isConnected() && $connection->isTransactionActive()) {
                $this->attemptCleanup('rollback pre-DDL connection ' . $index, function () use ($connection): void {
                    $connection->rollBack();
                });
            }
            if ($connection->isConnected()) {
                $this->attemptCleanup('close pre-DDL connection ' . $index, function () use ($connection): void {
                    $connection->close();
                });
            }
        }
    }

    private function cleanup(?Connection $admin, ?Connection $primary, ?Connection $probe, Connection ...$extra): void
    {
        $owned = [$primary, $probe, ...$extra];
        $backendPids = $this->observedBackendPids;
        foreach ($owned as $index => $connection) {
            if (!$connection instanceof Connection) {
                continue;
            }
            if ($connection->isConnected()) {
                $this->attemptCleanup('read owned backend ' . $index, function () use (
                    $connection,
                    &$backendPids,
                ): void {
                    $backendPids[] = (int) $connection->fetchOne('SELECT pg_backend_pid()');
                });
            }
            if ($connection->isConnected() && $connection->isTransactionActive()) {
                $this->attemptCleanup('rollback owned connection ' . $index, function () use ($connection): void {
                    $connection->rollBack();
                });
            }
            if ($connection->isConnected()) {
                $this->attemptCleanup('close owned connection ' . $index, function () use ($connection): void {
                    $connection->close();
                });
            }
        }
        if ($admin instanceof Connection) {
            $this->attemptCleanup('rollback admin connection', function () use ($admin): void {
                if ($admin->isConnected() && $admin->isTransactionActive()) {
                    $admin->rollBack();
                }
            });
            foreach ([self::SCHEMA, self::ALT_SCHEMA] as $schema) {
                $this->attemptCleanup('drop owned schema ' . $schema, function () use ($admin, $schema): void {
                    $this->executeBoundedCleanupDdl($admin, 'DROP SCHEMA IF EXISTS "' . $schema . '" CASCADE');
                });
            }
            foreach ([self::SCHEMA, self::ALT_SCHEMA] as $schema) {
                $this->attemptCleanup('verify removed schema ' . $schema, function () use ($admin, $schema): void {
                    self::assertSame(
                        0,
                        (int) $admin->fetchOne(
                            'SELECT count(*) FROM pg_namespace WHERE nspname = :schema',
                            ['schema' => $schema],
                            ['schema' => ParameterType::STRING],
                        ),
                    );
                });
            }
            foreach ($backendPids as $backendPid) {
                $this->attemptCleanup('verify closed backend ' . $backendPid, function () use (
                    $admin,
                    $backendPid,
                ): void {
                    $this->assertBackendGone($admin, $backendPid);
                });
            }
        }
        if ($admin instanceof Connection) {
            if ($admin->isConnected()) {
                $this->attemptCleanup('close admin connection', function () use ($admin): void {
                    $admin->close();
                });
            }
        }
    }

    private function assertBackendGone(Connection $admin, int $backendPid): void
    {
        $deadline = microtime(true) + 2.0;
        do {
            $active = (int) $admin->fetchOne(
                'SELECT count(*) FROM pg_stat_activity WHERE pid = :pid',
                ['pid' => $backendPid],
                ['pid' => ParameterType::INTEGER],
            );
            if ($active === 0) {
                return;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);
        self::fail('owned backend ' . $backendPid . ' remained active after child cleanup');
    }
}
