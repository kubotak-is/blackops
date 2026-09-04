<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Relay;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Delivery\AuditOutboxState;
use BlackOps\Internal\Audit\Delivery\AuditOutboxStateSnapshot;
use BlackOps\Internal\Audit\Outbox\AuditOutboxLookupResult;
use BlackOps\Internal\Audit\Outbox\AuditOutboxRecord;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreException;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreFailure;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedIngress;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Protection\AuditProtectionFailure;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Protection\AuditStoredRecord;
use BlackOps\Internal\Audit\Record\AuditLogicalRecordCandidate;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use BlackOps\Internal\Audit\Relay\AuditIngressCanonicalizer as RelayIngressCanonicalizer;
use BlackOps\Internal\Audit\Relay\AuditOutboxRelayRuntime;
use BlackOps\Internal\Audit\Relay\AuditRelayException;
use BlackOps\Internal\Audit\Relay\AuditRelayFailure;
use BlackOps\Internal\Audit\Relay\AuditRelayOutcome;
use BlackOps\Internal\Audit\Relay\AuditRelayPersistence;
use BlackOps\Internal\Audit\Storage\AuditAppendResult;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\StorageProtection\StorageKey;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:kan-defect
 */
#[AllowMockObjectsWithoutExpectations]
final class AuditOutboxRelayRuntimeTest extends TestCase
{
    /** @mago-expect lint:no-literal-password */
    private const string TOKEN = '01941f29-7c00-7000-8000-000000000009';

    private const string EXPIRES_AT = '2026-08-19T00:05:03.000000Z';

    private const string AVAILABLE_AT = '2026-08-19T00:00:03.000000Z';

    public function testRecoveryReturnsIdleWithExactlyOneCallAndNoLaterPersistence(): void
    {
        $persistence = new RelayRuntimeRecoveryPersistenceFake();
        $events = [];
        $runtime = $this->runtime(
            $persistence,
            [
                [true, false, 0],
                [true, false, 0],
            ],
            $events,
        );

        self::assertSame(AuditRelayOutcome::Idle, $runtime->recoverOne());
        self::assertSame(['recover'], $persistence->calls);
        self::assertSame(
            [
                'connection:auto',
                'connection:active',
                'connection:level',
                'connection:auto',
                'connection:active',
                'connection:level',
            ],
            $events,
        );
    }

    public function testRecoveryReturnsPendingAndPoisonedStatesWithoutClaimOrSettlement(): void
    {
        foreach ([
            [AuditOutboxState::Pending,  AuditRelayOutcome::RecoveredPending],
            [AuditOutboxState::Poisoned, AuditRelayOutcome::RecoveryPoisoned],
        ] as [$state, $expected]) {
            $context = $this->fixture();
            $persistence = new RelayRuntimeRecoveryPersistenceFake();
            $persistence->recoverResponse = AuditOutboxLookupResult::fromRecord($this->withState($context, $state));
            $events = [];
            $runtime = $this->runtime(
                $persistence,
                [
                    [true, false, 0],
                    [true, false, 0],
                ],
                $events,
                $context['configuration'],
            );

            self::assertSame($expected, $runtime->recoverOne());
            self::assertSame(['recover'], $persistence->calls);
        }
    }

    public function testRecoveryMapsEveryOutboxFailureAndUnexpectedThrowable(): void
    {
        $cases = [
            [AuditOutboxStoreFailure::TransitionConflict,  null],
            [AuditOutboxStoreFailure::ResourceUnavailable, AuditRelayFailure::ResourceUnavailable],
            [AuditOutboxStoreFailure::IntegrityInvalid,    AuditRelayFailure::StorageUnavailable],
            [AuditOutboxStoreFailure::IdempotencyConflict, AuditRelayFailure::StorageUnavailable],
            [AuditOutboxStoreFailure::StorageUnavailable,  AuditRelayFailure::StorageUnavailable],
        ];
        foreach ($cases as [$failure, $expected]) {
            $persistence = new RelayRuntimeRecoveryPersistenceFake();
            $persistence->recoverResponse = new AuditOutboxStoreException($failure);
            $events = [];
            $runtime = $this->runtime($persistence, [[true, false, 0]], $events);
            if ($expected === null) {
                self::assertSame(AuditRelayOutcome::Stale, $runtime->recoverOne());
            } else {
                $this->assertRelayFailure(fn(): AuditRelayOutcome => $runtime->recoverOne(), $expected);
            }
            self::assertSame(['recover'], $persistence->calls);
            self::assertSame(['connection:auto', 'connection:active', 'connection:level'], $events);
        }

        $persistence = new RelayRuntimeRecoveryPersistenceFake();
        $persistence->recoverResponse = new \RuntimeException('recovery-canary');
        $events = [];
        $runtime = $this->runtime($persistence, [[true, false, 0]], $events);
        $this->assertRelayFailure(
            fn(): AuditRelayOutcome => $runtime->recoverOne(),
            AuditRelayFailure::StorageUnavailable,
        );
        self::assertSame(['recover'], $persistence->calls);
        self::assertSame(['connection:auto', 'connection:active', 'connection:level'], $events);
    }

    public function testRecoveryRejectsEveryInvalidEntryConnectionStateBeforeStore(): void
    {
        foreach ([
            [false, false, 0],
            [true, true, 1],
            [true, false, 1],
            [true, false, 2],
        ] as $state) {
            $persistence = new RelayRuntimeRecoveryPersistenceFake();
            $events = [];
            $runtime = $this->runtime($persistence, [$state], $events);
            $this->assertRelayFailure(
                fn(): AuditRelayOutcome => $runtime->recoverOne(),
                AuditRelayFailure::TransactionInvalid,
            );
            self::assertSame([], $persistence->calls);
        }

        foreach (['isAutoCommit', 'isTransactionActive', 'getTransactionNestingLevel'] as $method) {
            $connection = $this->createMock(Connection::class);
            $connection->method('isAutoCommit')->willReturn(true);
            $connection->method('isTransactionActive')->willReturn(false);
            $connection->method('getTransactionNestingLevel')->willReturn(0);
            $connection
                ->expects(self::once())
                ->method($method)
                ->willReturnCallback(match ($method) {
                    'getTransactionNestingLevel' => static function (): int {
                        throw new \RuntimeException('connection-canary');
                    },
                    default => static function (): bool {
                        throw new \RuntimeException('connection-canary');
                    },
                });
            $persistence = new RelayRuntimeRecoveryPersistenceFake();
            $context = $this->fixture();
            $runtime = new AuditOutboxRelayRuntime(
                $connection,
                $persistence,
                $context['canonicalizer'],
                $context['configuration'],
            );
            $this->assertRelayFailure(
                fn(): AuditRelayOutcome => $runtime->recoverOne(),
                AuditRelayFailure::TransactionInvalid,
            );
            self::assertSame([], $persistence->calls);
        }
    }

    public function testRecoveryChecksSuccessfulPostStateBeforeInterpretingResult(): void
    {
        $context = $this->fixture();
        $persistence = new RelayRuntimeRecoveryPersistenceFake();
        $persistence->recoverResponse = $this->corruptLookup('bad', null);
        $events = [];
        $runtime = $this->runtime(
            $persistence,
            [
                [true, false, 0],
                [true, true, 1],
            ],
            $events,
            $context['configuration'],
        );

        $this->assertRelayFailure(
            fn(): AuditRelayOutcome => $runtime->recoverOne(),
            AuditRelayFailure::TransactionInvalid,
        );
        self::assertSame(['recover'], $persistence->calls);
        self::assertSame(
            [
                'connection:auto',
                'connection:active',
                'connection:level',
                'connection:auto',
                'connection:active',
            ],
            $events,
        );
    }

    public function testRecoveryContractsThrowingFoundRecordAndStateInterpretation(): void
    {
        $context = $this->fixture();
        $cases = [
            $this->corruptLookup('bad', null),
            $this->corruptLookup(true, null),
            $this->corruptLookup(true, $this->corruptRecordState('result-canary', $context)),
            AuditOutboxLookupResult::fromRecord($context['record']),
        ];
        foreach ($cases as $lookup) {
            $persistence = new RelayRuntimeRecoveryPersistenceFake();
            $persistence->recoverResponse = $lookup;
            $events = [];
            $runtime = $this->runtime(
                $persistence,
                [
                    [true, false, 0],
                    [true, false, 0],
                ],
                $events,
                $context['configuration'],
            );
            $this->assertRelayFailure(
                fn(): AuditRelayOutcome => $runtime->recoverOne(),
                AuditRelayFailure::StorageUnavailable,
            );
            self::assertSame(['recover'], $persistence->calls);
        }
    }

    public function testRelayPositiveCasesPreserveCanonicalAndFenceContract(): void
    {
        foreach ([
            ['durableSystemIngress', true,  AuditRelayOutcome::RelayedInserted],
            ['durableSystemIngress', false, AuditRelayOutcome::RelayedExisting],
            ['durableTenantIngress', true,  AuditRelayOutcome::RelayedInserted],
            ['durableTenantIngress', false, AuditRelayOutcome::RelayedExisting],
        ] as [$fixtureName, $inserted, $expectedOutcome]) {
            $events = new \ArrayObject();
            $emit = static function (string $event) use ($events): void {
                $events->append($event);
            };
            $keys = new RelayRuntimeKeySource($emit);
            $context = $this->fixture(null, $fixtureName, $keys);
            $events->exchangeArray([]);
            $append = $this->appendResult($context, $inserted);
            $relayed = $this->withState($context, AuditOutboxState::Relayed);
            $persistence = new RelayRuntimePositivePersistenceFake(
                $emit,
                AuditOutboxLookupResult::fromRecord($context['record']),
                $append,
                $relayed,
            );
            $runtime = new AuditOutboxRelayRuntime(
                $this->connection([
                    [true, false, 0],
                    [true, false, 0],
                    [true, false, 0],
                    [true, false, 0],
                    [true, false, 0],
                    [true, false, 0],
                ], $events),
                $persistence,
                $context['canonicalizer'],
                $context['configuration'],
            );

            self::assertSame($expectedOutcome, $runtime->relayOne());
            self::assertSame(
                [
                    'connection:auto',
                    'connection:active',
                    'connection:level',
                    'claim',
                    'connection:auto',
                    'connection:active',
                    'connection:level',
                    'outbox-key:decode/open',
                    'connection:auto',
                    'connection:active',
                    'connection:level',
                    'append',
                    'connection:auto',
                    'connection:active',
                    'connection:level',
                    'connection:auto',
                    'connection:active',
                    'connection:level',
                    'relay',
                    'connection:auto',
                    'connection:active',
                    'connection:level',
                ],
                $events->getArrayCopy(),
            );
            self::assertSame(['claim', 'append', 'relay'], $persistence->calls);
            self::assertSame($append, $persistence->appendResponse);
            self::assertSame($relayed, $persistence->relayResponse);
            self::assertInstanceOf(CanonicalAuditLogicalRecord::class, $persistence->appendedLogical);
            self::assertSame(
                $context['envelope']->toArray()['logicalRecord'],
                $persistence->appendedLogical->toArray(),
            );
            self::assertSame($inserted, $append->inserted());

            $row = $context['record']->toArray();
            $stored = $append->record()->toArray();
            foreach (['recordId', 'partitionId', 'tenantBinding', 'contentHash'] as $field) {
                self::assertSame($row[$field], $stored[$field], $field);
            }
            self::assertSame([
                $row['recordId'],
                $row['partitionId'],
                2,
                self::TOKEN,
            ], [
                $persistence->relayArgs[0],
                $persistence->relayArgs[2],
                $persistence->relayArgs[3],
                $persistence->relayArgs[4],
            ]);
            if ($row['tenantBinding'] === null) {
                self::assertNull($persistence->relayArgs[1]);
            } else {
                self::assertInstanceOf(AuditTypedReference::class, $persistence->relayArgs[1]);
                self::assertSame($row['tenantBinding'], $persistence->relayArgs[1]->toArray());
            }
            self::assertSame(1, $keys->activeKeyCalls);
            self::assertSame(1, $keys->keyCalls);
            self::assertSame(['audit-outbox-key'], $keys->readKeyIds);
            self::assertSame([AuditProtectionPurpose::AuditOutboxRecord], $keys->readPurposes);
            self::assertSame([str_repeat('o', 32)], $keys->readMaterials);
            $this->assertRelayPositiveNoSecret(
                $context,
                $persistence->appendedLogical,
                $events->getArrayCopy(),
                $persistence->relayArgs,
                $expectedOutcome,
            );
        }
    }

