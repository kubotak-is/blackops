<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\OperationData;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Outcome;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\OperationData\DefaultOperationJournalQuery;
use BlackOps\Internal\OperationData\DefaultOperationOutcomeQuery;
use BlackOps\Internal\OperationData\OperationDataReadAuthorizerResolver;
use BlackOps\Internal\OperationData\OperationDataSubject;
use BlackOps\Internal\OperationData\OperationDataSubjectReader;
use BlackOps\Internal\OperationData\OperationDataSubjectReadFailure;
use BlackOps\Internal\OperationData\TenantScopedCanonicalJournalReader;
use BlackOps\Internal\OperationData\TenantScopedOutcomeReader;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\OperationData\Exception\OperationOutcomeQueryException;
use BlackOps\OperationData\OperationDataPurpose;
use BlackOps\OperationData\OperationDataReadAuthorizationDecision;
use BlackOps\OperationData\OperationDataReadAuthorizationRequest;
use BlackOps\OperationData\OperationDataReadAuthorizer;
use BlackOps\OperationData\OperationJournalFound;
use BlackOps\OperationData\OperationJournalUnavailable;
use BlackOps\OperationData\OperationOutcomeFound;
use BlackOps\OperationData\OperationOutcomeUnavailable;
use BlackOps\Outcome\OutcomeRecord;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Throwable;

final class OperationDataQueryTest extends TestCase
{
    private const string ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testJournalDenyAndCrossTenantNeverReadBlob(): void
    {
        $reader = new RecordingJournalReader();
        $query = new DefaultOperationJournalQuery(
            new FakeSubjectReader($this->subject()),
            new FakeDataAuthorizer(OperationDataReadAuthorizationDecision::deny()),
            $reader,
        );
        self::assertThrowsUnavailable(fn() => $query->records(
            $this->id(),
            null,
            new TenantRef('customer', 'tenant-a'),
            OperationDataPurpose::fromString('application.view'),
        ));
        self::assertSame(0, $reader->calls);

        self::assertThrowsUnavailable(fn() => $query->records(
            $this->id(),
            null,
            new TenantRef('customer', 'tenant-b'),
            OperationDataPurpose::fromString('application.view'),
        ));
        self::assertSame(0, $reader->calls);
    }

    public function testAllowedJournalReadsAfterAuthorizationAndReturnsTypedFound(): void
    {
        $reader = new RecordingJournalReader([$this->record()]);
        $authorizer = new FakeDataAuthorizer(OperationDataReadAuthorizationDecision::allow());
        $result = new DefaultOperationJournalQuery(
            new FakeSubjectReader($this->subject()),
            $authorizer,
            $reader,
        )->records(
            $this->id(),
            new ActorRef('current', 'user'),
            new TenantRef('customer', 'tenant-a'),
            OperationDataPurpose::fromString('application.view'),
        );
        self::assertInstanceOf(OperationJournalFound::class, $result);
        self::assertSame(1, $reader->calls);
        self::assertSame('tenant-a', $authorizer->request?->currentTenant()?->id());
    }

    public function testUnboundAuthorizerDefaultsToDenyWithoutReadingJournal(): void
    {
        $container = new ContainerBuilder();
        $container->compile();
        $reader = new RecordingJournalReader([$this->record()]);
        $result = new DefaultOperationJournalQuery(
            new FakeSubjectReader($this->subject()),
            new OperationDataReadAuthorizerResolver($container)->resolve(),
            $reader,
        )->records(
            $this->id(),
            null,
            new TenantRef('customer', 'tenant-a'),
            OperationDataPurpose::fromString('application.view'),
        );

        self::assertInstanceOf(OperationJournalUnavailable::class, $result);
        self::assertSame(0, $reader->calls);
    }

