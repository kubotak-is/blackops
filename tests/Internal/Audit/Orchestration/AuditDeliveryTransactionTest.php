<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Orchestration;

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
use BlackOps\Core\OperationResult;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Database\AfterCommitFailure;
use BlackOps\Database\AfterCommitFailureReporter;
use BlackOps\Database\DatabaseManager;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Orchestration\AuditMutationTransactionCoordinator;
use BlackOps\Internal\Audit\Outbox\AuditOutboxIdempotencyKeyHasher;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxRecordHydrator;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxSchema;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditProtectedIngressHydrator;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Database\DoctrineDatabaseManager;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use BlackOps\Internal\Transaction\OperationTransactionRejected;
use BlackOps\Internal\Transaction\TransactionRuntime;
use BlackOps\StorageProtection\StorageKey;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionClass;

final class AuditDeliveryTransactionTest extends TestCase
{
    public function testStandaloneOwnsLevelZeroRootForRequiredAndDurableRoutes(): void
    {
        [$admin, $primary, $probe, $schema, $coordinator, $selector] = $this->fixture();
        try {
            $required = $this->draft(AuditDelivery::Required);
            $coordinator->deliverStandalone(new AuditDeliveryRequest($required, null));
            self::assertFalse($primary->isTransactionActive());
            self::assertSame(0, $primary->getTransactionNestingLevel());
            self::assertSame(
                1,
                (int) $probe->fetchOne('SELECT count(*) FROM ' . new PostgreSqlAuditSchema($schema)->recordsTable()),
            );

            $durable = $this->draft(AuditDelivery::Durable);
            $coordinator->deliverStandalone(new AuditDeliveryRequest($durable, 'delivery-key-1'));
            self::assertFalse($primary->isTransactionActive());
            self::assertSame(0, $primary->getTransactionNestingLevel());
            self::assertSame(
                1,
                (int) $probe->fetchOne(
                    'SELECT count(*) FROM '
                        . new PostgreSqlAuditOutboxSchema(new PostgreSqlMigrationSchema($schema))->recordsTable(),
                ),
            );
            self::assertSame(AuditDelivery::Durable, $selector->forDraft($durable)->delivery());
        } finally {
            $this->cleanup($admin, $primary, $probe, $schema);
        }
    }

    public function testCurrentTransactionParticipatesAtExactlyLevelOneUntilCallerCommits(): void
    {
        [$admin, $primary, $probe, $schema, $coordinator, $selector] = $this->fixture();
        try {
            $draft = $this->draft(AuditDelivery::Required);
            $plan = $selector->forDraft($draft);
            $primary->beginTransaction();
            $coordinator->deliverInCurrentTransaction($plan, new AuditDeliveryRequest($draft, null));
            self::assertTrue($primary->isTransactionActive());
            self::assertSame(1, $primary->getTransactionNestingLevel());
            self::assertSame(
                0,
                (int) $probe->fetchOne('SELECT count(*) FROM ' . new PostgreSqlAuditSchema($schema)->recordsTable()),
            );

            $primary->commit();
            self::assertFalse($primary->isTransactionActive());
            self::assertSame(
                1,
                (int) $probe->fetchOne('SELECT count(*) FROM ' . new PostgreSqlAuditSchema($schema)->recordsTable()),
            );
        } finally {
            $this->cleanup($admin, $primary, $probe, $schema);
        }
    }

    public function testMutationCoordinatorCommitsBusinessAndRequiredAuditAtOneRoot(): void
    {
        [$admin, $primary, $probe, $schema, $coordinator, $selector] = $this->fixture();
        $businessTable = $this->businessTable($primary);
        try {
            $draft = $this->draft(AuditDelivery::Required);
            $plan = $selector->forDraft($draft);
            $requests = 0;
            $transactions = $this->transactionCoordinator($primary, $primary);
            $mutations = new AuditMutationTransactionCoordinator($transactions, $coordinator);
            $result = $mutations->execute(
                $plan,
                $this->metadata('app'),
                function () use ($primary, $businessTable): OperationResult {
                    self::assertTrue($primary->isTransactionActive());
                    $primary->insert($businessTable, ['id' => 1, 'value' => 'required-business']);

                    return OperationResult::completed();
                },
                function (OperationResult $result) use (&$requests, $draft): AuditDeliveryRequest {
                    self::assertTrue($result->isCompleted());
                    $requests++;

                    return new AuditDeliveryRequest($draft, null);
                },
            );
            self::assertTrue($result->isCompleted());
            self::assertSame(1, $requests);
            self::assertFalse($primary->isTransactionActive());
            self::assertSame(['required-business'], $this->businessValues($probe, $businessTable));
            self::assertSame(1, $this->recordCount($probe, $schema));
        } finally {
            $this->dropBusinessTable($primary, $businessTable);
            $this->cleanup($admin, $primary, $probe, $schema);
        }
    }

    public function testMutationCoordinatorCommitsBusinessAndDurableAuditAtOneRoot(): void
    {
        [$admin, $primary, $probe, $schema, $coordinator, $selector] = $this->fixture();
        $businessTable = $this->businessTable($primary);
        try {
            $draft = $this->draft(AuditDelivery::Durable);
            $plan = $selector->forDraft($draft);
            $requests = 0;
            $mutations = new AuditMutationTransactionCoordinator(
                $this->transactionCoordinator($primary, $primary),
                $coordinator,
            );
            $result = $mutations->execute(
                $plan,
                $this->metadata('app'),
                function () use ($primary, $businessTable): OperationResult {
                    self::assertTrue($primary->isTransactionActive());
                    $primary->insert($businessTable, ['id' => 1, 'value' => 'durable-business']);

                    return OperationResult::completed();
                },
                function (#[\SensitiveParameter] OperationResult $result) use (
                    &$requests,
                    $draft,
                ): AuditDeliveryRequest {
                    self::assertTrue($result->isCompleted());
                    $requests++;

                    return new AuditDeliveryRequest($draft, 'mutation-durable-key');
                },
            );
            self::assertTrue($result->isCompleted());
            self::assertSame(1, $requests);
            self::assertFalse($primary->isTransactionActive());
            self::assertSame(['durable-business'], $this->businessValues($probe, $businessTable));
            self::assertSame(1, $this->outboxCount($probe, $schema));
        } finally {
            $this->dropBusinessTable($primary, $businessTable);
            $this->cleanup($admin, $primary, $probe, $schema);
        }
    }

