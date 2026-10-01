<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\OperationData;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditReadAuthorizationDecision as AuditDecision;
use BlackOps\Core\Audit\AuditReadContext;
use BlackOps\Core\Audit\AuditReadRequestBasis;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Outcome;
use BlackOps\Core\TenantRef;
use BlackOps\Identifier\Uuidv7Generator;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Read\AuditReadEventWriter;
use BlackOps\Internal\Audit\Read\OperationReadAuditSessionFactory;
use BlackOps\Internal\Audit\Read\OperationReadExecutionSource;
use BlackOps\Internal\Audit\Read\OperationReadNonceSource;
use BlackOps\Internal\OperationData\AuditedOperationJournalQuery;
use BlackOps\Internal\OperationData\AuditedOperationOutcomeQuery;
use BlackOps\Internal\OperationData\OperationDataSubject;
use BlackOps\Internal\OperationData\OperationDataSubjectReader;
use BlackOps\Internal\OperationData\OperationJournalReadExecutionSource;
use BlackOps\Internal\OperationData\OperationOutcomeReadExecutionSource;
use BlackOps\Internal\OperationData\TenantScopedCanonicalJournalReader;
use BlackOps\Internal\OperationData\TenantScopedOutcomeReader;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\OperationData\Exception\OperationJournalQueryException;
use BlackOps\OperationData\Exception\OperationOutcomeQueryException;
use BlackOps\OperationData\OperationDataPurpose;
use BlackOps\OperationData\OperationDataReadAuthorizationDecision;
use BlackOps\OperationData\OperationDataReadAuthorizationRequest;
use BlackOps\OperationData\OperationDataReadAuthorizer;
use BlackOps\OperationData\OperationJournalFound;
use BlackOps\OperationData\OperationOutcomeFound;
use BlackOps\Outcome\OutcomeRecord;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionClass;
use RuntimeException;
use Throwable;

final class AuditedOperationDataQueryTest extends TestCase
{
    private const string OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';
    private const string DEPENDENCY_CANARY = 'report.dependencycanary';

    public function testJournalFoundCommitsRequestedAndAccessedBeforeReturning(): void
    {
        $events = [];
        $subject = $this->subject();
        $writer = new OperationDataQueryWriter($events);
        $reader = new OperationDataQueryJournalReader($events, [$this->record()]);
        $query = new AuditedOperationJournalQuery(
            new OperationDataQuerySubjectReader($events, $subject),
            new OperationDataQueryAuthorizer($events),
            $reader,
            $this->sessions($writer, new OperationJournalReadExecutionSource(new OperationDataQueryUuidGenerator())),
            $this->idleConnection(),
        );

        $result = $query->records(
            $this->operationId(),
            new ActorRef('current-user', 'user'),
            $subject->originTenant,
            OperationDataPurpose::fromString('application.view'),
        );

        self::assertInstanceOf(OperationJournalFound::class, $result);
        self::assertSame(['requested', 'subject', 'authorize', 'read', 'yielded', 'terminal:accessed'], $events);
        self::assertSame(1, $reader->calls);
        self::assertSame(1, $writer->terminalCalls);
        self::assertSame('access.operation_journal.requested', $writer->requested[0][1]->event->type);
        self::assertSame('access.operation_journal.accessed', $writer->terminals[0][1]->event->type);
        self::assertStringNotContainsString(
            'application.view',
            (string) json_encode($writer->requested[0][1]->toArray()),
        );
    }

    public function testJournalLateGeneratorFailureReleasesNoPartialPrefix(): void
    {
        $events = [];
        $failure = OperationJournalQueryException::decodeFailed();
        $writer = new OperationDataQueryWriter($events);
        $reader = new OperationDataQueryJournalReader($events, [$this->record()], failureAfter: $failure);
        $query = new AuditedOperationJournalQuery(
            new OperationDataQuerySubjectReader($events, $this->subject()),
            new OperationDataQueryAuthorizer($events),
            $reader,
            $this->sessions($writer, new OperationJournalReadExecutionSource(new OperationDataQueryUuidGenerator())),
            $this->idleConnection(),
        );

        try {
            $query->records(
                $this->operationId(),
                null,
                $this->subject()->originTenant,
                OperationDataPurpose::fromString('application.view'),
            );
            self::fail('A late Journal iteration failure was released as a result.');
        } catch (OperationJournalQueryException $exception) {
            self::assertSame($failure, $exception);
            self::assertSame(OperationJournalQueryException::DECODE_FAILED, $exception->queryCode());
        }

        self::assertSame(
            ['requested', 'subject', 'authorize', 'read', 'yielded', 'late', 'terminal:unavailable'],
            $events,
        );
        self::assertSame(1, $reader->yielded);
        self::assertSame(1, $writer->terminalCalls);
        self::assertCount(0, $writer->releasedResults);
    }

