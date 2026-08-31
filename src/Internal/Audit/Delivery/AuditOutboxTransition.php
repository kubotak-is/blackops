<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Delivery;

use BlackOps\Core\Audit\AuditRecordTime;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final readonly class AuditOutboxTransition
{
    private const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    public function __construct(
        private AuditOutboxConfiguration $configuration,
    ) {}

    public function claim(
        #[\SensitiveParameter]
        AuditOutboxStateSnapshot $snapshot,
        int $expectedVersion,
        #[\SensitiveParameter]
        string $newToken,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditOutboxStateSnapshot {
        $this->assertExpectedVersion($expectedVersion);
        self::assertToken($newToken);
        self::assertTransitionTime($trustedNow);

        if ($snapshot->state() !== AuditOutboxState::Pending) {
            throw $this->conflict();
        }
        if ($snapshot->version() !== $expectedVersion) {
            throw $this->conflict();
        }

        $availableAt = $snapshot->availableAt();
        if ($availableAt === null) {
            throw $this->integrity();
        }
        if ($trustedNow < $availableAt) {
            throw $this->conflict();
        }

        $attempt = $snapshot->attempt();
        if ($attempt >= $this->configuration->maxAttempts) {
            throw $this->conflict();
        }

        $version = $this->nextVersion($snapshot->version());
        $leaseExpiresAt = $this->addSeconds($trustedNow, $this->configuration->leaseDurationSeconds);

        return $this->makeSnapshot([
            'state' => AuditOutboxState::Leased->value,
            'attempt' => $attempt + 1,
            'availableAt' => null,
            'leaseFencingToken' => $newToken,
            'leaseExpiresAt' => $leaseExpiresAt,
            'version' => $version,
        ]);
    }

    public function retry(
        #[\SensitiveParameter]
        AuditOutboxStateSnapshot $snapshot,
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditOutboxStateSnapshot {
        $this->assertExpectedVersion($expectedVersion);
        self::assertToken($expectedToken);
        self::assertTransitionTime($trustedNow);

        $this->assertLeased($snapshot, $expectedVersion, $expectedToken);
        $leaseExpiresAt = $snapshot->leaseExpiresAt();
        if ($leaseExpiresAt === null) {
            throw $this->integrity();
        }
        if ($trustedNow >= $leaseExpiresAt) {
            throw $this->conflict();
        }

        $attempt = $snapshot->attempt();
        if ($attempt >= $this->configuration->maxAttempts) {
            throw $this->conflict();
        }

        $version = $this->nextVersion($snapshot->version());
        $availableAt = $this->addSeconds($trustedNow, $this->backoffSeconds($attempt));

        return $this->makeSnapshot([
            'state' => AuditOutboxState::Pending->value,
            'attempt' => $attempt,
            'availableAt' => $availableAt,
            'leaseFencingToken' => null,
            'leaseExpiresAt' => null,
            'version' => $version,
        ]);
    }

    public function recover(
        #[\SensitiveParameter]
        AuditOutboxStateSnapshot $snapshot,
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditOutboxStateSnapshot {
        $this->assertExpectedVersion($expectedVersion);
        self::assertToken($expectedToken);
        self::assertTransitionTime($trustedNow);

        $this->assertLeased($snapshot, $expectedVersion, $expectedToken);
        $leaseExpiresAt = $snapshot->leaseExpiresAt();
        if ($leaseExpiresAt === null) {
            throw $this->integrity();
        }
        if ($trustedNow < $leaseExpiresAt) {
            throw $this->conflict();
        }

        $attempt = $snapshot->attempt();
        if ($attempt > $this->configuration->maxAttempts) {
            throw $this->conflict();
        }

        $version = $this->nextVersion($snapshot->version());
        if ($attempt === $this->configuration->maxAttempts) {
            return $this->makeSnapshot([
                'state' => AuditOutboxState::Poisoned->value,
                'attempt' => $attempt,
                'availableAt' => null,
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'version' => $version,
            ]);
        }

        $availableAt = $this->addSeconds($trustedNow, $this->backoffSeconds($attempt));

        return $this->makeSnapshot([
            'state' => AuditOutboxState::Pending->value,
            'attempt' => $attempt,
            'availableAt' => $availableAt,
            'leaseFencingToken' => null,
            'leaseExpiresAt' => null,
            'version' => $version,
        ]);
    }

    public function relay(
        #[\SensitiveParameter]
        AuditOutboxStateSnapshot $snapshot,
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditOutboxStateSnapshot {
        return $this->settle($snapshot, $expectedVersion, $expectedToken, $trustedNow, AuditOutboxState::Relayed);
    }

    public function poison(
        #[\SensitiveParameter]
        AuditOutboxStateSnapshot $snapshot,
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
        #[\SensitiveParameter]
        string $trustedNow,
    ): AuditOutboxStateSnapshot {
        return $this->settle($snapshot, $expectedVersion, $expectedToken, $trustedNow, AuditOutboxState::Poisoned);
    }

    private function settle(
        #[\SensitiveParameter]
        AuditOutboxStateSnapshot $snapshot,
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
        #[\SensitiveParameter]
        string $trustedNow,
        AuditOutboxState $state,
    ): AuditOutboxStateSnapshot {
        $this->assertExpectedVersion($expectedVersion);
        self::assertToken($expectedToken);
        self::assertTransitionTime($trustedNow);

        $this->assertLeased($snapshot, $expectedVersion, $expectedToken);
        $leaseExpiresAt = $snapshot->leaseExpiresAt();
        if ($leaseExpiresAt === null) {
            throw $this->integrity();
        }
        if ($trustedNow >= $leaseExpiresAt) {
            throw $this->conflict();
        }

        $version = $this->nextVersion($snapshot->version());

        return $this->makeSnapshot([
            'state' => $state->value,
            'attempt' => $snapshot->attempt(),
            'availableAt' => null,
            'leaseFencingToken' => null,
            'leaseExpiresAt' => null,
            'version' => $version,
        ]);
    }

    private function assertLeased(
        #[\SensitiveParameter]
        AuditOutboxStateSnapshot $snapshot,
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): void {
        if ($snapshot->state() !== AuditOutboxState::Leased) {
            throw $this->conflict();
        }
        if ($snapshot->version() !== $expectedVersion) {
            throw $this->conflict();
        }

        $storedToken = $snapshot->leaseFencingToken();
        if ($storedToken === null || !hash_equals($storedToken, $expectedToken)) {
            throw $this->conflict();
        }
    }

    private function assertExpectedVersion(int $expectedVersion): void
    {
        if ($expectedVersion < 1 || $expectedVersion > self::MAX_SAFE_INTEGER) {
            throw $this->conflict();
        }
    }

    private static function assertToken(#[\SensitiveParameter] string $token): void
    {
        if (
            strlen($token) !== 36
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $token) !== 1
        ) {
            throw new AuditOutboxException(AuditOutboxFailure::TransitionConflict);
        }
    }

    private static function assertTransitionTime(#[\SensitiveParameter] string $trustedNow): void
    {
        try {
            AuditRecordTime::assertTimestamp($trustedNow);
        } catch (\Throwable) {
            throw new AuditOutboxException(AuditOutboxFailure::TransitionConflict);
        }
    }

    private function nextVersion(int $version): int
    {
        if ($version >= self::MAX_SAFE_INTEGER) {
            throw new AuditOutboxException(AuditOutboxFailure::ResourceUnavailable);
        }

        return $version + 1;
    }

    private function backoffSeconds(int $attempt): int
    {
        $delay = min($this->configuration->backoffBaseSeconds, $this->configuration->backoffCapSeconds);
        for ($attemptIndex = 1; $attemptIndex < $attempt; $attemptIndex++) {
            if ($delay > intdiv(num1: $this->configuration->backoffCapSeconds, num2: 2)) {
                $delay = $this->configuration->backoffCapSeconds;
                continue;
            }
            $delay *= 2;
        }

        return $delay;
    }

    private function addSeconds(#[\SensitiveParameter] string $timestamp, int $seconds): string
    {
        try {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s.u\\Z', $timestamp, new \DateTimeZone('UTC'));
            $errors = \DateTimeImmutable::getLastErrors();
            if (
                $date === false
                || is_array($errors) && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0)
                || $date->format('Y-m-d\\TH:i:s.u\\Z') !== $timestamp
            ) {
                throw new \RuntimeException();
            }

            $result = $date->add(new \DateInterval('PT' . $seconds . 'S'));
            $formatted = $result->format('Y-m-d\\TH:i:s.u\\Z');
            AuditRecordTime::assertTimestamp($formatted);

            return $formatted;
        } catch (\Throwable) {
            throw new AuditOutboxException(AuditOutboxFailure::ResourceUnavailable);
        }
    }

    /** @param array{state:string,attempt:int,availableAt:?string,leaseFencingToken:?string,leaseExpiresAt:?string,version:int} $data */
    private function makeSnapshot(#[\SensitiveParameter] array $data): AuditOutboxStateSnapshot
    {
        try {
            return AuditOutboxStateSnapshot::fromArray($data, $this->configuration);
        } catch (\Throwable) {
            throw new AuditOutboxException(AuditOutboxFailure::ResourceUnavailable);
        }
    }

    private function conflict(): AuditOutboxException
    {
        return new AuditOutboxException(AuditOutboxFailure::TransitionConflict);
    }

    private function integrity(): AuditOutboxException
    {
        return new AuditOutboxException(AuditOutboxFailure::IntegrityInvalid);
    }
}
