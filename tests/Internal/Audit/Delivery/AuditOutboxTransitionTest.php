<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Delivery;

use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Delivery\AuditOutboxException;
use BlackOps\Internal\Audit\Delivery\AuditOutboxFailure;
use BlackOps\Internal\Audit\Delivery\AuditOutboxState;
use BlackOps\Internal\Audit\Delivery\AuditOutboxStateSnapshot;
use BlackOps\Internal\Audit\Delivery\AuditOutboxTransition;
use PHPUnit\Framework\TestCase;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class AuditOutboxTransitionTest extends TestCase
{
    private const string NOW = '2026-09-01T03:00:00.123456Z';
    private const string TOKEN_ONE = '018f0a00-0000-7000-8000-000000000001';
    private const string TOKEN_TWO = '018f0a00-0000-7000-8000-000000000002';

    public function testClaimUsesDueEqualityAndOnlyIncrementsAttemptAndVersion(): void
    {
        $configuration = new AuditOutboxConfiguration(300, 5, 1, 3600);
        $transition = new AuditOutboxTransition($configuration);
        $pending = $this->pending('2026-09-01T03:00:00.123456Z', $configuration);

        $this->assertFailure(
            static fn() => $transition->claim($pending, 1, self::TOKEN_ONE, '2026-09-01T02:59:59.123456Z'),
            AuditOutboxFailure::TransitionConflict,
        );

        $claimed = $transition->claim($pending, 1, self::TOKEN_ONE, self::NOW);
        self::assertSame(
            [
                'state' => 'leased',
                'attempt' => 1,
                'availableAt' => null,
                'leaseFencingToken' => self::TOKEN_ONE,
                'leaseExpiresAt' => '2026-09-01T03:05:00.123456Z',
                'version' => 2,
            ],
            $claimed->toArray(),
        );
        self::assertSame(
            [
                'state' => 'pending',
                'attempt' => 0,
                'availableAt' => '2026-09-01T03:00:00.123456Z',
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'version' => 1,
            ],
            $pending->toArray(),
        );

        $later = $transition->claim(
            $this->pending('2026-09-01T03:00:00.123455Z', $configuration),
            1,
            self::TOKEN_TWO,
            self::NOW,
        );
        self::assertSame(1, $later->attempt());
        self::assertSame(2, $later->version());

        $boundedConfiguration = new AuditOutboxConfiguration(60, 3, 1, 10);
        $almostExhausted = AuditOutboxStateSnapshot::fromArray([
            'state' => 'pending',
            'attempt' => 2,
            'availableAt' => self::NOW,
            'leaseFencingToken' => null,
            'leaseExpiresAt' => null,
            'version' => 7,
        ], $boundedConfiguration);
        $before = $almostExhausted->toArray();
        $exhaustingClaim = new AuditOutboxTransition($boundedConfiguration)->claim(
            $almostExhausted,
            7,
            self::TOKEN_TWO,
            self::NOW,
        );
        self::assertSame(3, $exhaustingClaim->attempt());
        self::assertSame(8, $exhaustingClaim->version());
        self::assertSame($before, $almostExhausted->toArray());
    }

    public function testClaimRejectsInvalidOwnershipTimeStateAndVersionInputs(): void
    {
        $configuration = new AuditOutboxConfiguration();
        $transition = new AuditOutboxTransition($configuration);
        $pending = $this->pending(self::NOW, $configuration);

        $this->assertFailure(
            static fn() => $transition->claim($pending, 0, self::TOKEN_ONE, self::NOW),
            AuditOutboxFailure::TransitionConflict,
        );
        $this->assertFailure(
            static fn() => $transition->claim($pending, 1, 'not-a-token', self::NOW),
            AuditOutboxFailure::TransitionConflict,
        );
        $this->assertFailure(
            static fn() => $transition->claim($pending, 1, self::TOKEN_ONE, 'not-a-timestamp'),
            AuditOutboxFailure::TransitionConflict,
        );
        $this->assertFailure(
            fn() => $transition->claim($this->terminal('relayed', 1, 1, $configuration), 1, self::TOKEN_ONE, self::NOW),
            AuditOutboxFailure::TransitionConflict,
        );
        $this->assertFailure(
            static fn() => $transition->claim($pending, 2, self::TOKEN_ONE, self::NOW),
            AuditOutboxFailure::TransitionConflict,
        );
    }

    public function testRetryRequiresAnActiveMatchingLeaseAndUsesSaturatedBackoff(): void
    {
        $configuration = new AuditOutboxConfiguration(60, 32, 3, 10);
        $transition = new AuditOutboxTransition($configuration);
        $leased = $this->leased(3, '2026-09-01T03:01:00.123456Z', 4, self::TOKEN_ONE, $configuration);

        $retried = $transition->retry($leased, 4, self::TOKEN_ONE, self::NOW);
        self::assertSame(
            [
                'state' => 'pending',
                'attempt' => 3,
                'availableAt' => '2026-09-01T03:00:10.123456Z',
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'version' => 5,
            ],
            $retried->toArray(),
        );
        self::assertSame(3, $leased->attempt());
        self::assertSame(4, $leased->version());

        $this->assertFailure(
            static fn() => $transition->retry($leased, 4, self::TOKEN_TWO, self::NOW),
            AuditOutboxFailure::TransitionConflict,
        );
        $this->assertFailure(
            static fn() => $transition->retry($leased, 3, self::TOKEN_ONE, self::NOW),
            AuditOutboxFailure::TransitionConflict,
        );
        $this->assertFailure(
            static fn() => $transition->retry($leased, 5, self::TOKEN_ONE, self::NOW),
            AuditOutboxFailure::TransitionConflict,
        );
        $this->assertFailure(
            static fn() => $transition->retry($leased, 4, 'invalid-token', self::NOW),
            AuditOutboxFailure::TransitionConflict,
        );
        $this->assertFailure(
            static fn() => $transition->retry($leased, 4, self::TOKEN_ONE, '2026-09-01T03:01:00.123456Z'),
            AuditOutboxFailure::TransitionConflict,
        );
        $this->assertFailure(
            static fn() => $transition->retry($leased, 4, self::TOKEN_ONE, '2026-09-01T03:01:00.123457Z'),
            AuditOutboxFailure::TransitionConflict,
        );

        $boundary = $this->leased(3, '2026-09-01T03:01:00.123456Z', 4, self::TOKEN_ONE, $configuration);
        $before = $boundary->toArray();
        $boundaryResult = $transition->retry($boundary, 4, self::TOKEN_ONE, '2026-09-01T03:00:59.123455Z');
        self::assertSame('pending', $boundaryResult->toArray()['state']);
        self::assertSame(5, $boundaryResult->version());
        self::assertSame($before, $boundary->toArray());
    }

    public function testRecoveryUsesExpiredEqualityAndPoisonsAtMaximumAttempt(): void
    {
        $configuration = new AuditOutboxConfiguration(60, 5, 2, 9);
        $transition = new AuditOutboxTransition($configuration);
        $expired = '2026-09-01T03:01:00.123456Z';

        $recoverable = $this->leased(2, $expired, 3, self::TOKEN_ONE, $configuration);
        $recoverableBefore = $recoverable->toArray();
        $pending = $transition->recover($recoverable, 3, self::TOKEN_ONE, $expired);
        self::assertSame('pending', $pending->toArray()['state']);
        self::assertSame(2, $pending->attempt());
        self::assertSame('2026-09-01T03:01:04.123456Z', $pending->availableAt());
        self::assertSame(4, $pending->version());
        self::assertSame($recoverableBefore, $recoverable->toArray());

        $afterExpiry = $transition->recover(
            $this->leased(2, $expired, 3, self::TOKEN_TWO, $configuration),
            3,
            self::TOKEN_TWO,
            '2026-09-01T03:01:00.123457Z',
        );
        self::assertSame('pending', $afterExpiry->toArray()['state']);
        self::assertSame(4, $afterExpiry->version());

        $poisoned = $transition->recover(
            $this->leased(5, $expired, 7, self::TOKEN_ONE, $configuration),
            7,
            self::TOKEN_ONE,
            $expired,
        );
        self::assertSame(
            [
                'state' => 'poisoned',
                'attempt' => 5,
                'availableAt' => null,
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'version' => 8,
            ],
            $poisoned->toArray(),
        );

        $this->assertFailure(
            fn() => $transition->recover(
                $this->leased(2, $expired, 3, self::TOKEN_ONE, $configuration),
                3,
                self::TOKEN_ONE,
                '2026-09-01T03:00:59.123456Z',
            ),
            AuditOutboxFailure::TransitionConflict,
        );
    }

    public function testRelayAndPoisonRequireActiveLeasesAndSettleExactlyOnce(): void
    {
        $configuration = new AuditOutboxConfiguration(60, 5, 1, 3600);
        $transition = new AuditOutboxTransition($configuration);
        foreach (['relay' => 'relayed', 'poison' => 'poisoned'] as $operation => $state) {
            $leased = $this->leased(5, '2026-09-01T03:01:00.123456Z', 4, self::TOKEN_ONE, $configuration);
            $before = $leased->toArray();
            $result = $transition->{$operation}($leased, 4, self::TOKEN_ONE, '2026-09-01T03:01:00.123455Z');
            self::assertSame($state, $result->toArray()['state']);
            self::assertSame(5, $result->version());
            self::assertSame(5, $result->attempt());
            self::assertNull($result->leaseFencingToken());
            self::assertNull($result->leaseExpiresAt());
            self::assertSame($before, $leased->toArray());

            foreach ([
                '2026-09-01T03:01:00.123456Z',
                '2026-09-01T03:01:00.123457Z',
            ] as $trustedNow) {
                $boundary = $this->leased(5, '2026-09-01T03:01:00.123456Z', 4, self::TOKEN_ONE, $configuration);
                $boundaryBefore = $boundary->toArray();
                $this->assertFailure(
                    fn() => $transition->{$operation}($boundary, 4, self::TOKEN_ONE, $trustedNow),
                    AuditOutboxFailure::TransitionConflict,
                );
                self::assertSame($boundaryBefore, $boundary->toArray());
            }
        }

        foreach (['relayed', 'poisoned'] as $state) {
            foreach (['relay', 'poison'] as $operation) {
                $terminal = $this->terminal($state, 1, 1, $configuration);
                $before = $terminal->toArray();
                $this->assertFailure(
                    fn() => $transition->{$operation}($terminal, 1, self::TOKEN_ONE, self::NOW),
                    AuditOutboxFailure::TransitionConflict,
                );
                self::assertSame($before, $terminal->toArray());
            }
        }
    }

    public function testEveryOperationRejectsWrongStatesAndFenceMismatchesWithoutMutation(): void
    {
        $configuration = new AuditOutboxConfiguration(60, 5, 1, 10);
        $transition = new AuditOutboxTransition($configuration);
        $leased = $this->leased(1, '2026-09-01T03:01:00.123456Z', 4, self::TOKEN_ONE, $configuration);
        $pending = $this->pending(self::NOW, $configuration);

        $before = $leased->toArray();
        $this->assertFailure(
            fn() => $transition->claim($leased, 4, self::TOKEN_TWO, self::NOW),
            AuditOutboxFailure::TransitionConflict,
        );
        self::assertSame($before, $leased->toArray());

        foreach (['retry', 'recover', 'relay', 'poison'] as $operation) {
            $before = $pending->toArray();
            $this->assertFailure(
                fn() => $transition->{$operation}($pending, 1, self::TOKEN_ONE, self::NOW),
                AuditOutboxFailure::TransitionConflict,
            );
            self::assertSame($before, $pending->toArray());
        }

        foreach (['retry', 'recover', 'relay', 'poison'] as $operation) {
            foreach ([
                [3, self::TOKEN_ONE, self::NOW],
                [5, self::TOKEN_ONE, self::NOW],
                [4, self::TOKEN_TWO, self::NOW],
                [4, 'invalid-token', self::NOW],
                [4, self::TOKEN_ONE, 'invalid-time'],
            ] as [$version, $token, $trustedNow]) {
                $fenced = $this->leased(1, '2026-09-01T03:01:00.123456Z', 4, self::TOKEN_ONE, $configuration);
                $fencedBefore = $fenced->toArray();
                $this->assertFailure(
                    fn() => $transition->{$operation}($fenced, $version, $token, $trustedNow),
                    AuditOutboxFailure::TransitionConflict,
                );
                self::assertSame($fencedBefore, $fenced->toArray());
            }
        }
    }

    public function testNonPendingConflictsAndSuccessfulRecoveryDoNotMutateInput(): void
    {
        $configuration = new AuditOutboxConfiguration(60, 5, 2, 9);
        $transition = new AuditOutboxTransition($configuration);
        $recoverable = $this->leased(2, '2026-09-01T03:01:00.123456Z', 3, self::TOKEN_ONE, $configuration);
        $recoverableBefore = $recoverable->toArray();
        $recovered = $transition->recover($recoverable, 3, self::TOKEN_ONE, '2026-09-01T03:01:00.123456Z');
        self::assertSame('pending', $recovered->state()->value);
        self::assertSame($recoverableBefore, $recoverable->toArray());

        foreach (['relayed', 'poisoned'] as $state) {
            foreach (['retry', 'recover'] as $operation) {
                $terminal = $this->terminal($state, 1, 1, $configuration);
                $before = $terminal->toArray();
                $this->assertFailure(
                    fn() => $transition->{$operation}($terminal, 1, self::TOKEN_ONE, self::NOW),
                    AuditOutboxFailure::TransitionConflict,
                );
                self::assertSame($before, $terminal->toArray());
            }
        }

        foreach (['leased', 'relayed', 'poisoned'] as $state) {
            $nonPending = $state === 'leased'
                ? $this->leased(1, '2026-09-01T03:01:00.123456Z', 1, self::TOKEN_ONE, $configuration)
                : $this->terminal($state, 1, 1, $configuration);
            $before = $nonPending->toArray();
            $this->assertFailure(
                fn() => $transition->claim($nonPending, 1, self::TOKEN_TWO, self::NOW),
                AuditOutboxFailure::TransitionConflict,
            );
            self::assertSame($before, $nonPending->toArray());
        }
    }

    public function testBackoffDoublesWithoutOverflowAndSaturatesAtTheCap(): void
    {
        $cases = [
            [1, 10, 1, 1],
            [1, 10, 2, 2],
            [1, 10, 4, 3],
            [1, 10, 8, 4],
            [1, 10, 10, 5],
        ];
        foreach ($cases as [$base, $cap, $expected, $attempt]) {
            $configuration = new AuditOutboxConfiguration(60, 32, $base, $cap);
            $transition = new AuditOutboxTransition($configuration);
            $leased = $this->leased($attempt, '2026-09-01T04:00:00.123456Z', 1, self::TOKEN_ONE, $configuration);
            $result = $transition->retry($leased, 1, self::TOKEN_ONE, self::NOW);
            self::assertSame(
                $this->plusSeconds(self::NOW, $expected),
                $result->availableAt(),
                sprintf('base=%d cap=%d attempt=%d', $base, $cap, $attempt),
            );
        }

        $backoff = new \ReflectionMethod(AuditOutboxTransition::class, 'backoffSeconds');
        foreach ([
            [1, 10, 1, 1],
            [10, 10, 10, 1],
            [11, 10, 10, 1],
            [1, 10, 4, 3],
            [5, 10, 10, 2],
            [6, 10, 10, 2],
            [1, 10, 10, 32],
            [7, 7, 7, 32],
            [3600, 1, 1, 32],
        ] as [$base, $cap, $expected, $attempt]) {
            $configuration = new AuditOutboxConfiguration(60, 32, $base, $cap);
            $transition = new AuditOutboxTransition($configuration);
            self::assertSame($expected, $backoff->invoke($transition, $attempt));
        }
    }

    public function testVersionCapacityWinsBeforeTimestampCapacityForEveryOperation(): void
    {
        $configuration = new AuditOutboxConfiguration(1, 5, 1, 3600);
        $transition = new AuditOutboxTransition($configuration);
        $max = 9_007_199_254_740_991;
        $overflowNow = '9999-12-31T23:59:59.999999Z';
        $activeOverflowNow = '9999-12-31T23:59:59.999998Z';

        $this->assertFailure(
            fn() => $transition->claim(
                $this->pending($overflowNow, $configuration, $max),
                $max,
                self::TOKEN_ONE,
                $overflowNow,
            ),
            AuditOutboxFailure::ResourceUnavailable,
        );
        foreach (['retry', 'recover', 'relay', 'poison'] as $operation) {
            $trustedNow = $operation === 'recover' ? $overflowNow : $activeOverflowNow;
            $this->assertFailure(
                fn() => $transition->{$operation}(
                    $this->leased(1, $overflowNow, $max, self::TOKEN_ONE, $configuration),
                    $max,
                    self::TOKEN_ONE,
                    $trustedNow,
                ),
                AuditOutboxFailure::ResourceUnavailable,
            );
        }
    }

    public function testTimeOverflowProducesNoSnapshotAndSuccessfulResultsHaveExactVersionStep(): void
    {
        $configuration = new AuditOutboxConfiguration(1, 5, 1, 3600);
        $transition = new AuditOutboxTransition($configuration);
        $pending = $this->pending('9999-12-31T23:59:59.999999Z', $configuration);
        $before = $pending->toArray();
        $this->assertFailure(
            static fn() => $transition->claim($pending, 1, self::TOKEN_ONE, '9999-12-31T23:59:59.999999Z'),
            AuditOutboxFailure::ResourceUnavailable,
        );
        self::assertSame($before, $pending->toArray());

        $claimed = $transition->claim($this->pending(self::NOW, $configuration), 1, self::TOKEN_ONE, self::NOW);
        self::assertSame(2, $claimed->version());
        $retried = $transition->retry($claimed, 2, self::TOKEN_ONE, '2026-09-01T03:00:00.123457Z');
        self::assertSame(3, $retried->version());
    }

    public function testFailureSurfacesHideLeaseAndTimeCanaries(): void
    {
        $configuration = new AuditOutboxConfiguration();
        $transition = new AuditOutboxTransition($configuration);
        $storedToken = self::TOKEN_ONE;
        $storedTime = '2026-09-01T03:05:00.123456Z';
        $invalidExpectedToken = 'invalid-expected-token-canary-20260901';
        $invalidTrustedNow = 'invalid-trusted-now-canary-20260901';
        $snapshot = $this->leased(1, $storedTime, 1, $storedToken, $configuration);

        foreach ([
            [$invalidExpectedToken, self::NOW],
            [$storedToken, $invalidTrustedNow],
        ] as [$expectedToken, $trustedNow]) {
            try {
                $transition->retry($snapshot, 1, $expectedToken, $trustedNow);
                self::fail('invalid transition input must fail');
            } catch (AuditOutboxException $exception) {
                self::assertSame(AuditOutboxFailure::TransitionConflict, $exception->failure());
                self::assertSame('transition_conflict', $exception->code());
                $trace = $exception->getTrace();
                self::assertIsArray($trace);
                $surfaces = [
                    $exception->getMessage(),
                    (string) $exception->getPrevious(),
                    $exception->getTraceAsString(),
                    var_export($trace, true),
                    print_r($exception, true),
                    var_export($exception, true),
                    json_encode($exception),
                ];
                try {
                    $surfaces[] = serialize($exception);
                } catch (\Throwable $serializationFailure) {
                    $surfaces[] = $serializationFailure->getMessage();
                }
                foreach ($surfaces as $surface) {
                    self::assertIsString($surface);
                    foreach ([$invalidExpectedToken, $invalidTrustedNow, $storedToken, $storedTime] as $canary) {
                        self::assertStringNotContainsString($canary, $surface);
                    }
                }
                self::assertNull($exception->getPrevious());
            }
        }
    }

    private function pending(
        string $availableAt,
        AuditOutboxConfiguration $configuration,
        int $version = 1,
    ): AuditOutboxStateSnapshot {
        if ($version === 1) {
            return AuditOutboxStateSnapshot::pending($availableAt, $configuration);
        }

        return AuditOutboxStateSnapshot::fromArray([
            'state' => 'pending',
            'attempt' => 0,
            'availableAt' => $availableAt,
            'leaseFencingToken' => null,
            'leaseExpiresAt' => null,
            'version' => $version,
        ], $configuration);
    }

    private function leased(
        int $attempt,
        string $expiresAt,
        int $version,
        string $token,
        AuditOutboxConfiguration $configuration,
    ): AuditOutboxStateSnapshot {
        return AuditOutboxStateSnapshot::fromArray([
            'state' => 'leased',
            'attempt' => $attempt,
            'availableAt' => null,
            'leaseFencingToken' => $token,
            'leaseExpiresAt' => $expiresAt,
            'version' => $version,
        ], $configuration);
    }

    private function terminal(
        string $state,
        int $attempt,
        int $version,
        AuditOutboxConfiguration $configuration,
    ): AuditOutboxStateSnapshot {
        return AuditOutboxStateSnapshot::fromArray([
            'state' => $state,
            'attempt' => $attempt,
            'availableAt' => null,
            'leaseFencingToken' => null,
            'leaseExpiresAt' => null,
            'version' => $version,
        ], $configuration);
    }

    private function plusSeconds(string $timestamp, int $seconds): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s.u\\Z', $timestamp, new \DateTimeZone('UTC'));
        self::assertInstanceOf(\DateTimeImmutable::class, $date);

        return $date->add(new \DateInterval('PT' . $seconds . 'S'))->format('Y-m-d\\TH:i:s.u\\Z');
    }

    private function assertFailure(callable $operation, AuditOutboxFailure $failure): void
    {
        try {
            $operation();
            self::fail('transition must fail');
        } catch (AuditOutboxException $exception) {
            self::assertSame($failure, $exception->failure());
            self::assertSame($failure->value, $exception->code());
            self::assertSame('Audit outbox state transition failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }
}
