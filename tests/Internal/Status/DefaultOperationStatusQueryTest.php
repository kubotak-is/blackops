<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Status;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Status\DefaultOperationStatusQuery;
use BlackOps\Internal\Status\OperationStatusDetail;
use BlackOps\Internal\Status\OperationStatusDetailExpired;
use BlackOps\Internal\Status\OperationStatusDetailResult;
use BlackOps\Internal\Status\OperationStatusDetailUnavailable;
use BlackOps\Internal\Status\OperationStatusSource;
use BlackOps\Internal\Status\OperationStatusSourceException;
use BlackOps\Internal\Status\OperationStatusSubject;
use BlackOps\Status\Exception\OperationStatusQueryException;
use BlackOps\Status\OperationStatus;
use BlackOps\Status\OperationStatusAuthorizationDecision;
use BlackOps\Status\OperationStatusAuthorizationRequest;
use BlackOps\Status\OperationStatusAuthorizer;
use BlackOps\Status\OperationStatusExpired;
use BlackOps\Status\OperationStatusFound;
use BlackOps\Status\OperationStatusUnavailable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/** @mago-expect lint:too-many-methods */
final class DefaultOperationStatusQueryTest extends TestCase
{
    private const string OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';
    private const string OTHER_OPERATION_ID = '019f32ac-2be0-7b38-a0a7-1ab2f9687697';

    public function testUnknownReturnsUnavailableWithoutAuthorizationOrDetailRead(): void
    {
        $calls = new StatusCallLog();
        $source = new FakeOperationStatusSource($calls, null);
        $authorizer = new FakeOperationStatusAuthorizer($calls, OperationStatusAuthorizationDecision::allow());

        $result = new DefaultOperationStatusQuery($source, $authorizer)->find($this->operationId());

        self::assertInstanceOf(OperationStatusUnavailable::class, $result);
        self::assertSame(['subject'], $calls->calls);
        self::assertNull($authorizer->request);
    }

    public function testDenyMatchesUnknownAndNeverReadsDetail(): void
    {
        $calls = new StatusCallLog();
        $source = new FakeOperationStatusSource($calls, $this->subject(), new OperationStatusDetailExpired());
        $authorizer = new FakeOperationStatusAuthorizer($calls, OperationStatusAuthorizationDecision::deny());

        $result = new DefaultOperationStatusQuery($source, $authorizer)->find($this->operationId());

        self::assertInstanceOf(OperationStatusUnavailable::class, $result);
        self::assertSame(['subject', 'authorize'], $calls->calls);
    }

    public function testAllowReturnsExpiredOnlyAfterDetailRead(): void
    {
        $calls = new StatusCallLog();
        $subject = new OperationStatusSubject(
            $this->operationId(),
            'report.generate',
            new ActorRef('origin-user', 'user'),
        );
        $source = new FakeOperationStatusSource($calls, $subject, new OperationStatusDetailExpired());
        $authorizer = new FakeOperationStatusAuthorizer($calls, OperationStatusAuthorizationDecision::allow());

        $result = new DefaultOperationStatusQuery($source, $authorizer)->find(
            $this->operationId(),
            new ActorRef('current-user', 'user'),
        );

        self::assertInstanceOf(OperationStatusExpired::class, $result);
        self::assertSame(['subject', 'authorize', 'detail'], $calls->calls);
        self::assertSame('current-user', $authorizer->request?->currentActor()?->id());
        self::assertSame('origin-user', $authorizer->request?->originActor()?->id());
    }

    public function testEphemeralStatusBecomesUnavailableOnlyAfterAuthorization(): void
    {
        $calls = new StatusCallLog();
        $source = new FakeOperationStatusSource($calls, $this->subject(), new OperationStatusDetailUnavailable());
        $authorizer = new FakeOperationStatusAuthorizer($calls, OperationStatusAuthorizationDecision::allow());

        $result = new DefaultOperationStatusQuery($source, $authorizer)->find($this->operationId());

        self::assertInstanceOf(OperationStatusUnavailable::class, $result);
        self::assertSame(['subject', 'authorize', 'detail'], $calls->calls);
        self::assertNotNull($authorizer->request);
    }