    public function testOutcomeFoundUsesItsFixedAuditPurposeAndTerminal(): void
    {
        $events = [];
        $subject = $this->subject();
        $record = new OutcomeRecord($this->operationId(), new OperationDataQueryOutcome(), new DateTimeImmutable());
        $writer = new OperationDataQueryWriter($events);
        $query = new AuditedOperationOutcomeQuery(
            new OperationDataQuerySubjectReader($events, $subject),
            new OperationDataQueryAuthorizer($events),
            new OperationDataQueryOutcomeReader($events, $record),
            $this->sessions($writer, new OperationOutcomeReadExecutionSource(new OperationDataQueryUuidGenerator())),
            $this->idleConnection(),
        );

        $result = $query->find(
            $this->operationId(),
            null,
            $subject->originTenant,
            OperationDataPurpose::fromString('application.export'),
        );

        self::assertInstanceOf(OperationOutcomeFound::class, $result);
        self::assertSame($record, $result->record());
        self::assertSame(['requested', 'subject', 'authorize', 'read', 'terminal:accessed'], $events);
        self::assertSame('access.operation_outcome.requested', $writer->requested[0][1]->event->type);
        self::assertSame('access.operation_outcome.accessed', $writer->terminals[0][1]->event->type);
        self::assertStringNotContainsString(
            'application.export',
            (string) json_encode($writer->requested[0][1]->toArray()),
        );
    }

    public function testTerminalFailureMasksFoundResultWithoutRetry(): void
    {
        $events = [];
        $writer = new OperationDataQueryWriter($events);
        $writer->terminalFailure = new AuditDeliveryException(AuditDeliveryFailure::ResourceUnavailable);
        $query = new AuditedOperationOutcomeQuery(
            new OperationDataQuerySubjectReader($events, $this->subject()),
            new OperationDataQueryAuthorizer($events),
            new OperationDataQueryOutcomeReader(
                $events,
                new OutcomeRecord($this->operationId(), new OperationDataQueryOutcome(), new DateTimeImmutable()),
            ),
            $this->sessions($writer, new OperationOutcomeReadExecutionSource(new OperationDataQueryUuidGenerator())),
            $this->idleConnection(),
        );

        try {
            $query->find(
                $this->operationId(),
                null,
                $this->subject()->originTenant,
                OperationDataPurpose::fromString('application.view'),
            );
            self::fail('A terminal failure released an unaudited Outcome.');
        } catch (OperationOutcomeQueryException $exception) {
            self::assertSame(OperationOutcomeQueryException::STORAGE_FAILED, $exception->queryCode());
            self::assertNull($exception->getPrevious());
        }

        self::assertSame(1, $writer->terminalCalls);
        self::assertSame(['requested', 'subject', 'authorize', 'read', 'terminal:accessed'], $events);
    }

