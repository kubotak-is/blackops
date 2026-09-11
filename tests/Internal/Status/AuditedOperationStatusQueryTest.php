<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Status;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditReadAuthorizationDecision as AuditDecision;
use BlackOps\Core\Audit\AuditReadContext;
use BlackOps\Core\Audit\AuditReadRequestBasis;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Identifier\Uuidv7Generator;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Read\AuditReadEventWriter;
use BlackOps\Internal\Audit\Read\OperationReadAuditSessionFactory;
use BlackOps\Internal\Audit\Read\OperationReadNonceSource;
use BlackOps\Internal\Status\AuditedOperationStatusQuery;
use BlackOps\Internal\Status\OperationStatusDetail;
use BlackOps\Internal\Status\OperationStatusDetailExpired;
use BlackOps\Internal\Status\OperationStatusDetailResult;
use BlackOps\Internal\Status\OperationStatusDetailUnavailable;
use BlackOps\Internal\Status\OperationStatusReadExecutionSource;
use BlackOps\Internal\Status\OperationStatusSourceException;
use BlackOps\Internal\Status\OperationStatusSubject;
use BlackOps\Internal\Status\TenantScopedOperationStatusSource;
use BlackOps\Status\Exception\OperationStatusQueryException;
use BlackOps\Status\OperationStatus;
use BlackOps\Status\OperationStatusAuthorizationDecision;
use BlackOps\Status\OperationStatusAuthorizationRequest;
use BlackOps\Status\OperationStatusAuthorizer;
use BlackOps\Status\OperationStatusExpired;
use BlackOps\Status\OperationStatusFound;
use BlackOps\Status\OperationStatusUnavailable;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionClass;
use RuntimeException;
use Throwable;

final class AuditedOperationStatusQueryTest extends TestCase
{
    private const string OPERATION_ID = '019f80ab-2000-7000-8000-000000000001';

    public function testFoundReadUsesOneBoundViewAndReleasesAfterTheAccessedTerminal(): void
    {
        $events = [];
        $tenant = new TenantRef('account', 'tenant-a');
        $subject = new OperationStatusSubject(
            $this->operationId(),
            'status.audited',
            new ActorRef('origin-a', 'user'),
            $tenant,
        );
        $status = OperationStatus::running($this->operationId(), 'status.audited', 1);
        $source = new AuditedStatusSource($events, $subject, new OperationStatusDetail($status));
        $authorizer = new AuditedStatusAuthorizer($events, OperationStatusAuthorizationDecision::allow());
        $writer = new AuditedStatusWriter($events);
        $query = $this->query($source, $authorizer, $writer, $events);

        $result = $query->findForTenant($this->operationId(), new ActorRef('current-a', 'user'), $tenant);

        self::assertInstanceOf(OperationStatusFound::class, $result);
        self::assertSame($status, $result->status());
        self::assertSame(['requested', 'subject', 'authorize', 'detail', 'terminal:accessed'], $events);
        self::assertSame([$tenant], $source->tenants);
        self::assertSame(1, $authorizer->calls);
        self::assertSame(1, $writer->terminalCalls);
    }

    public function testExpiredUsesAccessedAndUnavailableUsesUnavailableTerminal(): void
    {
        foreach ([
            'expired' => [new OperationStatusDetailExpired(), OperationStatusExpired::class, 'terminal:accessed'],
            'unavailable' => [
                new OperationStatusDetailUnavailable(),
                OperationStatusUnavailable::class,
                'terminal:unavailable',
            ],
        ] as $label => [$detail, $resultType, $terminal]) {
            $events = [];
            $source = new AuditedStatusSource($events, $this->subject(), $detail);
            $writer = new AuditedStatusWriter($events);
            $result = $this->query($source, new AuditedStatusAuthorizer($events), $writer, $events)->find(
                $this->operationId(),
            );

            self::assertInstanceOf($resultType, $result, $label);
            self::assertSame($terminal, $events[array_key_last($events)], $label);
            self::assertSame(1, $writer->terminalCalls, $label);
        }
    }

