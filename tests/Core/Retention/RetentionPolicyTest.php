<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Retention\RetentionPeriod;
use BlackOps\Core\Retention\RetentionPolicy;
use BlackOps\Core\Retention\RetentionTarget;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RetentionPolicyTest extends TestCase
{
    public function testContractsArePublicApi(): void
    {
        foreach ([RetentionTarget::class, RetentionPeriod::class, RetentionPolicy::class] as $type) {
            self::assertCount(1, new ReflectionClass($type)->getAttributes(PublicApi::class));
        }
    }

    public function testTargetsUseStableWireValues(): void
    {
        self::assertSame('transport_payload', RetentionTarget::TransportPayload->value);
        self::assertSame('journal', RetentionTarget::Journal->value);
        self::assertSame('outcome', RetentionTarget::Outcome->value);
        self::assertSame('dead_letter', RetentionTarget::DeadLetter->value);
    }

    public function testPeriodRequiresPositiveSeconds(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RetentionPeriod::seconds(0);
    }

    public function testPeriodRequiresPositiveDays(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RetentionPeriod::days(-1);
    }

    public function testPeriodComputesExpiryTime(): void
    {
        $period = RetentionPeriod::days(2);

        self::assertSame(172_800, $period->secondsValue());
        self::assertSame(
            '2026-07-12T00:00:00+00:00',
            $period->expiresAt(new DateTimeImmutable('2026-07-10T00:00:00Z'))->format(DATE_ATOM),
        );
    }

    public function testPolicyRequiresExplicitPeriodForEveryTarget(): void
    {
        $transport = RetentionPeriod::days(7);
        $journal = RetentionPeriod::days(30);
        $outcome = RetentionPeriod::days(14);
        $deadLetter = RetentionPeriod::days(90);
        $policy = new RetentionPolicy($transport, $journal, $outcome, $deadLetter);

        self::assertSame($transport, $policy->transportPayloadRetention());
        self::assertSame($journal, $policy->journalRetention());
        self::assertSame($outcome, $policy->outcomeRetention());
        self::assertSame($deadLetter, $policy->deadLetterRetention());
        self::assertSame($transport, $policy->forTarget(RetentionTarget::TransportPayload));
        self::assertSame($journal, $policy->forTarget(RetentionTarget::Journal));
        self::assertSame($outcome, $policy->forTarget(RetentionTarget::Outcome));
        self::assertSame($deadLetter, $policy->forTarget(RetentionTarget::DeadLetter));
    }
}
