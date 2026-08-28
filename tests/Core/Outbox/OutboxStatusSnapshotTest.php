<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Outbox;

use BlackOps\Core\Outbox\OutboxStatusSnapshot;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OutboxStatusSnapshotTest extends TestCase
{
    public function testToArrayUsesTheClosedCanonicalShapeAndUtcTimestamp(): void
    {
        $snapshot = new OutboxStatusSnapshot(
            observedAt: new DateTimeImmutable('2026-08-27T15:00:00.123456+09:00'),
            pendingReady: 2,
            pendingDelayed: 3,
            retryReady: 4,
            retryDelayed: 5,
            leasedActive: 6,
            leasedExpired: 7,
            deadLettered: 8,
            oldestReady: 9,
            oldestLeasedExpired: 10,
            oldestDeadLettered: 11,
        );

        self::assertSame(
            [
                'agesSeconds' => [
                    'oldestDeadLettered' => 11,
                    'oldestLeasedExpired' => 10,
                    'oldestReady' => 9,
                ],
                'counts' => [
                    'deadLettered' => 8,
                    'leasedActive' => 6,
                    'leasedExpired' => 7,
                    'pendingDelayed' => 3,
                    'pendingReady' => 2,
                    'retryDelayed' => 5,
                    'retryReady' => 4,
                ],
                'observedAt' => '2026-08-27T06:00:00.123456Z',
                'schemaVersion' => 1,
                'scope' => 'installation',
            ],
            $snapshot->toArray(),
        );
    }

    public function testMaximumSafeCountAndAgeAreAccepted(): void
    {
        $snapshot = new OutboxStatusSnapshot(
            observedAt: new DateTimeImmutable('2026-08-27T15:00:00Z'),
            pendingReady: OutboxStatusSnapshot::MAX_SAFE_INTEGER,
            pendingDelayed: 0,
            retryReady: 0,
            retryDelayed: 0,
            leasedActive: 0,
            leasedExpired: 0,
            deadLettered: 0,
            oldestReady: OutboxStatusSnapshot::MAX_SAFE_INTEGER,
            oldestLeasedExpired: null,
            oldestDeadLettered: null,
        );

        self::assertSame(OutboxStatusSnapshot::MAX_SAFE_INTEGER, $snapshot->pendingReady);
        self::assertSame(OutboxStatusSnapshot::MAX_SAFE_INTEGER, $snapshot->oldestReady);
    }

    public function testNegativeAndOverflowCountsAndAgesAreRejected(): void
    {
        $cases = [
            'negative count' => ['pendingReady' => -1],
            'overflow count' => ['pendingReady' => OutboxStatusSnapshot::MAX_SAFE_INTEGER + 1],
            'negative age' => ['oldestReady' => -1],
            'overflow age' => ['oldestReady' => OutboxStatusSnapshot::MAX_SAFE_INTEGER + 1],
        ];

        foreach ($cases as $label => $overrides) {
            try {
                new OutboxStatusSnapshot(
                    observedAt: new DateTimeImmutable('2026-08-27T15:00:00Z'),
                    pendingReady: $overrides['pendingReady'] ?? 1,
                    pendingDelayed: 0,
                    retryReady: 0,
                    retryDelayed: 0,
                    leasedActive: 0,
                    leasedExpired: 0,
                    deadLettered: 0,
                    oldestReady: $overrides['oldestReady'] ?? 0,
                    oldestLeasedExpired: null,
                    oldestDeadLettered: null,
                );
                self::fail("Expected {$label} to be rejected.");
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testEachAgeMustMatchThePresenceOfItsCorrespondingCount(): void
    {
        $cases = [
            'ready age missing' => [
                'pendingReady' => 1,
                'oldestReady' => null,
            ],
            'ready age without count' => [
                'pendingReady' => 0,
                'oldestReady' => 0,
            ],
            'leased expired age missing' => [
                'leasedExpired' => 1,
                'oldestLeasedExpired' => null,
            ],
            'leased expired age without count' => [
                'leasedExpired' => 0,
                'oldestLeasedExpired' => 0,
            ],
            'dead letter age missing' => [
                'deadLettered' => 1,
                'oldestDeadLettered' => null,
            ],
            'dead letter age without count' => [
                'deadLettered' => 0,
                'oldestDeadLettered' => 0,
            ],
        ];

        foreach ($cases as $label => $overrides) {
            try {
                new OutboxStatusSnapshot(
                    observedAt: new DateTimeImmutable('2026-08-27T15:00:00Z'),
                    pendingReady: $overrides['pendingReady'] ?? 0,
                    pendingDelayed: 0,
                    retryReady: 0,
                    retryDelayed: 0,
                    leasedActive: 0,
                    leasedExpired: $overrides['leasedExpired'] ?? 0,
                    deadLettered: $overrides['deadLettered'] ?? 0,
                    oldestReady: $overrides['oldestReady'] ?? null,
                    oldestLeasedExpired: $overrides['oldestLeasedExpired'] ?? null,
                    oldestDeadLettered: $overrides['oldestDeadLettered'] ?? null,
                );
                self::fail("Expected {$label} to be rejected.");
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