    public function testQueryFailureIsRethrownAfterUnavailableTerminal(): void
    {
        $events = [];
        $source = new AuditedStatusSource(
            $events,
            $this->subject(),
            new OperationStatusDetailUnavailable(),
            OperationStatusSourceException::decodeFailed(),
        );
        $writer = new AuditedStatusWriter($events);
        $exception = $this->captureQueryFailure(fn() => $this->query(
            $source,
            new AuditedStatusAuthorizer($events),
            $writer,
            $events,
        )->find($this->operationId()));

        self::assertSame(OperationStatusQueryException::DECODE_FAILED, $exception->queryCode());
        self::assertSame(['requested', 'subject', 'terminal:unavailable'], $events);
        self::assertSame(1, $writer->terminalCalls);
    }

    public function testAllFourQueryCodesHideSensitiveArgumentsWithExceptionArgumentsEnabled(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));

        try {
            foreach ([
                OperationStatusQueryException::AUTHORIZATION_FAILED => 'authorization',
                OperationStatusQueryException::STORAGE_FAILED => 'storage',
                OperationStatusQueryException::DECODE_FAILED => 'decode',
                OperationStatusQueryException::INTEGRITY_FAILED => 'integrity',
            ] as $expectedCode => $failureKind) {
                $events = [];
                $tenant = new TenantRef('account', 'trace-tenant-canary');
                $subject = new OperationStatusSubject(
                    $this->operationId(),
                    'status.trace',
                    new ActorRef('trace-origin-canary', 'user'),
                    $tenant,
                );
                $sourceFailure = match ($failureKind) {
                    'storage' => OperationStatusSourceException::storageFailed(),
                    'decode' => OperationStatusSourceException::decodeFailed(),
                    'integrity' => OperationStatusSourceException::integrityFailed(),
                    default => null,
                };
                $authorizerFailure = $failureKind === 'authorization'
                    ? new RuntimeException('trace-authorizer-canary')
                    : null;
                $source = new AuditedStatusSource(
                    $events,
                    $subject,
                    new OperationStatusDetailUnavailable(),
                    $sourceFailure,
                );
                $authorizer = new AuditedStatusAuthorizer($events, null, $authorizerFailure);
                $writer = new AuditedStatusWriter($events);
                $exception = $this->captureQueryFailure(fn() => $this->query(
                    $source,
                    $authorizer,
                    $writer,
                    $events,
                )->findForTenant($this->operationId(), new ActorRef('trace-actor-canary', 'user'), $tenant));

                self::assertSame($expectedCode, $exception->queryCode(), $failureKind);
                self::assertNull($exception->getPrevious(), $failureKind);
                self::assertSame(1, $writer->terminalCalls, $failureKind);
                self::assertSame('terminal:unavailable', $events[array_key_last($events)], $failureKind);
                $traceFrames = array_filter($exception->getTrace(), static fn(array $frame): bool => str_starts_with(
                    (string) ($frame['class'] ?? ''),
                    'BlackOps\\Internal\\',
                ));
                $trace = print_r($traceFrames, return: true) . var_export($traceFrames, true);
                foreach ([
                    self::OPERATION_ID,
                    'trace-origin-canary',
                    'trace-actor-canary',
                    'trace-tenant-canary',
                    'trace-authorizer-canary',
                ] as $canary) {
                    self::assertStringNotContainsString($canary, $trace, $failureKind);
                }
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testBeginFailureDoesNoReaderWorkAndTerminalFailureDoesNotRetry(): void
    {
        $events = [];
        $source = new AuditedStatusSource($events, $this->subject(), new OperationStatusDetailUnavailable());
        $writer = new AuditedStatusWriter($events);
        $writer->requestedFailure = new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        $exception = $this->captureQueryFailure(fn() => $this->query(
            $source,
            new AuditedStatusAuthorizer($events),
            $writer,
            $events,
        )->find($this->operationId()));

        self::assertSame(OperationStatusQueryException::STORAGE_FAILED, $exception->queryCode());
        self::assertSame([], $source->calls);
        self::assertSame(0, $writer->terminalCalls);

        $events = [];
        $source = new AuditedStatusSource($events, $this->subject(), new OperationStatusDetailUnavailable());
        $writer = new AuditedStatusWriter($events);
        $writer->terminalFailure = new AuditDeliveryException(AuditDeliveryFailure::ResourceUnavailable);
        $authorizer = new AuditedStatusAuthorizer($events);
        $exception = $this->captureQueryFailure(fn() => $this->query($source, $authorizer, $writer, $events)->find(
            $this->operationId(),
        ));

        self::assertSame(OperationStatusQueryException::STORAGE_FAILED, $exception->queryCode());
        self::assertSame(1, $writer->terminalCalls);
        self::assertSame(['requested', 'subject', 'authorize', 'detail', 'terminal:unavailable'], $events);
    }

    public function testTerminalFailureMasksFoundExpiredAndPendingQueryFailureWithoutRetry(): void
    {
        foreach ([
            'found' => [
                new OperationStatusDetail(OperationStatus::running($this->operationId(), 'status.audited', 1)),
                'terminal:accessed',
                null,
            ],
            'expired' => [new OperationStatusDetailExpired(), 'terminal:accessed', null],
            'query failure' => [
                new OperationStatusDetailUnavailable(),
                'terminal:unavailable',
                OperationStatusSourceException::decodeFailed(),
            ],
        ] as $label => [$detail, $terminal, $sourceFailure]) {
            $events = [];
            $source = new AuditedStatusSource($events, $this->subject(), $detail, $sourceFailure ?? null);
            $writer = new AuditedStatusWriter($events);
            $writer->terminalFailure = new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
            $authorizer = new AuditedStatusAuthorizer($events);
            $exception = $this->captureQueryFailure(fn() => $this->query($source, $authorizer, $writer, $events)->find(
                $this->operationId(),
            ));

            self::assertSame(OperationStatusQueryException::STORAGE_FAILED, $exception->queryCode(), $label);
            self::assertNull($exception->getPrevious(), $label);
            self::assertSame(1, $writer->terminalCalls, $label);
            self::assertSame($terminal, $events[array_key_last($events)], $label);
        }
    }

    public function testRequestedAndTerminalPostStateFailuresWithholdTheRead(): void
    {
        $events = [];
        $source = new AuditedStatusSource($events, $this->subject(), new OperationStatusDetailUnavailable());
        $writer = new AuditedStatusWriter($events);
        $writer->recordRequestedBeforeFailure = true;
        $writer->requestedFailure = new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        $exception = $this->captureQueryFailure(fn() => $this->query(
            $source,
            new AuditedStatusAuthorizer($events),
            $writer,
            $events,
        )->find($this->operationId()));

        self::assertSame(OperationStatusQueryException::STORAGE_FAILED, $exception->queryCode());
        self::assertSame(['requested'], $events);
        self::assertSame([], $source->calls);
        self::assertSame(0, $writer->terminalCalls);

        $events = [];
        $state = 'idle';
        $source = new AuditedStatusSource(
            $events,
            $this->subject(),
            new OperationStatusDetail(OperationStatus::running($this->operationId(), 'status.audited', 1)),
        );
        $writer = new AuditedStatusWriter($events);
        $authorizer = new AuditedStatusAuthorizer($events);
        $writer->afterRequested = static function () use (&$state): void {
            $state = 'active';
        };
        $query = $this->query($source, $authorizer, $writer, $events, $this->connectionWithState($state));
        $exception = $this->captureQueryFailure(fn() => $query->find($this->operationId()));

        self::assertSame(OperationStatusQueryException::STORAGE_FAILED, $exception->queryCode());
        self::assertSame(['requested'], $events);
        self::assertSame([], $source->calls);
        self::assertSame(0, $writer->terminalCalls);

        $events = [];
        $state = 'idle';
        $source = new AuditedStatusSource(
            $events,
            $this->subject(),
            new OperationStatusDetail(OperationStatus::running($this->operationId(), 'status.audited', 1)),
        );
        $writer = new AuditedStatusWriter($events);
        $authorizer = new AuditedStatusAuthorizer($events);
        $writer->afterTerminal = static function () use (&$state): void {
            $state = 'active';
        };
        $query = $this->query($source, $authorizer, $writer, $events, $this->connectionWithState($state));
        $exception = $this->captureQueryFailure(fn() => $query->find($this->operationId()));

        self::assertSame(OperationStatusQueryException::STORAGE_FAILED, $exception->queryCode());
        self::assertSame(['requested', 'subject', 'authorize', 'detail', 'terminal:accessed'], $events);
        self::assertSame(1, $writer->terminalCalls);
    }

    public function testMissingForeignAndDeniedReadsNeverDecodeDetail(): void
    {
        $cases = [
            'missing' => [
                null,
                OperationStatusAuthorizationDecision::allow(),
                null,
                ['requested', 'subject', 'terminal:unavailable'],
            ],
            'foreign' => [
                new OperationStatusSubject(
                    $this->operationId(),
                    'status.foreign',
                    null,
                    new TenantRef('account', 'tenant-b'),
                ),
                OperationStatusAuthorizationDecision::allow(),
                new TenantRef('account', 'tenant-a'),
                ['requested', 'subject', 'terminal:unavailable'],
            ],
            'denied' => [
                $this->subject(),
                OperationStatusAuthorizationDecision::deny(),
                null,
                ['requested', 'subject', 'authorize', 'terminal:unavailable'],
            ],
        ];

        foreach ($cases as $label => [$subject, $decision, $tenant, $expectedEvents]) {
            $events = [];
            $source = new AuditedStatusSource($events, $subject, new OperationStatusDetailUnavailable());
            $authorizer = new AuditedStatusAuthorizer($events, $decision);
            $writer = new AuditedStatusWriter($events);
            $result = $this->query($source, $authorizer, $writer, $events)->findForTenant(
                $this->operationId(),
                null,
                $tenant,
            );

            self::assertInstanceOf(OperationStatusUnavailable::class, $result, $label);
            self::assertSame($expectedEvents, $events, $label);
            self::assertSame([], $source->decodeCalls, $label);
            self::assertSame(0, $source->detailCalls, $label);
            self::assertSame($label === 'denied' ? 1 : 0, $authorizer->calls, $label);
        }
    }

    #[DataProvider('invalidConnectionStates')]
    public function testInvalidConnectionStateStopsBeforeSessionOrReader(string $state): void
    {
        $events = [];
        $source = new AuditedStatusSource($events, $this->subject(), new OperationStatusDetailUnavailable());
        $writer = new AuditedStatusWriter($events);
        $connection = $this->connectionFor($state);
        $exception = $this->captureQueryFailure(fn() => $this->query(
            $source,
            new AuditedStatusAuthorizer($events),
            $writer,
            $events,
            $connection,
        )->find($this->operationId()));

        self::assertSame(OperationStatusQueryException::STORAGE_FAILED, $exception->queryCode());
        self::assertSame([], $events);
        self::assertSame([], $source->calls);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidConnectionStates(): iterable
    {
        yield 'not auto commit' => ['not-auto-commit'];
        yield 'active transaction' => ['active'];
        yield 'nonzero nesting' => ['nonzero'];
        yield 'inspection throwable' => ['throwing'];
        yield 'inspection query failure' => ['throwing-query'];
    }

    public function testPerCallTenantBindingIsNotRetainedAcrossAlternatingReads(): void
    {
        $events = [];
        $tenantA = new TenantRef('account', 'tenant-a');
        $tenantB = new TenantRef('account', 'tenant-b');
        $source = new AlternatingAuditedStatusSource($events, $this->operationId());
        $authorizer = new AuditedStatusAuthorizer($events);
        $writer = new AuditedStatusWriter($events);
        $query = $this->query($source, $authorizer, $writer, $events);

        self::assertInstanceOf(OperationStatusUnavailable::class, $query->findForTenant(
            $this->operationId(),
            null,
            $tenantA,
        ));
        self::assertInstanceOf(OperationStatusUnavailable::class, $query->findForTenant(
            $this->operationId(),
            null,
            $tenantB,
        ));
        self::assertSame([$tenantA, $tenantB], $source->tenants);
        self::assertSame($tenantA, $authorizer->requests[0]->currentTenant());
        self::assertSame($tenantB, $authorizer->requests[1]->currentTenant());
    }

    public function testDebugSerializationAndUnserializeDoNotExposeCapturedDependencies(): void
    {
        $events = [];
        $source = new AuditedStatusSource($events, $this->subject(), new OperationStatusDetailUnavailable());
        $query = $this->query($source, new AuditedStatusAuthorizer($events), new AuditedStatusWriter($events), $events);

        self::assertSame([], $query->__debugInfo());
        self::assertSame([], $query->__serialize());
        self::assertStringNotContainsString(AuditedStatusSource::CANARY, print_r($query, return: true));
        self::assertStringNotContainsString(AuditedStatusSource::CANARY, var_export($query, return: true));
        self::assertStringNotContainsString(AuditedStatusSource::CANARY, serialize($query));
        try {
            $query->__unserialize([]);
            self::fail('Unserialize was accepted.');
        } catch (OperationStatusQueryException $exception) {
            self::assertSame(OperationStatusQueryException::STORAGE_FAILED, $exception->queryCode());
        }
    }

    public function testSnapshotTypeChecksRejectAnUnwrappedAuthorizer(): void
    {
        $events = [];
        $source = new AuditedStatusSource($events, $this->subject(), new OperationStatusDetailUnavailable());
        $writer = new AuditedStatusWriter($events);
        $connection = $this->idleConnection();
        $sessions = $this->sessions($writer, $events);
        $query = new ReflectionClass(AuditedOperationStatusQuery::class)->newInstanceWithoutConstructor();
        new ReflectionClass($query)
            ->getProperty('snapshot')
            ->setValue($query, new \SensitiveParameterValue([
                'source' => $source,
                'authorizer' => new AuditedStatusAuthorizer($events),
                'sessions' => $sessions,
                'connection' => $connection,
            ]));

        $exception = $this->captureQueryFailure(fn() => $query->find($this->operationId()));

        self::assertSame(OperationStatusQueryException::STORAGE_FAILED, $exception->queryCode());
        self::assertSame([], $events);
    }

    public function testDefaultHasExactlyFourteenSensitiveParameters(): void
    {
        $reflection = new ReflectionClass(\BlackOps\Internal\Status\DefaultOperationStatusQuery::class);
        $count = 0;
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            foreach ($method->getParameters() as $parameter) {
                $count += count($parameter->getAttributes(\SensitiveParameter::class));
            }
        }
        self::assertSame(14, $count);
    }

    private function query(
        TenantScopedOperationStatusSource $source,
        OperationStatusAuthorizer $authorizer,
        AuditedStatusWriter $writer,
        array &$events,
        ?Connection $connection = null,
    ): AuditedOperationStatusQuery {
        return new AuditedOperationStatusQuery(
            $source,
            $authorizer,
            $this->sessions($writer, $events),
            $connection ?? $this->idleConnection(),
        );
    }

    private function sessions(AuditedStatusWriter $writer, array &$events): OperationReadAuditSessionFactory
    {
        return new OperationReadAuditSessionFactory(
            $writer,
            new HmacAuditOpaqueIdDeriver('0123456789abcdef0123456789abcdef'),
            new AuditedStatusNonceSource(),
            new AuditedStatusClock(),
            new OperationStatusReadExecutionSource(new AuditedQueryUuidGenerator()),
            AuditSourceRuntime::Http,
            'audited-status-test-build',
        );
    }

    private function idleConnection(): Connection
    {
        return $this->connectionFor('idle');
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
                if ($state === 'throwing') {
                    throw new RuntimeException('connection state canary');
                }
                if ($state === 'throwing-query') {
                    throw OperationStatusQueryException::decodeFailed();
                }

                return $state === 'nonzero' ? 1 : 0;
            });

        return $connection;
    }

    private function connectionFor(string $state): Connection
    {
        return $this->connectionWithState($state);
    }

    private function subject(): OperationStatusSubject
    {
        return new OperationStatusSubject($this->operationId(), 'status.audited', null, null);
    }

    private function operationId(): OperationId
    {
        return OperationId::fromString(self::OPERATION_ID);
    }

    private function captureQueryFailure(\Closure $call): OperationStatusQueryException
    {
        try {
            $call();
            self::fail('Expected status query failure.');
        } catch (OperationStatusQueryException $exception) {
            return $exception;
        }
    }
}