    public function testRequestFactoryFailureRollsBackBusinessAndRequiredOrDurableAudit(): void
    {
        foreach ([AuditDelivery::Required, AuditDelivery::Durable] as $delivery) {
            [$admin, $primary, $probe, $schema, $coordinator, $selector] = $this->fixture();
            $businessTable = $this->businessTable($primary);
            try {
                $draft = $this->draft($delivery);
                $plan = $selector->forDraft($draft);
                $invocations = 0;
                $requests = 0;
                $mutations = new AuditMutationTransactionCoordinator(
                    $this->transactionCoordinator($primary, $primary),
                    $coordinator,
                );
                try {
                    $mutations->execute(
                        $plan,
                        $this->metadata('app'),
                        function () use ($primary, $businessTable, &$invocations): OperationResult {
                            $invocations++;
                            $primary->insert($businessTable, ['id' => 1, 'value' => 'factory-rollback']);

                            return OperationResult::completed(new C3ResultCanaryOutcome());
                        },
                        function (#[\SensitiveParameter] OperationResult $result) use (
                            &$requests,
                        ): AuditDeliveryRequest {
                            $requests++;
                            throw new \RuntimeException('result-canary');
                        },
                    );
                    self::fail($delivery->value . ' request factory failure was accepted');
                } catch (AuditDeliveryException $exception) {
                    self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
                    self::assertSame('Audit delivery failed.', $exception->getMessage());
                    self::assertNull($exception->getPrevious());
                    self::assertStringNotContainsString('result-canary', $exception->getTraceAsString());
                    self::assertStringNotContainsString('result-canary', var_export($exception, true));
                    self::assertStringNotContainsString('result-canary', serialize($exception));
                    self::assertStringNotContainsString('result-canary', (string) $exception);
                    self::assertStringNotContainsString('result-canary', json_encode($exception, JSON_THROW_ON_ERROR));
                }
                self::assertSame(1, $invocations);
                self::assertSame(1, $requests);
                self::assertFalse($primary->isTransactionActive());
                self::assertSame([], $this->businessValues($probe, $businessTable));
                self::assertSame(0, $this->recordCount($probe, $schema));
                self::assertSame(0, $this->outboxCount($probe, $schema));
            } finally {
                $this->dropBusinessTable($primary, $businessTable);
                $this->cleanup($admin, $primary, $probe, $schema);
            }
        }
    }

    public function testRequiredOrDurableAppendFailureRollsBackBusinessAndAudit(): void
    {
        foreach ([AuditDelivery::Required, AuditDelivery::Durable] as $delivery) {
            [$admin, $primary, $probe, $schema, $coordinator, $selector] = $this->fixture(true);
            $businessTable = $this->businessTable($primary);
            try {
                $draft = $this->draft($delivery);
                $plan = $selector->forDraft($draft);
                $requests = 0;
                $mutations = new AuditMutationTransactionCoordinator(
                    $this->transactionCoordinator($primary, $primary),
                    $coordinator,
                );
                try {
                    $mutations->execute(
                        $plan,
                        $this->metadata('app'),
                        function () use ($primary, $businessTable): OperationResult {
                            $primary->insert($businessTable, ['id' => 1, 'value' => 'append-rollback']);

                            return OperationResult::completed();
                        },
                        function (#[\SensitiveParameter] OperationResult $result) use (
                            &$requests,
                            $draft,
                            $delivery,
                        ): AuditDeliveryRequest {
                            $requests++;

                            return new AuditDeliveryRequest(
                                $draft,
                                $delivery === AuditDelivery::Durable ? 'append-failure-key' : null,
                            );
                        },
                    );
                    self::fail($delivery->value . ' append failure was accepted');
                } catch (AuditDeliveryException $exception) {
                    self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
                    self::assertSame('Audit delivery failed.', $exception->getMessage());
                    self::assertNull($exception->getPrevious());
                }
                self::assertSame(1, $requests);
                self::assertFalse($primary->isTransactionActive());
                self::assertSame([], $this->businessValues($probe, $businessTable));
                self::assertSame(0, $this->recordCount($probe, $schema));
                self::assertSame(0, $this->outboxCount($probe, $schema));
            } finally {
                $this->dropBusinessTable($primary, $businessTable);
                $this->cleanup($admin, $primary, $probe, $schema);
            }
        }
    }

