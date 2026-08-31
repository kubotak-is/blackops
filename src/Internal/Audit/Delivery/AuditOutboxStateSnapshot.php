<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Delivery;

use BlackOps\Core\Audit\AuditRecordTime;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final readonly class AuditOutboxStateSnapshot
{
    private const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    private \SensitiveParameterValue $snapshot;

    private function __construct(
        string $state,
        int $attempt,
        #[\SensitiveParameter]
        ?string $availableAt,
        #[\SensitiveParameter]
        ?string $leaseFencingToken,
        #[\SensitiveParameter]
        ?string $leaseExpiresAt,
        int $version,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'state' => $state,
            'attempt' => $attempt,
            'availableAt' => $availableAt,
            'leaseFencingToken' => $leaseFencingToken,
            'leaseExpiresAt' => $leaseExpiresAt,
            'version' => $version,
        ]);
    }

    public static function pending(
        #[\SensitiveParameter]
        string $availableAt,
        AuditOutboxConfiguration $configuration,
    ): self {
        return self::fromArray([
            'state' => AuditOutboxState::Pending->value,
            'attempt' => 0,
            'availableAt' => $availableAt,
            'leaseFencingToken' => null,
            'leaseExpiresAt' => null,
            'version' => 1,
        ], $configuration);
    }

    /**
     * @mago-expect lint:halstead
     * @param array<array-key,mixed> $data
     */
    public static function fromArray(#[\SensitiveParameter] array $data, AuditOutboxConfiguration $configuration): self
    {
        if (count($data) !== 6) {
            throw self::integrity();
        }

        $keys = [
            'state',
            'attempt',
            'availableAt',
            'leaseFencingToken',
            'leaseExpiresAt',
            'version',
        ];
        if (array_is_list($data) || array_keys($data) !== $keys) {
            throw self::integrity();
        }

        try {
            if (
                !is_string($data['state'])
                || !is_int($data['attempt'])
                || $data['availableAt'] !== null && !is_string($data['availableAt'])
                || $data['leaseFencingToken'] !== null && !is_string($data['leaseFencingToken'])
                || $data['leaseExpiresAt'] !== null && !is_string($data['leaseExpiresAt'])
                || !is_int($data['version'])
            ) {
                throw self::integrity();
            }

            $state = AuditOutboxState::tryFrom($data['state']);
            if ($state === null) {
                throw self::integrity();
            }
            if ($data['version'] < 1 || $data['version'] > self::MAX_SAFE_INTEGER) {
                throw self::integrity();
            }

            self::assertStateMatrix(
                $state,
                $data['attempt'],
                $data['availableAt'],
                $data['leaseFencingToken'],
                $data['leaseExpiresAt'],
                $configuration,
            );

            return new self(
                $state->value,
                $data['attempt'],
                $data['availableAt'],
                $data['leaseFencingToken'],
                $data['leaseExpiresAt'],
                $data['version'],
            );
        } catch (AuditOutboxException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw self::integrity();
        }
    }

    public function state(): AuditOutboxState
    {
        $value = $this->value('state');
        if (!is_string($value)) {
            throw self::integrity();
        }
        $state = AuditOutboxState::tryFrom($value);
        if ($state === null) {
            throw self::integrity();
        }

        return $state;
    }

    public function attempt(): int
    {
        $value = $this->value('attempt');
        if (!is_int($value)) {
            throw self::integrity();
        }

        return $value;
    }

    public function availableAt(): ?string
    {
        $value = $this->value('availableAt');
        if ($value !== null && !is_string($value)) {
            throw self::integrity();
        }

        return $value;
    }

    public function leaseFencingToken(): ?string
    {
        $value = $this->value('leaseFencingToken');
        if ($value !== null && !is_string($value)) {
            throw self::integrity();
        }

        return $value;
    }

    public function leaseExpiresAt(): ?string
    {
        $value = $this->value('leaseExpiresAt');
        if ($value !== null && !is_string($value)) {
            throw self::integrity();
        }

        return $value;
    }

    public function version(): int
    {
        $value = $this->value('version');
        if (!is_int($value)) {
            throw self::integrity();
        }

        return $value;
    }

    /** @return array{state:string,attempt:int,availableAt:?string,leaseFencingToken:?string,leaseExpiresAt:?string,version:int} */
    public function toArray(): array
    {
        return [
            'state' => $this->stringValue('state'),
            'attempt' => $this->intValue('attempt'),
            'availableAt' => $this->nullableStringValue('availableAt'),
            'leaseFencingToken' => $this->nullableStringValue('leaseFencingToken'),
            'leaseExpiresAt' => $this->nullableStringValue('leaseExpiresAt'),
            'version' => $this->intValue('version'),
        ];
    }

    /** @return array<string,never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<string,never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<string,mixed> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw self::integrity();
    }

    /** @mago-expect lint:excessive-parameter-list */
    private static function assertStateMatrix(
        AuditOutboxState $state,
        int $attempt,
        #[\SensitiveParameter]
        ?string $availableAt,
        #[\SensitiveParameter]
        ?string $leaseFencingToken,
        #[\SensitiveParameter]
        ?string $leaseExpiresAt,
        AuditOutboxConfiguration $configuration,
    ): void {
        if ($state === AuditOutboxState::Pending) {
            if (
                $attempt < 0
                || $attempt >= $configuration->maxAttempts
                || $availableAt === null
                || $leaseFencingToken !== null
                || $leaseExpiresAt !== null
            ) {
                throw self::integrity();
            }
            self::assertTimestamp($availableAt);
            return;
        }

        if ($state === AuditOutboxState::Leased) {
            if (
                $attempt < 1
                || $attempt > $configuration->maxAttempts
                || $availableAt !== null
                || $leaseFencingToken === null
                || $leaseExpiresAt === null
            ) {
                throw self::integrity();
            }
            self::assertUuidV7($leaseFencingToken);
            self::assertTimestamp($leaseExpiresAt);
            return;
        }

        if (
            $attempt < 1
            || $attempt > $configuration->maxAttempts
            || $availableAt !== null
            || $leaseFencingToken !== null
            || $leaseExpiresAt !== null
        ) {
            throw self::integrity();
        }
    }

    private static function assertTimestamp(#[\SensitiveParameter] string $value): void
    {
        try {
            AuditRecordTime::assertTimestamp($value);
        } catch (\Throwable) {
            throw self::integrity();
        }
    }

    private static function assertUuidV7(#[\SensitiveParameter] string $value): void
    {
        if (
            strlen($value) !== 36
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value) !== 1
        ) {
            throw self::integrity();
        }
    }

    private function value(string $key): mixed
    {
        try {
            $snapshot = $this->snapshot->getValue();
        } catch (\Throwable) {
            throw self::integrity();
        }
        if (!is_array($snapshot) || !array_key_exists($key, $snapshot)) {
            throw self::integrity();
        }

        return $snapshot[$key];
    }

    private function stringValue(string $key): string
    {
        $value = $this->value($key);
        if (!is_string($value)) {
            throw self::integrity();
        }

        return $value;
    }

    private function nullableStringValue(string $key): ?string
    {
        $value = $this->value($key);
        if ($value !== null && !is_string($value)) {
            throw self::integrity();
        }

        return $value;
    }

    private function intValue(string $key): int
    {
        $value = $this->value($key);
        if (!is_int($value)) {
            throw self::integrity();
        }

        return $value;
    }

    private static function integrity(): AuditOutboxException
    {
        return new AuditOutboxException(AuditOutboxFailure::IntegrityInvalid);
    }
}