    public function testBothQueriesFailClosedForUninitializedCorruptAndUnserializableSnapshots(): void
    {
        $journalEvents = [];
        $journalWriter = new OperationDataQueryWriter($journalEvents);
        $journalReader = new OperationDataQueryJournalReader($journalEvents);
        $journal = new AuditedOperationJournalQuery(
            new OperationDataQuerySubjectReader($journalEvents, $this->subject()),
            new OperationDataQueryAuthorizer($journalEvents),
            $journalReader,
            $this->sessions(
                $journalWriter,
                new OperationJournalReadExecutionSource(new OperationDataQueryUuidGenerator()),
            ),
            $this->idleConnection(),
        );

        $journalException = $this->captureJournalFailure(fn() => unserialize(serialize($journal), [
            'allowed_classes' => true,
        ]));
        self::assertSame(OperationJournalQueryException::STORAGE_FAILED, $journalException->queryCode());
        self::assertNull($journalException->getPrevious());
        self::assertSame([], $journalEvents);
        self::assertCount(0, $journalWriter->requested);
        self::assertSame(0, $journalWriter->terminalCalls);

        $uninitializedJournal = new ReflectionClass(AuditedOperationJournalQuery::class)->newInstanceWithoutConstructor();
        $uninitializedJournalException = $this->captureJournalFailure(fn() => $uninitializedJournal->records(
            $this->operationId(),
            null,
            null,
            OperationDataPurpose::fromString('application.view'),
        ));
        self::assertSame(OperationJournalQueryException::STORAGE_FAILED, $uninitializedJournalException->queryCode());
        self::assertNull($uninitializedJournalException->getPrevious());

        $journalReflection = new ReflectionClass($journal);
        $journalSnapshot = $journalReflection->getProperty('snapshot')->getValue($journal);
        self::assertInstanceOf(\SensitiveParameterValue::class, $journalSnapshot);
        $journalValues = $journalSnapshot->getValue();
        self::assertIsArray($journalValues);
        /** @var array<string, mixed> $journalValues */
        $journalValues['extra'] = 'corrupt';
        $corruptJournal = $journalReflection->newInstanceWithoutConstructor();
        $journalReflection->getProperty('snapshot')->setValue(
            $corruptJournal,
            new \SensitiveParameterValue($journalValues),
        );
        $corruptJournalException = $this->captureJournalFailure(fn() => $corruptJournal->records(
            $this->operationId(),
            null,
            null,
            OperationDataPurpose::fromString('application.view'),
        ));
        self::assertSame(OperationJournalQueryException::STORAGE_FAILED, $corruptJournalException->queryCode());
        self::assertNull($corruptJournalException->getPrevious());
        self::assertSame([], $journalEvents);
        self::assertSame(0, $journalReader->calls);
        self::assertCount(0, $journalWriter->requested);
        self::assertSame(0, $journalWriter->terminalCalls);

        $outcomeEvents = [];
        $outcomeWriter = new OperationDataQueryWriter($outcomeEvents);
        $outcomeReader = new OperationDataQueryOutcomeReader($outcomeEvents, null);
        $outcome = new AuditedOperationOutcomeQuery(
            new OperationDataQuerySubjectReader($outcomeEvents, $this->subject()),
            new OperationDataQueryAuthorizer($outcomeEvents),
            $outcomeReader,
            $this->sessions(
                $outcomeWriter,
                new OperationOutcomeReadExecutionSource(new OperationDataQueryUuidGenerator()),
            ),
            $this->idleConnection(),
        );

        $outcomeException = $this->captureOutcomeFailure(fn() => unserialize(serialize($outcome), [
            'allowed_classes' => true,
        ]));
        self::assertSame(OperationOutcomeQueryException::STORAGE_FAILED, $outcomeException->queryCode());
        self::assertNull($outcomeException->getPrevious());
        self::assertSame([], $outcomeEvents);
        self::assertCount(0, $outcomeWriter->requested);
        self::assertSame(0, $outcomeWriter->terminalCalls);

        $uninitializedOutcome = new ReflectionClass(AuditedOperationOutcomeQuery::class)->newInstanceWithoutConstructor();
        $uninitializedOutcomeException = $this->captureOutcomeFailure(fn() => $uninitializedOutcome->find(
            $this->operationId(),
            null,
            null,
            OperationDataPurpose::fromString('application.view'),
        ));
        self::assertSame(OperationOutcomeQueryException::STORAGE_FAILED, $uninitializedOutcomeException->queryCode());
        self::assertNull($uninitializedOutcomeException->getPrevious());

        $outcomeReflection = new ReflectionClass($outcome);
        $outcomeSnapshot = $outcomeReflection->getProperty('snapshot')->getValue($outcome);
        self::assertInstanceOf(\SensitiveParameterValue::class, $outcomeSnapshot);
        $outcomeValues = $outcomeSnapshot->getValue();
        self::assertIsArray($outcomeValues);
        /** @var array<string, mixed> $outcomeValues */
        $outcomeValues['extra'] = 'corrupt';
        $corruptOutcome = $outcomeReflection->newInstanceWithoutConstructor();
        $outcomeReflection->getProperty('snapshot')->setValue(
            $corruptOutcome,
            new \SensitiveParameterValue($outcomeValues),
        );
        $corruptOutcomeException = $this->captureOutcomeFailure(fn() => $corruptOutcome->find(
            $this->operationId(),
            null,
            null,
            OperationDataPurpose::fromString('application.view'),
        ));
        self::assertSame(OperationOutcomeQueryException::STORAGE_FAILED, $corruptOutcomeException->queryCode());
        self::assertNull($corruptOutcomeException->getPrevious());
        self::assertSame([], $outcomeEvents);
        self::assertSame(0, $outcomeReader->calls);
        self::assertCount(0, $outcomeWriter->requested);
        self::assertSame(0, $outcomeWriter->terminalCalls);
    }