    public function testCapacityAndWriteProtectionResourceFailuresRollBackBusinessAndAudit(): void
    {
        foreach ([
            ['capacity', false],
            ['protection', true],
        ] as [$mode, $throwingProtection]) {
            foreach ([AuditDelivery::Required, AuditDelivery::Durable] as $delivery) {
                [$admin, $primary, $probe, $schema, $coordinator, $selector, $uuids] = $this->fixture(
                    $throwingProtection,
                    false,
                    null,
                    $mode === 'capacity',
                );
                $businessTable = $this->businessTable($primary);
                try {
                    $draft = $this->draft($delivery);
                    $plan = $selector->forDraft($draft);
                    $expectedRecords = 0;
                    $expectedOutbox = 0;
                    $uuidCallsBeforeFailure = $uuids->calls;
                    if ($mode === 'capacity') {
                        self::assertInstanceOf(C3CapacityConnection::class, $primary);
                        $coordinator->deliverStandalone(
                            new AuditDeliveryRequest(
                                $draft,
                                $delivery === AuditDelivery::Durable ? 'capacity-seed-key' : null,
                            ),
                        );
                        $primary->arm();
                        $expectedRecords = $this->recordCount($probe, $schema);
                        $expectedOutbox = $this->outboxCount($probe, $schema);
                        $uuidCallsBeforeFailure = $uuids->calls;
                    }
                    $invocations = 0;
                    $requests = 0;
                    $mutations = new AuditMutationTransactionCoordinator(
                        $this->transactionCoordinator($primary, $primary),
                        $coordinator,
                    );
                    try {
                        $mutations->execute(
                            $plan,
                            $this->metadata('app'),
                            function () use ($primary, $businessTable, &$invocations): OperationResult {
                                $invocations++;
                                $primary->insert($businessTable, ['id' => 1, 'value' => 'resource-rollback']);

                                return OperationResult::completed();
                            },
                            function (#[\SensitiveParameter] OperationResult $result) use (
                                &$requests,
                                $draft,
                                $delivery,
                            ): AuditDeliveryRequest {
                                $requests++;

                                return new AuditDeliveryRequest(
                                    $draft,
                                    $delivery === AuditDelivery::Durable ? 'resource-failure-key' : null,
                                );
                            },
                        );
                        self::fail($mode . ' ' . $delivery->value . ' failure was accepted');
                    } catch (AuditDeliveryException $exception) {
                        self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
                        self::assertSame('Audit delivery failed.', $exception->getMessage());
                        self::assertNull($exception->getPrevious());
                    }
                    self::assertSame(1, $invocations);
                    self::assertSame(1, $requests);
                    self::assertFalse($primary->isTransactionActive());
                    self::assertSame([], $this->businessValues($probe, $businessTable));
                    self::assertSame($expectedRecords, $this->recordCount($probe, $schema));
                    self::assertSame($expectedOutbox, $this->outboxCount($probe, $schema));
                    if ($mode === 'capacity') {
                        self::assertSame($uuidCallsBeforeFailure, $uuids->calls);
                    }
                } finally {
                    $this->dropBusinessTable($primary, $businessTable);
                    $this->cleanup($admin, $primary, $probe, $schema);
                }
            }
        }
    }

    public function testStoreIntegrityIdempotencyTransitionAndDatabaseFailuresMapToStorageUnavailable(): void
    {
        foreach (['integrity', 'transition', 'database'] as $mode) {
            foreach ([AuditDelivery::Required, AuditDelivery::Durable] as $delivery) {
                [$admin, $primary, $probe, $schema, $coordinator, $selector] = $this->fixture(
                    false,
                    false,
                    null,
                    false,
                    $mode,
                );
                $businessTable = $this->businessTable($primary);
                try {
                    $draft = $this->draft($delivery);
                    $plan = $selector->forDraft($draft);
                    $existingRecords = 0;
                    $existingOutbox = 0;
                    if ($mode === 'integrity') {
                        $coordinator->deliverStandalone(
                            new AuditDeliveryRequest(
                                $draft,
                                $delivery === AuditDelivery::Durable ? 'integrity-seed-key' : null,
                            ),
                        );
                        self::assertInstanceOf(C3StoreFailureConnection::class, $primary);
                        $primary->arm();
                        $existingRecords = $this->recordCount($probe, $schema);
                        $existingOutbox = $this->outboxCount($probe, $schema);
                    }
                    $invocations = 0;
                    $requests = 0;
                    $mutations = new AuditMutationTransactionCoordinator(
                        $this->transactionCoordinator($primary, $primary),
                        $coordinator,
                    );
                    try {
                        $mutations->execute(
                            $plan,
                            $this->metadata('app'),
                            function () use ($primary, $businessTable, &$invocations): OperationResult {
                                $invocations++;
                                $primary->insert($businessTable, ['id' => 1, 'value' => 'storage-rollback']);

                                return OperationResult::completed();
                            },
                            function (#[\SensitiveParameter] OperationResult $result) use (
                                &$requests,
                                $draft,
                                $delivery,
                            ): AuditDeliveryRequest {
                                $requests++;

                                return new AuditDeliveryRequest(
                                    $draft,
                                    $delivery === AuditDelivery::Durable ? 'storage-failure-key' : null,
                                );
                            },
                        );
                        self::fail($mode . ' ' . $delivery->value . ' failure was accepted');
                    } catch (AuditDeliveryException $exception) {
                        self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
                        self::assertSame('Audit delivery failed.', $exception->getMessage());
                        self::assertNull($exception->getPrevious());
                    }
                    self::assertSame(1, $invocations);
                    self::assertSame(1, $requests);
                    self::assertFalse($primary->isTransactionActive());
                    self::assertSame([], $this->businessValues($probe, $businessTable));
                    self::assertSame($existingRecords, $this->recordCount($probe, $schema));
                    self::assertSame($existingOutbox, $this->outboxCount($probe, $schema));
                } finally {
                    $this->dropBusinessTable($primary, $businessTable);
                    $this->cleanup($admin, $primary, $probe, $schema);
                }
            }
        }
    }

