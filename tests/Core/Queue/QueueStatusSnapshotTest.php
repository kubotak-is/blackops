<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Queue;

use BlackOps\Core\Queue\QueueStatusSnapshot;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class QueueStatusSnapshotTest extends TestCase
{
    public function testToArrayUsesTheClosedInstallationWireAndUtcMicroseconds(): void
    {
        $snapshot = $this->snapshot(
            observedAt: new DateTimeImmutable('2026-08-27T15:00:00+09:00'),
            acceptedReady: 1,
            oldestReady: 0,
        );

        self::assertSame(
            [
                'agesSeconds' => [
                    'oldestDeadLettered' => null,
                    'oldestReady' => 0,
                    'oldestRunningExpired' => null,
                    'oldestSupervising' => null,
                ],
                'counts' => [
                    'acceptedDelayed' => 0,
                    'acceptedReady' => 1,
                    'deadLettered' => 0,
                    'retryDelayed' => 0,
                    'retryReady' => 0,
                    'runningActive' => 0,
                    'runningExpired' => 0,
                    'supervising' => 0,
                ],
                'observedAt' => '2026-08-27T06:00:00.000000Z',
                'schemaVersion' => 1,
                'scope' => 'installation',
            ],
            $snapshot->toArray(),
        );
    }

    public function testSafeIntegerUpperBoundIsAccepted(): void
    {
        $maximum = QueueStatusSnapshot::MAX_SAFE_INTEGER;
        $snapshot = new QueueStatusSnapshot(
            observedAt: new DateTimeImmutable('2026-08-27T15:00:00Z'),
            acceptedReady: $maximum,
            acceptedDelayed: 0,
            retryReady: 0,
            retryDelayed: 0,
            runningActive: 0,
            runningExpired: 0,
            supervising: 0,
            deadLettered: 0,
            oldestReady: $maximum,
            oldestRunningExpired: null,
            oldestSupervising: null,
            oldestDeadLettered: null,
        );

        self::assertSame($maximum, $snapshot->acceptedReady);
        self::assertSame($maximum, $snapshot->oldestReady);
    }

    public function testAgeAboveSafeIntegerUpperBoundIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->snapshot(acceptedReady: 1, oldestReady: QueueStatusSnapshot::MAX_SAFE_INTEGER + 1);
    }

    public function testNegativeAndUnsafeIntegersAreRejected(): void
    {
        foreach ([-1, QueueStatusSnapshot::MAX_SAFE_INTEGER + 1] as $count) {
            try {
                $this->snapshot(acceptedReady: $count);
                self::fail('Expected an invalid count to be rejected.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testAgeNullabilityMustMatchItsBucketCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->snapshot(oldestReady: 1);
    }

    private function snapshot(
        ?DateTimeImmutable $observedAt = null,
        int $acceptedReady = 0,
        int $acceptedDelayed = 0,
        int $retryReady = 0,
        int $retryDelayed = 0,
        int $runningActive = 0,
        int $runningExpired = 0,
        int $supervising = 0,
        int $deadLettered = 0,
        ?int $oldestReady = null,
        ?int $oldestRunningExpired = null,
        ?int $oldestSupervising = null,
        ?int $oldestDeadLettered = null,
    ): QueueStatusSnapshot {
        return new QueueStatusSnapshot(
            observedAt: $observedAt ?? new DateTimeImmutable('2026-08-27T15:00:00Z'),
            acceptedReady: $acceptedReady,
            acceptedDelayed: $acceptedDelayed,
            retryReady: $retryReady,
            retryDelayed: $retryDelayed,
            runningActive: $runningActive,
            runningExpired: $runningExpired,
            supervising: $supervising,
            deadLettered: $deadLettered,
            oldestReady: $oldestReady,
            oldestRunningExpired: $oldestRunningExpired,
            oldestSupervising: $oldestSupervising,
            oldestDeadLettered: $oldestDeadLettered,
        );
    }
}
