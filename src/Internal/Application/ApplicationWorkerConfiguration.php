<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use InvalidArgumentException;

final readonly class ApplicationWorkerConfiguration
{
    private function __construct(
        public string $id,
        public int $leaseSeconds,
        public int $heartbeatSeconds,
        public int $graceSeconds,
        public bool $continueAfterHandlerFailure,
    ) {}

    /** @param array<string, array<array-key, mixed>> $configuration */
    public static function fromConfiguration(array $configuration): self
    {
        /** @var mixed $worker */
        $worker = $configuration['execution']['worker'] ?? null;
        if (!is_array($worker)) {
            throw new InvalidArgumentException('Application configuration key "execution.worker" must be an array.');
        }

        $id = self::requiredString($worker, 'id');
        $lease = self::positiveInt($worker, 'lease_seconds', 60);
        $heartbeat = self::positiveInt($worker, 'heartbeat_seconds', 10);
        $grace = self::positiveInt($worker, 'grace_seconds', 20);
        /** @var mixed $continue */
        $continue = $worker['continue_after_handler_failure'] ?? true;

        if (!is_bool($continue)) {
            throw new InvalidArgumentException(
                'Application configuration key "execution.worker.continue_after_handler_failure" must be boolean.',
            );
        }

        if ($heartbeat >= $lease) {
            throw new InvalidArgumentException(
                'Application worker heartbeat_seconds must be shorter than lease_seconds.',
            );
        }

        return new self($id, $lease, $heartbeat, $grace, $continue);
    }

    /** @param array<array-key, mixed> $worker */
    private static function requiredString(array $worker, string $key): string
    {
        /** @var mixed $value */
        $value = $worker[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf(
                'Application configuration key "execution.worker.%s" must be a non-empty string.',
                $key,
            ));
        }

        return $value;
    }

    /** @param array<array-key, mixed> $worker */
    private static function positiveInt(array $worker, string $key, int $default): int
    {
        /** @var mixed $value */
        $value = $worker[$key] ?? $default;
        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException(sprintf(
                'Application configuration key "execution.worker.%s" must be a positive integer.',
                $key,
            ));
        }

        return $value;
    }
}
