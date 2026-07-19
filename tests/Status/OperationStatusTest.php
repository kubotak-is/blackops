<?php

declare(strict_types=1);

namespace BlackOps\Tests\Status;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Outcome;
use BlackOps\Status\OperationStatus;
use BlackOps\Status\OperationStatusError;
use BlackOps\Status\OperationStatusState;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OperationStatusTest extends TestCase
{
    private const string OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testAcceptedContainsOnlyIdentityAndState(): void
    {
        $status = OperationStatus::accepted($this->operationId(), 'report.generate');

        $this->assertCommon($status, OperationStatusState::Accepted);
        self::assertNull($status->attempt());
        self::assertNull($status->retryAt());
        self::assertNull($status->outcome());
        self::assertNull($status->error());
    }

    public function testRunningContainsOnlyPositiveAttempt(): void
    {
        $status = OperationStatus::running($this->operationId(), 'report.generate', 2);

        $this->assertCommon($status, OperationStatusState::Running);
        self::assertSame(2, $status->attempt());
        self::assertNull($status->retryAt());
        self::assertNull($status->outcome());
        self::assertNull($status->error());
    }

    public function testRetryScheduledContainsAttemptAndUtcRetryTime(): void
    {
        $status = OperationStatus::retryScheduled(
            $this->operationId(),
            'report.generate',
            1,
            new DateTimeImmutable('2026-07-19T18:30:00.123456+09:00'),
        );

        $this->assertCommon($status, OperationStatusState::RetryScheduled);
        self::assertSame(1, $status->attempt());
        self::assertSame('UTC', $status->retryAt()?->getTimezone()->getName());
        self::assertSame('2026-07-19T09:30:00.123456+00:00', $status->retryAt()?->format('Y-m-d\TH:i:s.uP'));
        self::assertNull($status->outcome());
        self::assertNull($status->error());
    }

    public function testCompletedContainsTypedOutcomeAndDefaultsToEmptyOutcome(): void
    {
        $outcome = new StatusTestOutcome('report-1042');
        $completed = OperationStatus::completed($this->operationId(), 'report.generate', $outcome);
        $void = OperationStatus::completed($this->operationId(), 'report.generate');

        $this->assertCommon($completed, OperationStatusState::Completed);
        self::assertSame($outcome, $completed->outcome());
        self::assertInstanceOf(EmptyOutcome::class, $void->outcome());
        self::assertNull($completed->attempt());
        self::assertNull($completed->retryAt());
        self::assertNull($completed->error());
    }

    public function testRejectedContainsOnlySafeCategoryAndCode(): void
    {
        $status = OperationStatus::rejected($this->operationId(), 'report.generate', 'validation', 'validation_failed');

        $this->assertCommon($status, OperationStatusState::Rejected);
        self::assertSame('validation', $status->error()?->category());
        self::assertSame('validation_failed', $status->error()?->code());
        self::assertNull($status->attempt());
        self::assertNull($status->retryAt());
        self::assertNull($status->outcome());
    }

    public function testFailedAndDeadLetteredUseFixedPublicCodes(): void
    {
        $failed = OperationStatus::failed($this->operationId(), 'report.generate');
        $dead = OperationStatus::deadLettered($this->operationId(), 'report.generate');

        $this->assertCommon($failed, OperationStatusState::Failed);
        self::assertNull($failed->error()?->category());
        self::assertSame(OperationStatusError::OPERATION_FAILED, $failed->error()?->code());
        $this->assertCommon($dead, OperationStatusState::DeadLettered);
        self::assertNull($dead->error()?->category());
        self::assertSame(OperationStatusError::OPERATION_DEAD_LETTERED, $dead->error()?->code());
    }

    public static function invalidAttempts(): iterable
    {
        yield 'running zero' => [
            static fn(OperationId $id): OperationStatus => OperationStatus::running($id, 'report.generate', 0),
        ];
        yield 'retry negative' => [
            static fn(OperationId $id): OperationStatus => OperationStatus::retryScheduled(
                $id,
                'report.generate',
                -1,
                new DateTimeImmutable('2026-07-19T00:00:00Z'),
            ),
        ];
    }

    #[DataProvider('invalidAttempts')]
    public function testRejectsNonPositiveAttempts(callable $factory): void
    {
        $this->expectException(InvalidArgumentException::class);

        $factory($this->operationId());
    }

    public function testRejectsInvalidOperationTypeAndUnstableRejectedError(): void
    {
        foreach ([
            static fn(OperationId $id): OperationStatus => OperationStatus::accepted($id, 'Report Generate'),
            static fn(OperationId $id): OperationStatus => OperationStatus::rejected(
                $id,
                'report.generate',
                'Validation Error',
                'validation_failed',
            ),
            static fn(OperationId $id): OperationStatus => OperationStatus::rejected(
                $id,
                'report.generate',
                'validation',
                'secret token!',
            ),
        ] as $factory) {
            try {
                $factory($this->operationId());
                self::fail('Expected invalid status field to be rejected.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testAggregateAndErrorAreFinalReadonlyPublicApiWithPrivateConstructors(): void
    {
        foreach ([OperationStatus::class, OperationStatusError::class] as $type) {
            $reflection = new ReflectionClass($type);

            self::assertTrue($reflection->isFinal());
            self::assertTrue($reflection->isReadOnly());
            self::assertTrue($reflection->getConstructor()?->isPrivate());
            self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        }
    }

    private function operationId(): OperationId
    {
        return OperationId::fromString(self::OPERATION_ID);
    }

    private function assertCommon(OperationStatus $status, OperationStatusState $state): void
    {
        self::assertSame(self::OPERATION_ID, $status->operationId()->toString());
        self::assertSame('report.generate', $status->operationType());
        self::assertSame($state, $status->state());
    }
}

final readonly class StatusTestOutcome implements Outcome
{
    public function __construct(
        public string $reportId,
    ) {}
}
