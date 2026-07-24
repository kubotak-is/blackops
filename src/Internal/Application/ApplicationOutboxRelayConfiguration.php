<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use InvalidArgumentException;

final readonly class ApplicationOutboxRelayConfiguration
{
    private function __construct(
        public string $id,
        public int $batchSize,
        public int $leaseSeconds,
        public int $heartbeatSeconds,
        public int $graceSeconds,
        public int $maxAttempts,
        public int $initialBackoffSeconds,
        public int $maxBackoffSeconds,
        public int $pollIntervalMilliseconds,
    ) {}

    /** @param array<string, array<array-key, mixed>> $configuration */
    public static function fromConfiguration(array $configuration): self
    {
        /** @var mixed $relay */
        $relay = $configuration['execution']['outbox_relay'] ?? null;
        if (!is_array($relay)) {
            throw new InvalidArgumentException(
                'Application configuration key "execution.outbox_relay" must be an array.',
            );
        }

        /** @var mixed $id */
        $id = $relay['id'] ?? null;
        if (!is_string($id) || trim($id) === '') {
            throw new InvalidArgumentException(
                'Application configuration key "execution.outbox_relay.id" must be a non-empty string.',
            );
        }

        $value = static function (array $source, string $key, int $default): int {
            /** @var mixed $candidate */
            $candidate = $source[$key] ?? $default;
            if (!is_int($candidate) || $candidate < 1) {
                throw new InvalidArgumentException(sprintf(
                    'Application configuration key "execution.outbox_relay.%s" must be a positive integer.',
                    $key,
                ));
            }
            return $candidate;
        };
        $lease = $value(source: $relay, key: 'lease_seconds', default: 60);
        $heartbeat = $value(source: $relay, key: 'heartbeat_seconds', default: 10);
        if ($heartbeat >= $lease) {
            throw new InvalidArgumentException(
                'Application outbox relay heartbeat_seconds must be shorter than lease_seconds.',
            );
        }
        $initial = $value(source: $relay, key: 'initial_backoff_seconds', default: 1);
        $maximum = $value(source: $relay, key: 'max_backoff_seconds', default: 300);
        if ($initial > $maximum) {
            throw new InvalidArgumentException(
                'Application outbox relay initial_backoff_seconds must not exceed max_backoff_seconds.',
            );
        }

        return new self(
            $id,
            $value(source: $relay, key: 'batch_size', default: 50),
            $lease,
            $heartbeat,
            $value(source: $relay, key: 'grace_seconds', default: 20),
            $value(source: $relay, key: 'max_attempts', default: 8),
            $initial,
            $maximum,
            $value(source: $relay, key: 'poll_interval_milliseconds', default: 1000),
        );
    }
}