    public function testBothQueriesHideRetainedDependencyCanariesAndSensitiveTraceArguments(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));

        try {
            $subject = new OperationDataSubject(
                $this->operationId(),
                self::DEPENDENCY_CANARY,
                new ActorRef('dependency-origin-canary', 'user'),
                new TenantRef('customer', 'dependency-tenant-canary'),
            );
            $journalEvents = [];
            $journalWriter = new OperationDataQueryWriter($journalEvents);
            $journalSubjectReader = new OperationDataQuerySubjectReader($journalEvents, $subject);
            $journal = new AuditedOperationJournalQuery(
                $journalSubjectReader,
                new OperationDataQueryAuthorizer($journalEvents),
                new OperationDataQueryJournalReader($journalEvents),
                $this->sessions(
                    $journalWriter,
                    new OperationJournalReadExecutionSource(new OperationDataQueryUuidGenerator()),
                ),
                $this->idleConnection(),
            );
            $outcomeEvents = [];
            $outcomeWriter = new OperationDataQueryWriter($outcomeEvents);
            $outcomeSubjectReader = new OperationDataQuerySubjectReader($outcomeEvents, $subject);
            $outcome = new AuditedOperationOutcomeQuery(
                $outcomeSubjectReader,
                new OperationDataQueryAuthorizer($outcomeEvents),
                new OperationDataQueryOutcomeReader($outcomeEvents, null),
                $this->sessions(
                    $outcomeWriter,
                    new OperationOutcomeReadExecutionSource(new OperationDataQueryUuidGenerator()),
                ),
                $this->idleConnection(),
            );

            self::assertSame(self::DEPENDENCY_CANARY, $journalSubjectReader->subject?->operationType);
            self::assertSame(self::DEPENDENCY_CANARY, $outcomeSubjectReader->subject?->operationType);
            foreach ([$journal, $outcome] as $query) {
                $serialized =
                    print_r($query, return: true)
                    . var_export($query, return: true)
                    . serialize($query)
                    . json_encode($query, JSON_THROW_ON_ERROR);
                self::assertSame([], $query->__debugInfo());
                self::assertSame([], $query->__serialize());
                self::assertStringNotContainsString(self::DEPENDENCY_CANARY, $serialized);
                self::assertStringNotContainsString('dependency-origin-canary', $serialized);
                self::assertStringNotContainsString('dependency-tenant-canary', $serialized);
            }

            $journalTraceQuery = new ReflectionClass(AuditedOperationJournalQuery::class)->newInstanceWithoutConstructor();
            $journalTrace = $this->captureJournalFailure(fn() => $journalTraceQuery->records(
                $this->operationId(),
                new ActorRef('trace-journal-actor-canary', 'user'),
                new TenantRef('customer', 'trace-journal-tenant-canary'),
                OperationDataPurpose::fromString('application.tracecanary'),
            ));
            $this->assertSensitiveQueryTrace($journalTrace, [
                'trace-journal-actor-canary',
                'trace-journal-tenant-canary',
                'application.tracecanary',
                self::OPERATION_ID,
            ]);

            $outcomeTraceQuery = new ReflectionClass(AuditedOperationOutcomeQuery::class)->newInstanceWithoutConstructor();
            $outcomeTrace = $this->captureOutcomeFailure(fn() => $outcomeTraceQuery->find(
                $this->operationId(),
                new ActorRef('trace-outcome-actor-canary', 'user'),
                new TenantRef('customer', 'trace-outcome-tenant-canary'),
                OperationDataPurpose::fromString('application.tracecanary'),
            ));
            $this->assertSensitiveQueryTrace($outcomeTrace, [
                'trace-outcome-actor-canary',
                'trace-outcome-tenant-canary',
                'application.tracecanary',
                self::OPERATION_ID,
            ]);
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testPostBeginAndPostReadIdleFailuresReleaseNoAuditTerminal(): void
    {
        $state = 'idle';
        $journalEvents = [];
        $journalWriter = new OperationDataQueryWriter($journalEvents);
        $journalWriter->afterRequested = static function () use (&$state): void {
            $state = 'active';
        };
        $journalReader = new OperationDataQueryJournalReader($journalEvents, [$this->record()]);
        $journal = new AuditedOperationJournalQuery(
            new OperationDataQuerySubjectReader($journalEvents, $this->subject()),
            new OperationDataQueryAuthorizer($journalEvents),
            $journalReader,
            $this->sessions(
                $journalWriter,
                new OperationJournalReadExecutionSource(new OperationDataQueryUuidGenerator()),
            ),
            $this->connectionWithState($state),
        );
        $journalException = $this->captureJournalFailure(fn() => $journal->records(
            $this->operationId(),
            null,
            $this->subject()->originTenant,
            OperationDataPurpose::fromString('application.view'),
        ));
        self::assertSame(OperationJournalQueryException::STORAGE_FAILED, $journalException->queryCode());
        self::assertNull($journalException->getPrevious());
        self::assertSame(['requested'], $journalEvents);
        self::assertSame(0, $journalReader->calls);
        self::assertCount(1, $journalWriter->requested);
        self::assertSame(0, $journalWriter->terminalCalls);

        $state = 'idle';
        $outcomeEvents = [];
        $outcomeWriter = new OperationDataQueryWriter($outcomeEvents);
        $outcomeReader = new OperationDataQueryOutcomeReader(
            $outcomeEvents,
            new OutcomeRecord($this->operationId(), new OperationDataQueryOutcome(), new DateTimeImmutable()),
        );
        $outcomeReader->afterRead = static function () use (&$state): void {
            $state = 'active';
        };
        $outcome = new AuditedOperationOutcomeQuery(
            new OperationDataQuerySubjectReader($outcomeEvents, $this->subject()),
            new OperationDataQueryAuthorizer($outcomeEvents),
            $outcomeReader,
            $this->sessions(
                $outcomeWriter,
                new OperationOutcomeReadExecutionSource(new OperationDataQueryUuidGenerator()),
            ),
            $this->connectionWithState($state),
        );
        $outcomeException = $this->captureOutcomeFailure(fn() => $outcome->find(
            $this->operationId(),
            null,
            $this->subject()->originTenant,
            OperationDataPurpose::fromString('application.view'),
        ));
        self::assertSame(OperationOutcomeQueryException::STORAGE_FAILED, $outcomeException->queryCode());
        self::assertNull($outcomeException->getPrevious());
        self::assertSame(['requested', 'subject', 'authorize', 'read'], $outcomeEvents);
        self::assertSame(1, $outcomeReader->calls);
        self::assertCount(1, $outcomeWriter->requested);
        self::assertSame(0, $outcomeWriter->terminalCalls);
    }

    private function operationId(): OperationId
    {
        return OperationId::fromString(self::OPERATION_ID);
    }

    private function subject(): OperationDataSubject
    {
        return new OperationDataSubject(
            $this->operationId(),
            'report.generate',
            new ActorRef('origin-user', 'user'),
            new TenantRef('customer', 'tenant-a'),
        );
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
                $this->operationId(),
                'report.generate',
                1,
                'inline',
                CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687699'),
                actorContext: new ActorContext(
                    new ActorRef('origin-user', 'user'),
                    null,
                    new ActorRef('execution-user', 'user'),
                ),
                tenant: new TenantRef('customer', 'tenant-a'),
            ),
            null,
            new EmptyJournalData(),
        );
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

    private function sessions(
        OperationDataQueryWriter $writer,
        OperationReadExecutionSource $execution,
    ): OperationReadAuditSessionFactory {
        return new OperationReadAuditSessionFactory(
            $writer,
            new HmacAuditOpaqueIdDeriver('0123456789abcdef0123456789abcdef'),
            new OperationDataQueryNonceSource(),
            new OperationDataQueryClock(),
            $execution,
            AuditSourceRuntime::Http,
            'operation-data-query-test-build',
        );
    }

    private function captureJournalFailure(\Closure $call): OperationJournalQueryException
    {
        try {
            $call();
            self::fail('Expected Journal query failure.');
        } catch (OperationJournalQueryException $exception) {
            return $exception;
        }
    }

    private function captureOutcomeFailure(\Closure $call): OperationOutcomeQueryException
    {
        try {
            $call();
            self::fail('Expected Outcome query failure.');
        } catch (OperationOutcomeQueryException $exception) {
            return $exception;
        }
    }

    private function assertSensitiveQueryTrace(Throwable $exception, array $canaries): void
    {
        $traceFrames = array_filter($exception->getTrace(), static fn(array $frame): bool => str_starts_with(
            (string) ($frame['class'] ?? ''),
            'BlackOps\\Internal\\OperationData\\',
        ));
        self::assertNotEmpty($traceFrames);
        $trace = print_r($traceFrames, return: true) . var_export($traceFrames, return: true);
        foreach ($canaries as $canary) {
            self::assertStringNotContainsString($canary, $trace);
        }
    }
}

