<?php

declare(strict_types=1);

namespace BlackOps\Internal\Outbox;

use InvalidArgumentException;

final readonly class OutboxRelayConfiguration
{
    public function __construct(
        public string $id,
        public int $batchSize = 50,
        public int $leaseSeconds = 60,
        public int $heartbeatSeconds = 10,
        public int $graceSeconds = 20,
        public int $maxAttempts = 8,
        public int $initialBackoffSeconds = 1,
        public int $maxBackoffSeconds = 300,
        public int $pollIntervalMilliseconds = 1000,
    ) {
        if (
            trim($id) === ''
            || $batchSize < 1
            || $leaseSeconds < 1
            || $heartbeatSeconds < 1
            || $heartbeatSeconds >= $leaseSeconds
            || $graceSeconds < 1
            || $maxAttempts < 1
            || $initialBackoffSeconds < 1
            || $maxBackoffSeconds < $initialBackoffSeconds
            || $pollIntervalMilliseconds < 1
        ) {
            throw new InvalidArgumentException('Outbox relay configuration is invalid.');
        }
    }
}