final class AuditedStatusSource implements TenantScopedOperationStatusSource
{
    public const string CANARY = 'audited-status-source-canary';

    /** @var list<string> */
    public array $calls = [];

    /** @var list<TenantRef|null> */
    public array $tenants = [];

    public int $detailCalls = 0;

    /** @var list<string> */
    public array $decodeCalls = [];

    /** @var list<string> */
    private array $events;

    public function __construct(
        array &$events,
        private readonly ?OperationStatusSubject $subject,
        private readonly OperationStatusDetailResult $detail,
        private readonly ?OperationStatusSourceException $failure = null,
        private readonly string $canary = self::CANARY,
    ) {
        $this->events = &$events;
    }

    public function findSubject(OperationId $operationId): ?OperationStatusSubject
    {
        throw new RuntimeException(self::CANARY);
    }

    public function readDetail(OperationStatusSubject $subject): OperationStatusDetailResult
    {
        throw new RuntimeException(self::CANARY);
    }

    public function findSubjectForTenant(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): ?OperationStatusSubject {
        $this->events[] = 'subject';
        $this->calls[] = 'subject';
        $this->tenants[] = $tenant;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->subject;
    }

    public function readDetailForTenant(
        #[\SensitiveParameter]
        OperationStatusSubject $subject,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): OperationStatusDetailResult {
        $this->events[] = 'detail';
        $this->calls[] = 'detail';
        ++$this->detailCalls;
        $this->decodeCalls[] = $this->canary;

        return $this->detail;
    }
}