    public function testAllowOnAvailableSubjectReadsDetailAndReturnsFound(): void
    {
        $calls = new StatusCallLog();
        $status = OperationStatus::running($this->operationId(), 'report.generate', 1);
        $source = new FakeOperationStatusSource($calls, $this->subject(), new OperationStatusDetail($status));
        $authorizer = new FakeOperationStatusAuthorizer($calls, OperationStatusAuthorizationDecision::allow());

        $result = new DefaultOperationStatusQuery($source, $authorizer)->find($this->operationId());

        self::assertInstanceOf(OperationStatusFound::class, $result);
        self::assertSame($status, $result->status());
        self::assertSame(['subject', 'authorize', 'detail'], $calls->calls);
        self::assertSame(self::OPERATION_ID, $authorizer->request?->operationId()->toString());
        self::assertSame('report.generate', $authorizer->request?->operationType());
        self::assertNull($authorizer->request?->currentActor());
        self::assertNull($authorizer->request?->originActor());
    }

    public function testMismatchedSubjectFailsIntegrityBeforeAuthorization(): void
    {
        $calls = new StatusCallLog();
        $subject = new OperationStatusSubject(
            OperationId::fromString(self::OTHER_OPERATION_ID),
            'report.generate',
            null,
        );
        $source = new FakeOperationStatusSource($calls, $subject);
        $authorizer = new FakeOperationStatusAuthorizer($calls, OperationStatusAuthorizationDecision::allow());

        $exception = $this->captureQueryFailure(static fn() => new DefaultOperationStatusQuery(
            $source,
            $authorizer,
        )->find(OperationId::fromString(self::OPERATION_ID)));

        self::assertSame(OperationStatusQueryException::INTEGRITY_FAILED, $exception->queryCode());
        self::assertSame(['subject'], $calls->calls);
    }

    public static function mismatchedDetails(): iterable
    {
        yield 'operation id' => [OperationStatus::accepted(
            OperationId::fromString(self::OTHER_OPERATION_ID),
            'report.generate',
        )];
        yield 'operation type' => [OperationStatus::accepted(
            OperationId::fromString(self::OPERATION_ID),
            'order.create',
        )];
    }

    #[DataProvider('mismatchedDetails')]
    public function testMismatchedDetailFailsIntegrity(OperationStatus $status): void
    {
        $calls = new StatusCallLog();
        $source = new FakeOperationStatusSource($calls, $this->subject(), new OperationStatusDetail($status));
        $authorizer = new FakeOperationStatusAuthorizer($calls, OperationStatusAuthorizationDecision::allow());

        $exception = $this->captureQueryFailure(fn() => new DefaultOperationStatusQuery($source, $authorizer)->find(
            $this->operationId(),
        ));

        self::assertSame(OperationStatusQueryException::INTEGRITY_FAILED, $exception->queryCode());
        self::assertSame(['subject', 'authorize', 'detail'], $calls->calls);
    }

    public function testAuthorizerFailureIsSafeAndDoesNotReadDetail(): void
    {
        $calls = new StatusCallLog();
        $source = new FakeOperationStatusSource($calls, $this->subject());
        $authorizer = new FakeOperationStatusAuthorizer(
            $calls,
            OperationStatusAuthorizationDecision::deny(),
            new RuntimeException('authorization backend credential secret'),
        );

        $exception = $this->captureQueryFailure(fn() => new DefaultOperationStatusQuery($source, $authorizer)->find(
            $this->operationId(),
        ));

        self::assertSame(OperationStatusQueryException::AUTHORIZATION_FAILED, $exception->queryCode());
        self::assertStringNotContainsString('credential secret', $exception->getMessage());
        self::assertSame(['subject', 'authorize'], $calls->calls);
    }