    public function testRequestFactoryWrongTypeRollsBackBusinessAndAudit(): void
    {
        foreach ([AuditDelivery::Required, AuditDelivery::Durable] as $delivery) {
            [$admin, $primary, $probe, $schema, $coordinator, $selector] = $this->fixture();
            $businessTable = $this->businessTable($primary);
            try {
                $draft = $this->draft($delivery);
                $plan = $selector->forDraft($draft);
                $invocations = 0;
                $requests = 0;
                $mutations = new AuditMutationTransactionCoordinator(
                    $this->transactionCoordinator($primary, $primary),
                    $coordinator,
                );
                try {
                    $mutations->execute(
                        $plan,
                        $this->metadata('app'),
                        function () use ($primary, $businessTable, &$invocations): OperationResult {
                            $invocations++;
                            $primary->insert($businessTable, ['id' => 1, 'value' => 'wrong-type-rollback']);

                            return OperationResult::completed();
                        },
                        function (#[\SensitiveParameter] OperationResult $result) use (&$requests): object {
                            $requests++;

                            return new \stdClass();
                        },
                    );
                    self::fail($delivery->value . ' wrong request type was accepted');
                } catch (AuditDeliveryException $exception) {
                    self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
                    self::assertSame('Audit delivery failed.', $exception->getMessage());
                    self::assertNull($exception->getPrevious());
                }
                self::assertSame(1, $invocations);
                self::assertSame(1, $requests);
                self::assertFalse($primary->isTransactionActive());
                self::assertSame([], $this->businessValues($probe, $businessTable));
                self::assertSame(0, $this->recordCount($probe, $schema));
                self::assertSame(0, $this->outboxCount($probe, $schema));
            } finally {
                $this->dropBusinessTable($primary, $businessTable);
                $this->cleanup($admin, $primary, $probe, $schema);
            }
        }
    }