final class AlternatingAuditedStatusSource implements TenantScopedOperationStatusSource
{
    /** @var list<TenantRef|null> */
    public array $tenants = [];

    /** @var list<string> */
    private array $events;

    public function __construct(
        array &$events,
        private readonly OperationId $operationId,
    ) {
        $this->events = &$events;
    }

    public function findSubject(OperationId $operationId): ?OperationStatusSubject
    {
        throw new RuntimeException('legacy source path used');
    }

    public function readDetail(OperationStatusSubject $subject): OperationStatusDetailResult
    {
        throw new RuntimeException('legacy source path used');
    }

    public function findSubjectForTenant(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): ?OperationStatusSubject {
        $this->events[] = 'subject';
        $this->tenants[] = $tenant;

        return new OperationStatusSubject($this->operationId, 'status.audited', null, $tenant);
    }

    public function readDetailForTenant(
        #[\SensitiveParameter]
        OperationStatusSubject $subject,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): OperationStatusDetailResult {
        $this->events[] = 'detail';

        return new OperationStatusDetailUnavailable();
    }
}

final class AuditedStatusAuthorizer implements OperationStatusAuthorizer
{
    public int $calls = 0;

    /** @var list<OperationStatusAuthorizationRequest> */
    public array $requests = [];

    private readonly OperationStatusAuthorizationDecision $decision;