final class OperationDataQuerySubjectReader implements OperationDataSubjectReader
{
    private array $events;

    public function __construct(
        array &$events,
        public readonly ?OperationDataSubject $subject,
    ) {
        $this->events = &$events;
    }

    public function findSubject(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): ?OperationDataSubject {
        $this->events[] = 'subject';

        return $this->subject;
    }
}

final class OperationDataQueryAuthorizer implements OperationDataReadAuthorizer
{
    public int $calls = 0;

    private array $events;

    private readonly OperationDataReadAuthorizationDecision $decision;

    public function __construct(array &$events, ?OperationDataReadAuthorizationDecision $decision = null)
    {
        $this->events = &$events;
        $this->decision = $decision ?? OperationDataReadAuthorizationDecision::allow();
    }

    public function decide(
        #[\SensitiveParameter]
        OperationDataReadAuthorizationRequest $request,
    ): OperationDataReadAuthorizationDecision {
        $this->events[] = 'authorize';
        ++$this->calls;

        return $this->decision;
    }
}

final class OperationDataQueryJournalReader implements TenantScopedCanonicalJournalReader
{
    public int $calls = 0;

    public int $yielded = 0;

    public ?\Closure $afterRead = null;

    private array $events;

    /** @param list<JournalRecord> $records */
    public function __construct(
        array &$events,
        private readonly array $records = [],
        private readonly ?Throwable $failureBefore = null,
        private readonly ?Throwable $failureAfter = null,
    ) {
        $this->events = &$events;
    }