    public function testAuthorizerThrowableUsesResourceSpecificSafeCode(): void
    {
        $journal = new DefaultOperationJournalQuery(
            new FakeSubjectReader($this->subject()),
            new FakeDataAuthorizer(OperationDataReadAuthorizationDecision::allow(), new RuntimeException('secret')),
            new RecordingJournalReader(),
        );
        $outcome = new DefaultOperationOutcomeQuery(
            new FakeSubjectReader($this->subject()),
            new FakeDataAuthorizer(OperationDataReadAuthorizationDecision::allow(), new RuntimeException('secret')),
            new RecordingOutcomeReader(),
        );
        try {
            $journal->records(
                $this->id(),
                null,
                new TenantRef('customer', 'tenant-a'),
                OperationDataPurpose::fromString('application.view'),
            );
            self::fail('Expected journal authorization failure.');
        } catch (\BlackOps\OperationData\Exception\OperationJournalQueryException $exception) {
            self::assertSame(
                \BlackOps\OperationData\Exception\OperationJournalQueryException::AUTHORIZATION_FAILED,
                $exception->queryCode(),
            );
            self::assertStringNotContainsString('secret', $exception->getMessage());
        }
        try {
            $outcome->find(
                $this->id(),
                null,
                new TenantRef('customer', 'tenant-a'),
                OperationDataPurpose::fromString('application.view'),
            );
            self::fail('Expected outcome authorization failure.');
        } catch (OperationOutcomeQueryException $exception) {
            self::assertSame(OperationOutcomeQueryException::AUTHORIZATION_FAILED, $exception->queryCode());
            self::assertStringNotContainsString('secret', $exception->getMessage());
        }
    }

    public function testOutcomeStorageFailureIsStableAndUnavailableIsTyped(): void
    {
        $failure = new DefaultOperationOutcomeQuery(
            new FakeSubjectReader($this->subject()),
            new FakeDataAuthorizer(OperationDataReadAuthorizationDecision::allow()),
            new RecordingOutcomeReader(failure: new RuntimeException('secret sql')),
        );
        try {
            $failure->find(
                $this->id(),
                null,
                new TenantRef('customer', 'tenant-a'),
                OperationDataPurpose::fromString('application.view'),
            );
            self::fail('Expected safe outcome query failure.');
        } catch (OperationOutcomeQueryException $exception) {
            self::assertSame(OperationOutcomeQueryException::STORAGE_FAILED, $exception->queryCode());
            self::assertStringNotContainsString('secret', $exception->getMessage());
        }

        $unavailable = new DefaultOperationOutcomeQuery(
            new FakeSubjectReader($this->subject()),
            new FakeDataAuthorizer(OperationDataReadAuthorizationDecision::deny()),
            new RecordingOutcomeReader(new OutcomeRecord($this->id(), new TestOutcome(), new DateTimeImmutable())),
        )->find(
            $this->id(),
            null,
            new TenantRef('customer', 'tenant-a'),
            OperationDataPurpose::fromString('application.view'),
        );
        self::assertInstanceOf(OperationOutcomeUnavailable::class, $unavailable);
    }

    public function testUnknownAndRetentionEmptyResultsAreTypedUnavailable(): void
    {
        $purpose = OperationDataPurpose::fromString('application.view');
        $journal = new DefaultOperationJournalQuery(
            new FakeSubjectReader(null),
            new FakeDataAuthorizer(OperationDataReadAuthorizationDecision::allow()),
            new RecordingJournalReader(),
        );
        self::assertInstanceOf(OperationJournalUnavailable::class, $journal->records(
            $this->id(),
            null,
            null,
            $purpose,
        ));

        $emptyJournal = new DefaultOperationJournalQuery(
            new FakeSubjectReader($this->subject()),
            new FakeDataAuthorizer(OperationDataReadAuthorizationDecision::allow()),
            new RecordingJournalReader(),
        );
        self::assertInstanceOf(OperationJournalUnavailable::class, $emptyJournal->records(
            $this->id(),
            null,
            new TenantRef('customer', 'tenant-a'),
            $purpose,
        ));
        $outcome = new DefaultOperationOutcomeQuery(
            new FakeSubjectReader(null),
            new FakeDataAuthorizer(OperationDataReadAuthorizationDecision::allow()),
            new RecordingOutcomeReader(),
        );
        self::assertInstanceOf(OperationOutcomeUnavailable::class, $outcome->find($this->id(), null, null, $purpose));
    }