    private readonly ?Throwable $failure;

    /** @var list<string> */
    private array $events;

    public function __construct(
        array &$events,
        ?OperationStatusAuthorizationDecision $decision = null,
        ?Throwable $failure = null,
    ) {
        $this->events = &$events;
        $this->decision = $decision ?? OperationStatusAuthorizationDecision::allow();
        $this->failure = $failure;
    }

    public function decide(
        #[\SensitiveParameter]
        OperationStatusAuthorizationRequest $request,
    ): OperationStatusAuthorizationDecision {
        $this->events[] = 'authorize';
        ++$this->calls;
        $this->requests[] = $request;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->decision;
    }
}

final class AuditedStatusWriter implements AuditReadEventWriter
{
    public int $terminalCalls = 0;

    public ?AuditDeliveryException $requestedFailure = null;

    public ?AuditDeliveryException $terminalFailure = null;

    public bool $recordRequestedBeforeFailure = false;

    public ?\Closure $afterRequested = null;

    public ?\Closure $afterTerminal = null;

    /** @var list<string> */
    private array $events;

    public function __construct(array &$events)
    {
        $this->events = &$events;
    }

    public function requested(
        #[\SensitiveParameter]
        AuditReadRequestBasis $basis,
        #[\SensitiveParameter]
        \BlackOps\Core\Audit\AuditRecordDraft $draft,
    ): void {
        if ($this->recordRequestedBeforeFailure) {
            $this->events[] = 'requested';
        }
        if ($this->afterRequested !== null) {
            ($this->afterRequested)();
        }
        if ($this->requestedFailure !== null) {
            throw $this->requestedFailure;
        }
        if (!$this->recordRequestedBeforeFailure) {
            $this->events[] = 'requested';
        }
    }

    public function terminal(
        #[\SensitiveParameter]
        AuditReadContext $context,
        #[\SensitiveParameter]
        \BlackOps\Core\Audit\AuditRecordDraft $draft,
    ): \BlackOps\Core\Audit\AuditReadAuthorizationDecision {
        ++$this->terminalCalls;
        $this->events[] = 'terminal:' . match ($context->authorizationDecision) {
            AuditDecision::Allow => 'accessed',
            default => 'unavailable',
        };
        if ($this->afterTerminal !== null) {
            ($this->afterTerminal)();
        }
        if ($this->terminalFailure !== null) {
            throw $this->terminalFailure;
        }

        return $context->authorizationDecision;
    }
}

final class AuditedStatusNonceSource implements OperationReadNonceSource
{
    public function generate(): string
    {
        return "\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee\xff";
    }
}

final class AuditedStatusClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-11T00:00:00.123456Z');
    }
}

final class AuditedQueryUuidGenerator implements Uuidv7Generator
{
    public function generate(): string
    {
        return '019f80ab-2000-7000-8000-000000000099';
    }
}