    public function recordsForTenant(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): iterable {
        ++$this->calls;
        $this->events[] = 'read';
        if ($this->failureBefore !== null) {
            throw $this->failureBefore;
        }
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

final class OperationDataQueryOutcomeReader implements TenantScopedOutcomeReader
{
    public int $calls = 0;

    public ?\Closure $afterRead = null;

    private array $events;

    public function __construct(
        array &$events,
        private readonly ?OutcomeRecord $record,
    ) {
        $this->events = &$events;
    }

    public function findForTenant(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): ?OutcomeRecord {
        $this->events[] = 'read';
        ++$this->calls;
        if ($this->afterRead !== null) {
            ($this->afterRead)();
        }

        return $this->record;
    }
}

final class OperationDataQueryWriter implements AuditReadEventWriter
{
    public int $terminalCalls = 0;

    /** @var list<array{AuditReadRequestBasis, AuditRecordDraft}> */
    public array $requested = [];

    /** @var list<array{AuditReadContext, AuditRecordDraft}> */
    public array $terminals = [];

    /** @var list<object> */
    public array $releasedResults = [];

    public ?AuditDeliveryException $terminalFailure = null;

    public ?\Closure $afterRequested = null;

    private array $events;

    public function __construct(array &$events)
    {
        $this->events = &$events;
    }

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
    }

    public function terminal(
        #[\SensitiveParameter]
        AuditReadContext $context,
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
    ): AuditDecision {
        ++$this->terminalCalls;
        $this->events[] =
            'terminal:' . ($context->authorizationDecision === AuditDecision::Allow ? 'accessed' : 'unavailable');
        $this->terminals[] = [$context, $draft];
        if ($this->terminalFailure !== null) {
            throw $this->terminalFailure;
        }

        return $context->authorizationDecision;
    }
}

final class OperationDataQueryNonceSource implements OperationReadNonceSource
{
    public function generate(): string
    {
        return "\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee\xff";
    }
}

final class OperationDataQueryClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-14T00:00:00.123456Z');
    }
}

final class OperationDataQueryUuidGenerator implements Uuidv7Generator
{
    private int $calls = 0;

    public function generate(): string
    {
        ++$this->calls;

        return sprintf('019f80ab-2000-7000-8000-%012d', $this->calls);
    }
}

final readonly class OperationDataQueryOutcome implements Outcome {}