    public function testClassifiedProtectionDecodeAndIntegrityFailuresRemainStable(): void
    {
        $purpose = OperationDataPurpose::fromString('application.view');
        $journal = new DefaultOperationJournalQuery(
            new FakeSubjectReader($this->subject()),
            new FakeDataAuthorizer(OperationDataReadAuthorizationDecision::allow()),
            new RecordingJournalReader(
                failure: \BlackOps\OperationData\Exception\OperationJournalQueryException::decodeFailed(),
            ),
        );
        try {
            $journal->records($this->id(), null, new TenantRef('customer', 'tenant-a'), $purpose);
            self::fail('Expected journal decode failure.');
        } catch (\BlackOps\OperationData\Exception\OperationJournalQueryException $exception) {
            self::assertSame(
                \BlackOps\OperationData\Exception\OperationJournalQueryException::DECODE_FAILED,
                $exception->queryCode(),
            );
        }

        $outcome = new DefaultOperationOutcomeQuery(
            new FakeSubjectReader($this->subject()),
            new FakeDataAuthorizer(OperationDataReadAuthorizationDecision::allow()),
            new RecordingOutcomeReader(failure: OperationOutcomeQueryException::protectionFailed()),
        );
        try {
            $outcome->find($this->id(), null, new TenantRef('customer', 'tenant-a'), $purpose);
            self::fail('Expected outcome protection failure.');
        } catch (OperationOutcomeQueryException $exception) {
            self::assertSame(OperationOutcomeQueryException::PROTECTION_FAILED, $exception->queryCode());
        }
    }

    public function testSubjectIntegrityFailureUsesResourceSpecificStableCode(): void
    {
        $journal = new DefaultOperationJournalQuery(
            new FailingSubjectReader(OperationDataSubjectReadFailure::integrity()),
            new FakeDataAuthorizer(OperationDataReadAuthorizationDecision::allow()),
            new RecordingJournalReader(),
        );
        $outcome = new DefaultOperationOutcomeQuery(
            new FailingSubjectReader(OperationDataSubjectReadFailure::integrity()),
            new FakeDataAuthorizer(OperationDataReadAuthorizationDecision::allow()),
            new RecordingOutcomeReader(),
        );

        try {
            $journal->records($this->id(), null, null, OperationDataPurpose::fromString('application.view'));
            self::fail('Expected journal integrity failure.');
        } catch (\BlackOps\OperationData\Exception\OperationJournalQueryException $exception) {
            self::assertSame(
                \BlackOps\OperationData\Exception\OperationJournalQueryException::INTEGRITY_FAILED,
                $exception->queryCode(),
            );
        }
        try {
            $outcome->find($this->id(), null, null, OperationDataPurpose::fromString('application.view'));
            self::fail('Expected outcome integrity failure.');
        } catch (OperationOutcomeQueryException $exception) {
            self::assertSame(OperationOutcomeQueryException::INTEGRITY_FAILED, $exception->queryCode());
        }
    }

