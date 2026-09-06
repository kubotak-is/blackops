<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Orchestration;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditReadAuthorizationDecision;
use BlackOps\Core\Audit\AuditReadContext;
use BlackOps\Core\Audit\AuditReadPurpose;
use BlackOps\Core\Audit\AuditReadRequestBasis;
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
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Orchestration\AuditProtectedReadGate;
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
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionClass;

final class AuditProtectedReadGateTest extends TestCase
{
    public function testRequestedBindingFailureHappensBeforeDelivery(): void
    {
        $delivery = new ReflectionClass(AuditDeliveryCoordinator::class)->newInstanceWithoutConstructor();
        $gate = new AuditProtectedReadGate($delivery);
        $basis = $this->basis();
        $draft = $this->draft($basis, 'requested', 'wrong-request-ref');

        try {
            $gate->requested($basis, $draft);
            self::fail('invalid requested binding was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testAllowTerminalFailureWithholdsTheDecision(): void
    {
        $delivery = new ReflectionClass(AuditDeliveryCoordinator::class)->newInstanceWithoutConstructor();
        $gate = new AuditProtectedReadGate($delivery);
        $basis = $this->basis();
        $context = AuditReadContext::fromBasis($basis, AuditReadAuthorizationDecision::Allow);

        try {
            $gate->terminal($context, $this->draft($basis, 'accessed', $basis->requestRef()));
            self::fail('allow was released without a successful append');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testEveryTerminalDecisionWithholdsReleaseOnDeliveryFailure(): void
    {
        $delivery = new ReflectionClass(AuditDeliveryCoordinator::class)->newInstanceWithoutConstructor();
        $gate = new AuditProtectedReadGate($delivery);
        $basis = $this->basis();
        foreach ([
            AuditReadAuthorizationDecision::Allow,
            AuditReadAuthorizationDecision::Deny,
            AuditReadAuthorizationDecision::ResourceUnavailable,
        ] as $decision) {
            $phase = $decision === AuditReadAuthorizationDecision::Allow ? 'accessed' : 'unavailable';
            try {
                $gate->terminal(AuditReadContext::fromBasis($basis, $decision), $this->draftFor($basis, $phase));
                self::fail($decision->value . ' was released without a successful append');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testEveryPurposeCommitsRequestedBeforeAuthorizationAndTerminalBeforeAllowRelease(): void
    {
        foreach (AuditReadPurpose::cases() as $purpose) {
            [$admin, $primary, $probe, $schema, $gate] = $this->realFixture();
            try {
                $basis = $this->basis($purpose);
                $authorizationSentinel = false;
                $gate->requested($basis, $this->draftFor($basis, 'requested'));
                self::assertSame(1, $this->countRecords($probe, $schema));
                $authorizationSentinel = true;
                self::assertTrue($authorizationSentinel);

                foreach ([
                    AuditReadAuthorizationDecision::Allow,
                    AuditReadAuthorizationDecision::Deny,
                    AuditReadAuthorizationDecision::ResourceUnavailable,
                ] as $decision) {
                    $context = AuditReadContext::fromBasis($basis, $decision);
                    $phase = $decision === AuditReadAuthorizationDecision::Allow ? 'accessed' : 'unavailable';
                    $releaseSentinel = false;
                    $returned = $gate->terminal($context, $this->draftFor($basis, $phase));
                    self::assertSame($decision, $returned);
                    self::assertSame(
                        $decision === AuditReadAuthorizationDecision::Allow
                            ? 2
                            : ($decision === AuditReadAuthorizationDecision::Deny ? 3 : 4),
                        $this->countRecords($probe, $schema),
                    );
                    $releaseSentinel = true;
                    self::assertTrue($releaseSentinel);
                    self::assertFalse($primary->isTransactionActive());
                }
            } finally {
                $this->cleanupRealFixture($admin, $primary, $probe, $schema);
            }
        }
    }

    public function testProtectedReadBindingSubstitutionsReachEachDecisionBranchWithoutDelivery(): void
    {
        $cases = [
            'purpose' => fn(AuditReadRequestBasis $basis): AuditRecordDraft => $this->draftFor(
                $basis,
                'requested',
                purpose: AuditReadPurpose::Journal,
            ),
            'request-ref' => fn(AuditReadRequestBasis $basis): AuditRecordDraft => $this->draftFor(
                $basis,
                'requested',
                actionId: 'wrong-request-ref',
            ),
            'principal' => fn(AuditReadRequestBasis $basis): AuditRecordDraft => $this->draftFor(
                $basis,
                'requested',
                actor: new AuditTypedReference('user', 'wrong-user'),
            ),
            'execution' => fn(AuditReadRequestBasis $basis): AuditRecordDraft => $this->draftFor(
                $basis,
                'requested',
                execution: new AuditTypedReference('execution', 'wrong-execution'),
            ),
            'tenant' => fn(AuditReadRequestBasis $basis): AuditRecordDraft => $this->draftFor(
                $basis,
                'requested',
                tenant: new AuditTypedReference('account', 'wrong-tenant'),
            ),
            'occurred-at' => fn(AuditReadRequestBasis $basis): AuditRecordDraft => $this->draftFor(
                $basis,
                'requested',
                occurredAt: '2026-09-02T00:00:02.000000Z',
            ),
            'action' => fn(AuditReadRequestBasis $basis): AuditRecordDraft => $this->draftFor(
                $basis,
                'requested',
                actionType: 'access.operation_status.other',
            ),
            'terminal-event' => fn(AuditReadRequestBasis $basis): AuditRecordDraft => $this->draftFor(
                $basis,
                'unavailable',
                eventType: 'access.operation_status.accessed',
            ),
            'policy' => fn(AuditReadRequestBasis $basis): AuditRecordDraft => $this->draftFor(
                $basis,
                'requested',
                policyDelivery: AuditDelivery::Durable,
            ),
            'decision' => fn(AuditReadRequestBasis $basis): AuditRecordDraft => $this->draftFor($basis, 'accessed'),
        ];
        $reached = 0;
        foreach ($cases as $name => $factory) {
            [$admin, $primary, $probe, $schema, $gate] = $this->realFixture();
            try {
                $basis = $this->basis(AuditReadPurpose::Status);
                $draft = $factory($basis);
                try {
                    $gate->requested($basis, $draft);
                    self::fail($name . ' requested substitution was accepted');
                } catch (AuditDeliveryException $exception) {
                    self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
                    $reached++;
                }
                foreach ([
                    AuditReadAuthorizationDecision::Allow,
                    AuditReadAuthorizationDecision::Deny,
                    AuditReadAuthorizationDecision::ResourceUnavailable,
                ] as $decision) {
                    $phase = $decision === AuditReadAuthorizationDecision::Allow ? 'accessed' : 'unavailable';
                    if ($name === 'decision') {
                        $terminalDraft = $this->draftFor(
                            $basis,
                            $decision === AuditReadAuthorizationDecision::Allow ? 'unavailable' : 'accessed',
                        );
                    } elseif ($name === 'terminal-event') {
                        $terminalDraft = $this->draftFor(
                            $basis,
                            $phase,
                            eventType: $decision === AuditReadAuthorizationDecision::Allow
                                ? 'access.operation_status.unavailable'
                                : 'access.operation_status.accessed',
                        );
                    } else {
                        $terminalDraft = $this->draftFor($basis, $phase, ...$this->mutationArguments($name));
                    }
                    try {
                        $gate->terminal(AuditReadContext::fromBasis($basis, $decision), $terminalDraft);
                        self::fail($name . ' ' . $decision->value . ' substitution was accepted');
                    } catch (AuditDeliveryException $exception) {
                        self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
                        $reached++;
                    }
                }
                self::assertSame(0, $this->countRecords($probe, $schema));
                self::assertFalse($primary->isTransactionActive());
            } finally {
                $this->cleanupRealFixture($admin, $primary, $probe, $schema);
            }
        }
        self::assertSame(count($cases) * 4, $reached);
    }

    public function testProtectedReadBindingChecksEveryDraftFieldIndependentlyWithoutDelivery(): void
    {
        $cases = [
            'event-version' => ['eventVersion' => 2],
            'event-category' => ['eventCategory' => AuditEventCategory::Business],
            'event-catalog-hash' => ['catalogHash' => str_repeat('a', 64)],
            'action-phase' => ['actionPhase' => AuditActionPhase::Started],
            'resource-type' => ['resourceType' => 'other'],
            'resource-id' => ['resourceId' => 'oid:v1:' . str_repeat('b', 63)],
            'outcome-status' => ['outcomeStatus' => AuditOutcomeStatus::Failed],
            'outcome-code' => ['outcomeCode' => 'read_mutated'],
            'policy-id' => ['policyId' => 'other-policy'],
            'policy-version' => ['policyVersion' => 2],
            'policy-decision' => ['policyDecision' => AuditPolicyDecision::Allow],
            'policy-reason' => ['policyReason' => 'mutated_reason'],
            'policy-delivery' => ['policyDelivery' => AuditDelivery::Durable],
            'source-owner' => ['sourceOwner' => AuditSourceOwner::Application],
            'source-component' => ['sourceComponent' => 'operation-mutated'],
            'correlation' => ['correlation' => AuditRecordCorrelation::fromArray(['correlationId' => 'mutated'])],
            'evidence-version' => ['evidenceVersion' => 2],
            'evidence-fields' => ['evidenceFields' => ['unexpected' => true]],
        ];
        foreach ($cases as $name => $arguments) {
            [$admin, $primary, $probe, $schema, $gate] = $this->realFixture();
            try {
                $basis = $this->basis(AuditReadPurpose::Status);
                try {
                    $gate->requested($basis, $this->draftFor($basis, 'requested', ...$arguments));
                    self::fail($name . ' requested mutation was accepted');
                } catch (AuditDeliveryException $exception) {
                    self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure(), $name);
                    self::assertNull($exception->getPrevious(), $name);
                }
                foreach (AuditReadAuthorizationDecision::cases() as $decision) {
                    $phase = $decision === AuditReadAuthorizationDecision::Allow ? 'accessed' : 'unavailable';
                    $terminalArguments = $arguments;
                    if ($name === 'policy-decision') {
                        $terminalArguments['policyDecision'] = match ($decision) {
                            AuditReadAuthorizationDecision::Allow => AuditPolicyDecision::Deny,
                            AuditReadAuthorizationDecision::Deny,
                            AuditReadAuthorizationDecision::ResourceUnavailable,
                                => AuditPolicyDecision::Allow,
                        };
                    }
                    try {
                        $gate->terminal(
                            AuditReadContext::fromBasis($basis, $decision),
                            $this->draftFor($basis, $phase, ...$terminalArguments),
                        );
                        self::fail($name . ' ' . $decision->value . ' mutation was accepted');
                    } catch (AuditDeliveryException $exception) {
                        self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure(), $name);
                        self::assertNull($exception->getPrevious(), $name);
                    }
                }
                self::assertSame(0, $this->countRecords($probe, $schema), $name);
                self::assertFalse($primary->isTransactionActive(), $name);
            } finally {
                $this->cleanupRealFixture($admin, $primary, $probe, $schema);
            }
        }
    }

    public function testProtectedReadBindingAcceptsTypedRuntimeAndSafeBuildValues(): void
    {
        foreach (AuditSourceRuntime::cases() as $runtime) {
            [$admin, $primary, $probe, $schema, $gate] = $this->realFixture();
            try {
                $basis = $this->basis(AuditReadPurpose::Status);
                $gate->requested($basis, $this->draftFor(
                    $basis,
                    'requested',
                    sourceRuntime: $runtime,
                    sourceBuildId: 'build-' . $runtime->value,
                ));
                self::assertSame(1, $this->countRecords($probe, $schema), $runtime->value);
                self::assertFalse($primary->isTransactionActive(), $runtime->value);
            } finally {
                $this->cleanupRealFixture($admin, $primary, $probe, $schema);
            }
        }
    }

    public function testProtectedReadBindingRejectsUnsafeBuildWithoutDelivery(): void
    {
        [$admin, $primary, $probe, $schema, $gate] = $this->realFixture();
        try {
            $basis = $this->basis();
            $draft = $this->draftFor($basis, 'requested');
            $sourceReflection = new ReflectionClass(AuditRecordSource::class);
            $source = $sourceReflection->newInstanceWithoutConstructor();
            foreach ([
                'owner' => AuditSourceOwner::Framework,
                'component' => 'operation-status',
                'runtime' => AuditSourceRuntime::Cli,
                'buildId' => "build-\0unsafe",
            ] as $property => $value) {
                $sourceReflection->getProperty($property)->setValue($source, $value);
            }
            $draftReflection = new ReflectionClass(AuditRecordDraft::class);
            $corruptDraft = $draftReflection->newInstanceWithoutConstructor();
            foreach ([
                'event',
                'action',
                'actor',
                'resource',
                'outcome',
                'policy',
                'tenant',
                'occurredAt',
                'correlation',
                'evidence',
            ] as $property) {
                $draftReflection->getProperty($property)->setValue($corruptDraft, $draft->{$property});
            }
            $draftReflection->getProperty('source')->setValue($corruptDraft, $source);
            try {
                $gate->requested($basis, $corruptDraft);
                self::fail('unsafe build was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
            self::assertSame(0, $this->countRecords($probe, $schema));
            self::assertFalse($primary->isTransactionActive());
        } finally {
            $this->cleanupRealFixture($admin, $primary, $probe, $schema);
        }
    }

    public function testMalformedContextRequestReferenceIsRejectedFromReadonlySafeFixture(): void
    {
        $delivery = new ReflectionClass(AuditDeliveryCoordinator::class)->newInstanceWithoutConstructor();
        $gate = new AuditProtectedReadGate($delivery);
        $basis = $this->basis();
        $context = $this->malformedContext($basis, AuditReadAuthorizationDecision::Allow, 'malformed-request-ref');

        try {
            $gate->terminal($context, $this->draftFor($basis, 'accessed'));
            self::fail('malformed context request reference was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('malformed-request-ref', $exception->getTraceAsString());
        }
    }

    public function testActorAndTenantNullabilityQuadrantsRejectInRequestedAndTerminalBranches(): void
    {
        $quadrants = [
            [
                'basisActor' => new AuditTypedReference('user', 'actor-present'),
                'basisTenant' => new AuditTypedReference('account', 'tenant-present'),
                'draftActor' => null,
                'draftTenant' => null,
            ],
            [
                'basisActor' => new AuditTypedReference('user', 'actor-present'),
                'basisTenant' => null,
                'draftActor' => null,
                'draftTenant' => new AuditTypedReference('account', 'tenant-present'),
            ],
            [
                'basisActor' => null,
                'basisTenant' => new AuditTypedReference('account', 'tenant-present'),
                'draftActor' => new AuditTypedReference('user', 'actor-present'),
                'draftTenant' => null,
            ],
            [
                'basisActor' => null,
                'basisTenant' => null,
                'draftActor' => new AuditTypedReference('user', 'actor-present'),
                'draftTenant' => new AuditTypedReference('account', 'tenant-present'),
            ],
        ];
        foreach ($quadrants as $quadrant) {
            [$admin, $primary, $probe, $schema, $gate] = $this->realFixture();
            try {
                $basis = $this->basis(
                    AuditReadPurpose::Status,
                    $quadrant['basisActor'],
                    $quadrant['basisTenant'],
                    overrideActor: true,
                    overrideTenant: true,
                );
                $requestedDraft = $this->draftFor(
                    $basis,
                    'requested',
                    actor: $quadrant['draftActor'],
                    tenant: $quadrant['draftTenant'],
                    overrideActor: true,
                    overrideTenant: true,
                );
                try {
                    $gate->requested($basis, $requestedDraft);
                    self::fail('requested nullable binding substitution was accepted');
                } catch (AuditDeliveryException $exception) {
                    self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
                }

                $terminalDraft = $this->draftFor(
                    $basis,
                    'accessed',
                    actor: $quadrant['draftActor'],
                    tenant: $quadrant['draftTenant'],
                    overrideActor: true,
                    overrideTenant: true,
                );
                try {
                    $gate->terminal(
                        AuditReadContext::fromBasis($basis, AuditReadAuthorizationDecision::Allow),
                        $terminalDraft,
                    );
                    self::fail('terminal nullable binding substitution was accepted');
                } catch (AuditDeliveryException $exception) {
                    self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
                }
                self::assertSame(0, $this->countRecords($probe, $schema));
                self::assertFalse($primary->isTransactionActive());
            } finally {
                $this->cleanupRealFixture($admin, $primary, $probe, $schema);
            }
        }
    }

    public function testSingleAxisNullableSubstitutionsRejectWithOtherBindingValid(): void
    {
        $actor = new AuditTypedReference('user', 'actor-present');
        $tenant = new AuditTypedReference('account', 'tenant-present');
        $cases = [
            [
                'label' => 'actor-present-to-null',
                'basisActor' => $actor,
                'basisTenant' => $tenant,
                'draftActor' => null,
                'draftTenant' => $tenant,
            ],
            [
                'label' => 'actor-null-to-present',
                'basisActor' => null,
                'basisTenant' => $tenant,
                'draftActor' => $actor,
                'draftTenant' => $tenant,
            ],
            [
                'label' => 'tenant-present-to-null',
                'basisActor' => $actor,
                'basisTenant' => $tenant,
                'draftActor' => $actor,
                'draftTenant' => null,
            ],
            [
                'label' => 'tenant-null-to-present',
                'basisActor' => $actor,
                'basisTenant' => null,
                'draftActor' => $actor,
                'draftTenant' => $tenant,
            ],
        ];
        foreach ($cases as $case) {
            [$admin, $primary, $probe, $schema, $gate] = $this->realFixture();
            try {
                $basis = $this->basis(
                    AuditReadPurpose::Status,
                    $case['basisActor'],
                    $case['basisTenant'],
                    overrideActor: true,
                    overrideTenant: true,
                );
                $requestedDraft = $this->draftFor(
                    $basis,
                    'requested',
                    actor: $case['draftActor'],
                    tenant: $case['draftTenant'],
                    overrideActor: true,
                    overrideTenant: true,
                );
                try {
                    $gate->requested($basis, $requestedDraft);
                    self::fail($case['label'] . ' requested nullable substitution was accepted');
                } catch (AuditDeliveryException $exception) {
                    self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
                }

                $terminalDraft = $this->draftFor(
                    $basis,
                    'accessed',
                    actor: $case['draftActor'],
                    tenant: $case['draftTenant'],
                    overrideActor: true,
                    overrideTenant: true,
                );
                try {
                    $gate->terminal(
                        AuditReadContext::fromBasis($basis, AuditReadAuthorizationDecision::Allow),
                        $terminalDraft,
                    );
                    self::fail($case['label'] . ' terminal nullable substitution was accepted');
                } catch (AuditDeliveryException $exception) {
                    self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
                }
                self::assertSame(0, $this->countRecords($probe, $schema));
                self::assertFalse($primary->isTransactionActive());
            } finally {
                $this->cleanupRealFixture($admin, $primary, $probe, $schema);
            }
        }
    }

    private function malformedContext(
        AuditReadRequestBasis $basis,
        AuditReadAuthorizationDecision $decision,
        string $requestRef,
    ): AuditReadContext {
        $reflection = new ReflectionClass(AuditReadContext::class);
        $context = $reflection->newInstanceWithoutConstructor();
        foreach ([
            'purpose' => $basis->purpose,
            'actor' => $basis->actor,
            'execution' => $basis->execution,
            'tenant' => $basis->tenant,
            'requestedAt' => $basis->requestedAt,
            'nonce' => $basis->nonce,
            'requestRef' => $requestRef,
            'authorizationDecision' => $decision,
        ] as $name => $value) {
            $reflection->getProperty($name)->setValue($context, $value);
        }

        return $context;
    }

    private function basis(
        AuditReadPurpose $purpose = AuditReadPurpose::Status,
        ?AuditTypedReference $actor = null,
        ?AuditTypedReference $tenant = null,
        bool $overrideActor = false,
        bool $overrideTenant = false,
    ): AuditReadRequestBasis {
        $basisActor = $overrideActor ? $actor : new AuditTypedReference('user', 'user-1');
        $basisTenant = $overrideTenant ? $tenant : new AuditTypedReference('account', 'tenant-1');

        return new AuditReadRequestBasis(
            $purpose,
            $basisActor,
            new AuditTypedReference('execution', 'execution-1'),
            $basisTenant,
            '2026-09-02T00:00:00.000000Z',
            'nonce-1',
        );
    }

    private function draft(
        AuditReadRequestBasis $basis,
        string $phase,
        string $actionId,
        ?string $actionTypeOverride = null,
    ): AuditRecordDraft {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $type = 'access.operation_' . $basis->purpose->value . '.' . $phase;
        foreach ($catalog->events as $definition) {
            if ($definition->type !== $type || $definition->delivery !== AuditDelivery::Required) {
                continue;
            }
            $tuple = $definition->semanticTuples[0];
            $actionType = $actionTypeOverride ?? $type;
            return new AuditRecordDraft(
                new AuditRecordEvent(
                    $definition->type,
                    $definition->version,
                    $definition->category,
                    $catalog->catalogHash,
                ),
                new AuditRecordAction($actionId, $actionType, $tuple->phase),
                new AuditRecordActor($basis->actor, $basis->execution),
                new AuditRecordResource($tuple->resourceType, 'oid:v1:' . str_repeat('a', 64)),
                new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
                new AuditRecordPolicy(
                    'operation-read',
                    1,
                    $tuple->policyDecision,
                    $tuple->policyReasonCode,
                    AuditDelivery::Required,
                ),
                $basis->tenant,
                $basis->requestedAt,
                new AuditRecordSource(
                    AuditSourceOwner::Framework,
                    'operation-' . $basis->purpose->value,
                    AuditSourceRuntime::Cli,
                    'build-1',
                ),
                AuditRecordCorrelation::fromArray([]),
                new AuditRecordEvidence($definition->evidenceVersion),
            );
        }

        self::fail('read definition unavailable');
    }

    /** @return array<string,mixed> */
    private function mutationArguments(string $name): array
    {
        return match ($name) {
            'purpose' => ['purpose' => AuditReadPurpose::Journal],
            'request-ref' => ['actionId' => 'wrong-request-ref'],
            'principal' => ['actor' => new AuditTypedReference('user', 'wrong-user')],
            'execution' => ['execution' => new AuditTypedReference('execution', 'wrong-execution')],
            'tenant' => ['tenant' => new AuditTypedReference('account', 'wrong-tenant')],
            'occurred-at' => ['occurredAt' => '2026-09-02T00:00:02.000000Z'],
            'action' => ['actionType' => 'access.operation_status.other'],
            'terminal-event' => ['eventType' => 'access.operation_status.accessed'],
            'policy' => ['policyDelivery' => AuditDelivery::Durable],
            'decision' => [],
            default => [],
        };
    }

    private function draftFor(
        AuditReadRequestBasis $basis,
        string $phase,
        ?AuditReadPurpose $purpose = null,
        ?string $actionId = null,
        ?AuditTypedReference $actor = null,
        ?AuditTypedReference $execution = null,
        ?AuditTypedReference $tenant = null,
        ?string $occurredAt = null,
        ?string $actionType = null,
        ?string $eventType = null,
        ?int $eventVersion = null,
        ?AuditEventCategory $eventCategory = null,
        ?string $catalogHash = null,
        ?AuditActionPhase $actionPhase = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?AuditOutcomeStatus $outcomeStatus = null,
        ?string $outcomeCode = null,
        ?string $policyId = null,
        ?int $policyVersion = null,
        ?AuditPolicyDecision $policyDecision = null,
        ?string $policyReason = null,
        ?AuditDelivery $policyDelivery = null,
        ?AuditSourceOwner $sourceOwner = null,
        ?string $sourceComponent = null,
        ?AuditSourceRuntime $sourceRuntime = null,
        ?string $sourceBuildId = null,
        ?AuditRecordCorrelation $correlation = null,
        ?int $evidenceVersion = null,
        ?array $evidenceFields = null,
        bool $overrideActor = false,
        bool $overrideTenant = false,
    ): AuditRecordDraft {
        $draftPurpose = $purpose ?? $basis->purpose;
        $draftActor = $basis->actor;
        if ($overrideActor || $actor !== null) {
            $draftActor = $actor;
        }
        $draftTenant = $basis->tenant;
        if ($overrideTenant || $tenant !== null) {
            $draftTenant = $tenant;
        }
        $catalog = FrameworkAuditEventCatalog::catalog();
        $type = 'access.operation_' . $draftPurpose->value . '.' . $phase;
        foreach ($catalog->events as $definition) {
            if ($definition->type !== $type || $definition->delivery !== AuditDelivery::Required) {
                continue;
            }
            $tuple = $definition->semanticTuples[0];

            return new AuditRecordDraft(
                new AuditRecordEvent(
                    $eventType ?? $definition->type,
                    $eventVersion ?? $definition->version,
                    $eventCategory ?? $definition->category,
                    $catalogHash ?? $catalog->catalogHash,
                ),
                new AuditRecordAction(
                    $actionId ?? $basis->requestRef(),
                    $actionType ?? $definition->type,
                    $actionPhase ?? $tuple->phase,
                ),
                new AuditRecordActor($draftActor, $execution ?? $basis->execution),
                new AuditRecordResource(
                    $resourceType ?? $tuple->resourceType,
                    $resourceId ?? 'oid:v1:' . str_repeat('a', 64),
                ),
                new AuditRecordOutcome($outcomeStatus ?? $tuple->outcomeStatus, $outcomeCode ?? $tuple->outcomeCode),
                new AuditRecordPolicy(
                    $policyId ?? 'operation-read',
                    $policyVersion ?? 1,
                    $policyDecision ?? $tuple->policyDecision,
                    $policyReason ?? $tuple->policyReasonCode,
                    $policyDelivery ?? AuditDelivery::Required,
                ),
                $draftTenant,
                $occurredAt ?? $basis->requestedAt,
                new AuditRecordSource(
                    $sourceOwner ?? AuditSourceOwner::Framework,
                    $sourceComponent ?? 'operation-' . $basis->purpose->value,
                    $sourceRuntime ?? AuditSourceRuntime::Cli,
                    $sourceBuildId ?? 'build-1',
                ),
                $correlation ?? AuditRecordCorrelation::fromArray([]),
                new AuditRecordEvidence($evidenceVersion ?? $definition->evidenceVersion, $evidenceFields ?? []),
            );
        }

        self::fail('read definition unavailable');
    }

    /** @return array{0:Connection,1:Connection,2:Connection,3:string,4:AuditProtectedReadGate} */
    private function realFixture(): array
    {
        $admin = $this->connection();
        $primary = $this->connection();
        $probe = $this->connection();
        $schema = 'blackops_audit_read_' . bin2hex(random_bytes(6));
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
            new C3ReadAuditKeySource(),
            new C3ReadNonceSource(),
            new AuditAssociatedData(),
            $records,
            $ingress,
        );
        $clock = new C3ReadClock();
        $uuids = new C3ReadUuidGenerator();
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
            new AuditProtectedReadGate(new AuditDeliveryCoordinator($primary, $selector, $recordStore, $outboxStore)),
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

    private function countRecords(Connection $connection, string $schema): int
    {
        return (int) $connection->fetchOne(
            'SELECT count(*) FROM ' . new PostgreSqlAuditSchema($schema)->recordsTable(),
        );
    }

    private function cleanupRealFixture(Connection $admin, Connection $primary, Connection $probe, string $schema): void
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
}

final class C3ReadClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-02T00:00:01.000000Z');
    }
}

final class C3ReadUuidGenerator implements Uuidv7Generator
{
    private int $counter = 0;

    public function generate(\DateTimeImmutable $time): string
    {
        $this->counter++;

        return '01941f29-7c00-7000-8000-' . sprintf('%012x', $this->counter);
    }
}

final class C3ReadNonceSource implements NonceSource
{
    public function generate(): string
    {
        return str_repeat('n', 24);
    }
}

final class C3ReadAuditKeySource implements \BlackOps\Internal\Audit\Protection\AuditProtectionKeySource
{
    public function activeKey(
        ?AuditTypedReference $tenant,
        \BlackOps\Internal\Audit\Protection\AuditProtectionPurpose $purpose,
    ): \BlackOps\StorageProtection\StorageKey {
        return $purpose === \BlackOps\Internal\Audit\Protection\AuditProtectionPurpose::AuditRecord
            ? new \BlackOps\StorageProtection\StorageKey('read-record-key', str_repeat('r', 32))
            : new \BlackOps\StorageProtection\StorageKey('read-outbox-key', str_repeat('b', 32));
    }

    public function key(
        string $keyId,
        ?AuditTypedReference $tenant,
        \BlackOps\Internal\Audit\Protection\AuditProtectionPurpose $purpose,
    ): \BlackOps\StorageProtection\StorageKey {
        return new \BlackOps\StorageProtection\StorageKey($keyId, str_repeat('h', 32));
    }
}
