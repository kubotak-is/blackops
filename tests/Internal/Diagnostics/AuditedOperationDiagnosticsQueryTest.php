<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Diagnostics;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditReadAuthorizationDecision;
use BlackOps\Core\Audit\AuditReadContext;
use BlackOps\Core\Audit\AuditReadPurpose;
use BlackOps\Core\Audit\AuditReadRequestBasis;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Read\AuditReadEventWriter;
use BlackOps\Internal\Audit\Read\OperationReadAuditSessionFactory;
use BlackOps\Internal\Audit\Read\OperationReadExecutionSource;
use BlackOps\Internal\Audit\Read\OperationReadNonceSource;
use BlackOps\Internal\Diagnostics\AuditedOperationDiagnosticsQuery;
use BlackOps\Internal\Diagnostics\DiagnosticsDeferredState;
use BlackOps\Internal\Diagnostics\DiagnosticsFailureCode;
use BlackOps\Internal\Diagnostics\DiagnosticsPurgeAudit;
use BlackOps\Internal\Diagnostics\DiagnosticsSafeProjector;
use BlackOps\Internal\Diagnostics\DiagnosticsSourceReader;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsAuthorizationRequest;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsAuthorizer;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsException;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsFound;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsQuery;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsReadExecutionSource;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsUnavailable;
use BlackOps\Journal\CanonicalJournalReader;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\Exception\JournalReadFailed;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use BlackOps\Outcome\OutcomeReader;
use BlackOps\Outcome\OutcomeRecord;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final class AuditedOperationDiagnosticsQueryTest extends TestCase
{
    private const string OPERATION_ID = '019f80ab-3000-7000-8000-000000000001';
    private const string ACTOR_ID = 'diagnostics-reader-actor';
    private const string TENANT_ID = 'diagnostics-reader-tenant';
    private const string DEPENDENCY_CANARY = 'diagnostics-retained-dependency-canary';
    private const string READER_CANARY = 'diagnostics-reader-failure-private-canary';
    private const string RAW_CANARY = 'diagnostics.raw.record.private.canary';

    public function testAllowedGlobalReadUsesFixedPurposeAndPreservesFound(): void
    {
        $events = [];
        $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
        $reader = new DiagnosticsAdapterJournalReader($events, [], self::DEPENDENCY_CANARY);
        $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
        $sources = new DiagnosticsAdapterSourceReader(
            $events,
            $this->acceptedState(),
            $this->journalPurged(),
            self::DEPENDENCY_CANARY,
        );
        $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow);
        $actor = new ActorRef(self::ACTOR_ID, 'user');
        $operationId = $this->operationId();
        $query = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer);

        $result = $query->find($operationId, $actor, null);

        self::assertInstanceOf(OperationDiagnosticsFound::class, $result);
        self::assertSame(LifecycleState::Accepted, $result->diagnostics->state->current);
        self::assertSame(1, $authorizer->calls);
        self::assertSame($operationId, $authorizer->request?->operationId());
        self::assertSame($actor, $authorizer->request?->currentActor());
        self::assertNull($authorizer->request?->currentTenant());
        self::assertSame(1, $reader->calls);
        self::assertSame(1, $outcomes->calls);
        self::assertSame(['requested', 'authorize', 'read', 'outcome', 'terminal:accessed'], $events);
        self::assertSame(AuditReadPurpose::Diagnostics, $writer->requested[0][0]->purpose);
        self::assertSame(AuditReadPurpose::Diagnostics, $writer->terminals[0][0]->purpose);
        self::assertSame(AuditReadAuthorizationDecision::Allow, $writer->terminals[0][0]->authorizationDecision);
        self::assertStringNotContainsString(
            'operation_data',
            (string) json_encode($writer->requested[0][1]->toArray(), JSON_THROW_ON_ERROR),
        );
    }

    public function testNonNullTenantIsUnavailableWithoutAuthorizerOrDiagnosticsReads(): void
    {
        $events = [];
        $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
        $reader = new DiagnosticsAdapterJournalReader($events, [], self::DEPENDENCY_CANARY);
        $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
        $sources = new DiagnosticsAdapterSourceReader(
            $events,
            $this->acceptedState(),
            $this->journalPurged(),
            self::DEPENDENCY_CANARY,
        );
        $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow);
        $query = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer);

        $result = $query->find(
            $this->operationId(),
            new ActorRef(self::ACTOR_ID, 'user'),
            new TenantRef('customer', self::TENANT_ID),
        );

        self::assertInstanceOf(OperationDiagnosticsUnavailable::class, $result);
        self::assertSame('operation.unavailable', $result->code);
        self::assertSame(0, $authorizer->calls);
        self::assertSame(0, $reader->calls);
        self::assertSame(0, $outcomes->calls);
        self::assertSame(0, $sources->calls);
        self::assertSame(['requested', 'terminal:unavailable'], $events);
        self::assertSame(AuditReadPurpose::Diagnostics, $writer->requested[0][0]->purpose);
    }

    public function testDenyAndResourceUnavailableAreUnavailableWithoutAggregateRead(): void
    {
        foreach ([
            AuditReadAuthorizationDecision::Deny,
            AuditReadAuthorizationDecision::ResourceUnavailable,
        ] as $decision) {
            $events = [];
            $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
            $reader = new DiagnosticsAdapterJournalReader($events, [], self::DEPENDENCY_CANARY);
            $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
            $sources = new DiagnosticsAdapterSourceReader($events, $this->acceptedState(), [], self::DEPENDENCY_CANARY);
            $authorizer = new DiagnosticsAdapterAuthorizer($events, $decision);
            $query = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer);

            $result = $query->find($this->operationId(), null, null);

            self::assertInstanceOf(OperationDiagnosticsUnavailable::class, $result);
            self::assertSame(1, $authorizer->calls);
            self::assertSame(0, $reader->calls);
            self::assertSame(0, $outcomes->calls);
            self::assertSame(0, $sources->calls);
            self::assertSame(['requested', 'authorize', 'terminal:unavailable'], $events);
        }
    }

    public function testAuthorizerDiagnosticsFailureBecomesFreshStorageAfterUnavailable(): void
    {
        $events = [];
        $original = OperationDiagnosticsException::decodeFailed();
        $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
        $reader = new DiagnosticsAdapterJournalReader($events, [], self::DEPENDENCY_CANARY);
        $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
        $sources = new DiagnosticsAdapterSourceReader(
            $events,
            $this->acceptedState(),
            $this->journalPurged(),
            self::DEPENDENCY_CANARY,
        );
        $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow, $original);
        $query = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer);

        $failure = $this->captureFailure(fn() => $query->find($this->operationId(), null, null));

        $this->assertFreshFailure($failure, DiagnosticsFailureCode::StorageFailed);
        self::assertNotSame($original, $failure);
        self::assertSame(1, $authorizer->calls);
        self::assertSame(0, $reader->calls);
        self::assertSame(['requested', 'authorize', 'terminal:unavailable'], $events);
    }

    public function testInvalidAuthorizerReturnTypeBecomesFreshStorageWithoutAggregateRead(): void
    {
        $events = [];
        $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
        $reader = new DiagnosticsAdapterJournalReader($events, [], self::DEPENDENCY_CANARY);
        $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
        $sources = new DiagnosticsAdapterSourceReader(
            $events,
            $this->acceptedState(),
            $this->journalPurged(),
            self::DEPENDENCY_CANARY,
        );
        $authorizer = $this->createMock(OperationDiagnosticsAuthorizer::class);
        $authorizer
            ->expects(self::once())
            ->method('decide')
            ->willReturnCallback(static fn() => 'invalid');
        $query = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer);

        $failure = $this->captureFailure(fn() => $query->find($this->operationId(), null, null));

        $this->assertFreshFailure($failure, DiagnosticsFailureCode::StorageFailed);
        self::assertSame(0, $reader->calls);
        self::assertSame(0, $outcomes->calls);
        self::assertSame(['requested', 'terminal:unavailable'], $events);
    }

    public function testLegacyDiagnosticsCodesAreReconstructedOnlyAfterUnavailable(): void
    {
        foreach (DiagnosticsFailureCode::cases() as $code) {
            $events = [];
            $original = match ($code) {
                DiagnosticsFailureCode::StorageFailed => OperationDiagnosticsException::storageFailed(),
                DiagnosticsFailureCode::DecodeFailed => OperationDiagnosticsException::decodeFailed(),
                DiagnosticsFailureCode::IntegrityFailed => OperationDiagnosticsException::integrityFailed(),
            };
            $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
            $reader = new DiagnosticsAdapterJournalReader($events, [], self::DEPENDENCY_CANARY);
            $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
            $sources = new DiagnosticsAdapterSourceReader(
                $events,
                $this->acceptedState(),
                [],
                self::DEPENDENCY_CANARY,
                $original,
            );
            $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow);
            $query = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer);

            $failure = $this->captureFailure(fn() => $query->find($this->operationId(), null, null));

            $this->assertFreshFailure($failure, $code);
            self::assertNotSame($original, $failure);
            self::assertSame(1, $writer->terminalCalls);
            self::assertSame(0, $reader->calls);
            self::assertSame(['requested', 'authorize', 'terminal:unavailable'], $events);
        }
    }

    public function testUnreadableDiagnosticsCodeBecomesFreshStorage(): void
    {
        $events = [];
        $original = new ReflectionClass(OperationDiagnosticsException::class)->newInstanceWithoutConstructor();
        $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
        $reader = new DiagnosticsAdapterJournalReader($events, [], self::DEPENDENCY_CANARY);
        $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
        $sources = new DiagnosticsAdapterSourceReader(
            $events,
            $this->acceptedState(),
            [],
            self::DEPENDENCY_CANARY,
            $original,
        );
        $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow);
        $query = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer);

        $failure = $this->captureFailure(fn() => $query->find($this->operationId(), null, null));

        $this->assertFreshFailure($failure, DiagnosticsFailureCode::StorageFailed);
        self::assertNotSame($original, $failure);
        self::assertSame(1, $writer->terminalCalls);
        self::assertSame(['requested', 'authorize', 'terminal:unavailable'], $events);
    }

    public function testLateJournalFailureIsFullyMaterializedBeforeUnavailableTerminal(): void
    {
        $events = [];
        $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
        $reader = new DiagnosticsAdapterJournalReader(
            $events,
            [$this->record()],
            self::DEPENDENCY_CANARY,
            new JournalReadFailed('late reader failure', 0, new RuntimeException(self::READER_CANARY)),
        );
        $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
        $sources = new DiagnosticsAdapterSourceReader($events, null, [], self::DEPENDENCY_CANARY);
        $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow);
        $query = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer);

        $failure = $this->captureFailure(fn() => $query->find($this->operationId(), null, null));

        $this->assertFreshFailure($failure, DiagnosticsFailureCode::DecodeFailed);
        self::assertSame(1, $reader->calls);
        self::assertSame(1, $reader->yielded);
        self::assertSame(1, $writer->terminalCalls);
        self::assertSame(['requested', 'authorize', 'read', 'yielded', 'late', 'terminal:unavailable'], $events);
    }

    public function testEntryAndPhaseIdleFailuresWithholdAllLaterWork(): void
    {
        $state = 'active';
        $events = [];
        $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
        $reader = new DiagnosticsAdapterJournalReader($events, [], self::DEPENDENCY_CANARY);
        $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
        $sources = new DiagnosticsAdapterSourceReader(
            $events,
            $this->acceptedState(),
            $this->journalPurged(),
            self::DEPENDENCY_CANARY,
        );
        $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow);
        $entry = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer, $state);
        $failure = $this->captureFailure(fn() => $entry->find($this->operationId(), null, null));
        $this->assertFreshFailure($failure, DiagnosticsFailureCode::StorageFailed);
        self::assertSame([], $events);

        $state = 'idle';
        $events = [];
        $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
        $writer->afterRequested = static function () use (&$state): void {
            $state = 'active';
        };
        $reader = new DiagnosticsAdapterJournalReader($events, [], self::DEPENDENCY_CANARY);
        $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
        $sources = new DiagnosticsAdapterSourceReader(
            $events,
            $this->acceptedState(),
            $this->journalPurged(),
            self::DEPENDENCY_CANARY,
        );
        $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow);
        $postBegin = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer, $state);
        $failure = $this->captureFailure(fn() => $postBegin->find($this->operationId(), null, null));
        $this->assertFreshFailure($failure, DiagnosticsFailureCode::StorageFailed);
        self::assertSame(['requested'], $events);
        self::assertSame(0, $authorizer->calls);
        self::assertSame(0, $reader->calls);
        self::assertSame(0, $writer->terminalCalls);

        $state = 'idle';
        $events = [];
        $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
        $reader = new DiagnosticsAdapterJournalReader($events, [], self::DEPENDENCY_CANARY);
        $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
        $sources = new DiagnosticsAdapterSourceReader(
            $events,
            $this->acceptedState(),
            $this->journalPurged(),
            self::DEPENDENCY_CANARY,
        );
        $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow);
        $authorizer->afterDecide = static function () use (&$state): void {
            $state = 'active';
        };
        $postAuthorizer = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer, $state);
        $failure = $this->captureFailure(fn() => $postAuthorizer->find($this->operationId(), null, null));
        $this->assertFreshFailure($failure, DiagnosticsFailureCode::StorageFailed);
        self::assertSame(['requested', 'authorize'], $events);
        self::assertSame(0, $reader->calls);
        self::assertSame(0, $writer->terminalCalls);

        $state = 'idle';
        $events = [];
        $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
        $reader = new DiagnosticsAdapterJournalReader($events, [], self::DEPENDENCY_CANARY);
        $reader->afterRead = static function () use (&$state): void {
            $state = 'active';
        };
        $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
        $sources = new DiagnosticsAdapterSourceReader(
            $events,
            $this->acceptedState(),
            $this->journalPurged(),
            self::DEPENDENCY_CANARY,
        );
        $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow);
        $postQuery = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer, $state);
        $failure = $this->captureFailure(fn() => $postQuery->find($this->operationId(), null, null));
        $this->assertFreshFailure($failure, DiagnosticsFailureCode::StorageFailed);
        self::assertSame(['requested', 'authorize', 'read', 'outcome'], $events);
        self::assertSame(0, $writer->terminalCalls);

        $state = 'idle';
        $events = [];
        $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
        $writer->afterTerminal = static function () use (&$state): void {
            $state = 'active';
        };
        $reader = new DiagnosticsAdapterJournalReader($events, [], self::DEPENDENCY_CANARY);
        $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
        $sources = new DiagnosticsAdapterSourceReader(
            $events,
            $this->acceptedState(),
            $this->journalPurged(),
            self::DEPENDENCY_CANARY,
        );
        $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow);
        $postTerminal = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer, $state);
        $failure = $this->captureFailure(fn() => $postTerminal->find($this->operationId(), null, null));
        $this->assertFreshFailure($failure, DiagnosticsFailureCode::StorageFailed);
        self::assertSame(1, $writer->terminalCalls);
        self::assertSame(['requested', 'authorize', 'read', 'outcome', 'terminal:accessed'], $events);
    }

    public function testBeginFailurePerformsNoAuthorizationOrQueryOrTerminalRetry(): void
    {
        $events = [];
        $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
        $writer->requestedFailure = new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        $reader = new DiagnosticsAdapterJournalReader($events, [], self::DEPENDENCY_CANARY);
        $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
        $sources = new DiagnosticsAdapterSourceReader($events, $this->acceptedState(), [], self::DEPENDENCY_CANARY);
        $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow);
        $query = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer);

        $failure = $this->captureFailure(fn() => $query->find($this->operationId(), null, null));

        $this->assertFreshFailure($failure, DiagnosticsFailureCode::StorageFailed);
        self::assertSame(0, $authorizer->calls);
        self::assertSame(0, $reader->calls);
        self::assertSame(0, $writer->terminalCalls);
        self::assertSame(['requested'], $events);
    }

    public function testTerminalFailureMasksFoundUnavailableAndFailuresWithoutRetry(): void
    {
        $cases = [
            'found' => [true, null],
            'unavailable' => [false, null],
            'storage' => [false, DiagnosticsFailureCode::StorageFailed],
            'decode' => [false, DiagnosticsFailureCode::DecodeFailed],
            'integrity' => [false, DiagnosticsFailureCode::IntegrityFailed],
        ];
        foreach ($cases as $name => [$found, $code]) {
            $events = [];
            $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
            $writer->terminalFailure = new AuditDeliveryException(AuditDeliveryFailure::ResourceUnavailable);
            $reader = new DiagnosticsAdapterJournalReader($events, [], self::DEPENDENCY_CANARY);
            $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
            $sources = new DiagnosticsAdapterSourceReader(
                $events,
                $found ? $this->acceptedState() : null,
                $found ? $this->journalPurged() : [],
                self::DEPENDENCY_CANARY,
                $code === null
                    ? null
                    : match ($code) {
                        DiagnosticsFailureCode::StorageFailed => OperationDiagnosticsException::storageFailed(),
                        DiagnosticsFailureCode::DecodeFailed => OperationDiagnosticsException::decodeFailed(),
                        DiagnosticsFailureCode::IntegrityFailed => OperationDiagnosticsException::integrityFailed(),
                    },
            );
            $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow);
            $query = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer);

            if ($name === 'unavailable') {
                $failure = $this->captureFailure(fn() => $query->find(
                    $this->operationId(),
                    null,
                    new TenantRef('customer', self::TENANT_ID),
                ));
            } else {
                $failure = $this->captureFailure(fn() => $query->find($this->operationId(), null, null));
            }

            $this->assertFreshFailure($failure, DiagnosticsFailureCode::StorageFailed);
            self::assertSame(1, $writer->terminalCalls);
        }
    }

    public function testRepeatedRequestsAndCloneUseCurrentCallerAndNoPriorResult(): void
    {
        $events = [];
        $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
        $reader = new DiagnosticsAdapterJournalReader($events, [], self::DEPENDENCY_CANARY);
        $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
        $sources = new DiagnosticsAdapterSourceReader(
            $events,
            $this->acceptedState(),
            $this->journalPurged(),
            self::DEPENDENCY_CANARY,
        );
        $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow);
        $query = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer);
        $operationId = $this->operationId();

        $first = $query->find($operationId, new ActorRef('first-actor', 'user'), null);
        $second = $query->find(
            $operationId,
            new ActorRef('second-actor', 'user'),
            new TenantRef('customer', 'second-tenant'),
        );
        $clone = clone $query;
        $third = $clone->find($operationId, new ActorRef('clone-actor', 'user'), null);

        self::assertInstanceOf(OperationDiagnosticsFound::class, $first);
        self::assertInstanceOf(OperationDiagnosticsUnavailable::class, $second);
        self::assertInstanceOf(OperationDiagnosticsFound::class, $third);
        self::assertNotSame($first, $third);
        self::assertSame(2, $authorizer->calls);
        self::assertCount(2, $authorizer->requests);
        self::assertSame('first-actor', $authorizer->requests[0]->currentActor()?->id());
        self::assertSame('clone-actor', $authorizer->requests[1]->currentActor()?->id());
        self::assertSame(2, $reader->calls);
        self::assertSame(2, $outcomes->calls);
        self::assertSame(3, $writer->terminalCalls);
        self::assertSame(
            [
                'requested',
                'authorize',
                'read',
                'outcome',
                'terminal:accessed',
                'requested',
                'terminal:unavailable',
                'requested',
                'authorize',
                'read',
                'outcome',
                'terminal:accessed',
            ],
            $events,
        );
    }

    public function testSnapshotsRoundTripCorruptionCloneAndSurfacesFailClosed(): void
    {
        $events = [];
        $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
        $reader = new DiagnosticsAdapterJournalReader($events, [], self::DEPENDENCY_CANARY);
        $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
        $sources = new DiagnosticsAdapterSourceReader($events, $this->acceptedState(), [], self::DEPENDENCY_CANARY);
        $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow);
        $executionGenerator = new DiagnosticsAdapterUuidGenerator(self::DEPENDENCY_CANARY);
        $execution = new OperationDiagnosticsReadExecutionSource($executionGenerator);
        $sessions = $this->sessions($writer, $execution);
        $query = new AuditedOperationDiagnosticsQuery(
            $reader,
            $outcomes,
            $sources,
            $authorizer,
            $sessions,
            $this->idleConnection(),
        );

        self::assertSame([], $query->__debugInfo());
        self::assertSame([], $query->__serialize());
        $surface =
            print_r($query, true)
            . var_export($query, true)
            . serialize($query)
            . json_encode($query, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString(self::DEPENDENCY_CANARY, $surface);
        self::assertSame($reader, $this->nestedQueryReader($query));
        self::assertSame($writer, $this->nestedSessionWriter($sessions));
        self::assertSame($executionGenerator, $this->nestedGenerator($execution));

        $roundTripFailure = $this->captureFailure(fn() => unserialize(serialize($query), [
            'allowed_classes' => true,
        ]));
        $this->assertFreshFailure($roundTripFailure, DiagnosticsFailureCode::StorageFailed);

        $uninitialized = new ReflectionClass(AuditedOperationDiagnosticsQuery::class)->newInstanceWithoutConstructor();
        $uninitializedFailure = $this->captureFailure(fn() => $uninitialized->find($this->operationId(), null, null));
        $this->assertFreshFailure($uninitializedFailure, DiagnosticsFailureCode::StorageFailed);

        $reflection = new ReflectionClass($query);
        $snapshot = $reflection->getProperty('snapshot')->getValue($query);
        self::assertInstanceOf(\SensitiveParameterValue::class, $snapshot);
        $values = $snapshot->getValue();
        self::assertIsArray($values);
        /** @var array<string, mixed> $values */
        $values['corrupt'] = self::DEPENDENCY_CANARY;
        $corrupt = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('snapshot')->setValue($corrupt, new \SensitiveParameterValue($values));
        $corruptFailure = $this->captureFailure(fn() => $corrupt->find($this->operationId(), null, null));
        $this->assertFreshFailure($corruptFailure, DiagnosticsFailureCode::StorageFailed);
        self::assertSame([], $events);
        self::assertSame(0, $reader->calls);
        self::assertSame(0, $writer->terminalCalls);

        $clone = clone $query;
        self::assertSame([], $clone->__debugInfo());
        self::assertSame([], $events);
        $cloneResult = $clone->find($this->operationId(), null, new TenantRef('customer', 'clone-tenant'));
        self::assertInstanceOf(OperationDiagnosticsUnavailable::class, $cloneResult);
        self::assertSame(['requested', 'terminal:unavailable'], $events);
    }

    public function testActualLegacyFailureFramesContainCanariesButOutwardFailureDoesNot(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));

        try {
            $events = [];
            $rawReader = new DiagnosticsAdapterJournalReader($events, [$this->rawRecord()], self::RAW_CANARY);
            $legacyRaw = new OperationDiagnosticsQuery(
                $rawReader,
                new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY),
                new DiagnosticsAdapterSourceReader($events, null, [], self::DEPENDENCY_CANARY),
            );
            $rawOriginal = $this->captureFailure(fn() => $legacyRaw->find($this->operationId()));
            $this->assertFrameworkTraceCanary(
                $rawOriginal,
                DiagnosticsSafeProjector::class,
                'eventData',
                self::RAW_CANARY,
            );

            $events = [];
            $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
            $reader = new DiagnosticsAdapterJournalReader($events, [$this->rawRecord()], self::RAW_CANARY);
            $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
            $sources = new DiagnosticsAdapterSourceReader($events, null, [], self::DEPENDENCY_CANARY);
            $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow);
            $adapter = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer);
            $rawOutward = $this->captureFailure(fn() => $adapter->find($this->operationId(), null, null));
            $this->assertFreshFailure($rawOutward, DiagnosticsFailureCode::IntegrityFailed);
            self::assertNotSame($rawOriginal, $rawOutward);
            $this->assertFailureSurfaceOmits($rawOutward, self::RAW_CANARY);

            $readerFailure = new JournalReadFailed('reader failure', 0, new RuntimeException(self::READER_CANARY));
            $events = [];
            $legacyReader = new OperationDiagnosticsQuery(
                new DiagnosticsAdapterJournalReader($events, [], self::READER_CANARY, $readerFailure),
                new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY),
                new DiagnosticsAdapterSourceReader($events, null, [], self::DEPENDENCY_CANARY),
            );
            $readerOriginal = $this->captureFailure(fn() => $legacyReader->find($this->operationId()));
            $this->assertFrameworkTraceCanary(
                $readerOriginal,
                OperationDiagnosticsQuery::class,
                'readerFailure',
                self::READER_CANARY,
            );

            $events = [];
            $writer = new DiagnosticsAdapterWriter($events, self::DEPENDENCY_CANARY);
            $reader = new DiagnosticsAdapterJournalReader($events, [], self::READER_CANARY, $readerFailure);
            $outcomes = new DiagnosticsAdapterOutcomeReader($events, null, self::DEPENDENCY_CANARY);
            $sources = new DiagnosticsAdapterSourceReader($events, null, [], self::DEPENDENCY_CANARY);
            $authorizer = new DiagnosticsAdapterAuthorizer($events, AuditReadAuthorizationDecision::Allow);
            $adapter = $this->adapter($reader, $outcomes, $sources, $authorizer, $writer);
            $readerOutward = $this->captureFailure(fn() => $adapter->find($this->operationId(), null, null));
            $this->assertFreshFailure($readerOutward, DiagnosticsFailureCode::DecodeFailed);
            self::assertNotSame($readerOriginal, $readerOutward);
            $this->assertFailureSurfaceOmits($readerOutward, self::READER_CANARY);
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    /** @param ?string $state */
    private function adapter(
        CanonicalJournalReader $reader,
        OutcomeReader $outcomes,
        DiagnosticsSourceReader $sources,
        OperationDiagnosticsAuthorizer $authorizer,
        DiagnosticsAdapterWriter $writer,
        ?string &$state = null,
    ): AuditedOperationDiagnosticsQuery {
        return new AuditedOperationDiagnosticsQuery(
            $reader,
            $outcomes,
            $sources,
            $authorizer,
            $this->sessions(
                $writer,
                new OperationDiagnosticsReadExecutionSource(new DiagnosticsAdapterUuidGenerator()),
            ),
            $state === null ? $this->idleConnection() : $this->connectionWithState($state),
        );
    }

    private function sessions(
        DiagnosticsAdapterWriter $writer,
        OperationReadExecutionSource $execution,
    ): OperationReadAuditSessionFactory {
        return new OperationReadAuditSessionFactory(
            $writer,
            new HmacAuditOpaqueIdDeriver('0123456789abcdef0123456789abcdef'),
            new DiagnosticsAdapterNonceSource(),
            new DiagnosticsAdapterClock(),
            $execution,
            AuditSourceRuntime::Http,
            'diagnostics-adapter-test-build',
        );
    }

    private function acceptedState(): DiagnosticsDeferredState
    {
        return new DiagnosticsDeferredState(
            self::OPERATION_ID,
            'diagnostics.test',
            1,
            LifecycleState::Accepted,
            3,
            false,
            0,
            null,
            null,
        );
    }

    /** @return list<DiagnosticsPurgeAudit> */
    private function journalPurged(): array
    {
        return [new DiagnosticsPurgeAudit(RetentionPurgeTarget::Journal, 1, '2026-09-15T00:00:00.000000Z')];
    }

    private function record(): JournalRecord
    {
        return new JournalRecord(
            JournalRecordId::fromString('019f80ab-3000-7000-8000-000000000002'),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable('2026-09-15T00:00:00Z'),
            1,
            new JournalOperation(
                $this->operationId(),
                'diagnostics.test',
                1,
                'inline',
                CorrelationId::fromString('019f80ab-3000-7000-8000-000000000003'),
                actorContext: new ActorContext(
                    new ActorRef('origin-user', 'user'),
                    null,
                    new ActorRef('execution-user', 'user'),
                ),
            ),
            null,
            new OperationReceivedData(new DiagnosticsAdapterValue('safe')),
        );
    }

    private function rawRecord(): JournalRecord
    {
        $record = $this->record();
        return new JournalRecord(
            $record->recordId,
            $record->schemaVersion,
            $record->event,
            $record->occurredAt,
            $record->sequence,
            new JournalOperation(
                $record->operation->id,
                self::RAW_CANARY,
                $record->operation->schemaVersion,
                $record->operation->strategy,
                $record->operation->correlationId,
                actorContext: $record->operation->actorContext,
            ),
            $record->attempt,
            new EmptyJournalData(),
        );
    }

    private function operationId(): OperationId
    {
        return OperationId::fromString(self::OPERATION_ID);
    }

    private function idleConnection(): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('isAutoCommit')->willReturn(true);
        $connection->method('isTransactionActive')->willReturn(false);
        $connection->method('getTransactionNestingLevel')->willReturn(0);

        return $connection;
    }

    private function connectionWithState(string &$state): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('isAutoCommit')
            ->willReturnCallback(static function () use (&$state): bool {
                return $state !== 'not-auto-commit';
            });
        $connection
            ->method('isTransactionActive')
            ->willReturnCallback(static function () use (&$state): bool {
                return $state === 'active';
            });
        $connection
            ->method('getTransactionNestingLevel')
            ->willReturnCallback(static function () use (&$state): int {
                return $state === 'nonzero' ? 1 : 0;
            });

        return $connection;
    }

    private function captureFailure(#[\SensitiveParameter] \Closure $call): OperationDiagnosticsException
    {
        try {
            $call();
            self::fail('Expected a Diagnostics failure.');
        } catch (OperationDiagnosticsException $exception) {
            return $exception;
        }
    }

    private function assertFreshFailure(OperationDiagnosticsException $failure, DiagnosticsFailureCode $code): void
    {
        self::assertSame($code, $failure->diagnosticsCode);
        self::assertSame($code->value, $failure->getMessage());
        self::assertSame(0, $failure->getCode());
        self::assertNull($failure->getPrevious());
    }

    private function assertFrameworkTraceCanary(
        Throwable $failure,
        string $class,
        string $function,
        string $canary,
    ): void {
        $frames = array_filter(
            $failure->getTrace(),
            static fn(array $frame): bool => (
                ($frame['class'] ?? null) === $class
                && ($frame['function'] ?? null) === $function
            ),
        );
        self::assertNotEmpty($frames);
        $trace = print_r($frames, true) . var_export($frames, true);
        self::assertStringContainsString($canary, $trace);
    }

    private function assertFailureSurfaceOmits(Throwable $failure, string $canary): void
    {
        $surfaces = [
            'print_r' => print_r($failure, true),
            'serialize' => $this->captureSurface(static fn(): string => serialize($failure)),
            'json' => $this->captureSurface(static fn(): string => (string) json_encode($failure, JSON_THROW_ON_ERROR)),
            'var_export' => var_export($failure, true),
            'trace' => $failure->getTraceAsString(),
            'trace-args' => print_r($failure->getTrace(), true) . var_export($failure->getTrace(), true),
        ];
        ob_start();
        debug_zval_dump($failure);
        $surfaces['debug'] = (string) ob_get_clean();
        foreach ($surfaces as $surface) {
            self::assertStringNotContainsString($canary, $surface);
        }
    }

    private function captureSurface(#[\SensitiveParameter] \Closure $surface): string
    {
        try {
            return $surface();
        } catch (Throwable $exception) {
            return $exception->getMessage();
        }
    }

    private function nestedQueryReader(AuditedOperationDiagnosticsQuery $adapter): object
    {
        $snapshot = new ReflectionClass($adapter)
            ->getProperty('snapshot')
            ->getValue($adapter);
        $values = $snapshot->getValue();
        $query = $values['query'];
        return new ReflectionClass($query)
            ->getProperty('journal')
            ->getValue($query);
    }

    private function nestedSessionWriter(OperationReadAuditSessionFactory $sessions): object
    {
        $snapshot = new ReflectionClass($sessions)
            ->getProperty('snapshot')
            ->getValue($sessions);
        $values = $snapshot->getValue();
        return $values['writer'];
    }

    private function nestedGenerator(OperationDiagnosticsReadExecutionSource $execution): object
    {
        $snapshot = new ReflectionClass($execution)
            ->getProperty('snapshot')
            ->getValue($execution);
        $values = $snapshot->getValue();
        $validated = $values['identifiers'];
        return new ReflectionClass($validated)
            ->getProperty('source')
            ->getValue($validated);
    }
}

final class DiagnosticsAdapterAuthorizer implements OperationDiagnosticsAuthorizer
{
    public int $calls = 0;

    public ?OperationDiagnosticsAuthorizationRequest $request = null;

    /** @var list<OperationDiagnosticsAuthorizationRequest> */
    public array $requests = [];

    public ?\Closure $afterDecide = null;

    public function __construct(
        array &$events,
        private readonly AuditReadAuthorizationDecision $decision,
        private readonly ?Throwable $failure = null,
    ) {
        $this->events = &$events;
    }

    private array $events;

    public function decide(
        #[\SensitiveParameter]
        OperationDiagnosticsAuthorizationRequest $request,
    ): AuditReadAuthorizationDecision {
        $this->events[] = 'authorize';
        ++$this->calls;
        $this->request = $request;
        $this->requests[] = $request;
        if ($this->afterDecide !== null) {
            ($this->afterDecide)();
        }
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->decision;
    }
}

final class DiagnosticsAdapterJournalReader implements CanonicalJournalReader
{
    public int $calls = 0;

    public int $yielded = 0;

    public ?\Closure $afterRead = null;

    public function __construct(
        array &$events,
        private readonly array $records,
        public readonly string $canary,
        private readonly ?Throwable $failureAfter = null,
    ) {
        $this->events = &$events;
    }

    private array $events;

    public function records(#[\SensitiveParameter] OperationId $operationId): iterable
    {
        ++$this->calls;
        $this->events[] = 'read';
        foreach ($this->records as $record) {
            ++$this->yielded;
            $this->events[] = 'yielded';
            yield $record;
        }
        if ($this->failureAfter !== null) {
            $this->events[] = 'late';
            throw $this->failureAfter;
        }
        if ($this->afterRead !== null) {
            ($this->afterRead)();
        }
    }
}

final class DiagnosticsAdapterOutcomeReader implements OutcomeReader
{
    public int $calls = 0;

    public function __construct(
        array &$events,
        private readonly ?OutcomeRecord $record,
        public readonly string $canary,
    ) {
        $this->events = &$events;
    }

    private array $events;

    public function find(#[\SensitiveParameter] OperationId $operationId): ?OutcomeRecord
    {
        ++$this->calls;
        $this->events[] = 'outcome';

        return $this->record;
    }
}

final class DiagnosticsAdapterSourceReader implements DiagnosticsSourceReader
{
    public int $calls = 0;

    public function __construct(
        array &$events,
        private readonly ?DiagnosticsDeferredState $state,
        private readonly array $audits,
        public readonly string $canary,
        private readonly ?OperationDiagnosticsException $failure = null,
    ) {
        $this->events = &$events;
    }

    private array $events;

    public function deferredState(#[\SensitiveParameter] OperationId $operationId): ?DiagnosticsDeferredState
    {
        ++$this->calls;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->state;
    }

    public function deadLetter(
        #[\SensitiveParameter]
        OperationId $operationId,
    ): ?\BlackOps\Internal\Diagnostics\DiagnosticsDeadLetter {
        return null;
    }

    /** @return list<DiagnosticsPurgeAudit> */
    public function purgeAudits(#[\SensitiveParameter] OperationId $operationId): array
    {
        return $this->audits;
    }
}

final class DiagnosticsAdapterWriter implements AuditReadEventWriter
{
    public int $terminalCalls = 0;

    /** @var list<array{AuditReadRequestBasis, AuditRecordDraft}> */
    public array $requested = [];

    /** @var list<array{AuditReadContext, AuditRecordDraft}> */
    public array $terminals = [];

    public ?Throwable $requestedFailure = null;

    public ?Throwable $terminalFailure = null;

    public ?\Closure $afterRequested = null;

    public ?\Closure $afterTerminal = null;

    public function __construct(
        array &$events,
        public readonly string $canary,
    ) {
        $this->events = &$events;
    }

    private array $events;

    public function requested(
        #[\SensitiveParameter]
        AuditReadRequestBasis $basis,
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
    ): void {
        $this->events[] = 'requested';
        $this->requested[] = [$basis, $draft];
        if ($this->afterRequested !== null) {
            ($this->afterRequested)();
        }
        if ($this->requestedFailure !== null) {
            throw $this->requestedFailure;
        }
    }

    public function terminal(
        #[\SensitiveParameter]
        AuditReadContext $context,
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
    ): AuditReadAuthorizationDecision {
        ++$this->terminalCalls;
        $this->events[] = $context->authorizationDecision === AuditReadAuthorizationDecision::Allow
            ? 'terminal:accessed'
            : 'terminal:unavailable';
        $this->terminals[] = [$context, $draft];
        if ($this->afterTerminal !== null) {
            ($this->afterTerminal)();
        }
        if ($this->terminalFailure !== null) {
            throw $this->terminalFailure;
        }

        return $context->authorizationDecision;
    }
}

final class DiagnosticsAdapterNonceSource implements OperationReadNonceSource
{
    public function generate(): string
    {
        return "\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee\xff";
    }
}

final class DiagnosticsAdapterClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-15T00:00:00.123456Z');
    }
}

final class DiagnosticsAdapterUuidGenerator implements \BlackOps\Identifier\Uuidv7Generator
{
    public int $calls = 0;

    public function __construct(
        public readonly string $canary = 'diagnostics-execution-canary',
    ) {}

    public function generate(): string
    {
        ++$this->calls;

        return sprintf('019f80ab-3000-7000-8000-%012d', $this->calls);
    }
}

final readonly class DiagnosticsAdapterValue implements OperationValue
{
    public function __construct(
        public string $message,
    ) {}
}