    public function testAllowedBlobMetadataMismatchUsesIntegrityCode(): void
    {
        $record = $this->record();
        $tampered = new JournalRecord(
            $record->recordId,
            $record->schemaVersion,
            $record->event,
            $record->occurredAt,
            $record->sequence,
            new JournalOperation(
                $record->operation->id,
                'other.operation',
                $record->operation->schemaVersion,
                $record->operation->strategy,
                $record->operation->correlationId,
                actorContext: $record->operation->actorContext,
                tenant: $record->operation->tenant,
            ),
            $record->attempt,
            $record->data,
        );
        $query = new DefaultOperationJournalQuery(
            new FakeSubjectReader($this->subject()),
            new FakeDataAuthorizer(OperationDataReadAuthorizationDecision::allow()),
            new RecordingJournalReader([$tampered]),
        );

        try {
            $query->records(
                $this->id(),
                null,
                new TenantRef('customer', 'tenant-a'),
                OperationDataPurpose::fromString('application.view'),
            );
            self::fail('Expected journal integrity failure.');
        } catch (\BlackOps\OperationData\Exception\OperationJournalQueryException $exception) {
            self::assertSame(
                \BlackOps\OperationData\Exception\OperationJournalQueryException::INTEGRITY_FAILED,
                $exception->queryCode(),
            );
        }

        $outcome = new DefaultOperationOutcomeQuery(
            new FakeSubjectReader($this->subject()),
            new FakeDataAuthorizer(OperationDataReadAuthorizationDecision::allow()),
            new RecordingOutcomeReader(
                new OutcomeRecord(
                    OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687999'),
                    new TestOutcome(),
                    new DateTimeImmutable(),
                ),
            ),
        );
        try {
            $outcome->find(
                $this->id(),
                null,
                new TenantRef('customer', 'tenant-a'),
                OperationDataPurpose::fromString('application.view'),
            );
            self::fail('Expected outcome integrity failure.');
        } catch (OperationOutcomeQueryException $exception) {
            self::assertSame(OperationOutcomeQueryException::INTEGRITY_FAILED, $exception->queryCode());
        }
    }

    private function id(): OperationId
    {
        return OperationId::fromString(self::ID);
    }

    private function subject(): OperationDataSubject
    {
        return new OperationDataSubject($this->id(), 'report.generate', null, new TenantRef('customer', 'tenant-a'));
    }

    private function record(): JournalRecord
    {
        return new JournalRecord(
            JournalRecordId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687698'),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable('2026-08-08T00:00:00Z'),
            1,
            new JournalOperation(
                $this->id(),
                'report.generate',
                1,
                'inline',
                CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687699'),
                tenant: new TenantRef('customer', 'tenant-a'),
            ),
            null,
            new EmptyJournalData(),
        );
    }

    private static function assertThrowsUnavailable(callable $call): void
    {
        $result = $call();
        self::assertInstanceOf(OperationJournalUnavailable::class, $result);
    }
}

final readonly class FakeSubjectReader implements OperationDataSubjectReader
{
    public function __construct(
        private ?OperationDataSubject $subject,
    ) {}

    public function findSubject(OperationId $operationId, ?TenantRef $tenant): ?OperationDataSubject
    {
        return $this->subject !== null
        && $this->subject->originTenant !== null
        && $tenant?->id() !== $this->subject->originTenant->id()
            ? null
            : $this->subject;
    }
}

final readonly class FailingSubjectReader implements OperationDataSubjectReader
{
    public function __construct(
        private OperationDataSubjectReadFailure $failure,
    ) {}

    public function findSubject(OperationId $operationId, ?TenantRef $tenant): ?OperationDataSubject
    {
        throw $this->failure;
    }
}

final class FakeDataAuthorizer implements OperationDataReadAuthorizer
{
    public ?OperationDataReadAuthorizationRequest $request = null;

    public function __construct(
        private readonly OperationDataReadAuthorizationDecision $decision,
        private readonly ?Throwable $failure = null,
    ) {}

    public function decide(OperationDataReadAuthorizationRequest $request): OperationDataReadAuthorizationDecision
    {
        $this->request = $request;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        return $this->decision;
    }
}

final class RecordingJournalReader implements TenantScopedCanonicalJournalReader
{
    public int $calls = 0;

    /** @param list<JournalRecord> $records */
    public function __construct(
        private readonly array $records = [],
        private readonly ?Throwable $failure = null,
    ) {}

    public function recordsForTenant(OperationId $operationId, ?TenantRef $tenant): iterable
    {
        $this->calls++;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        return $this->records;
    }
}

final class RecordingOutcomeReader implements TenantScopedOutcomeReader
{
    public function __construct(
        private readonly ?OutcomeRecord $record = null,
        private readonly ?RuntimeException $failure = null,
    ) {}

    public function findForTenant(OperationId $operationId, ?TenantRef $tenant): ?OutcomeRecord
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
        return $this->record;
    }
}

final readonly class TestOutcome implements Outcome
{
    public function value(): string
    {
        return 'ok';
    }
}