    public function testRelayClaimNotFoundReturnsIdleWithoutAnyLaterCall(): void
    {
        $events = [];
        $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
            $events[] = $event;
        });
        $context = $this->fixture(null, 'durableSystemIngress', $keys);
        $events = [];
        $persistence = new RelayRuntimeFailurePersistenceFake();
        $persistence->claimResponse = AuditOutboxLookupResult::notFound();
        $runtime = new AuditOutboxRelayRuntime(
            $this->connection([
                [true, false, 0],
                [true, false, 0],
            ], $events),
            $persistence,
            $context['canonicalizer'],
            $context['configuration'],
        );

        self::assertSame(AuditRelayOutcome::Idle, $runtime->relayOne());
        self::assertSame(['claim'], $persistence->calls);
        self::assertSame([], $persistence->laterCalls());
        self::assertSame(
            [
                'connection:auto',
                'connection:active',
                'connection:level',
                'connection:auto',
                'connection:active',
                'connection:level',
            ],
            $events,
        );
        self::assertSame(0, $keys->keyCalls);
    }

    public function testRelayClaimMapsEveryOutboxFailureAndUnexpectedWithoutLaterCall(): void
    {
        $cases = [
            [AuditOutboxStoreFailure::TransitionConflict,  null],
            [AuditOutboxStoreFailure::ResourceUnavailable, AuditRelayFailure::ResourceUnavailable],
            [AuditOutboxStoreFailure::IntegrityInvalid,    AuditRelayFailure::StorageUnavailable],
            [AuditOutboxStoreFailure::IdempotencyConflict, AuditRelayFailure::StorageUnavailable],
            [AuditOutboxStoreFailure::StorageUnavailable,  AuditRelayFailure::StorageUnavailable],
        ];

        foreach ($cases as [$failure, $expected]) {
            $events = [];
            $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
                $events[] = $event;
            });
            $context = $this->fixture(null, 'durableSystemIngress', $keys);
            $events = [];
            $persistence = new RelayRuntimeFailurePersistenceFake();
            $persistence->claimResponse = new AuditOutboxStoreException($failure);
            $runtime = new AuditOutboxRelayRuntime(
                $this->connection([[true, false, 0]], $events),
                $persistence,
                $context['canonicalizer'],
                $context['configuration'],
            );

            if ($expected === null) {
                self::assertSame(AuditRelayOutcome::Stale, $runtime->relayOne());
            } else {
                $this->assertRelayFailure(fn(): AuditRelayOutcome => $runtime->relayOne(), $expected);
            }
            self::assertSame(['claim'], $persistence->calls);
            self::assertSame([], $persistence->laterCalls());
            self::assertSame(['connection:auto', 'connection:active', 'connection:level'], $events);
            self::assertSame(0, $keys->keyCalls);
        }

        $events = [];
        $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
            $events[] = $event;
        });
        $context = $this->fixture(null, 'durableSystemIngress', $keys);
        $events = [];
        $persistence = new RelayRuntimeFailurePersistenceFake();
        $persistence->claimResponse = new \RuntimeException('claim-canary');
        $runtime = new AuditOutboxRelayRuntime(
            $this->connection([[true, false, 0]], $events),
            $persistence,
            $context['canonicalizer'],
            $context['configuration'],
        );

        $exception = $this->assertRelayFailure(
            fn(): AuditRelayOutcome => $runtime->relayOne(),
            AuditRelayFailure::StorageUnavailable,
        );
        $surfaces = [
            'print_r' => print_r($exception, true),
            'var_export' => $this->safeVarExport($exception),
            'json' => json_encode($exception, JSON_THROW_ON_ERROR),
            'serialize' => serialize($exception),
            'trace' => $exception->getTraceAsString(),
            'trace-json' => json_encode($exception->getTrace(), JSON_THROW_ON_ERROR),
        ];
        foreach ($surfaces as $name => $surface) {
            self::assertFalse(str_contains($surface, 'claim-canary'), $name . ': claim-canary');
        }
        self::assertSame(['claim'], $persistence->calls);
        self::assertSame([], $persistence->laterCalls());
        self::assertSame(['connection:auto', 'connection:active', 'connection:level'], $events);
        self::assertSame(0, $keys->keyCalls);
    }

    /** @mago-expect lint:no-literal-password */
    public function testRelayRejectsEveryCorruptClaimBeforeCanonicalizerAndSettlement(): void
    {
        $configuration = new AuditOutboxConfiguration(maxAttempts: 5);
        $cases = [
            [
                'wrong-state',
                fn(array $context): AuditOutboxLookupResult => AuditOutboxLookupResult::fromRecord($this->malformedClaimRecord($context, [
                    'state' => AuditOutboxState::Pending->value,
                ])),
            ],
            [
                'missing-record',
                fn(array $context): AuditOutboxLookupResult => $this->corruptLookup(true, null),
            ],
            [
                'corrupt-record',
                fn(array $context): AuditOutboxLookupResult => AuditOutboxLookupResult::fromRecord($this->corruptRecordState(
                    'result-canary',
                    $context,
                )),
            ],
            [
                'attempt-zero',
                fn(array $context): AuditOutboxLookupResult => AuditOutboxLookupResult::fromRecord($this->malformedClaimRecord($context, [
                    'attempt' => 0,
                ])),
            ],
            [
                'attempt-over-max',
                fn(array $context): AuditOutboxLookupResult => AuditOutboxLookupResult::fromRecord($this->malformedClaimRecord($context, [
                    'attempt' => $context['configuration']->maxAttempts + 1,
                ])),
            ],
            [
                'version-zero',
                fn(array $context): AuditOutboxLookupResult => AuditOutboxLookupResult::fromRecord($this->malformedClaimRecord($context, [
                    'version' => 0,
                ])),
            ],
            [
                'version-over-safe-max',
                fn(array $context): AuditOutboxLookupResult => AuditOutboxLookupResult::fromRecord($this->malformedClaimRecord($context, [
                    'version' => AuditValidationException::MAX_SAFE_INTEGER + 1,
                ])),
            ],
            [
                'token-null',
                fn(array $context): AuditOutboxLookupResult => AuditOutboxLookupResult::fromRecord($this->malformedClaimRecord($context, [
                    'leaseFencingToken' => null,
                ])),
            ],
            [
                'token-malformed',
                fn(array $context): AuditOutboxLookupResult => AuditOutboxLookupResult::fromRecord($this->malformedClaimRecord($context, [
                    'leaseFencingToken' => 'not-a-uuid',
                ])),
            ],
            [
                'expiry-missing',
                fn(array $context): AuditOutboxLookupResult => AuditOutboxLookupResult::fromRecord($this->malformedClaimRecord($context, [
                    'leaseExpiresAt' => null,
                ])),
            ],
            [
                'available-at-nonnull',
                fn(array $context): AuditOutboxLookupResult => AuditOutboxLookupResult::fromRecord($this->malformedClaimRecord($context, [
                    'availableAt' => self::AVAILABLE_AT,
                ])),
            ],
            [
                'record-id-malformed',
                fn(array $context): AuditOutboxLookupResult => AuditOutboxLookupResult::fromRecord($this->malformedClaimRecord(
                    $context,
                    [],
                    ['recordId' => 'not-a-uuid'],
                )),
            ],
            [
                'partition-malformed',
                fn(array $context): AuditOutboxLookupResult => AuditOutboxLookupResult::fromRecord($this->malformedClaimRecord(
                    $context,
                    [],
                    ['partitionId' => 'not-a-partition'],
                )),
            ],
            [
                'content-hash-malformed',
                fn(array $context): AuditOutboxLookupResult => AuditOutboxLookupResult::fromRecord($this->malformedClaimRecord(
                    $context,
                    [],
                    ['contentHash' => 'not-a-hash'],
                )),
            ],
            [
                'tenant-malformed',
                fn(array $context): AuditOutboxLookupResult => AuditOutboxLookupResult::fromRecord($this->malformedClaimRecord(
                    $context,
                    [],
                    ['tenantBinding' => ['type' => 'malformed']],
                )),
            ],
        ];

        foreach ($cases as [$name, $lookupFactory]) {
            $events = [];
            $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
                $events[] = $event;
            });
            $context = $this->fixture($configuration, 'durableSystemIngress', $keys);
            $events = [];
            $persistence = new RelayRuntimeFailurePersistenceFake();
            $persistence->claimResponse = $lookupFactory($context);
            $runtime = new AuditOutboxRelayRuntime(
                $this->connection([
                    [true, false, 0],
                    [true, false, 0],
                ], $events),
                $persistence,
                $context['canonicalizer'],
                $context['configuration'],
            );

            $exception = $this->assertRelayFailure(
                fn(): AuditRelayOutcome => $runtime->relayOne(),
                AuditRelayFailure::StorageUnavailable,
            );
            $row = $context['record']->toArray();
            $this->assertExceptionSurfacesOmit($exception, [
                $context['rawKey'],
                $context['record']->protectedIngress()->toArray()['protectedBody'],
                self::TOKEN,
                $row['contentHash'],
            ]);
            self::assertSame(['claim'], $persistence->calls, $name);
            self::assertSame([], $persistence->laterCalls(), $name . ': later persistence');
            self::assertSame(
                [
                    'connection:auto',
                    'connection:active',
                    'connection:level',
                    'connection:auto',
                    'connection:active',
                    'connection:level',
                ],
                $events,
                $name . ': connection calls',
            );
            self::assertSame(1, $keys->activeKeyCalls, $name . ': fixture active key only');
            self::assertSame(0, $keys->keyCalls, $name . ': canonicalizer key calls');
        }
    }

    public function testRelayMapsAppendProtectionValidationAndUnexpectedToStorageWithoutSettlement(): void
    {
        $cases = [
            ['protection', AuditProtectionException::unavailable(), null],
            ['validation', new AuditValidationException(AuditValidationFailure::SemanticInvalid), null],
            ['unexpected', new \RuntimeException('append-canary'), 'append-canary'],
        ];

        foreach ($cases as [$name, $failure, $canary]) {
            $events = [];
            $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
                $events[] = $event;
            });
            $context = $this->fixture(null, 'durableSystemIngress', $keys);
            $events = [];
            $persistence = new RelayRuntimeFailurePersistenceFake();
            $persistence->claimResponse = AuditOutboxLookupResult::fromRecord($context['record']);
            $persistence->appendResponse = $failure;
            $runtime = new AuditOutboxRelayRuntime(
                $this->connection([
                    [true, false, 0],
                    [true, false, 0],
                    [true, false, 0],
                ], $events),
                $persistence,
                $context['canonicalizer'],
                $context['configuration'],
            );

            $exception = $this->assertRelayFailure(
                fn(): AuditRelayOutcome => $runtime->relayOne(),
                AuditRelayFailure::StorageUnavailable,
            );
            self::assertSame(['claim', 'append'], $persistence->calls, $name);
            self::assertSame([], $persistence->settlementCalls(), $name . ': settlement');
            self::assertInstanceOf(CanonicalAuditLogicalRecord::class, $persistence->appendedLogical);
            self::assertSame(
                $context['envelope']->toArray()['logicalRecord'],
                $persistence->appendedLogical->toArray(),
                $name . ': canonicalizer completed',
            );
            self::assertSame(1, $keys->activeKeyCalls, $name . ': fixture active key only');
            self::assertSame(1, $keys->keyCalls, $name . ': canonicalizer key');
            $this->assertExceptionSurfacesOmit($exception, array_merge(
                [
                    $context['rawKey'],
                    $context['record']->protectedIngress()->toArray()['protectedBody'],
                    self::TOKEN,
                    $context['record']->toArray()['contentHash'],
                ],
                $keys->readKeyIds,
                $keys->readMaterials,
            ));
            self::assertSame(
                [
                    'connection:auto',
                    'connection:active',
                    'connection:level',
                    'connection:auto',
                    'connection:active',
                    'connection:level',
                    'outbox-key:decode/open',
                    'connection:auto',
                    'connection:active',
                    'connection:level',
                ],
                $events,
                $name . ': order',
            );
            if ($canary !== null) {
                $surfaces = [
                    'print_r' => print_r($exception, true),
                    'var_export' => $this->safeVarExport($exception),
                    'json' => json_encode($exception, JSON_THROW_ON_ERROR),
                    'serialize' => serialize($exception),
                    'trace' => $exception->getTraceAsString(),
                    'trace-json' => json_encode($exception->getTrace(), JSON_THROW_ON_ERROR),
                ];
                foreach ($surfaces as $surfaceName => $surface) {
                    self::assertFalse(str_contains($surface, $canary), $name . ': ' . $surfaceName);
                }
            }
        }
    }

    public function testActualProtectionFailureSettlesRetryBelowMaxAndPoisonAtMax(): void
    {
        $configuration = new AuditOutboxConfiguration(maxAttempts: 2);
        $cases = [
            ['retry',  1, AuditRelayOutcome::Retried,  AuditOutboxState::Pending,  1],
            ['poison', 2, AuditRelayOutcome::Poisoned, AuditOutboxState::Poisoned, 2],
        ];

        foreach ($cases as [$name, $attempt, $expectedOutcome, $settlementState, $settlementAttempt]) {
            $events = [];
            $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
                $events[] = $event;
            });
            $context = $this->fixture($configuration, 'durableSystemIngress', $keys);
            $events = [];
            $corruptBody = $this->mutatedProtectedBody($context);
            $claimed = $this->malformedClaimRecord(
                $context,
                ['attempt' => $attempt],
                [
                    'protectedBody' => $corruptBody,
                ],
            );
            try {
                $context['canonicalizer']->canonicalize($claimed);
                self::fail($name . ': the actual canonicalizer unexpectedly accepted corrupt protection');
            } catch (AuditProtectionException $failure) {
                self::assertSame(
                    AuditProtectionFailure::ProtectionFailed,
                    $failure->failure(),
                    $name . ': actual exception',
                );
                $this->assertThrowableSurfacesOmit($failure, array_merge(
                    [
                        $context['rawKey'],
                        $context['record']->protectedIngress()->toArray()['protectedBody'],
                        $corruptBody,
                        self::TOKEN,
                        $context['record']->toArray()['contentHash'],
                    ],
                    $keys->readKeyIds,
                    $keys->readMaterials,
                ));
            }
            self::assertSame(1, $keys->keyCalls, $name . ': direct key/decode reachability');
            $keys->keyCalls = 0;
            $keys->readKeyIds = [];
            $keys->readPurposes = [];
            $keys->readMaterials = [];
            $events = [];
            $persistence = new RelayRuntimeFailurePersistenceFake(static function (string $event) use (&$events): void {
                $events[] = $event;
            });
            $persistence->claimResponse = AuditOutboxLookupResult::fromRecord($claimed);
            if ($settlementState === AuditOutboxState::Pending) {
                $persistence->retryResponse = $this->withState($context, $settlementState, $settlementAttempt);
                $settlement = 'retry';
            } else {
                $persistence->poisonResponse = $this->withState($context, $settlementState, $settlementAttempt);
                $settlement = 'poison';
            }
            $runtime = new AuditOutboxRelayRuntime(
                $this->connection([
                    [true, false, 0],
                    [true, false, 0],
                    [true, false, 0],
                    [true, false, 0],
                ], $events),
                $persistence,
                $context['canonicalizer'],
                $context['configuration'],
            );

            $outcome = $runtime->relayOne();
            self::assertSame($expectedOutcome, $outcome, $name);
            self::assertSame(['claim', $settlement], $persistence->calls, $name . ': calls');
            self::assertSame([$settlement], $persistence->settlementCalls(), $name . ': one settlement');
            self::assertNull($persistence->appendedLogical, $name . ': append skipped');
            self::assertSame(1, $keys->activeKeyCalls, $name . ': fixture active key only');
            self::assertSame(1, $keys->keyCalls, $name . ': actual canonicalizer decode');
            self::assertSame(['audit-outbox-key'], $keys->readKeyIds, $name . ': key id');
            self::assertSame([AuditProtectionPurpose::AuditOutboxRecord], $keys->readPurposes, $name . ': key purpose');
            self::assertSame([str_repeat('o', 32)], $keys->readMaterials, $name . ': key material');
            self::assertSame(
                $this->relayEvents([
                    'connection',
                    'claim',
                    'connection',
                    'outbox-key:decode/open',
                    'connection',
                    $settlement,
                    'connection',
                ]),
                $events,
                $name . ': exact order',
            );
            $this->assertFenceArgs($claimed, $persistence->{$settlement . 'Args'});
            $this->assertSettledState($persistence->{$settlement . 'Response'}, $settlementState, $settlementAttempt);
            $this->assertSuccessSurfacesOmit(
                $context,
                null,
                $events,
                $outcome,
                array_merge([$corruptBody], $keys->readKeyIds, $keys->readMaterials),
            );
        }
    }

    public function testActualValidationFailurePoisonSettlesAtBothAttempts(): void
    {
        $configuration = new AuditOutboxConfiguration(maxAttempts: 2);
        foreach ([1, 2] as $attempt) {
            $events = [];
            $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
                $events[] = $event;
            });
            $context = $this->fixture($configuration, 'durableSystemIngress', $keys);
            $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([]));
            try {
                $validator->validateLogical(
                    new AuditLogicalRecordCandidate($context['envelope']->toArray()['logicalRecord']),
                );
                self::fail('the independent validator unexpectedly accepted the fixture');
            } catch (AuditValidationException $exception) {
                self::assertSame(AuditValidationFailure::SemanticInvalid, $exception->failure());
            }
            $canonicalizer = new RelayIngressCanonicalizer($context['protection'], $validator);
            $events = [];
            $claimed = $this->malformedClaimRecord($context, ['attempt' => $attempt]);
            try {
                $canonicalizer->canonicalize($claimed);
                self::fail(
                    'attempt ' . $attempt . ': the actual canonicalizer unexpectedly accepted invalid validation',
                );
            } catch (AuditValidationException $failure) {
                self::assertSame(AuditValidationFailure::SemanticInvalid, $failure->failure());
                $this->assertThrowableSurfacesOmit($failure, array_merge(
                    [
                        $context['rawKey'],
                        $context['record']->protectedIngress()->toArray()['protectedBody'],
                        self::TOKEN,
                        $context['record']->toArray()['contentHash'],
                    ],
                    $keys->readKeyIds,
                    $keys->readMaterials,
                ));
            }
            self::assertSame(1, $keys->keyCalls, 'attempt ' . $attempt . ': direct key/decode reachability');
            $keys->keyCalls = 0;
            $keys->readKeyIds = [];
            $keys->readPurposes = [];
            $keys->readMaterials = [];
            $events = [];
            $persistence = new RelayRuntimeFailurePersistenceFake(static function (string $event) use (&$events): void {
                $events[] = $event;
            });
            $persistence->claimResponse = AuditOutboxLookupResult::fromRecord($claimed);
            $persistence->poisonResponse = $this->withState($context, AuditOutboxState::Poisoned, $attempt);
            $runtime = new AuditOutboxRelayRuntime(
                $this->connection([
                    [true, false, 0],
                    [true, false, 0],
                    [true, false, 0],
                    [true, false, 0],
                ], $events),
                $persistence,
                $canonicalizer,
                $context['configuration'],
            );

            $outcome = $runtime->relayOne();
            self::assertSame(AuditRelayOutcome::Poisoned, $outcome, 'attempt ' . $attempt);
            self::assertSame(['claim', 'poison'], $persistence->calls, 'attempt ' . $attempt . ': calls');
            self::assertSame(['poison'], $persistence->settlementCalls(), 'attempt ' . $attempt . ': one poison');
            self::assertNull($persistence->appendedLogical, 'attempt ' . $attempt . ': append skipped');
            self::assertSame(1, $keys->activeKeyCalls, 'attempt ' . $attempt . ': fixture active key only');
            self::assertSame(1, $keys->keyCalls, 'attempt ' . $attempt . ': actual canonicalizer decode');
            self::assertSame(['audit-outbox-key'], $keys->readKeyIds, 'attempt ' . $attempt . ': key id');
            self::assertSame(
                [AuditProtectionPurpose::AuditOutboxRecord],
                $keys->readPurposes,
                'attempt ' . $attempt . ': key purpose',
            );
            self::assertSame([str_repeat('o', 32)], $keys->readMaterials, 'attempt ' . $attempt . ': key material');
            self::assertSame(
                $this->relayEvents([
                    'connection',
                    'claim',
                    'connection',
                    'outbox-key:decode/open',
                    'connection',
                    'poison',
                    'connection',
                ]),
                $events,
                'attempt ' . $attempt . ': exact order',
            );
            $this->assertFenceArgs($claimed, $persistence->poisonArgs);
            $this->assertSettledState($persistence->poisonResponse, AuditOutboxState::Poisoned, $attempt);
            $this->assertSuccessSurfacesOmit(
                $context,
                null,
                $events,
                $outcome,
                array_merge($keys->readKeyIds, $keys->readMaterials),
            );
        }
    }

    public function testEveryAppendStoreFailureUsesTheExactRetryOrPoisonSettlement(): void
    {
        $configuration = new AuditOutboxConfiguration(maxAttempts: 2);
        $failures = [
            [AuditStoreFailure::ResourceUnavailable, true],
            [AuditStoreFailure::StorageUnavailable,  true],
            [AuditStoreFailure::IdempotencyConflict, false],
            [AuditStoreFailure::SegmentConflict,     false],
            [AuditStoreFailure::IntegrityInvalid,    false],
        ];

        foreach ($failures as [$failure, $retryable]) {
            foreach ([1, 2] as $attempt) {
                $events = [];
                $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
                    $events[] = $event;
                });
                $context = $this->fixture($configuration, 'durableSystemIngress', $keys);
                $events = [];
                $claimed = $this->malformedClaimRecord($context, ['attempt' => $attempt]);
                $appendFailure = new AuditStoreException($failure);
                $persistence = new RelayRuntimeFailurePersistenceFake(static function (string $event) use (
                    &$events,
                ): void {
                    $events[] = $event;
                });
                $persistence->claimResponse = AuditOutboxLookupResult::fromRecord($claimed);
                $persistence->appendResponse = $appendFailure;
                $settlement = $retryable && $attempt < $configuration->maxAttempts ? 'retry' : 'poison';
                $expectedOutcome = $settlement === 'retry' ? AuditRelayOutcome::Retried : AuditRelayOutcome::Poisoned;
                $settlementState = $settlement === 'retry' ? AuditOutboxState::Pending : AuditOutboxState::Poisoned;
                $persistence->{$settlement . 'Response'} = $this->withState($context, $settlementState, $attempt);
                $runtime = new AuditOutboxRelayRuntime(
                    $this->connection([
                        [true, false, 0],
                        [true, false, 0],
                        [true, false, 0],
                        [true, false, 0],
                        [true, false, 0],
                    ], $events),
                    $persistence,
                    $context['canonicalizer'],
                    $context['configuration'],
                );

                $outcome = $runtime->relayOne();
                $label = $failure->value . ' attempt ' . $attempt;
                self::assertSame($expectedOutcome, $outcome, $label);
                self::assertSame(['claim', 'append', $settlement], $persistence->calls, $label . ': calls');
                self::assertSame($appendFailure, $persistence->appendThrown, $label . ': typed append failure');
                $this->assertThrowableSurfacesOmit($appendFailure, array_merge(
                    [
                        $context['rawKey'],
                        $context['record']->protectedIngress()->toArray()['protectedBody'],
                        self::TOKEN,
                        $context['record']->toArray()['contentHash'],
                    ],
                    $keys->readKeyIds,
                    $keys->readMaterials,
                ));
                self::assertSame([$settlement], $persistence->settlementCalls(), $label . ': one settlement');
                self::assertInstanceOf(
                    CanonicalAuditLogicalRecord::class,
                    $persistence->appendedLogical,
                    $label . ': canonicalized',
                );
                self::assertSame(
                    $context['envelope']->toArray()['logicalRecord'],
                    $persistence->appendedLogical->toArray(),
                    $label . ': canonical logical value',
                );
                self::assertSame(1, $keys->activeKeyCalls, $label . ': fixture active key only');
                self::assertSame(1, $keys->keyCalls, $label . ': actual canonicalizer decode');
                self::assertSame(['audit-outbox-key'], $keys->readKeyIds, $label . ': key id');
                self::assertSame(
                    [AuditProtectionPurpose::AuditOutboxRecord],
                    $keys->readPurposes,
                    $label . ': key purpose',
                );
                self::assertSame([str_repeat('o', 32)], $keys->readMaterials, $label . ': key material');
                self::assertSame(
                    $this->relayEvents([
                        'connection',
                        'claim',
                        'connection',
                        'outbox-key:decode/open',
                        'connection',
                        'append',
                        'connection',
                        $settlement,
                        'connection',
                    ]),
                    $events,
                    $label . ': exact order',
                );
                $this->assertFenceArgs($claimed, $persistence->{$settlement . 'Args'});
                $this->assertSettledState($persistence->{$settlement . 'Response'}, $settlementState, $attempt);
                $this->assertSuccessSurfacesOmit(
                    $context,
                    $persistence->appendedLogical,
                    $events,
                    $outcome,
                    array_merge($keys->readKeyIds, $keys->readMaterials),
                );
            }
        }
    }

    public function testCommittedResultBindingAndInspectionFailuresPoisonExactlyOnce(): void
    {
        $cases = [
            [
                'record-id',
                fn(array $context): AuditAppendResult => $this->mismatchedAppendResult($context, 'recordId'),
                null,
            ],
            [
                'partition',
                fn(array $context): AuditAppendResult => $this->mismatchedAppendResult($context, 'partitionId'),
                null,
            ],
            [
                'tenant',
                fn(array $context): AuditAppendResult => $this->mismatchedAppendResult($context, 'tenantBinding'),
                null,
            ],
            [
                'content-hash',
                fn(array $context): AuditAppendResult => $this->mismatchedAppendResult($context, 'contentHash'),
                null,
            ],
            [
                'record-inspection',
                fn(array $context): AuditAppendResult => $this->malformedAppendResult('record'),
                'append-record-canary',
            ],
            [
                'stored-array-inspection',
                fn(array $context): AuditAppendResult => $this->malformedAppendResult('stored'),
                'stored-array-canary',
            ],
            [
                'inserted-inspection',
                fn(array $context): AuditAppendResult => $this->malformedAppendResult('inserted', $context),
                'append-inserted-canary',
            ],
        ];

        foreach ($cases as [$name, $appendFactory, $canary]) {
            $events = [];
            $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
                $events[] = $event;
            });
            $context = $this->fixture(null, 'durableSystemIngress', $keys);
            $events = [];
            $claimed = $context['record'];
            $append = $appendFactory($context);
            if ($canary !== null) {
                self::assertIsString($canary);
                $inspectionFailure = $this->assertActualInspectionFailure($append, $name);
                $this->assertThrowableSurfacesOmit(
                    $inspectionFailure,
                    [
                        $context['rawKey'],
                        $context['record']->protectedIngress()->toArray()['protectedBody'],
                        self::TOKEN,
                        $context['record']->toArray()['contentHash'],
                        $canary,
                    ],
                    $name,
                );
            }
            $persistence = new RelayRuntimeFailurePersistenceFake(static function (string $event) use (&$events): void {
                $events[] = $event;
            });
            $persistence->claimResponse = AuditOutboxLookupResult::fromRecord($claimed);
            $persistence->appendResponse = $append;
            $persistence->poisonResponse = $this->withState($context, AuditOutboxState::Poisoned, 1);
            $runtime = new AuditOutboxRelayRuntime(
                $this->connection([
                    [true, false, 0],
                    [true, false, 0],
                    [true, false, 0],
                    [true, false, 0],
                    [true, false, 0],
                    [true, false, 0],
                ], $events),
                $persistence,
                $context['canonicalizer'],
                $context['configuration'],
            );

            $outcome = $runtime->relayOne();
            self::assertSame(AuditRelayOutcome::Poisoned, $outcome, $name);
            self::assertSame(['claim', 'append', 'poison'], $persistence->calls, $name . ': calls');
            self::assertSame(['poison'], $persistence->settlementCalls(), $name . ': one poison');
            self::assertSame(1, $keys->keyCalls, $name . ': actual canonicalizer decode');
            self::assertSame(['audit-outbox-key'], $keys->readKeyIds, $name . ': key id');
            self::assertSame([AuditProtectionPurpose::AuditOutboxRecord], $keys->readPurposes, $name . ': key purpose');
            self::assertSame([str_repeat('o', 32)], $keys->readMaterials, $name . ': key material');
            self::assertSame(
                $this->relayEvents([
                    'connection',
                    'claim',
                    'connection',
                    'outbox-key:decode/open',
                    'connection',
                    'append',
                    'connection',
                    'connection',
                    'poison',
                    'connection',
                ]),
                $events,
                $name . ': exact order',
            );
            $this->assertFenceArgs($claimed, $persistence->poisonArgs);
            $this->assertSettledState($persistence->poisonResponse, AuditOutboxState::Poisoned, 1);
            $extraSecrets = $canary === null ? [] : [$canary];
            $this->assertSuccessSurfacesOmit($context, $persistence->appendedLogical, $events, $outcome, $extraSecrets);
            if ($canary === null) {
                $claimedValue = $claimed->toArray();
                $storedValue = $append->record()->toArray();
                $field = match ($name) {
                    'record-id' => 'recordId',
                    'partition' => 'partitionId',
                    'tenant' => 'tenantBinding',
                    'content-hash' => 'contentHash',
                    default => throw new \LogicException('unexpected binding case'),
                };
                self::assertNotSame($claimedValue[$field], $storedValue[$field], $name . ': targeted binding mutation');
                foreach (['recordId', 'partitionId', 'tenantBinding', 'contentHash'] as $binding) {
                    if ($binding !== $field) {
                        self::assertSame(
                            $claimedValue[$binding],
                            $storedValue[$binding],
                            $name . ': untouched ' . $binding,
                        );
                    }
                }
            }
        }
    }

    public function testSettlementFailureMappingSupersedesEachSenderPath(): void
    {
        $failureCases = [
            ['transition-conflict', AuditOutboxStoreFailure::TransitionConflict, null, AuditRelayOutcome::Stale],
            [
                'resource-unavailable',
                AuditOutboxStoreFailure::ResourceUnavailable,
                AuditRelayFailure::ResourceUnavailable,
                null,
            ],
            [
                'integrity-invalid',
                AuditOutboxStoreFailure::IntegrityInvalid,
                AuditRelayFailure::StorageUnavailable,
                null,
            ],
            [
                'idempotency-conflict',
                AuditOutboxStoreFailure::IdempotencyConflict,
                AuditRelayFailure::StorageUnavailable,
                null,
            ],
            [
                'storage-unavailable',
                AuditOutboxStoreFailure::StorageUnavailable,
                AuditRelayFailure::StorageUnavailable,
                null,
            ],
            ['unexpected', null, AuditRelayFailure::StorageUnavailable, null],
        ];

        foreach (['retry', 'poison', 'relay'] as $path) {
            foreach ($failureCases as [$failureName, $storeFailure, $expectedFailure, $expectedOutcome]) {
                $label = $path . '/' . $failureName;
                $configuration = new AuditOutboxConfiguration(maxAttempts: 2);
                $events = [];
                $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
                    $events[] = $event;
                });
                $context = $this->fixture($configuration, 'durableSystemIngress', $keys);
                $events = [];
                $claimed = $context['record'];
                $canonicalizer = $context['canonicalizer'];
                $corruptBody = null;
                $append = null;
                $initiatingOutcome = match ($path) {
                    'retry' => AuditRelayOutcome::Retried,
                    'poison' => AuditRelayOutcome::Poisoned,
                    'relay' => AuditRelayOutcome::RelayedInserted,
                };

                if ($path === 'retry') {
                    $corruptBody = $this->mutatedProtectedBody($context);
                    $claimed = $this->malformedClaimRecord(
                        $context,
                        ['attempt' => 1],
                        ['protectedBody' => $corruptBody],
                    );
                    try {
                        $canonicalizer->canonicalize($claimed);
                        self::fail($label . ': actual protection sender failure did not occur');
                    } catch (AuditProtectionException $senderFailure) {
                        self::assertSame(
                            AuditProtectionFailure::ProtectionFailed,
                            $senderFailure->failure(),
                            $label . ': actual protection failure',
                        );
                        $this->assertThrowableSurfacesOmit(
                            $senderFailure,
                            array_merge(
                                [
                                    $context['rawKey'],
                                    $context['record']->protectedIngress()->toArray()['protectedBody'],
                                    $corruptBody,
                                    self::TOKEN,
                                    $context['record']->toArray()['contentHash'],
                                ],
                                $keys->readKeyIds,
                                $keys->readMaterials,
                            ),
                            $label . ': sender failure',
                        );
                    }
                    self::assertSame(1, $keys->keyCalls, $label . ': direct key/decode reachability');
                    $keys->keyCalls = 0;
                    $keys->readKeyIds = [];
                    $keys->readPurposes = [];
                    $keys->readMaterials = [];
                    $events = [];
                } elseif ($path === 'poison') {
                    $canonicalizer = new RelayIngressCanonicalizer(
                        $context['protection'],
                        new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([])),
                    );
                    try {
                        $canonicalizer->canonicalize($claimed);
                        self::fail($label . ': actual validation sender failure did not occur');
                    } catch (AuditValidationException $senderFailure) {
                        self::assertSame(
                            AuditValidationFailure::SemanticInvalid,
                            $senderFailure->failure(),
                            $label . ': actual validation failure',
                        );
                        $this->assertThrowableSurfacesOmit(
                            $senderFailure,
                            array_merge(
                                [
                                    $context['rawKey'],
                                    $context['record']->protectedIngress()->toArray()['protectedBody'],
                                    self::TOKEN,
                                    $context['record']->toArray()['contentHash'],
                                ],
                                $keys->readKeyIds,
                                $keys->readMaterials,
                            ),
                            $label . ': sender failure',
                        );
                    }
                    self::assertSame(1, $keys->keyCalls, $label . ': direct key/decode reachability');
                    $keys->keyCalls = 0;
                    $keys->readKeyIds = [];
                    $keys->readPurposes = [];
                    $keys->readMaterials = [];
                    $events = [];
                } else {
                    $append = $this->appendResult($context, true);
                    $claimedValue = $claimed->toArray();
                    $storedValue = $append->record()->toArray();
                    foreach (['recordId', 'partitionId', 'tenantBinding', 'contentHash'] as $binding) {
                        self::assertSame(
                            $claimedValue[$binding],
                            $storedValue[$binding],
                            $label . ': matching ' . $binding,
                        );
                    }
                    self::assertTrue($append->inserted(), $label . ': matching inserted result');
                }

                $settlementFailure = $storeFailure === null
                    ? new \RuntimeException($label . '-settlement-canary')
                    : new AuditOutboxStoreException($storeFailure);
                $persistence = new RelayRuntimeFailurePersistenceFake(static function (string $event) use (
                    &$events,
                ): void {
                    $events[] = $event;
                });
                $persistence->claimResponse = AuditOutboxLookupResult::fromRecord($claimed);
                if ($path === 'retry') {
                    $persistence->retryResponse = $settlementFailure;
                } elseif ($path === 'poison') {
                    $persistence->poisonResponse = $settlementFailure;
                } else {
                    self::assertInstanceOf(AuditAppendResult::class, $append);
                    $persistence->appendResponse = $append;
                    $persistence->relayResponse = $settlementFailure;
                }

                $connectionStates = $path === 'relay'
                    ? array_fill(0, 5, [true, false, 0])
                    : array_fill(0, 3, [true, false, 0]);
                $runtime = new AuditOutboxRelayRuntime(
                    $this->connection($connectionStates, $events),
                    $persistence,
                    $canonicalizer,
                    $configuration,
                );
                $secrets = [
                    $context['rawKey'],
                    $context['record']->protectedIngress()->toArray()['protectedBody'],
                    self::TOKEN,
                    $context['record']->toArray()['contentHash'],
                ];
                if ($corruptBody !== null) {
                    $secrets[] = $corruptBody;
                }
                $secrets = array_merge($secrets, $keys->readKeyIds, $keys->readMaterials);
                $canary = $storeFailure === null ? $settlementFailure->getMessage() : null;
                if ($canary !== null) {
                    $secrets[] = $canary;
                }

                if ($expectedOutcome !== null) {
                    $outcome = $runtime->relayOne();
                    self::assertSame($expectedOutcome, $outcome, $label . ': outcome');
                    self::assertNotSame($initiatingOutcome, $outcome, $label . ': settlement supersedes sender result');
                    $secrets = array_merge($secrets, $keys->readKeyIds, $keys->readMaterials);
                    $this->assertSuccessSurfacesOmit(
                        $context,
                        $path === 'relay' ? $persistence->appendedLogical : null,
                        $events,
                        $outcome,
                        $secrets,
                    );
                } else {
                    $exception = $this->assertRelayFailure(
                        fn(): AuditRelayOutcome => $runtime->relayOne(),
                        $expectedFailure,
                    );
                    $secrets = array_merge($secrets, $keys->readKeyIds, $keys->readMaterials);
                    $this->assertExceptionSurfacesOmit($exception, $secrets);
                }

                $settlementThrown = $persistence->settlementThrown;
                self::assertNotNull($settlementThrown, $label . ': settlement throwable reached fake');
                if ($storeFailure !== null) {
                    self::assertInstanceOf(
                        AuditOutboxStoreException::class,
                        $settlementThrown,
                        $label . ': typed class',
                    );
                    self::assertSame($storeFailure, $settlementThrown->failure(), $label . ': typed failure');
                    $this->assertThrowableSurfacesOmit($settlementThrown, $secrets, $label . ': settlement failure');
                } else {
                    self::assertInstanceOf(\RuntimeException::class, $settlementThrown, $label . ': unexpected class');
                    self::assertSame($canary, $settlementThrown->getMessage(), $label . ': unique canary');
                }
                self::assertSame([$path], $persistence->settlementCalls(), $label . ': one settlement');
                self::assertSame(
                    $path === 'relay' ? ['claim', 'append', 'relay'] : ['claim', $path],
                    $persistence->calls,
                    $label . ': exact calls and no later call',
                );
                self::assertSame(1, $keys->activeKeyCalls, $label . ': fixture active key only');
                self::assertSame(1, $keys->keyCalls, $label . ': runtime key/decode reachability');
                self::assertSame(['audit-outbox-key'], $keys->readKeyIds, $label . ': key id');
                self::assertSame(
                    [AuditProtectionPurpose::AuditOutboxRecord],
                    $keys->readPurposes,
                    $label . ': key purpose',
                );
                self::assertSame([str_repeat('o', 32)], $keys->readMaterials, $label . ': key material');
                if ($path === 'relay') {
                    self::assertInstanceOf(CanonicalAuditLogicalRecord::class, $persistence->appendedLogical);
                    self::assertSame(
                        $context['envelope']->toArray()['logicalRecord'],
                        $persistence->appendedLogical->toArray(),
                        $label . ': canonical append reached',
                    );
                } else {
                    self::assertNull($persistence->appendedLogical, $label . ': append skipped');
                }
                self::assertSame(
                    $this->relayEvents(
                        $path === 'relay'
                            ? [
                                'connection',
                                'claim',
                                'connection',
                                'outbox-key:decode/open',
                                'connection',
                                'append',
                                'connection',
                                'connection',
                                'relay',
                            ]
                            : [
                                'connection',
                                'claim',
                                'connection',
                                'outbox-key:decode/open',
                                'connection',
                                $path,
                            ],
                    ),
                    $events,
                    $label . ': exact order and no post-settlement check',
                );
                $settlementArgs = match ($path) {
                    'retry' => $persistence->retryArgs,
                    'poison' => $persistence->poisonArgs,
                    'relay' => $persistence->relayArgs,
                };
                $this->assertFenceArgs($claimed, $settlementArgs);
            }
        }
    }

    public function testRelayEntryConnectionFailuresStopBeforeClaim(): void
    {
        $cases = [
            ['auto-commit-false', [false, false, 0], null, ['connection:auto']],
            ['active-level1', [true, true, 1], null, ['connection:auto', 'connection:active']],
            ['inactive-level1', [true, false, 1], null, ['connection']],
            ['level2', [true, false, 2], null, ['connection']],
            ['auto-commit-throws', null, 'isAutoCommit', ['connection:auto']],
            ['active-throws', null, 'isTransactionActive', ['connection:auto', 'connection:active']],
            ['level-throws', null, 'getTransactionNestingLevel', ['connection']],
        ];

        foreach ($cases as [$name, $state, $throwMethod, $expectedEvents]) {
            $events = [];
            $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
                $events[] = $event;
            });
            $context = $this->fixture(null, 'durableSystemIngress', $keys);
            $events = [];
            $persistence = new RelayRuntimeFailurePersistenceFake();
            $canary = $throwMethod === null ? null : $name . '-connection-canary';
            $connection = $throwMethod === null
                ? $this->connection([$state], $events)
                : $this->throwingConnection($throwMethod, $canary, $events);
            $runtime = new AuditOutboxRelayRuntime(
                $connection,
                $persistence,
                $context['canonicalizer'],
                $context['configuration'],
            );

            $exception = $this->assertRelayFailure(
                fn(): AuditRelayOutcome => $runtime->relayOne(),
                AuditRelayFailure::TransactionInvalid,
            );
            $secrets = [
                $context['rawKey'],
                $context['record']->protectedIngress()->toArray()['protectedBody'],
                self::TOKEN,
                $context['record']->toArray()['contentHash'],
            ];
            if ($canary !== null) {
                $secrets[] = $canary;
            }
            $this->assertExceptionSurfacesOmit($exception, $secrets);
            self::assertSame([], $persistence->calls, $name . ': claim and later calls');
            self::assertSame([], $persistence->laterCalls(), $name . ': later calls');
            self::assertSame([], $persistence->settlementCalls(), $name . ': settlement');
            self::assertSame(0, $keys->keyCalls, $name . ': key/decode');
            self::assertSame(1, $keys->activeKeyCalls, $name . ': fixture active key only');
            self::assertSame($this->relayEvents($expectedEvents), $events, $name . ': exact Connection order');
        }
    }

    public function testRelayPostClaimConnectionDriftPrecedesMalformedClaimInterpretation(): void
    {
        $events = [];
        $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
            $events[] = $event;
        });
        $context = $this->fixture(null, 'durableSystemIngress', $keys);
        $events = [];
        $canary = 'post-claim-state-canary';
        $claimed = $this->corruptRecordState($canary, $context);
        $persistence = new RelayRuntimeFailurePersistenceFake(static function (string $event) use (&$events): void {
            $events[] = $event;
        });
        $persistence->claimResponse = AuditOutboxLookupResult::fromRecord($claimed);
        $runtime = new AuditOutboxRelayRuntime(
            $this->connection([
                [true, false, 0],
                [true, false, 1],
            ], $events),
            $persistence,
            $context['canonicalizer'],
            $context['configuration'],
        );

        $exception = $this->assertRelayFailure(
            fn(): AuditRelayOutcome => $runtime->relayOne(),
            AuditRelayFailure::TransactionInvalid,
        );
        $this->assertExceptionSurfacesOmit($exception, [
            $context['rawKey'],
            $context['record']->protectedIngress()->toArray()['protectedBody'],
            self::TOKEN,
            $context['record']->toArray()['contentHash'],
            $canary,
        ]);
        self::assertSame(['claim'], $persistence->calls);
        self::assertSame([], $persistence->laterCalls());
        self::assertSame([], $persistence->settlementCalls());
        self::assertSame(0, $keys->keyCalls);
        self::assertSame(1, $keys->activeKeyCalls);
        self::assertSame($this->relayEvents(['connection', 'claim', 'connection']), $events);
    }

    public function testRelayPreAppendConnectionDriftStopsAfterActualCanonicalization(): void
    {
        $events = [];
        $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
            $events[] = $event;
        });
        $context = $this->fixture(null, 'durableSystemIngress', $keys);
        $events = [];
        $persistence = new RelayRuntimeFailurePersistenceFake(static function (string $event) use (&$events): void {
            $events[] = $event;
        });
        $persistence->claimResponse = AuditOutboxLookupResult::fromRecord($context['record']);
        $runtime = new AuditOutboxRelayRuntime(
            $this->connection([
                [true, false, 0],
                [true, false, 0],
                [true, false, 1],
            ], $events),
            $persistence,
            $context['canonicalizer'],
            $context['configuration'],
        );

        $exception = $this->assertRelayFailure(
            fn(): AuditRelayOutcome => $runtime->relayOne(),
            AuditRelayFailure::TransactionInvalid,
        );
        $this->assertExceptionSurfacesOmit($exception, array_merge(
            [
                $context['rawKey'],
                $context['record']->protectedIngress()->toArray()['protectedBody'],
                self::TOKEN,
                $context['record']->toArray()['contentHash'],
            ],
            $keys->readKeyIds,
            $keys->readMaterials,
        ));
        self::assertSame(['claim'], $persistence->calls);
        self::assertSame([], $persistence->laterCalls());
        self::assertSame([], $persistence->settlementCalls());
        self::assertNull($persistence->appendedLogical);
        self::assertSame(1, $keys->keyCalls);
        self::assertSame(1, $keys->activeKeyCalls);
        self::assertSame(['audit-outbox-key'], $keys->readKeyIds);
        self::assertSame([AuditProtectionPurpose::AuditOutboxRecord], $keys->readPurposes);
        self::assertSame([str_repeat('o', 32)], $keys->readMaterials);
        self::assertSame(
            $this->relayEvents([
                'connection',
                'claim',
                'connection',
                'outbox-key:decode/open',
                'connection',
            ]),
            $events,
        );
    }

    public function testRelayPostAppendConnectionDriftPrecedesMalformedResultInspection(): void
    {
        $events = [];
        $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
            $events[] = $event;
        });
        $context = $this->fixture(null, 'durableSystemIngress', $keys);
        $events = [];
        $append = $this->malformedAppendResult('record');
        $persistence = new RelayRuntimeFailurePersistenceFake(static function (string $event) use (&$events): void {
            $events[] = $event;
        });
        $persistence->claimResponse = AuditOutboxLookupResult::fromRecord($context['record']);
        $persistence->appendResponse = $append;
        $runtime = new AuditOutboxRelayRuntime(
            $this->connection([
                [true, false, 0],
                [true, false, 0],
                [true, false, 0],
                [true, false, 1],
            ], $events),
            $persistence,
            $context['canonicalizer'],
            $context['configuration'],
        );

        $exception = $this->assertRelayFailure(
            fn(): AuditRelayOutcome => $runtime->relayOne(),
            AuditRelayFailure::TransactionInvalid,
        );
        $this->assertExceptionSurfacesOmit($exception, array_merge(
            [
                $context['rawKey'],
                $context['record']->protectedIngress()->toArray()['protectedBody'],
                self::TOKEN,
                $context['record']->toArray()['contentHash'],
                'append-record-canary',
            ],
            $keys->readKeyIds,
            $keys->readMaterials,
        ));
        self::assertSame(['claim', 'append'], $persistence->calls);
        self::assertSame([], $persistence->settlementCalls());
        self::assertInstanceOf(CanonicalAuditLogicalRecord::class, $persistence->appendedLogical);
        self::assertSame(1, $keys->keyCalls);
        self::assertSame(1, $keys->activeKeyCalls);
        self::assertSame(['audit-outbox-key'], $keys->readKeyIds);
        self::assertSame([AuditProtectionPurpose::AuditOutboxRecord], $keys->readPurposes);
        self::assertSame([str_repeat('o', 32)], $keys->readMaterials);
        self::assertSame(
            $this->relayEvents([
                'connection',
                'claim',
                'connection',
                'outbox-key:decode/open',
                'connection',
                'append',
                'connection',
            ]),
            $events,
        );
    }

    public function testRelayPreSettlementConnectionDriftFollowsValidCommittedInspection(): void
    {
        $events = [];
        $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
            $events[] = $event;
        });
        $context = $this->fixture(null, 'durableSystemIngress', $keys);
        $events = [];
        $append = $this->appendResult($context, true);
        $claimedValue = $context['record']->toArray();
        $storedValue = $append->record()->toArray();
        foreach (['recordId', 'partitionId', 'tenantBinding', 'contentHash'] as $binding) {
            self::assertSame($claimedValue[$binding], $storedValue[$binding], 'matching ' . $binding);
        }
        $persistence = new RelayRuntimeFailurePersistenceFake(static function (string $event) use (&$events): void {
            $events[] = $event;
        });
        $persistence->claimResponse = AuditOutboxLookupResult::fromRecord($context['record']);
        $persistence->appendResponse = $append;
        $runtime = new AuditOutboxRelayRuntime(
            $this->connection([
                [true, false, 0],
                [true, false, 0],
                [true, false, 0],
                [true, false, 0],
                [true, false, 1],
            ], $events),
            $persistence,
            $context['canonicalizer'],
            $context['configuration'],
        );

        $exception = $this->assertRelayFailure(
            fn(): AuditRelayOutcome => $runtime->relayOne(),
            AuditRelayFailure::TransactionInvalid,
        );
        $this->assertExceptionSurfacesOmit($exception, array_merge(
            [
                $context['rawKey'],
                $context['record']->protectedIngress()->toArray()['protectedBody'],
                self::TOKEN,
                $context['record']->toArray()['contentHash'],
            ],
            $keys->readKeyIds,
            $keys->readMaterials,
        ));
        self::assertSame(['claim', 'append'], $persistence->calls);
        self::assertSame([], $persistence->settlementCalls());
        self::assertInstanceOf(CanonicalAuditLogicalRecord::class, $persistence->appendedLogical);
        self::assertSame(1, $keys->keyCalls);
        self::assertSame(1, $keys->activeKeyCalls);
        self::assertSame(['audit-outbox-key'], $keys->readKeyIds);
        self::assertSame([AuditProtectionPurpose::AuditOutboxRecord], $keys->readPurposes);
        self::assertSame([str_repeat('o', 32)], $keys->readMaterials);
        self::assertSame(
            $this->relayEvents([
                'connection',
                'claim',
                'connection',
                'outbox-key:decode/open',
                'connection',
                'append',
                'connection',
                'connection',
            ]),
            $events,
        );
    }

    public function testRelayPostSettlementConnectionDriftPrecedesReturnedStateInspection(): void
    {
        foreach (['retry', 'poison', 'relay'] as $path) {
            $events = [];
            $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
                $events[] = $event;
            });
            $configuration = new AuditOutboxConfiguration(maxAttempts: 2);
            $context = $this->fixture($configuration, 'durableSystemIngress', $keys);
            $events = [];
            $claimed = $context['record'];
            $canonicalizer = $context['canonicalizer'];
            $corruptBody = null;
            $append = null;
            if ($path === 'retry') {
                $corruptBody = $this->mutatedProtectedBody($context);
                $claimed = $this->malformedClaimRecord($context, ['attempt' => 1], ['protectedBody' => $corruptBody]);
            } elseif ($path === 'poison') {
                $canonicalizer = new RelayIngressCanonicalizer(
                    $context['protection'],
                    new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([])),
                );
            } else {
                $append = $this->appendResult($context, true);
            }
            $canary = $path . '-post-settlement-state-canary';
            $returned = $this->corruptRecordState($canary, $context);
            self::assertInstanceOf(AuditOutboxRecord::class, $returned);
            $persistence = new RelayRuntimeFailurePersistenceFake(static function (string $event) use (&$events): void {
                $events[] = $event;
            });
            $persistence->claimResponse = AuditOutboxLookupResult::fromRecord($claimed);
            if ($path === 'retry') {
                $persistence->retryResponse = $returned;
            } elseif ($path === 'poison') {
                $persistence->poisonResponse = $returned;
            } else {
                self::assertInstanceOf(AuditAppendResult::class, $append);
                $persistence->appendResponse = $append;
                $persistence->relayResponse = $returned;
            }
            $connectionStates = $path === 'relay'
                ? array_fill(0, 6, [true, false, 0])
                : array_fill(0, 4, [true, false, 0]);
            $connectionStates[$path === 'relay' ? 5 : 3] = [true, false, 1];
            $runtime = new AuditOutboxRelayRuntime(
                $this->connection($connectionStates, $events),
                $persistence,
                $canonicalizer,
                $configuration,
            );

            $exception = $this->assertRelayFailure(
                fn(): AuditRelayOutcome => $runtime->relayOne(),
                AuditRelayFailure::TransactionInvalid,
            );
            $secrets = [
                $context['rawKey'],
                $context['record']->protectedIngress()->toArray()['protectedBody'],
                self::TOKEN,
                $context['record']->toArray()['contentHash'],
                $canary,
            ];
            if ($corruptBody !== null) {
                $secrets[] = $corruptBody;
            }
            $this->assertExceptionSurfacesOmit($exception, array_merge(
                $secrets,
                $keys->readKeyIds,
                $keys->readMaterials,
            ));
            self::assertSame(
                $path === 'relay' ? ['claim', 'append', 'relay'] : ['claim', $path],
                $persistence->calls,
                $path . ': exact calls and no later call',
            );
            self::assertSame([$path], $persistence->settlementCalls(), $path . ': one settlement');
            self::assertSame(1, $keys->keyCalls, $path . ': key/decode');
            self::assertSame(1, $keys->activeKeyCalls, $path . ': fixture active key only');
            self::assertSame(['audit-outbox-key'], $keys->readKeyIds, $path . ': key id');
            self::assertSame([AuditProtectionPurpose::AuditOutboxRecord], $keys->readPurposes, $path . ': key purpose');
            self::assertSame([str_repeat('o', 32)], $keys->readMaterials, $path . ': key material');
            if ($path === 'relay') {
                self::assertInstanceOf(CanonicalAuditLogicalRecord::class, $persistence->appendedLogical);
            } else {
                self::assertNull($persistence->appendedLogical, $path . ': append skipped');
            }
            self::assertSame(
                $this->relayEvents(
                    $path === 'relay'
                        ? [
                            'connection',
                            'claim',
                            'connection',
                            'outbox-key:decode/open',
                            'connection',
                            'append',
                            'connection',
                            'connection',
                            'relay',
                            'connection',
                        ]
                        : [
                            'connection',
                            'claim',
                            'connection',
                            'outbox-key:decode/open',
                            'connection',
                            $path,
                            'connection',
                        ],
                ),
                $events,
                $path . ': post-settlement Connection drift order',
            );
            $settlementArgs = match ($path) {
                'retry' => $persistence->retryArgs,
                'poison' => $persistence->poisonArgs,
                'relay' => $persistence->relayArgs,
            };
            $this->assertFenceArgs($claimed, $settlementArgs);
        }
    }

    public function testRelaySuccessfulSettlementReturnedStateFailuresContractStorageUnavailable(): void
    {
        foreach (['retry', 'poison', 'relay'] as $path) {
            foreach (['wrong-valid', 'corrupt-inspection'] as $mode) {
                $events = [];
                $keys = new RelayRuntimeKeySource(static function (string $event) use (&$events): void {
                    $events[] = $event;
                });
                $configuration = new AuditOutboxConfiguration(maxAttempts: 2);
                $context = $this->fixture($configuration, 'durableSystemIngress', $keys);
                $events = [];
                $claimed = $context['record'];
                $canonicalizer = $context['canonicalizer'];
                $corruptBody = null;
                $append = null;
                if ($path === 'retry') {
                    $corruptBody = $this->mutatedProtectedBody($context);
                    $claimed = $this->malformedClaimRecord(
                        $context,
                        ['attempt' => 1],
                        ['protectedBody' => $corruptBody],
                    );
                } elseif ($path === 'poison') {
                    $canonicalizer = new RelayIngressCanonicalizer(
                        $context['protection'],
                        new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([])),
                    );
                } else {
                    $append = $this->appendResult($context, true);
                }

                $expectedState = match ($path) {
                    'retry' => AuditOutboxState::Pending,
                    'poison' => AuditOutboxState::Poisoned,
                    'relay' => AuditOutboxState::Relayed,
                };
                $returned = $mode === 'wrong-valid'
                    ? $this->withState(
                        $context,
                        $path === 'retry' ? AuditOutboxState::Poisoned : AuditOutboxState::Pending,
                        1,
                    )
                    : $this->corruptRecordState($path . '-' . $mode . '-state-canary', $context);
                self::assertInstanceOf(AuditOutboxRecord::class, $returned);
                if ($mode === 'wrong-valid') {
                    self::assertNotSame($expectedState, $returned->stateSnapshot()->state(), $path . ': wrong state');
                }

                $persistence = new RelayRuntimeFailurePersistenceFake(static function (string $event) use (
                    &$events,
                ): void {
                    $events[] = $event;
                });
                $persistence->claimResponse = AuditOutboxLookupResult::fromRecord($claimed);
                if ($path === 'retry') {
                    $persistence->retryResponse = $returned;
                } elseif ($path === 'poison') {
                    $persistence->poisonResponse = $returned;
                } else {
                    self::assertInstanceOf(AuditAppendResult::class, $append);
                    $persistence->appendResponse = $append;
                    $persistence->relayResponse = $returned;
                }
                $connectionStates = $path === 'relay'
                    ? array_fill(0, 6, [true, false, 0])
                    : array_fill(0, 4, [true, false, 0]);
                $runtime = new AuditOutboxRelayRuntime(
                    $this->connection($connectionStates, $events),
                    $persistence,
                    $canonicalizer,
                    $configuration,
                );

                $exception = $this->assertRelayFailure(
                    fn(): AuditRelayOutcome => $runtime->relayOne(),
                    AuditRelayFailure::StorageUnavailable,
                );
                $secrets = [
                    $context['rawKey'],
                    $context['record']->protectedIngress()->toArray()['protectedBody'],
                    self::TOKEN,
                    $context['record']->toArray()['contentHash'],
                ];
                if ($corruptBody !== null) {
                    $secrets[] = $corruptBody;
                }
                if ($mode === 'corrupt-inspection') {
                    $secrets[] = $path . '-' . $mode . '-state-canary';
                }
                $this->assertExceptionSurfacesOmit($exception, array_merge(
                    $secrets,
                    $keys->readKeyIds,
                    $keys->readMaterials,
                ));
                self::assertSame(
                    $path === 'relay' ? ['claim', 'append', 'relay'] : ['claim', $path],
                    $persistence->calls,
                    $path . '/' . $mode . ': exact calls and no later call',
                );
                self::assertSame([$path], $persistence->settlementCalls(), $path . '/' . $mode . ': one settlement');
                self::assertSame(1, $keys->keyCalls, $path . '/' . $mode . ': key/decode');
                self::assertSame(1, $keys->activeKeyCalls, $path . '/' . $mode . ': fixture active key only');
                self::assertSame(['audit-outbox-key'], $keys->readKeyIds, $path . '/' . $mode . ': key id');
                self::assertSame(
                    [AuditProtectionPurpose::AuditOutboxRecord],
                    $keys->readPurposes,
                    $path . '/' . $mode . ': key purpose',
                );
                self::assertSame([str_repeat('o', 32)], $keys->readMaterials, $path . '/' . $mode . ': key material');
                if ($path === 'relay') {
                    self::assertInstanceOf(CanonicalAuditLogicalRecord::class, $persistence->appendedLogical);
                    self::assertSame(
                        $context['envelope']->toArray()['logicalRecord'],
                        $persistence->appendedLogical->toArray(),
                        $path . '/' . $mode . ': canonical append reached',
                    );
                } else {
                    self::assertNull($persistence->appendedLogical, $path . '/' . $mode . ': append skipped');
                }
                self::assertSame(
                    $this->relayEvents(
                        $path === 'relay'
                            ? [
                                'connection',
                                'claim',
                                'connection',
                                'outbox-key:decode/open',
                                'connection',
                                'append',
                                'connection',
                                'connection',
                                'relay',
                                'connection',
                            ]
                            : [
                                'connection',
                                'claim',
                                'connection',
                                'outbox-key:decode/open',
                                'connection',
                                $path,
                                'connection',
                            ],
                    ),
                    $events,
                    $path . '/' . $mode . ': good Connection order',
                );
                $settlementArgs = match ($path) {
                    'retry' => $persistence->retryArgs,
                    'poison' => $persistence->poisonArgs,
                    'relay' => $persistence->relayArgs,
                };
                $this->assertFenceArgs($claimed, $settlementArgs);
            }
        }
    }

    public function testRuntimeSourceHasOnlyBoundedPersistenceAndConnectionCapabilities(): void
    {
        $path = __DIR__ . '/../../../../src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php';
        $source = file_get_contents($path);
        self::assertIsString($source);
        $reflection = new ReflectionClass(AuditOutboxRelayRuntime::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertSame(
            ['__construct', 'recoverOne', 'relayOne'],
            array_values(array_filter(
                get_class_methods(AuditOutboxRelayRuntime::class),
                static fn(string $method): bool => new ReflectionClass(AuditOutboxRelayRuntime::class)
                    ->getMethod($method)
                    ->isPublic(),
            )),
        );
        self::assertSame(1, substr_count($source, '$this->persistence->recoverNextExpired()'));
        foreach ([
            'beginTransaction(',
            'commit(',
            'rollBack(',
            'savepoint',
            'executeQuery(',
            'executeStatement(',
            'fetchAll',
            'ClockInterface',
            'Uuidv7Generator',
            'NonceSource',
            'activeKey(',
            '->key(',
            'sleep(',
            'random_bytes(',
            'random_int(',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source, $forbidden);
        }
    }

    /** @param list<array{0:bool,1:bool,2:int}> $states */
    private function runtime(
        RelayRuntimeRecoveryPersistenceFake $persistence,
        array $states,
        array &$events,
        ?AuditOutboxConfiguration $configuration = null,
    ): AuditOutboxRelayRuntime {
        $context = $this->fixture($configuration);
        return new AuditOutboxRelayRuntime(
            $this->connection($states, $events),
            $persistence,
            $context['canonicalizer'],
            $configuration ?? $context['configuration'],
        );
    }

    /** @param list<array{0:bool,1:bool,2:int}> $states */
    private function connection(array $states, array|\ArrayObject &$events): Connection
    {
        $connection = $this->createMock(Connection::class);
        $index = 0;
        $current = [true, false, 0];
        $connection
            ->method('isAutoCommit')
            ->willReturnCallback(function () use (&$events, &$index, &$current, $states): bool {
                $current = $states[$index] ?? [true, false, 0];
                ++$index;
                $events[] = 'connection:auto';
                return $current[0];
            });
        $connection
            ->method('isTransactionActive')
            ->willReturnCallback(function () use (&$current, &$events): bool {
                $events[] = 'connection:active';
                return $current[1];
            });
        $connection
            ->method('getTransactionNestingLevel')
            ->willReturnCallback(function () use (&$current, &$events): int {
                $events[] = 'connection:level';
                return $current[2];
            });

        return $connection;
    }

    private function throwingConnection(
        string $method,
        #[\SensitiveParameter]
        string $canary,
        array|\ArrayObject &$events,
    ): Connection {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('isAutoCommit')
            ->willReturnCallback(function () use (&$events, $method, $canary): bool {
                $events[] = 'connection:auto';
                if ($method === 'isAutoCommit') {
                    throw new \RuntimeException($canary);
                }
                return true;
            });
        $connection
            ->method('isTransactionActive')
            ->willReturnCallback(function () use (&$events, $method, $canary): bool {
                $events[] = 'connection:active';
                if ($method === 'isTransactionActive') {
                    throw new \RuntimeException($canary);
                }
                return false;
            });
        $connection
            ->method('getTransactionNestingLevel')
            ->willReturnCallback(function () use (&$events, $method, $canary): int {
                $events[] = 'connection:level';
                if ($method === 'getTransactionNestingLevel') {
                    throw new \RuntimeException($canary);
                }
                return 0;
            });

        return $connection;
    }

    /** @return array{record:AuditOutboxRecord,envelope:\BlackOps\Internal\Audit\Protection\AuditIngressEnvelope,rawKey:string,canonicalizer:RelayIngressCanonicalizer,configuration:AuditOutboxConfiguration,protection:AuditProtectedBodyCodec} */
    private function fixture(
        ?AuditOutboxConfiguration $configuration = null,
        string $fixtureName = 'durableSystemIngress',
        ?RelayRuntimeKeySource $keys = null,
    ): array {
        $configuration ??= new AuditOutboxConfiguration();
        $fixture = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../Fixtures/Audit/audit-canonical-record-v1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($fixture);
        $entry = $fixture['ingress'][$fixtureName] ?? null;
        self::assertIsArray($entry);
        $logicalValue = $entry['logical'] ?? null;
        $ordinal = $entry['ingressOrdinal'] ?? null;
        $idempotencyKey = $entry['idempotencyKey'] ?? null;
        self::assertIsArray($logicalValue);
        self::assertIsInt($ordinal);
        self::assertIsString($idempotencyKey);

        $jcs = new AuditJcsCodec();
        $lookup = new ImmutableAuditEventCatalogLookup([FrameworkAuditEventCatalog::catalog()]);
        $validator = new AuditRecordValidator($lookup);
        $records = new AuditRecordCodec($validator, $jcs);
        $partitions = new AuditPartitionIdDeriver($jcs);
        $envelopes = new AuditIngressEnvelopeCodec($records, $partitions, $jcs);
        $logical = new CanonicalAuditLogicalRecord($validator->validateLogical(
            new AuditLogicalRecordCandidate($logicalValue),
        ));
        $envelope = $envelopes->create($logical, $ordinal, $idempotencyKey);
        $keys ??= new RelayRuntimeKeySource();
        $protection = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            $keys,
            new RelayRuntimeNonceSource(),
            new AuditAssociatedData(),
            $records,
            $envelopes,
        );
        $protected = $protection->protectOutbox($envelope);
        $record = AuditOutboxRecord::fromParts($protected, $this->state(AuditOutboxState::Leased, $configuration));

        return [
            'record' => $record,
            'envelope' => $envelope,
            'rawKey' => $idempotencyKey,
            'canonicalizer' => new RelayIngressCanonicalizer($protection, $validator),
            'configuration' => $configuration,
            'protection' => $protection,
        ];
    }

    private function state(
        AuditOutboxState $state,
        AuditOutboxConfiguration $configuration,
        int $attempt = 1,
        int $version = 2,
    ): AuditOutboxStateSnapshot {
        return AuditOutboxStateSnapshot::fromArray(match ($state) {
            AuditOutboxState::Pending => [
                'state' => $state->value,
                'attempt' => $attempt,
                'availableAt' => self::AVAILABLE_AT,
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'version' => $version,
            ],
            AuditOutboxState::Leased => [
                'state' => $state->value,
                'attempt' => $attempt,
                'availableAt' => null,
                'leaseFencingToken' => self::TOKEN,
                'leaseExpiresAt' => self::EXPIRES_AT,
                'version' => $version,
            ],
            AuditOutboxState::Relayed, AuditOutboxState::Poisoned => [
                'state' => $state->value,
                'attempt' => $attempt,
                'availableAt' => null,
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'version' => $version,
            ],
        }, $configuration);
    }

    /** @param array{record:AuditOutboxRecord,envelope:\BlackOps\Internal\Audit\Protection\AuditIngressEnvelope,rawKey:string,canonicalizer:RelayIngressCanonicalizer,configuration:AuditOutboxConfiguration} $context */
    private function appendResult(array $context, bool $inserted): AuditAppendResult
    {
        $row = $context['record']->toArray();
        $stored = AuditStoredRecord::fromArray([
            'schemaVersion' => 1,
            'kind' => 'audit_stored_record',
            'recordId' => $row['recordId'],
            'partitionId' => $row['partitionId'],
            'tenantBinding' => $row['tenantBinding'],
            'segmentId' => '01941f29-7c00-7000-8000-000000000010',
            'sequence' => 1,
            'previousRecordHash' => null,
            'contentHash' => $row['contentHash'],
            'recordHash' => str_repeat('e', 64),
            'recordedAt' => '2026-08-19T00:00:03.000000Z',
            'eventCategory' => 'security',
            'protectedBody' => str_repeat('c', 53),
        ]);

        return new AuditAppendResult($stored, $inserted);
    }

    /** @param array{record:AuditOutboxRecord,envelope:\BlackOps\Internal\Audit\Protection\AuditIngressEnvelope,rawKey:string,canonicalizer:RelayIngressCanonicalizer,configuration:AuditOutboxConfiguration,protection:AuditProtectedBodyCodec} $context */
    private function mutatedProtectedBody(array $context): string
    {
        $body = $context['record']->protectedIngress()->toArray()['protectedBody'];
        self::assertNotSame('', $body);
        $last = strlen($body) - 1;
        return substr_replace($body, chr(ord($body[$last]) ^ 1), $last, 1);
    }

    /** @param list<string> $entries @return list<string> */
    private function relayEvents(array $entries): array
    {
        $events = [];
        foreach ($entries as $entry) {
            if ($entry === 'connection') {
                $events[] = 'connection:auto';
                $events[] = 'connection:active';
                $events[] = 'connection:level';
            } else {
                $events[] = $entry;
            }
        }
        return $events;
    }

    /** @param list<mixed> $args */
    private function assertFenceArgs(AuditOutboxRecord $claimed, array $args): void
    {
        self::assertCount(5, $args);
        $row = $claimed->toArray();
        self::assertSame($row['recordId'], $args[0]);
        if ($row['tenantBinding'] === null) {
            self::assertNull($args[1]);
        } else {
            self::assertInstanceOf(AuditTypedReference::class, $args[1]);
            self::assertSame($row['tenantBinding'], $args[1]->toArray());
        }
        self::assertSame($row['partitionId'], $args[2]);
        self::assertSame($row['version'], $args[3]);
        self::assertSame($row['leaseFencingToken'], $args[4]);
    }

    private function assertSettledState(mixed $settled, AuditOutboxState $state, int $attempt): void
    {
        self::assertInstanceOf(AuditOutboxRecord::class, $settled);
        self::assertSame($state, $settled->stateSnapshot()->state());
        self::assertSame($attempt, $settled->stateSnapshot()->attempt());
    }

    /** @param array{record:AuditOutboxRecord,envelope:\BlackOps\Internal\Audit\Protection\AuditIngressEnvelope,rawKey:string,canonicalizer:RelayIngressCanonicalizer,configuration:AuditOutboxConfiguration,protection:AuditProtectedBodyCodec} $context @param list<mixed> $events @param list<string> $extraSecrets */
    private function assertSuccessSurfacesOmit(
        array $context,
        ?CanonicalAuditLogicalRecord $logical,
        array $events,
        AuditRelayOutcome $outcome,
        array $extraSecrets = [],
    ): void {
        $row = $context['record']->toArray();
        $secrets = array_merge([
            $context['rawKey'],
            $row['protectedBody'],
            $row['leaseFencingToken'] ?? '',
            $row['contentHash'],
            $row['idempotencyKeyHash'],
        ], $extraSecrets);
        $surfaces = [
            'events' => implode('|', $this->eventStrings($events)),
            'outcome-debug' => print_r($outcome, true),
            'outcome-export' => var_export($outcome, true),
            'outcome-json' => json_encode($outcome, JSON_THROW_ON_ERROR),
            'outcome-serialize' => serialize($outcome),
        ];
        if ($logical !== null) {
            $surfaces += [
                'logical-debug' => print_r($logical, true),
                'logical-export' => var_export($logical, true),
                'logical-json' => json_encode($logical, JSON_THROW_ON_ERROR),
                'logical-serialize' => serialize($logical),
            ];
        }
        foreach ($surfaces as $name => $surface) {
            foreach (array_values(array_unique(array_filter(
                array_map('strval', $secrets),
                static fn(#[\SensitiveParameter] string $secret): bool => $secret !== '',
            ))) as $secret) {
                self::assertFalse(str_contains($surface, $secret), $name . ': secret surface');
            }
        }
    }

    /** @param array{record:AuditOutboxRecord,envelope:\BlackOps\Internal\Audit\Protection\AuditIngressEnvelope,rawKey:string,canonicalizer:RelayIngressCanonicalizer,configuration:AuditOutboxConfiguration,protection:AuditProtectedBodyCodec} $context */
    private function mismatchedAppendResult(array $context, string $field): AuditAppendResult
    {
        $row = $this->appendResult($context, true)->record()->toArray();
        $row[$field] = match ($field) {
            'recordId' => '01941f29-7c00-7000-8000-000000000011',
            'partitionId' => 'tenant:' . str_repeat('b', 64),
            'tenantBinding' => ['type' => 'tenant', 'id' => 'binding-mismatch'],
            'contentHash' => str_repeat('d', 64),
            default => throw new \LogicException('unexpected binding field'),
        };
        return new AuditAppendResult(AuditStoredRecord::fromArray($row), true);
    }

    /** @param array{record:AuditOutboxRecord,envelope:\BlackOps\Internal\Audit\Protection\AuditIngressEnvelope,rawKey:string,canonicalizer:RelayIngressCanonicalizer,configuration:AuditOutboxConfiguration,protection:AuditProtectedBodyCodec}|null $context */
    private function malformedAppendResult(string $failure, ?array $context = null): AuditAppendResult
    {
        $snapshot = match ($failure) {
            'record' => ['record' => 'append-record-canary', 'inserted' => true],
            'stored' => ['record' => $this->malformedStoredRecord('stored-array-canary'), 'inserted' => true],
            'inserted' => [
                'record' => $context === null
                    ? throw new \LogicException('inserted inspection requires a fixture')
                    : $this->appendResult($context, true)->record(),
                'inserted' => 'append-inserted-canary',
            ],
            default => throw new \LogicException('unexpected append inspection failure'),
        };
        $append = new ReflectionClass(AuditAppendResult::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AuditAppendResult::class, 'snapshot')->setValue(
            $append,
            new \SensitiveParameterValue($snapshot),
        );
        return $append;
    }

    private function malformedStoredRecord(string $canary): AuditStoredRecord
    {
        $stored = new ReflectionClass(AuditStoredRecord::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AuditStoredRecord::class, 'snapshot')->setValue(
            $stored,
            new \SensitiveParameterValue($canary),
        );
        return $stored;
    }

    private function assertActualInspectionFailure(AuditAppendResult $append, string $name): \Throwable
    {
        try {
            match ($name) {
                'record-inspection' => $append->record(),
                'stored-array-inspection' => $append->record()->toArray(),
                'inserted-inspection' => $append->inserted(),
                default => throw new \LogicException('unexpected append inspection case'),
            };
            self::fail($name . ': inspection unexpectedly succeeded');
        } catch (AuditStoreException $failure) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $failure->failure(), $name . ': actual exception');
            return $failure;
        } catch (AuditProtectionException $failure) {
            self::assertSame(
                AuditProtectionFailure::ProtectionFailed,
                $failure->failure(),
                $name . ': actual exception',
            );
            return $failure;
        }
    }

    /** @param array{record:AuditOutboxRecord,envelope:\BlackOps\Internal\Audit\Protection\AuditIngressEnvelope,rawKey:string,canonicalizer:RelayIngressCanonicalizer,configuration:AuditOutboxConfiguration} $context */
    private function assertRelayPositiveNoSecret(
        array $context,
        CanonicalAuditLogicalRecord $logical,
        array $events,
        array $relayArgs,
        AuditRelayOutcome $outcome,
    ): void {
        $ciphertext = $context['record']->protectedIngress()->toArray()['protectedBody'];
        $surfaces = [
            'events' => implode('|', $this->eventStrings($events)),
            'logical-debug' => print_r($logical, true),
            'logical-export' => var_export($logical, true),
            'logical-json' => json_encode($logical, JSON_THROW_ON_ERROR),
            'logical-serialize' => serialize($logical),
            'outcome-debug' => print_r($outcome, true),
            'outcome-export' => var_export($outcome, true),
            'outcome-json' => json_encode($outcome, JSON_THROW_ON_ERROR),
            'outcome-serialize' => serialize($outcome),
            'relay-args' => print_r($relayArgs, true),
        ];
        foreach ($surfaces as $name => $surface) {
            self::assertFalse(str_contains($surface, $context['rawKey']), $name . ': raw key');
            self::assertFalse(str_contains($surface, $ciphertext), $name . ': ciphertext');
        }
    }

    /** @param list<mixed> $values @return list<string> */
    private function eventStrings(array $values): array
    {
        return array_values(array_filter($values, static fn(mixed $value): bool => is_string($value)));
    }

    /** @param array{record:AuditOutboxRecord,envelope:\BlackOps\Internal\Audit\Protection\AuditIngressEnvelope,rawKey:string,canonicalizer:RelayIngressCanonicalizer,configuration:AuditOutboxConfiguration} $context */
    private function withState(
        array $context,
        AuditOutboxState $state,
        int $attempt = 1,
        int $version = 2,
    ): AuditOutboxRecord {
        return AuditOutboxRecord::fromParts($context['record']->protectedIngress(), $this->state(
            $state,
            $context['configuration'],
            $attempt,
            $version,
        ));
    }

    private function corruptLookup(mixed $found, mixed $record): AuditOutboxLookupResult
    {
        $lookup = new ReflectionClass(AuditOutboxLookupResult::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(
            AuditOutboxLookupResult::class,
            'snapshot',
        )->setValue($lookup, new \SensitiveParameterValue(['found' => $found, 'record' => $record]));
        return $lookup;
    }

    /** @param array{record:AuditOutboxRecord,envelope:\BlackOps\Internal\Audit\Protection\AuditIngressEnvelope,rawKey:string,canonicalizer:RelayIngressCanonicalizer,configuration:AuditOutboxConfiguration} $context */
    private function corruptRecordState(string $state, array $context): AuditOutboxRecord
    {
        $snapshot = new ReflectionClass(AuditOutboxStateSnapshot::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(
            AuditOutboxStateSnapshot::class,
            'snapshot',
        )->setValue($snapshot, new \SensitiveParameterValue(['state' => $state]));
        $record = new ReflectionClass(AuditOutboxRecord::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AuditOutboxRecord::class, 'snapshot')->setValue($record, new \SensitiveParameterValue([
            'protectedIngress' => $context['record']->protectedIngress(),
            'stateSnapshot' => $snapshot,
        ]));
        return $record;
    }

    /** @param array{record:AuditOutboxRecord,envelope:\BlackOps\Internal\Audit\Protection\AuditIngressEnvelope,rawKey:string,canonicalizer:RelayIngressCanonicalizer,configuration:AuditOutboxConfiguration} $context @param array<string,mixed> $stateChanges @param array<string,mixed> $ingressChanges */
    private function malformedClaimRecord(
        array $context,
        array $stateChanges = [],
        array $ingressChanges = [],
    ): AuditOutboxRecord {
        $ingressValue = $context['record']->protectedIngress()->toArray();
        foreach ($ingressChanges as $key => $value) {
            $ingressValue[$key] = $value;
        }
        $ingress = new ReflectionClass(AuditProtectedIngress::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AuditProtectedIngress::class, 'snapshot')->setValue(
            $ingress,
            new \SensitiveParameterValue($ingressValue),
        );

        $stateValue = $context['record']->stateSnapshot()->toArray();
        foreach ($stateChanges as $key => $value) {
            $stateValue[$key] = $value;
        }
        $state = new ReflectionClass(AuditOutboxStateSnapshot::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AuditOutboxStateSnapshot::class, 'snapshot')->setValue(
            $state,
            new \SensitiveParameterValue($stateValue),
        );

        $record = new ReflectionClass(AuditOutboxRecord::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AuditOutboxRecord::class, 'snapshot')->setValue($record, new \SensitiveParameterValue([
            'protectedIngress' => $ingress,
            'stateSnapshot' => $state,
        ]));
        return $record;
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

    private function assertRelayFailure(callable $operation, AuditRelayFailure $expected): AuditRelayException
    {
        try {
            $operation();
            self::fail('the operation unexpectedly succeeded');
        } catch (AuditRelayException $exception) {
            self::assertSame($expected, $exception->failure());
            self::assertSame($expected->value, $exception->code());
            self::assertSame('Audit relay operation failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            $surfaces = [
                'print_r' => print_r($exception, true),
                'var_export' => $this->safeVarExport($exception),
                'json' => json_encode($exception, JSON_THROW_ON_ERROR),
                'serialize' => serialize($exception),
                'trace' => $exception->getTraceAsString(),
                'trace-json' => json_encode($exception->getTrace(), JSON_THROW_ON_ERROR),
            ];
            foreach ($surfaces as $name => $surface) {
                foreach (['recovery-canary', 'connection-canary', 'result-canary'] as $canary) {
                    self::assertFalse(str_contains($surface, $canary), $name . ': ' . $canary);
                }
            }
            return $exception;
        }
    }

    /** @param list<string> $secrets */
    private function assertExceptionSurfacesOmit(AuditRelayException $exception, array $secrets): void
    {
        $surfaces = [
            'print_r' => print_r($exception, true),
            'var_export' => $this->safeVarExport($exception),
            'json' => json_encode($exception, JSON_THROW_ON_ERROR),
            'serialize' => serialize($exception),
            'trace' => $exception->getTraceAsString(),
            'trace-json' => json_encode($exception->getTrace(), JSON_THROW_ON_ERROR),
        ];
        foreach ($surfaces as $name => $surface) {
            foreach (array_values(array_unique(array_filter(
                $secrets,
                static fn(#[\SensitiveParameter] string $secret): bool => $secret !== '',
            ))) as $secret) {
                self::assertFalse(str_contains($surface, $secret), $name . ': secret surface');
            }
        }
    }

    /** @param list<string> $secrets */
    private function assertThrowableSurfacesOmit(\Throwable $exception, array $secrets, string $label = ''): void
    {
        $surfaces = [
            'print_r' => print_r($exception, true),
            'var_export' => $this->safeVarExport($exception),
            'json' => json_encode($exception, JSON_THROW_ON_ERROR),
            'serialize' => $this->throwableSerializeSurface($exception),
            'trace' => $exception->getTraceAsString(),
            'trace-json' => json_encode($exception->getTrace(), JSON_THROW_ON_ERROR),
        ];
        foreach ($surfaces as $name => $surface) {
            foreach (array_values(array_unique(array_filter(
                $secrets,
                static fn(#[\SensitiveParameter] string $secret): bool => $secret !== '',
            ))) as $secret) {
                self::assertFalse(
                    str_contains($surface, $secret),
                    ($label === '' ? '' : $label . ': ') . $name . ': secret surface',
                );
            }
        }
    }

    private function throwableSerializeSurface(\Throwable $exception): string
    {
        try {
            return serialize($exception);
        } catch (\Throwable $failure) {
            return 'serialization-failed:' . $failure->getMessage();
        }
    }
}

final class RelayRuntimeRecoveryPersistenceFake implements AuditRelayPersistence
{
    public mixed $recoverResponse = null;

    /** @var list<string> */
    public array $calls = [];

    public function recoverNextExpired(): AuditOutboxLookupResult
    {
        $this->calls[] = 'recover';
        $response = $this->recoverResponse ?? AuditOutboxLookupResult::notFound();
        if ($response instanceof \Throwable) {
            throw $response;
        }
        return $response;
    }

    public function claimNext(): AuditOutboxLookupResult
    {
        $this->calls[] = 'claim';
        throw new \LogicException('claim was called during recovery');
    }

    public function appendCanonical(#[\SensitiveParameter] CanonicalAuditLogicalRecord $logical): AuditAppendResult
    {
        $this->calls[] = 'append';
        throw new \LogicException('append was called during recovery');
    }

    public function retry(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        $this->calls[] = 'retry';
        throw new \LogicException('retry was called during recovery');
    }

    public function relay(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        $this->calls[] = 'relay';
        throw new \LogicException('relay was called during recovery');
    }

    public function poison(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        $this->calls[] = 'poison';
        throw new \LogicException('poison was called during recovery');
    }
}

/**
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:too-many-properties
 */
final class RelayRuntimeFailurePersistenceFake implements AuditRelayPersistence
{
    public mixed $claimResponse = null;

    public mixed $appendResponse = null;

    public mixed $retryResponse = null;

    public mixed $relayResponse = null;

    public mixed $poisonResponse = null;

    /** @var list<string> */
    public array $calls = [];

    public ?CanonicalAuditLogicalRecord $appendedLogical = null;

    public ?\Throwable $appendThrown = null;

    public ?\Throwable $settlementThrown = null;

    /** @var list<mixed> */
    public array $retryArgs = [];

    /** @var list<mixed> */
    public array $relayArgs = [];

    /** @var list<mixed> */
    public array $poisonArgs = [];

    private ?\Closure $recordEvent;

    public function __construct(?\Closure $recordEvent = null)
    {
        $this->recordEvent = $recordEvent;
    }

    public function recoverNextExpired(): AuditOutboxLookupResult
    {
        $this->calls[] = 'recover';
        throw new \LogicException('recovery was called during relay');
    }

    public function claimNext(): AuditOutboxLookupResult
    {
        $this->calls[] = 'claim';
        $this->record('claim');
        $response = $this->claimResponse ?? AuditOutboxLookupResult::notFound();
        if ($response instanceof \Throwable) {
            throw $response;
        }
        if (!$response instanceof AuditOutboxLookupResult) {
            throw new \LogicException('invalid claim response');
        }
        return $response;
    }

    public function appendCanonical(#[\SensitiveParameter] CanonicalAuditLogicalRecord $logical): AuditAppendResult
    {
        $this->calls[] = 'append';
        $this->record('append');
        $this->appendedLogical = $logical;
        $this->appendThrown = null;
        $response = $this->appendResponse;
        if ($response instanceof \Throwable) {
            $this->appendThrown = $response;
            throw $response;
        }
        if (!$response instanceof AuditAppendResult) {
            throw new \LogicException('invalid append response');
        }
        return $response;
    }

    public function retry(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        $this->calls[] = 'retry';
        $this->retryArgs = [$recordId, $expectedTenant, $expectedPartitionId, $expectedVersion, $expectedToken];
        $this->record('retry');
        return $this->settlement($this->retryResponse);
    }

    public function relay(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        $this->calls[] = 'relay';
        $this->relayArgs = [$recordId, $expectedTenant, $expectedPartitionId, $expectedVersion, $expectedToken];
        $this->record('relay');
        return $this->settlement($this->relayResponse);
    }

    public function poison(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        $this->calls[] = 'poison';
        $this->poisonArgs = [$recordId, $expectedTenant, $expectedPartitionId, $expectedVersion, $expectedToken];
        $this->record('poison');
        return $this->settlement($this->poisonResponse);
    }

    /** @return list<string> */
    public function laterCalls(): array
    {
        return array_slice($this->calls, 1);
    }

    /** @return list<string> */
    public function settlementCalls(): array
    {
        return array_values(array_filter($this->calls, static fn(string $call): bool => in_array(
            $call,
            ['retry', 'relay', 'poison'],
            true,
        )));
    }

    private function record(string $event): void
    {
        if ($this->recordEvent !== null) {
            ($this->recordEvent)($event);
        }
    }

    private function settlement(mixed $response): AuditOutboxRecord
    {
        if ($response instanceof \Throwable) {
            $this->settlementThrown = $response;
            throw $response;
        }
        if (!$response instanceof AuditOutboxRecord) {
            throw new \LogicException('invalid settlement response');
        }
        return $response;
    }
}

final class RelayRuntimePositivePersistenceFake implements AuditRelayPersistence
{
    /** @var list<string> */
    public array $calls = [];

    public ?CanonicalAuditLogicalRecord $appendedLogical = null;

    /** @var list<mixed> */
    public array $relayArgs = [];

    private \Closure $recordEvent;

    private AuditOutboxLookupResult $claimResponse;

    public AuditAppendResult $appendResponse;

    public AuditOutboxRecord $relayResponse;

    public function __construct(
        \Closure $recordEvent,
        AuditOutboxLookupResult $claimResponse,
        AuditAppendResult $appendResponse,
        AuditOutboxRecord $relayResponse,
    ) {
        $this->recordEvent = $recordEvent;
        $this->claimResponse = $claimResponse;
        $this->appendResponse = $appendResponse;
        $this->relayResponse = $relayResponse;
    }

    public function recoverNextExpired(): AuditOutboxLookupResult
    {
        throw new \LogicException('recovery was called during positive relay');
    }

    public function claimNext(): AuditOutboxLookupResult
    {
        $this->calls[] = 'claim';
        ($this->recordEvent)('claim');
        return $this->claimResponse;
    }

    public function appendCanonical(#[\SensitiveParameter] CanonicalAuditLogicalRecord $logical): AuditAppendResult
    {
        $this->calls[] = 'append';
        ($this->recordEvent)('append');
        $this->appendedLogical = $logical;
        return $this->appendResponse;
    }

    public function retry(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        throw new \LogicException('retry was called during positive relay');
    }

    public function relay(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        $this->calls[] = 'relay';
        $this->relayArgs = [$recordId, $expectedTenant, $expectedPartitionId, $expectedVersion, $expectedToken];
        ($this->recordEvent)('relay');
        return $this->relayResponse;
    }

    public function poison(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        throw new \LogicException('poison was called during positive relay');
    }
}

final class RelayRuntimeKeySource implements AuditProtectionKeySource
{
    /** @var list<string> */
    public array $readKeyIds = [];

    /** @var list<AuditProtectionPurpose> */
    public array $readPurposes = [];

    /** @var list<string> */
    public array $readMaterials = [];

    public int $activeKeyCalls = 0;

    public int $keyCalls = 0;

    public function __construct(
        private ?\Closure $recordEvent = null,
    ) {}

    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        ++$this->activeKeyCalls;
        $this->record('active-key');
        return new StorageKey('audit-outbox-key', str_repeat('o', 32));
    }

    public function key(
        #[\SensitiveParameter]
        string $keyId,
        ?AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): StorageKey {
        ++$this->keyCalls;
        $this->readKeyIds[] = $keyId;
        $this->readPurposes[] = $purpose;
        $this->readMaterials[] = str_repeat('o', 32);
        $this->record('outbox-key:decode/open');
        if ($keyId !== 'audit-outbox-key') {
            throw new \RuntimeException('unexpected runtime fixture key');
        }
        return new StorageKey($keyId, str_repeat('o', 32));
    }

    private function record(string $event): void
    {
        if ($this->recordEvent !== null) {
            ($this->recordEvent)($event);
        }
    }
}

final class RelayRuntimeNonceSource implements NonceSource
{
    public function generate(): string
    {
        return str_repeat("\x01", 24);
    }
}