    public function testDurableIdempotencyConflictMapsToStorageAndRollsBackBusiness(): void
    {
        [$admin, $primary, $probe, $schema, $coordinator, $selector] = $this->fixture();
        $businessTable = $this->businessTable($primary);
        try {
            $key = 'idempotency-conflict-key';
            $seed = $this->draft(AuditDelivery::Durable, 'seed-action');
            $coordinator->deliverStandalone(new AuditDeliveryRequest($seed, $key));
            $before = $this->outboxCount($probe, $schema);
            $conflict = $this->draft(AuditDelivery::Durable, 'conflict-action');
            $plan = $selector->forDraft($conflict);
            $requests = 0;
            $mutations = new AuditMutationTransactionCoordinator(
                $this->transactionCoordinator($primary, $primary),
                $coordinator,
            );
            try {
                $mutations->execute(
                    $plan,
                    $this->metadata('app'),
                    function () use ($primary, $businessTable): OperationResult {
                        $primary->insert($businessTable, ['id' => 1, 'value' => 'idempotency-rollback']);

                        return OperationResult::completed();
                    },
                    function (#[\SensitiveParameter] OperationResult $result) use (
                        &$requests,
                        $conflict,
                        $key,
                    ): AuditDeliveryRequest {
                        $requests++;

                        return new AuditDeliveryRequest($conflict, $key);
                    },
                );
                self::fail('idempotency conflict was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
            self::assertSame(1, $requests);
            self::assertFalse($primary->isTransactionActive());
            self::assertSame([], $this->businessValues($probe, $businessTable));
            self::assertSame(0, $this->recordCount($probe, $schema));
            self::assertSame($before, $this->outboxCount($probe, $schema));
        } finally {
            $this->dropBusinessTable($primary, $businessTable);
            $this->cleanup($admin, $primary, $probe, $schema);
        }
    }

    public function testBusinessThrowableIdentitySurvivesRollbackAndSkipsDelivery(): void
    {
        [$admin, $primary, $probe, $schema, $coordinator, $selector] = $this->fixture();
        $businessTable = $this->businessTable($primary);
        try {
            $draft = $this->draft(AuditDelivery::Required);
            $plan = $selector->forDraft($draft);
            $expected = new \RuntimeException('business-canary');
            $requests = 0;
            $mutations = new AuditMutationTransactionCoordinator(
                $this->transactionCoordinator($primary, $primary),
                $coordinator,
            );
            try {
                $mutations->execute(
                    $plan,
                    $this->metadata('app'),
                    function () use ($primary, $businessTable, $expected): OperationResult {
                        $primary->insert($businessTable, ['id' => 1, 'value' => 'business-failure']);
                        throw $expected;
                    },
                    function (#[\SensitiveParameter] OperationResult $result) use (
                        &$requests,
                        $draft,
                    ): AuditDeliveryRequest {
                        $requests++;

                        return new AuditDeliveryRequest($draft, null);
                    },
                );
                self::fail('business failure was swallowed');
            } catch (\Throwable $failure) {
                self::assertSame($expected, $failure);
                self::assertSame('business-canary', $failure->getMessage());
            }
            self::assertSame(0, $requests);
            self::assertFalse($primary->isTransactionActive());
            self::assertSame([], $this->businessValues($probe, $businessTable));
            self::assertSame(0, $this->recordCount($probe, $schema));
        } finally {
            $this->dropBusinessTable($primary, $businessTable);
            $this->cleanup($admin, $primary, $probe, $schema);
        }
    }

    public function testBusinessOperationTransactionRejectedIdentitySurvivesRollbackAndSkipsDelivery(): void
    {
        foreach ([
            OperationResult::completed(),
            OperationResult::rejected(RejectionReason::businessRule('rejected-canary')),
        ] as $rejectedResult) {
            [$admin, $primary, $probe, $schema, $coordinator, $selector] = $this->fixture();
            $businessTable = $this->businessTable($primary);
            try {
                $draft = $this->draft(AuditDelivery::Required);
                $plan = $selector->forDraft($draft);
                $expected = new OperationTransactionRejected($rejectedResult);
                $requests = 0;
                $mutations = new AuditMutationTransactionCoordinator(
                    $this->transactionCoordinator($primary, $primary),
                    $coordinator,
                );
                try {
                    $mutations->execute(
                        $plan,
                        $this->metadata('app'),
                        function () use ($primary, $businessTable, $expected): OperationResult {
                            $primary->insert($businessTable, ['id' => 1, 'value' => 'rejected-business']);
                            throw $expected;
                        },
                        function (#[\SensitiveParameter] OperationResult $result) use (
                            &$requests,
                            $draft,
                        ): AuditDeliveryRequest {
                            $requests++;

                            return new AuditDeliveryRequest($draft, null);
                        },
                    );
                    self::fail('OperationTransactionRejected business failure was swallowed');
                } catch (\Throwable $failure) {
                    self::assertSame($expected, $failure);
                }
                self::assertSame(0, $requests);
                self::assertFalse($primary->isTransactionActive());
                self::assertSame([], $this->businessValues($probe, $businessTable));
                self::assertSame(0, $this->recordCount($probe, $schema));
            } finally {
                $this->dropBusinessTable($primary, $businessTable);
                $this->cleanup($admin, $primary, $probe, $schema);
            }
        }
    }

    public function testRejectedResultAndCrossConnectionMetadataMakeZeroDeliveryCalls(): void
    {
        [$admin, $primary, $probe, $schema, $coordinator, $selector] = $this->fixture();
        $analytics = $this->connection();
        $businessTable = $this->businessTable($primary);
        try {
            $draft = $this->draft(AuditDelivery::Required);
            $plan = $selector->forDraft($draft);
            $mutations = new AuditMutationTransactionCoordinator(
                $this->transactionCoordinator($primary, $primary),
                $coordinator,
            );
            $invocations = 0;
            $requests = 0;
            $result = $mutations->execute(
                $plan,
                $this->metadata('app'),
                function () use (&$invocations): OperationResult {
                    $invocations++;

                    return OperationResult::rejected(RejectionReason::businessRule('rejected'));
                },
                function (#[\SensitiveParameter] OperationResult $result) use (
                    &$requests,
                    $draft,
                ): AuditDeliveryRequest {
                    $requests++;

                    return new AuditDeliveryRequest($draft, null);
                },
            );
            self::assertTrue($result->isRejected());
            self::assertSame(1, $invocations);
            self::assertSame(0, $requests);
            self::assertFalse($primary->isTransactionActive());

            $crossMutations = new AuditMutationTransactionCoordinator(
                $this->transactionCoordinator($primary, $analytics),
                $coordinator,
            );
            $crossInvocations = 0;
            $crossRequests = 0;
            try {
                $crossMutations->execute(
                    $plan,
                    $this->metadata('analytics'),
                    function () use (&$crossInvocations): OperationResult {
                        $crossInvocations++;

                        return OperationResult::completed();
                    },
                    function (#[\SensitiveParameter] OperationResult $result) use (
                        &$crossRequests,
                        $draft,
                    ): AuditDeliveryRequest {
                        $crossRequests++;

                        return new AuditDeliveryRequest($draft, null);
                    },
                );
                self::fail('cross-connection metadata was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::TransactionInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
            self::assertSame(0, $crossInvocations);
            self::assertSame(0, $crossRequests);
            self::assertFalse($primary->isTransactionActive());
            self::assertSame([], $this->businessValues($probe, $businessTable));
        } finally {
            $this->dropBusinessTable($primary, $businessTable);
            $analytics->close();
            $this->cleanup($admin, $primary, $probe, $schema);
        }
    }

    public function testTransactionBeginFailureMapsToValueFreeStorageUnavailable(): void
    {
        $base = $this->connection();
        $connection = new C3BeginFailureConnection($base->getParams(), $base->getDriver());
        $manager = new DoctrineDatabaseManager(
            'app',
            ['app' => ['c3_connection' => 'app']],
            static fn(array $parameters): Connection => $connection,
        );
        $runtime = new TransactionRuntime($manager, new class implements AfterCommitFailureReporter {
            public function report(AfterCommitFailure $failure): void {}
        }, new ExecutionScopeProvider());
        $transactions = new OperationTransactionCoordinator($runtime, $manager, $connection);
        $delivery = new ReflectionClass(AuditDeliveryCoordinator::class)->newInstanceWithoutConstructor();
        $mutations = new AuditMutationTransactionCoordinator($transactions, $delivery);
        $lookup = new ImmutableAuditEventCatalogLookup([FrameworkAuditEventCatalog::catalog()]);
        $selector = new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
        $draft = $this->draft(AuditDelivery::Required);
        $plan = $selector->forDraft($draft);
        $invocations = 0;
        $requests = 0;
        try {
            $mutations->execute(
                $plan,
                $this->metadata('app'),
                function () use (&$invocations): OperationResult {
                    $invocations++;

                    return OperationResult::completed();
                },
                function (#[\SensitiveParameter] OperationResult $result) use (
                    &$requests,
                    $draft,
                ): AuditDeliveryRequest {
                    $requests++;

                    return new AuditDeliveryRequest($draft, null);
                },
            );
            self::fail('transaction begin failure was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
            self::assertSame('Audit delivery failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('begin-canary', $exception->getTraceAsString());
            self::assertStringNotContainsString('begin-canary', var_export($exception, true));
            self::assertStringNotContainsString('begin-canary', serialize($exception));
        } finally {
            $base->close();
            $connection->close();
        }
        self::assertSame(0, $invocations);
        self::assertSame(0, $requests);
    }

    public function testTransactionCommitFailureMapsToValueFreeStorageUnavailableAndRollsBack(): void
    {
        [$admin, $primary, $probe, $schema, $coordinator, $selector] = $this->fixture(false, true);
        $businessTable = $this->businessTable($primary);
        try {
            $draft = $this->draft(AuditDelivery::Required);
            $plan = $selector->forDraft($draft);
            $requests = 0;
            $mutations = new AuditMutationTransactionCoordinator(
                $this->transactionCoordinator($primary, $primary),
                $coordinator,
            );
            try {
                $mutations->execute(
                    $plan,
                    $this->metadata('app'),
                    function () use ($primary, $businessTable): OperationResult {
                        $primary->insert($businessTable, ['id' => 1, 'value' => 'commit-rollback']);

                        return OperationResult::completed();
                    },
                    function (#[\SensitiveParameter] OperationResult $result) use (
                        &$requests,
                        $draft,
                    ): AuditDeliveryRequest {
                        $requests++;

                        return new AuditDeliveryRequest($draft, null);
                    },
                );
                self::fail('transaction commit failure was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
                self::assertSame('Audit delivery failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString('commit-canary', $exception->getTraceAsString());
                self::assertStringNotContainsString('commit-canary', var_export($exception, true));
                self::assertStringNotContainsString('commit-canary', serialize($exception));
            }
            self::assertSame(1, $requests);
            self::assertFalse($primary->isTransactionActive());
            self::assertSame([], $this->businessValues($probe, $businessTable));
            self::assertSame(0, $this->recordCount($probe, $schema));
        } finally {
            $this->dropBusinessTable($primary, $businessTable);
            $this->cleanup($admin, $primary, $probe, $schema);
        }
    }

    public function testSuccessfulStorePostStateDriftMapsToTransactionInvalidWithoutRetry(): void
    {
        foreach ([AuditDelivery::Required, AuditDelivery::Durable] as $delivery) {
            [$admin, $primary, $probe, $schema, $coordinator] = $this->fixture(false, false, 'standalone');
            try {
                $draft = $this->draft($delivery);
                try {
                    $coordinator->deliverStandalone(
                        new AuditDeliveryRequest(
                            $draft,
                            $delivery === AuditDelivery::Durable ? 'standalone-drift-key' : null,
                        ),
                    );
                    self::fail($delivery->value . ' post-state drift was accepted');
                } catch (AuditDeliveryException $exception) {
                    self::assertSame(AuditDeliveryFailure::TransactionInvalid, $exception->failure());
                    self::assertNull($exception->getPrevious());
                }
                self::assertSame($delivery === AuditDelivery::Required ? 1 : 0, $this->recordCount($probe, $schema));
                self::assertSame($delivery === AuditDelivery::Durable ? 1 : 0, $this->outboxCount($probe, $schema));
            } finally {
                $this->cleanup($admin, $primary, $probe, $schema);
            }
        }
    }

    public function testCurrentTransactionStorePostStateDriftMapsToTransactionInvalidWithoutRetry(): void
    {
        foreach ([AuditDelivery::Required, AuditDelivery::Durable] as $delivery) {
            [$admin, $primary, $probe, $schema, $coordinator, $selector] = $this->fixture(false, false, 'current');
            try {
                $draft = $this->draft($delivery);
                $primary->beginTransaction();
                try {
                    $coordinator->deliverInCurrentTransaction(
                        $selector->forDraft($draft),
                        new AuditDeliveryRequest(
                            $draft,
                            $delivery === AuditDelivery::Durable ? 'current-drift-key' : null,
                        ),
                    );
                    self::fail($delivery->value . ' current post-state drift was accepted');
                } catch (AuditDeliveryException $exception) {
                    self::assertSame(AuditDeliveryFailure::TransactionInvalid, $exception->failure());
                    self::assertNull($exception->getPrevious());
                }
                self::assertTrue($primary->isTransactionActive());
                self::assertSame(2, $primary->getTransactionNestingLevel());
                self::assertSame(0, $this->recordCount($probe, $schema));
                self::assertSame(0, $this->outboxCount($probe, $schema));
                $primary->rollBack();
                self::assertFalse($primary->isTransactionActive());
            } finally {
                $this->cleanup($admin, $primary, $probe, $schema);
            }
        }
    }

    /** @return array{0:Connection,1:Connection,2:Connection,3:string,4:AuditDeliveryCoordinator,5:AuditDeliverySelector,6:C3FixtureUuidGenerator} */
    private function fixture(
        bool $throwingProtection = false,
        bool $failingCommit = false,
        ?string $postStateDrift = null,
        bool $capacity = false,
        ?string $storeFailure = null,
    ): array {
        $admin = $this->connection();
        $primary = $this->connection();
        if ($failingCommit || $postStateDrift !== null || $capacity || $storeFailure !== null) {
            $params = $primary->getParams();
            $driver = $primary->getDriver();
            $primary->close();
            $primary = match (true) {
                $failingCommit => new C3CommitFailureConnection($params, $driver),
                $postStateDrift !== null => new C3PostStateDriftConnection($params, $driver, $postStateDrift),
                $capacity => new C3CapacityConnection($params, $driver),
                default => new C3StoreFailureConnection($params, $driver, $storeFailure),
            };
        }
        $probe = $this->connection();
        $schema = 'blackops_audit_delivery_' . bin2hex(random_bytes(6));
        $admin->executeStatement('CREATE SCHEMA "' . $schema . '"');
        $recordSchema = new PostgreSqlAuditSchema($schema);
        $outboxSchema = new PostgreSqlAuditOutboxSchema(new PostgreSqlMigrationSchema($schema));
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
        $protection = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            new C3FixtureAuditKeySource($throwingProtection),
            new C3FixtureNonceSource(),
            new AuditAssociatedData(),
            $records,
            $ingress,
        );
        $clock = new C3FixtureClock();
        $uuids = new C3FixtureUuidGenerator();
        $recordStore = new PostgreSqlAuditRecordStore(
            $primary,
            $recordSchema,
            $clock,
            $uuids,
            $validator,
            $finalizer,
            $partitions,
            $records,
            $protection,
            new PostgreSqlAuditStoredRecordHydrator($partitions),
        );
        $outboxStore = new PostgreSqlAuditOutboxStore(
            $primary,
            $outboxSchema,
            $clock,
            $uuids,
            $validator,
            $finalizer,
            $partitions,
            $ingress,
            $protection,
            new AuditOutboxIdempotencyKeyHasher(),
            new PostgreSqlAuditOutboxRecordHydrator(new PostgreSqlAuditProtectedIngressHydrator()),
            new AuditOutboxConfiguration(),
        );
        $selector = new AuditDeliverySelector($lookup, $validator);

        return [
            $admin,
            $primary,
            $probe,
            $schema,
            new AuditDeliveryCoordinator($primary, $selector, $recordStore, $outboxStore),
            $selector,
            $uuids,
        ];
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

    private function transactionCoordinator(Connection $primary, Connection $analytics): OperationTransactionCoordinator
    {
        $databases = new DoctrineDatabaseManager(
            'app',
            [
                'app' => ['c3_connection' => 'app'],
                'analytics' => ['c3_connection' => 'analytics'],
            ],
            static function (array $parameters) use ($primary, $analytics): Connection {
                return $parameters['c3_connection'] === 'analytics' ? $analytics : $primary;
            },
        );
        $runtime = new TransactionRuntime($databases, new class implements AfterCommitFailureReporter {
            public function report(AfterCommitFailure $failure): void {}
        }, new ExecutionScopeProvider());

        return new OperationTransactionCoordinator($runtime, $databases, $primary);
    }

    private function metadata(string $connection): OperationMetadata
    {
        return new OperationMetadata(
            'operation.c3-test',
            'BlackOps\\Tests\\C3Operation',
            'BlackOps\\Tests\\C3Value',
            'BlackOps\\Tests\\C3Handler',
            'BlackOps\\Tests\\C3Outcome',
            'sync',
            transactionConnection: $connection,
        );
    }

    private function businessTable(Connection $connection): string
    {
        $table = 'blackops_c3_business_' . bin2hex(random_bytes(6));
        $connection->executeStatement(
            'CREATE TABLE "' . $table . '" (id integer PRIMARY KEY, value varchar(128) NOT NULL)',
        );

        return $table;
    }

    private function dropBusinessTable(Connection $connection, string $table): void
    {
        try {
            $connection->executeStatement('DROP TABLE IF EXISTS "' . $table . '"');
        } catch (\Throwable) {
        }
    }

    /** @return list<string> */
    private function businessValues(Connection $connection, string $table): array
    {
        return array_map(
            static fn(mixed $value): string => (string) $value,
            $connection->fetchFirstColumn('SELECT value FROM "' . $table . '" ORDER BY id'),
        );
    }

    private function recordCount(Connection $connection, string $schema): int
    {
        return (int) $connection->fetchOne(
            'SELECT count(*) FROM ' . new PostgreSqlAuditSchema($schema)->recordsTable(),
        );
    }

    private function outboxCount(Connection $connection, string $schema): int
    {
        return (int) $connection->fetchOne(
            'SELECT count(*) FROM '
                . new PostgreSqlAuditOutboxSchema(new PostgreSqlMigrationSchema($schema))->recordsTable(),
        );
    }

    private function cleanup(Connection $admin, Connection $primary, Connection $probe, string $schema): void
    {
        try {
            if ($primary->isTransactionActive()) {
                $primary->rollBack();
            }
            $admin->executeStatement('DROP SCHEMA IF EXISTS "' . $schema . '" CASCADE');
        } finally {
            $admin->close();
            $primary->close();
            $probe->close();
        }
    }

    private function draft(AuditDelivery $delivery, string $actionId = 'action-1'): AuditRecordDraft
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        foreach ($catalog->events as $definition) {
            if ($definition->delivery !== $delivery) {
                continue;
            }
            $tuple = $definition->semanticTuples[0];
            return new AuditRecordDraft(
                new AuditRecordEvent(
                    $definition->type,
                    $definition->version,
                    $definition->category,
                    $catalog->catalogHash,
                ),
                new AuditRecordAction($actionId, $tuple->actionType, $tuple->phase),
                new AuditRecordActor(
                    new AuditTypedReference('user', 'user-1'),
                    new AuditTypedReference('execution', 'execution-1'),
                ),
                new AuditRecordResource($tuple->resourceType, 'resource-1'),
                new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
                new AuditRecordPolicy('policy-1', 1, $tuple->policyDecision, $tuple->policyReasonCode, $delivery),
                null,
                '2026-09-02T00:00:00.000000Z',
                new AuditRecordSource(AuditSourceOwner::Framework, 'test', AuditSourceRuntime::Cli, 'build-1'),
                AuditRecordCorrelation::fromArray([]),
                new AuditRecordEvidence($definition->evidenceVersion),
            );
        }

        self::fail('delivery definition unavailable');
    }
}

final class C3FixtureClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-02T00:00:01.000000Z');
    }
}

final class C3FixtureUuidGenerator implements Uuidv7Generator
{
    public int $calls = 0;

    public function generate(\DateTimeImmutable $time): string
    {
        $this->calls++;

        return '01941f29-7c00-7000-8000-' . sprintf('%012x', $this->calls);
    }
}

final class C3FixtureNonceSource implements NonceSource
{
    public function generate(): string
    {
        return str_repeat('n', 24);
    }
}

final class C3FixtureAuditKeySource implements \BlackOps\Internal\Audit\Protection\AuditProtectionKeySource
{
    public function __construct(
        private readonly bool $throwing = false,
    ) {}

    public function activeKey(
        ?AuditTypedReference $tenant,
        \BlackOps\Internal\Audit\Protection\AuditProtectionPurpose $purpose,
    ): StorageKey {
        if ($this->throwing) {
            throw \BlackOps\Internal\Audit\Protection\AuditProtectionException::unavailable();
        }

        return $purpose === \BlackOps\Internal\Audit\Protection\AuditProtectionPurpose::AuditRecord
            ? new StorageKey('record-key', str_repeat('r', 32))
            : new StorageKey('outbox-key', str_repeat('b', 32));
    }

    public function key(
        string $keyId,
        ?AuditTypedReference $tenant,
        \BlackOps\Internal\Audit\Protection\AuditProtectionPurpose $purpose,
    ): StorageKey {
        return new StorageKey($keyId, str_repeat('h', 32));
    }
}

final class C3ResultCanaryOutcome implements Outcome
{
    private string $canary = 'result-canary';
}

final class C3BeginFailureConnection extends Connection
{
    public function beginTransaction(): void
    {
        throw new \RuntimeException('begin-canary');
    }
}

final class C3CommitFailureConnection extends Connection
{
    public function commit(): void
    {
        throw new \RuntimeException('commit-canary');
    }
}

final class C3StoreFailureConnection extends Connection
{
    private bool $armed = false;

    public function __construct(
        array $params,
        \Doctrine\DBAL\Driver $driver,
        private readonly ?string $mode,
    ) {
        parent::__construct($params, $driver);
    }

    public function arm(): void
    {
        $this->armed = true;
    }

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        $row = parent::fetchAssociative($query, $params, $types);
        if ($this->mode !== 'integrity' || !$this->armed || !is_array($row)) {
            return $row;
        }
        if (str_contains($query, 'last_sequence')) {
            $row['last_sequence'] = 'integrity-canary';
        }
        if (str_contains($query, 'last_ingress_ordinal')) {
            $row['last_ingress_ordinal'] = 'integrity-canary';
        }

        return $row;
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        if ($this->mode === 'database' && str_contains($query, 'pg_advisory_xact_lock')) {
            throw new \RuntimeException('store-db-canary');
        }
        if ($this->mode === 'transition' && str_contains($query, 'SELECT committed FROM fence')) {
            return '0';
        }

        return parent::fetchOne($query, $params, $types);
    }
}

final class C3CapacityConnection extends Connection
{
    private const string MAX_SAFE_INTEGER = '9007199254740991';

    private bool $armed = false;

    public function arm(): void
    {
        $this->armed = true;
    }

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        $row = parent::fetchAssociative($query, $params, $types);
        if (!$this->armed || !is_array($row)) {
            return $row;
        }
        if (str_contains($query, 'last_sequence') && str_contains($query, 'audit_partition_state')) {
            $row['last_sequence'] = self::MAX_SAFE_INTEGER;
        }
        if (str_contains($query, 'first_sequence') && str_contains($query, 'audit_segments')) {
            $row['final_sequence'] = self::MAX_SAFE_INTEGER;
        }
        if (str_contains($query, 'last_ingress_ordinal') && str_contains($query, 'audit_outbox_partition_state')) {
            $row['last_ingress_ordinal'] = self::MAX_SAFE_INTEGER;
            $row['state_version'] = self::MAX_SAFE_INTEGER;
        }
        if (str_contains($query, 'row_count') && str_contains($query, 'audit_outbox_records')) {
            $row['row_count'] = self::MAX_SAFE_INTEGER;
            $row['min_ordinal'] = '1';
            $row['max_ordinal'] = self::MAX_SAFE_INTEGER;
            $row['tenant_mismatch_count'] = '0';
        }

        return $row;
    }

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        if (
            $this->armed
            && str_contains($query, 'record_hash')
            && str_contains($query, 'sequence = :sequence')
            && !str_contains($query, 'ORDER BY sequence')
        ) {
            $params['sequence'] = 1;
        }
        $rows = parent::fetchAllAssociative($query, $params, $types);
        if (
            $this->armed
            && $rows !== []
            && str_contains($query, 'record_hash')
            && str_contains($query, 'sequence = :sequence')
            && !str_contains($query, 'ORDER BY sequence')
        ) {
            $rows[0]['sequence'] = self::MAX_SAFE_INTEGER;
        }

        return $rows;
    }
}

final class C3PostStateDriftConnection extends Connection
{
    private bool $drifted = false;

    public function __construct(
        array $params,
        \Doctrine\DBAL\Driver $driver,
        private readonly ?string $mode,
    ) {
        parent::__construct($params, $driver);
    }

    public function commit(): void
    {
        parent::commit();
        if ($this->mode === 'standalone') {
            $this->drifted = true;
        }
    }

    public function getTransactionNestingLevel(): int
    {
        if ($this->drifted && $this->mode === 'current') {
            return 2;
        }
        if ($this->drifted && $this->mode === 'standalone') {
            return 1;
        }

        return parent::getTransactionNestingLevel();
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        $result = parent::fetchOne($query, $params, $types);
        if (
            $this->mode === 'current'
            && (str_contains($query, 'WITH new_segment') || str_contains($query, 'WITH inserted_state'))
        ) {
            $this->drifted = true;
        }

        return $result;
    }
}
