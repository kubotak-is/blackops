<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Read;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditReadAuthorizationDecision;
use BlackOps\Core\Audit\AuditReadPurpose;
use BlackOps\Core\Audit\AuditReadRequestBasis;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Orchestration\AuditProtectedReadGate;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Read\OperationReadAuditSession;
use BlackOps\Internal\Audit\Read\OperationReadAuditSessionFactory;
use BlackOps\Internal\Audit\Read\OperationReadExecutionSource;
use BlackOps\Internal\Audit\Read\OperationReadNonceSource;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\StorageProtection\StorageKey;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class PostgreSqlOperationReadAuditSessionTest extends TestCase
{
    public function testSystemAndTenantSessionsCommitTwoStoreRootsBeforeRelease(): void
    {
        $fixture = $this->fixture();
        try {
            $cases = [
                [
                    AuditReadPurpose::Status,
                    new ActorRef('actor-1', 'user'),
                    null,
                    AuditReadAuthorizationDecision::Allow,
                    'access.operation_status.accessed',
                ],
                [
                    AuditReadPurpose::Journal,
                    null,
                    new TenantRef('account', 'tenant-1'),
                    AuditReadAuthorizationDecision::ResourceUnavailable,
                    'access.operation_journal.unavailable',
                ],
            ];
            $operationId = OperationId::fromString('0194a000-0000-7000-8000-0000000000d2');
            $execution = new AuditTypedReference('process', 'blackops-cli');
            $requestedAt = '2026-09-05T02:00:00.123456Z';
            $nonce = '00112233445566778899aabbccddeeff';

            foreach ($cases as [$purpose, $actor, $tenant, $decision, $terminalType]) {
                $partition = $fixture['partitions']->derive(
                    $tenant === null ? null : new AuditTypedReference($tenant->type(), $tenant->id()),
                );
                $expectedBasis = new AuditReadRequestBasis(
                    $purpose,
                    $actor === null ? null : new AuditTypedReference($actor->type(), $actor->id()),
                    $execution,
                    $tenant === null ? null : new AuditTypedReference($tenant->type(), $tenant->id()),
                    $requestedAt,
                    $nonce,
                );
                $beforeBegin = $fixture['connection']->beginCalls;
                $beforeCommit = $fixture['connection']->commitCalls;
                $session = $fixture['factory']->begin($purpose, $operationId, $actor, $tenant);

                self::assertTrue($fixture['connection']->isAutoCommit());
                self::assertFalse($fixture['connection']->isTransactionActive());
                self::assertSame(0, $fixture['connection']->getTransactionNestingLevel());
                $rows = $this->rows($fixture['probe'], $fixture['schema'], $partition);
                self::assertCount(1, $rows);
                self::assertSame('access.operation_' . $purpose->value . '.requested', $rows[0]['action_type']);
                self::assertSame($partition, $rows[0]['partition_id']);
                self::assertSame($expectedBasis->requestRef(), $this->logicalActionId(
                    $fixture,
                    $rows[0],
                    $tenant,
                    $partition,
                ));

                $authorizationSentinel = true;
                self::assertTrue($authorizationSentinel);
                if ($decision === AuditReadAuthorizationDecision::Allow) {
                    $session->accessed();
                } else {
                    $session->unavailable();
                }
                $releaseSentinel = true;
                self::assertTrue($releaseSentinel);

                self::assertFalse($fixture['connection']->isTransactionActive());
                self::assertSame(0, $fixture['connection']->getTransactionNestingLevel());
                self::assertSame(2, $fixture['connection']->beginCalls - $beforeBegin);
                self::assertSame(2, $fixture['connection']->commitCalls - $beforeCommit);
                $rows = $this->rows($fixture['probe'], $fixture['schema'], $partition);
                self::assertCount(2, $rows);
                self::assertSame(['1', '2'], array_column($rows, 'sequence'));
                self::assertSame(
                    ['access.operation_' . $purpose->value . '.requested', $terminalType],
                    array_column($rows, 'action_type'),
                );
                self::assertSame(
                    [$expectedBasis->requestRef(), $expectedBasis->requestRef()],
                    array_map(fn(array $row): string => $this->logicalActionId(
                        $fixture,
                        $row,
                        $tenant,
                        $partition,
                    ), $rows),
                );
                self::assertSame(
                    ['oid:v1:e697b9f4332f3532bd84ba2bbe553152c5d223663d3bdec36e8b38e420f1d115']
                    + array_fill(1, 1, 'oid:v1:e697b9f4332f3532bd84ba2bbe553152c5d223663d3bdec36e8b38e420f1d115'),
                    array_column($rows, 'resource_id'),
                );
                self::assertSame(1, $this->tableCount(
                    $fixture['probe'],
                    $fixture['schema'],
                    'audit_partition_state',
                    $partition,
                ));
                self::assertSame(1, $this->tableCount(
                    $fixture['probe'],
                    $fixture['schema'],
                    'audit_segments',
                    $partition,
                ));
            }

            self::assertSame(4, $fixture['connection']->beginCalls);
            self::assertSame(4, $fixture['connection']->commitCalls);
            self::assertSame(0, $fixture['connection']->rollbackCalls);
            self::assertSame(4, $this->recordCount($fixture['probe'], $fixture['schema']));
            self::assertSame(0, $this->outboxCount($fixture['probe'], $fixture['schema']));
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function testStoreOwnedRollbackLeavesOnlyCommittedPrefixAndClosesSession(): void
    {
        $fixture = $this->fixture(protectionFailure: true);
        try {
            try {
                $fixture['factory']->begin(
                    AuditReadPurpose::Status,
                    OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                    null,
                    null,
                );
                self::fail('the requested append protection failure was ignored');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
            }
            self::assertSame(0, $this->recordCount($fixture['probe'], $fixture['schema']));
            self::assertSame(0, $this->outboxCount($fixture['probe'], $fixture['schema']));
            self::assertFalse($fixture['connection']->isTransactionActive());
            self::assertGreaterThanOrEqual(1, $fixture['connection']->rollbackCalls);

            $fixture['keySource']->throwing = false;
            $session = $fixture['factory']->begin(
                AuditReadPurpose::Outcome,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                null,
                null,
            );
            self::assertSame(1, $this->recordCount($fixture['probe'], $fixture['schema']));
            self::assertSame(0, $this->outboxCount($fixture['probe'], $fixture['schema']));
            $fixture['keySource']->throwing = true;
            try {
                $session->unavailable();
                self::fail('the terminal append rollback was ignored');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
            }
            self::assertSame(1, $this->recordCount($fixture['probe'], $fixture['schema']));
            self::assertSame(0, $this->outboxCount($fixture['probe'], $fixture['schema']));
            try {
                $session->unavailable();
                self::fail('a rolled-back terminal append was retried');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            }
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function testRollbackAfterTerminalFailureAllowsANewSessionWithANewRequestReference(): void
    {
        $fixture = $this->fixture('normal', false, true, true);
        try {
            $fixture['keySource']->throwing = false;
            $session = $fixture['factory']->begin(
                AuditReadPurpose::Status,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                null,
                null,
            );
            $firstRows = $this->rows($fixture['probe'], $fixture['schema'], 'system:global');
            self::assertCount(1, $firstRows);
            $firstReference = $this->logicalActionId($fixture, $firstRows[0], null, 'system:global');
            $fixture['keySource']->throwing = true;
            try {
                $session->unavailable();
                self::fail('terminal protection failure was ignored');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
            }
            self::assertSame(1, $this->recordCount($fixture['probe'], $fixture['schema']));

            $fixture['keySource']->throwing = false;
            $retry = $fixture['factory']->begin(
                AuditReadPurpose::Status,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                null,
                null,
            );
            $retryRows = $this->rows($fixture['probe'], $fixture['schema'], 'system:global');
            self::assertCount(2, $retryRows);
            $secondReference = $this->logicalActionId($fixture, $retryRows[1], null, 'system:global');
            self::assertNotSame($firstReference, $secondReference);
            $retry->unavailable();
            self::assertSame(3, $this->recordCount($fixture['probe'], $fixture['schema']));
            self::assertSame(0, $this->outboxCount($fixture['probe'], $fixture['schema']));
            self::assertSame(
                ['requested', 'requested', 'unavailable'],
                $this->phases($fixture['probe'], $fixture['schema']),
            );
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function testPostCommitCrashWindowsLeaveCommittedRowsWithoutRetry(): void
    {
        $fixture = $this->fixture('crash');
        try {
            $fixture['connection']->throwAfterCommit = true;
            try {
                $fixture['factory']->begin(
                    AuditReadPurpose::Diagnostics,
                    OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                    null,
                    null,
                );
                self::fail('the requested post-commit crash was ignored');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
            }
            self::assertSame(1, $this->recordCount($fixture['probe'], $fixture['schema']));
            self::assertSame(0, $this->outboxCount($fixture['probe'], $fixture['schema']));
            self::assertSame(['requested'], $this->phases($fixture['probe'], $fixture['schema']));
        } finally {
            $this->cleanup($fixture);
        }

        $fixture = $this->fixture('crash');
        try {
            $session = $fixture['factory']->begin(
                AuditReadPurpose::Status,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                null,
                null,
            );
            $fixture['connection']->throwAfterCommit = true;
            try {
                $session->accessed();
                self::fail('the terminal post-commit crash was ignored');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
            }
            self::assertSame(2, $this->recordCount($fixture['probe'], $fixture['schema']));
            self::assertSame(0, $this->outboxCount($fixture['probe'], $fixture['schema']));
            self::assertSame(['requested', 'accessed'], $this->phases($fixture['probe'], $fixture['schema']));
            try {
                $session->accessed();
                self::fail('the terminal crash window was retried');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            }
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function testPreCommitCrashReleasesNoRequestedRecordOrOutboxRow(): void
    {
        $fixture = $this->fixture('crash');
        try {
            $fixture['connection']->throwBeforeCommit = true;
            try {
                $fixture['factory']->begin(
                    AuditReadPurpose::Status,
                    OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                    null,
                    null,
                );
                self::fail('the pre-commit crash was ignored');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
            self::assertSame(0, $this->recordCount($fixture['probe'], $fixture['schema']));
            self::assertSame(0, $this->outboxCount($fixture['probe'], $fixture['schema']));
            self::assertFalse($fixture['connection']->isTransactionActive());
            self::assertSame(0, $fixture['connection']->getTransactionNestingLevel());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function testTransactionAndStoreFailurePrecedenceIsFailClosed(): void
    {
        foreach (['auto-commit-off', 'ambient-level1', 'ambient-level2', 'inactive-nonzero'] as $mode) {
            $fixture = $this->fixture();
            try {
                match ($mode) {
                    'auto-commit-off' => $fixture['connection']->setAutoCommit(false),
                    'ambient-level1', 'ambient-level2' => $this->beginAmbient($fixture['connection'], $mode),
                    'inactive-nonzero' => null,
                };
                if ($mode === 'inactive-nonzero') {
                    $fixture = $this->replaceConnectionFixture($fixture, 'inactive');
                }
                try {
                    $fixture['factory']->begin(
                        AuditReadPurpose::Status,
                        OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                        null,
                        null,
                    );
                    self::fail($mode . ' was accepted');
                } catch (AuditDeliveryException $exception) {
                    self::assertSame(AuditDeliveryFailure::TransactionInvalid, $exception->failure());
                }
                self::assertSame(0, $this->recordCount($fixture['probe'], $fixture['schema']));
            } finally {
                $this->cleanup($fixture);
            }
        }

        $fixture = $this->fixture('drift-requested');
        try {
            try {
                $fixture['factory']->begin(
                    AuditReadPurpose::Status,
                    OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                    null,
                    null,
                );
                self::fail('requested post-state drift was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::TransactionInvalid, $exception->failure());
            }
            self::assertSame(1, $this->recordCount($fixture['probe'], $fixture['schema']));
        } finally {
            $this->cleanup($fixture);
        }

        $fixture = $this->fixture('drift-terminal');
        try {
            $session = $fixture['factory']->begin(
                AuditReadPurpose::Status,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                null,
                null,
            );
            $fixture['connection']->armed = true;
            try {
                $session->accessed();
                self::fail('terminal post-state drift was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::TransactionInvalid, $exception->failure());
            }
            self::assertSame(2, $this->recordCount($fixture['probe'], $fixture['schema']));
            try {
                $session->accessed();
                self::fail('terminal post-state drift was retried');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            }
        } finally {
            $this->cleanup($fixture);
        }

        foreach ([
            ['uuid',        AuditDeliveryFailure::ResourceUnavailable],
            ['protection',  AuditDeliveryFailure::ResourceUnavailable],
            ['store-clock', AuditDeliveryFailure::ResourceUnavailable],
        ] as [$mode, $expected]) {
            $fixture = $this->fixture(
                'normal',
                $mode === 'uuid',
                $mode === 'protection',
                false,
                $mode === 'store-clock',
            );
            try {
                try {
                    $fixture['factory']->begin(
                        AuditReadPurpose::Status,
                        OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                        null,
                        null,
                    );
                    self::fail($mode . ' failure was ignored');
                } catch (AuditDeliveryException $exception) {
                    self::assertSame($expected, $exception->failure());
                }
                self::assertSame(0, $this->recordCount($fixture['probe'], $fixture['schema']));
            } finally {
                $this->cleanup($fixture);
            }
        }

        foreach ([
            ['capacity',  AuditDeliveryFailure::ResourceUnavailable],
            ['integrity', AuditDeliveryFailure::StorageUnavailable],
            ['database',  AuditDeliveryFailure::StorageUnavailable],
        ] as [$mode, $expected]) {
            $fixture = $this->fixture($mode);
            try {
                $session = $fixture['factory']->begin(
                    AuditReadPurpose::Status,
                    OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                    null,
                    null,
                );
                self::assertSame(1, $this->recordCount($fixture['probe'], $fixture['schema']));
                $fixture['connection']->armed = true;
                try {
                    $session->unavailable();
                    self::fail($mode . ' failure was ignored');
                } catch (AuditDeliveryException $exception) {
                    self::assertSame($expected, $exception->failure(), $mode);
                    self::assertNull($exception->getPrevious());
                }
                self::assertSame(1, $this->recordCount($fixture['probe'], $fixture['schema']));
                try {
                    $session->unavailable();
                    self::fail($mode . ' failure allowed a retry');
                } catch (AuditDeliveryException $exception) {
                    self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
                }
            } finally {
                $this->cleanup($fixture);
            }
        }
    }

    /**
     * @return array{
     *     admin:Connection,
     *     connection:D2CountingConnection,
     *     probe:Connection,
     *     schema:string,
     *     factory:OperationReadAuditSessionFactory,
     *     store:PostgreSqlAuditRecordStore,
     *     partitions:AuditPartitionIdDeriver,
     *     keySource:D2AuditKeySource,
     *     readNonce:D2ReadNonceSource,
     *     storeClock:D2StoreClock
     * }
     */
    private function fixture(
        string $mode = 'normal',
        bool $uuidFailure = false,
        bool $protectionFailure = false,
        bool $rotatingNonce = false,
        bool $storeClockFailure = false,
    ): array {
        $admin = $this->connection();
        $base = $this->connection();
        $params = $base->getParams();
        $driver = $base->getDriver();
        $base->close();
        $connection = match ($mode) {
            'inactive' => new D2InactiveNonzeroConnection($params, $driver),
            'drift-requested' => new D2PostStateDriftConnection($params, $driver, true),
            'drift-terminal' => new D2PostStateDriftConnection($params, $driver, false),
            'crash' => new D2CrashConnection($params, $driver),
            'capacity', 'integrity', 'database' => new D2StoreFaultConnection($params, $driver, $mode),
            default => new D2CountingConnection($params, $driver),
        };
        $probe = $this->connection();
        $schema = 'blackops_d2_read_' . bin2hex(random_bytes(6));
        $admin->executeStatement('CREATE SCHEMA "' . $schema . '"');
        $recordSchema = new PostgreSqlAuditSchema($schema);
        $outboxSchema = new \BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxSchema(
            new PostgreSqlMigrationSchema($schema),
        );
        foreach ([...$recordSchema->statements(), ...$outboxSchema->statements()] as $statement) {
            $admin->executeStatement($statement);
        }

        $catalog = FrameworkAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $jcs = new AuditJcsCodec();
        $partitions = new AuditPartitionIdDeriver($jcs);
        $validator = new AuditRecordValidator($lookup);
        $records = new AuditRecordCodec($validator, $jcs);
        $finalizer = new AuditRecordFinalizer($validator, $records);
        $ingress = new AuditIngressEnvelopeCodec($records, $partitions, $jcs);
        $keySource = new D2AuditKeySource($protectionFailure);
        $storeClock = new D2StoreClock($storeClockFailure);
        $protection = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            $keySource,
            new D2StoreProtectionNonceSource(),
            new AuditAssociatedData(),
            $records,
            $ingress,
        );
        $uuids = new D2StoreUuidGenerator($uuidFailure);
        $recordStore = new PostgreSqlAuditRecordStore(
            $connection,
            $recordSchema,
            $storeClock,
            $uuids,
            $validator,
            $finalizer,
            $partitions,
            $records,
            $protection,
            new PostgreSqlAuditStoredRecordHydrator($partitions),
        );
        $outboxStore = new \BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore(
            $connection,
            $outboxSchema,
            $storeClock,
            $uuids,
            $validator,
            $finalizer,
            $partitions,
            $ingress,
            $protection,
            new \BlackOps\Internal\Audit\Outbox\AuditOutboxIdempotencyKeyHasher(),
            new \BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxRecordHydrator(
                new \BlackOps\Internal\Audit\Outbox\PostgreSqlAuditProtectedIngressHydrator(),
            ),
            new \BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration(),
        );
        $selector = new AuditDeliverySelector($lookup, $validator);
        $coordinator = new AuditDeliveryCoordinator($connection, $selector, $recordStore, $outboxStore);
        $readNonce = new D2ReadNonceSource($rotatingNonce);
        $factory = new OperationReadAuditSessionFactory(
            new AuditProtectedReadGate($coordinator),
            new \BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver('0123456789abcdef0123456789abcdef'),
            $readNonce,
            new D2ReadClock(),
            new D2ReadExecutionSource(),
            AuditSourceRuntime::Cli,
            'build-1',
        );

        return [
            'admin' => $admin,
            'connection' => $connection,
            'probe' => $probe,
            'schema' => $schema,
            'factory' => $factory,
            'store' => $recordStore,
            'partitions' => $partitions,
            'keySource' => $keySource,
            'readNonce' => $readNonce,
            'storeClock' => $storeClock,
        ];
    }

    /** @param array{connection:D2CountingConnection} $fixture */
    private function replaceConnectionFixture(array $fixture, string $mode): array
    {
        $this->cleanup($fixture);

        return $this->fixture($mode);
    }

    private function beginAmbient(D2CountingConnection $connection, string $mode): void
    {
        $connection->beginTransaction();
        if ($mode === 'ambient-level2') {
            $connection->beginTransaction();
        }
    }

    /** @param array{store:PostgreSqlAuditRecordStore,probe:Connection,schema:string} $fixture */
    private function logicalActionId(array $fixture, array $row, ?TenantRef $tenant, string $partition): string
    {
        $result = $fixture['store']->find(
            (string) $row['record_id'],
            $tenant === null ? null : new AuditTypedReference($tenant->type(), $tenant->id()),
            $partition,
        );
        $record = $result->record();
        self::assertNotNull($record);
        $snapshot = $record->logicalSnapshot();
        self::assertIsArray($snapshot['action'] ?? null);
        self::assertIsString($snapshot['action']['id'] ?? null);
        self::assertSame(
            'oid:v1:e697b9f4332f3532bd84ba2bbe553152c5d223663d3bdec36e8b38e420f1d115',
            $snapshot['resource']['id'],
        );

        return $snapshot['action']['id'];
    }

    /** @return list<array{record_id:string,partition_id:string,sequence:string,action_type:string,resource_id:string}> */
    private function rows(Connection $probe, string $schema, string $partition): array
    {
        $table = new PostgreSqlAuditSchema($schema)->recordsTable();
        /** @var list<array{record_id:string,partition_id:string,sequence:string,action_type:string,resource_id:string}> $rows */
        $rows = $probe->fetchAllAssociative('SELECT record_id::text AS record_id, partition_id, sequence::text AS sequence, action_type, resource_id '
        . 'FROM '
        . $table
        . ' WHERE partition_id = ? ORDER BY sequence', [$partition]);

        return $rows;
    }

    private function recordCount(Connection $probe, string $schema): int
    {
        return (int) $probe->fetchOne('SELECT count(*) FROM ' . new PostgreSqlAuditSchema($schema)->recordsTable());
    }

    private function outboxCount(Connection $probe, string $schema): int
    {
        $outboxSchema = new \BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxSchema(
            new PostgreSqlMigrationSchema($schema),
        );

        return (int) $probe->fetchOne('SELECT count(*) FROM ' . $outboxSchema->recordsTable());
    }

    /** @return list<string> */
    private function phases(Connection $probe, string $schema): array
    {
        $phases = [];
        foreach ($probe->fetchFirstColumn(
            'SELECT action_type FROM ' . new PostgreSqlAuditSchema($schema)->recordsTable() . ' ORDER BY sequence',
        ) as $value) {
            $actionType = (string) $value;
            if (str_ends_with($actionType, '.requested')) {
                $phases[] = 'requested';
                continue;
            }
            if (str_ends_with($actionType, '.accessed')) {
                $phases[] = 'accessed';
                continue;
            }
            $phases[] = 'unavailable';
        }

        return $phases;
    }

    private function tableCount(Connection $probe, string $schema, string $table, string $partition): int
    {
        return (int) $probe->fetchOne(
            'SELECT count(*) FROM '
            . new PostgreSqlAuditSchema($schema)->schemaName()
            . '.'
            . $table
            . ' WHERE partition_id = ?',
            [$partition],
        );
    }

    /** @param array{admin:Connection,connection:D2CountingConnection,probe:Connection,schema:string} $fixture */
    private function cleanup(array $fixture): void
    {
        try {
            while ($fixture['connection']->isTransactionActive()) {
                $fixture['connection']->rollBack();
            }
        } catch (\Throwable) {
        }
        try {
            $fixture['admin']->executeStatement('DROP SCHEMA IF EXISTS "' . $fixture['schema'] . '" CASCADE');
        } finally {
            $fixture['admin']->close();
            $fixture['connection']->close();
            $fixture['probe']->close();
        }
    }

    private function connection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: 5432),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
    }
}

class D2CountingConnection extends Connection
{
    public int $beginCalls = 0;

    public int $commitCalls = 0;

    public int $rollbackCalls = 0;

    public function beginTransaction(): void
    {
        $this->beginCalls++;
        parent::beginTransaction();
    }

    public function commit(): void
    {
        $this->commitCalls++;
        parent::commit();
    }

    public function rollBack(): void
    {
        $this->rollbackCalls++;
        parent::rollBack();
    }
}

final class D2InactiveNonzeroConnection extends D2CountingConnection
{
    public function getTransactionNestingLevel(): int
    {
        return $this->isTransactionActive() ? parent::getTransactionNestingLevel() : 1;
    }
}

final class D2PostStateDriftConnection extends D2CountingConnection
{
    public bool $armed;

    private bool $drifted = false;

    public function __construct(array $params, \Doctrine\DBAL\Driver $driver, bool $armed)
    {
        parent::__construct($params, $driver);
        $this->armed = $armed;
    }

    public function commit(): void
    {
        parent::commit();
        if ($this->armed) {
            $this->drifted = true;
        }
    }

    public function getTransactionNestingLevel(): int
    {
        return $this->drifted ? 1 : parent::getTransactionNestingLevel();
    }
}

final class D2CrashConnection extends D2CountingConnection
{
    public bool $throwBeforeCommit = false;

    public bool $throwAfterCommit = false;

    public function commit(): void
    {
        if ($this->throwBeforeCommit) {
            throw new \RuntimeException('pre-commit-crash');
        }
        parent::commit();
        if ($this->throwAfterCommit) {
            throw new \RuntimeException('post-commit-crash');
        }
    }
}

final class D2StoreFaultConnection extends D2CountingConnection
{
    public bool $armed = false;

    public function __construct(
        array $params,
        \Doctrine\DBAL\Driver $driver,
        private readonly string $mode,
    ) {
        parent::__construct($params, $driver);
    }

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        $row = parent::fetchAssociative($query, $params, $types);
        if (!$this->armed || !is_array($row)) {
            return $row;
        }
        if (
            $this->mode === 'capacity'
            && str_contains($query, 'state_version')
            && str_contains($query, 'audit_partition_state')
        ) {
            $row['state_version'] = '9007199254740991';
        }
        if ($this->mode === 'integrity' && str_contains($query, 'last_sequence')) {
            $row['last_sequence'] = 'integrity-canary';
        }

        return $row;
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        if ($this->armed && $this->mode === 'database' && str_contains($query, 'pg_advisory_xact_lock')) {
            throw new \RuntimeException('database-canary');
        }

        return parent::fetchOne($query, $params, $types);
    }

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        return parent::fetchAllAssociative($query, $params, $types);
    }
}

final class D2StoreUuidGenerator implements Uuidv7Generator
{
    private int $calls = 0;

    public function __construct(
        private readonly bool $failure,
    ) {}

    public function generate(\DateTimeImmutable $time): string
    {
        if ($this->failure) {
            throw new \RuntimeException('uuid-canary');
        }
        $this->calls++;

        return '01941f29-7c00-7000-8000-' . sprintf('%012x', $this->calls);
    }
}

final class D2StoreClock implements ClockInterface
{
    public function __construct(
        private readonly bool $throwing = false,
    ) {}

    public function now(): \DateTimeImmutable
    {
        if ($this->throwing) {
            throw new \RuntimeException('store-clock-canary');
        }

        return new \DateTimeImmutable('2026-09-05T02:00:01.000000Z');
    }
}

final class D2ReadClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-05T02:00:00.123456Z');
    }
}

final class D2ReadNonceSource implements OperationReadNonceSource
{
    private int $calls = 0;

    public function __construct(
        private readonly bool $rotating = false,
    ) {}

    public function generate(): string
    {
        $this->calls++;
        if ($this->rotating && $this->calls > 1) {
            return "\x10\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee\xff";
        }

        return "\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee\xff";
    }
}

final class D2ReadExecutionSource implements OperationReadExecutionSource
{
    public function current(): AuditTypedReference
    {
        return new AuditTypedReference('process', 'blackops-cli');
    }
}

final class D2StoreProtectionNonceSource implements NonceSource
{
    public function generate(): string
    {
        return str_repeat('n', 24);
    }
}

final class D2AuditKeySource implements AuditProtectionKeySource
{
    public bool $throwing;

    public function __construct(bool $throwing)
    {
        $this->throwing = $throwing;
    }

    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        if ($this->throwing) {
            throw AuditProtectionException::unavailable();
        }

        return new StorageKey(
            $purpose->value,
            str_repeat($purpose === AuditProtectionPurpose::AuditRecord ? 'r' : 'b', 32),
        );
    }

    public function key(string $keyId, ?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        return new StorageKey($keyId, str_repeat(
            $keyId === AuditProtectionPurpose::AuditRecord->value ? 'r' : 'b',
            32,
        ));
    }
}
