<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Supervision;

use BlackOps\Core\AttemptContext;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Supervision\ExponentialBackoffSupervisionPolicy;
use BlackOps\Core\Supervision\RetryableException;
use BlackOps\Core\Supervision\SupervisionAction;
use BlackOps\Core\Supervision\SupervisionDecision;
use BlackOps\Core\Supervision\SupervisionPolicy;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class SupervisionPolicyTest extends TestCase
{
    private const ATTEMPT_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687751';

    public function testContractsArePublicApi(): void
    {
        foreach ([
            SupervisionPolicy::class,
            SupervisionAction::class,
            SupervisionDecision::class,
            RetryableException::class,
            ExponentialBackoffSupervisionPolicy::class,
        ] as $type) {
            self::assertCount(1, new ReflectionClass($type)->getAttributes(PublicApi::class));
        }
    }

    public function testDecisionShapes(): void
    {
        $retry = SupervisionDecision::retry(123);

        self::assertSame(SupervisionAction::Retry, $retry->action());
        self::assertSame(123, $retry->delayMilliseconds());
        self::assertSame(SupervisionAction::Fail, SupervisionDecision::fail()->action());
        self::assertSame(SupervisionAction::DeadLetter, SupervisionDecision::deadLetter()->action());
    }

    public function testNonRetryDecisionDoesNotExposeDelay(): void
    {
        $this->expectException(LogicException::class);

        SupervisionDecision::fail()->delayMilliseconds();
    }

    public function testRejectsNegativeDelay(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SupervisionDecision::retry(-1);
    }

    public function testDefaultPolicyRetriesRetryableExceptionWithDeterministicBackoffWhenJitterDisabled(): void
    {
        $policy = new ExponentialBackoffSupervisionPolicy(jitterRatio: 0.0);

        $first = $policy->decide(new RetryableRuntimeException('temporary'), $this->attempt(1));
        $second = $policy->decide(new RetryableRuntimeException('temporary'), $this->attempt(2));

        self::assertSame(SupervisionAction::Retry, $first->action());
        self::assertSame(1_000, $first->delayMilliseconds());
        self::assertSame(SupervisionAction::Retry, $second->action());
        self::assertSame(2_000, $second->delayMilliseconds());
    }

    public function testDefaultPolicyFailsNonRetryableException(): void
    {
        $decision = new ExponentialBackoffSupervisionPolicy(jitterRatio: 0.0)->decide(
            new RuntimeException('permanent'),
            $this->attempt(1),
        );

        self::assertSame(SupervisionAction::Fail, $decision->action());
    }

    public function testDefaultPolicyFailsWhenMaximumAttemptsReached(): void
    {
        $decision = new ExponentialBackoffSupervisionPolicy(jitterRatio: 0.0)->decide(
            new RetryableRuntimeException('temporary'),
            $this->attempt(3),
        );

        self::assertSame(SupervisionAction::Fail, $decision->action());
    }

    public function testJitterKeepsDelayInsideConfiguredRange(): void
    {
        $decision = new ExponentialBackoffSupervisionPolicy(jitterRatio: 0.2)->decide(
            new RetryableRuntimeException('temporary'),
            $this->attempt(1),
        );

        self::assertGreaterThanOrEqual(800, $decision->delayMilliseconds());
        self::assertLessThanOrEqual(1_200, $decision->delayMilliseconds());
    }

    private function attempt(int $number): AttemptContext
    {
        return new AttemptContext(
            AttemptId::fromString(self::ATTEMPT_ID),
            $number,
            new DateTimeImmutable('2026-07-10T00:00:00Z'),
        );
    }
}

final class RetryableRuntimeException extends RuntimeException implements RetryableException {}