    public static function sourceFailures(): iterable
    {
        yield 'storage' => [
            OperationStatusSourceException::storageFailed(),
            OperationStatusQueryException::STORAGE_FAILED,
        ];
        yield 'decode' => [
            OperationStatusSourceException::decodeFailed(),
            OperationStatusQueryException::DECODE_FAILED,
        ];
        yield 'integrity' => [
            OperationStatusSourceException::integrityFailed(),
            OperationStatusQueryException::INTEGRITY_FAILED,
        ];
        yield 'unexpected throwable' => [
            new RuntimeException('database password secret'),
            OperationStatusQueryException::STORAGE_FAILED,
        ];
    }

    #[DataProvider('sourceFailures')]
    public function testSubjectSourceFailuresAreSafelyNormalized(Throwable $failure, string $expectedCode): void
    {
        $calls = new StatusCallLog();
        $source = new FakeOperationStatusSource($calls, $this->subject(), subjectFailure: $failure);
        $authorizer = new FakeOperationStatusAuthorizer($calls, OperationStatusAuthorizationDecision::allow());

        $exception = $this->captureQueryFailure(fn() => new DefaultOperationStatusQuery($source, $authorizer)->find(
            $this->operationId(),
        ));

        self::assertSame($expectedCode, $exception->queryCode());
        self::assertStringNotContainsString('secret', $exception->getMessage());
        self::assertSame(['subject'], $calls->calls);
    }

    #[DataProvider('sourceFailures')]
    public function testDetailSourceFailuresAreSafelyNormalized(Throwable $failure, string $expectedCode): void
    {
        $calls = new StatusCallLog();
        $source = new FakeOperationStatusSource($calls, $this->subject(), detailFailure: $failure);
        $authorizer = new FakeOperationStatusAuthorizer($calls, OperationStatusAuthorizationDecision::allow());

        $exception = $this->captureQueryFailure(fn() => new DefaultOperationStatusQuery($source, $authorizer)->find(
            $this->operationId(),
        ));

        self::assertSame($expectedCode, $exception->queryCode());
        self::assertStringNotContainsString('secret', $exception->getMessage());
        self::assertSame(['subject', 'authorize', 'detail'], $calls->calls);
    }

    private function operationId(): OperationId
    {
        return OperationId::fromString(self::OPERATION_ID);
    }

    private function subject(): OperationStatusSubject
    {
        return new OperationStatusSubject($this->operationId(), 'report.generate', null);
    }

    /** @param callable(): mixed $query */
    private function captureQueryFailure(callable $query): OperationStatusQueryException
    {
        try {
            $query();
            self::fail('Expected status query failure.');
        } catch (OperationStatusQueryException $exception) {
            return $exception;
        }
    }
}

final class StatusCallLog
{
    /** @var list<string> */
    public array $calls = [];
}

final class FakeOperationStatusSource implements OperationStatusSource
{
    public function __construct(
        private readonly StatusCallLog $log,
        private readonly ?OperationStatusSubject $subject,
        private readonly ?OperationStatusDetailResult $detail = null,
        private readonly ?Throwable $subjectFailure = null,
        private readonly ?Throwable $detailFailure = null,
    ) {}

    public function findSubject(OperationId $operationId): ?OperationStatusSubject
    {
        $this->log->calls[] = 'subject';
        if ($this->subjectFailure !== null) {
            throw $this->subjectFailure;
        }

        return $this->subject;
    }

    public function readDetail(OperationStatusSubject $subject): OperationStatusDetailResult
    {
        $this->log->calls[] = 'detail';
        if ($this->detailFailure !== null) {
            throw $this->detailFailure;
        }

        return (
            $this->detail ?? new OperationStatusDetail(OperationStatus::accepted(
                $subject->operationId,
                $subject->operationType,
            ))
        );
    }
}

final class FakeOperationStatusAuthorizer implements OperationStatusAuthorizer
{
    public ?OperationStatusAuthorizationRequest $request = null;

    public function __construct(
        private readonly StatusCallLog $log,
        private readonly OperationStatusAuthorizationDecision $decision,
        private readonly ?Throwable $failure = null,
    ) {}

    public function decide(OperationStatusAuthorizationRequest $request): OperationStatusAuthorizationDecision
    {
        $this->log->calls[] = 'authorize';
        $this->request = $request;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->decision;
    }
}
