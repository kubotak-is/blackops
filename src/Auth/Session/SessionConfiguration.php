<?php

declare(strict_types=1);

namespace BlackOps\Auth\Session;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[PublicApi]
final readonly class SessionConfiguration
{
    public function __construct(
        public int $ttlSeconds = 28_800,
        public int $touchIntervalSeconds = 300,
    ) {
        if ($this->ttlSeconds < 1) {
            throw new InvalidArgumentException('Session TTL must be a positive number of seconds.');
        }

        if ($this->touchIntervalSeconds < 1) {
            throw new InvalidArgumentException('Session touch interval must be a positive number of seconds.');
        }

        if ($this->touchIntervalSeconds > $this->ttlSeconds) {
            throw new InvalidArgumentException('Session touch interval must not exceed the session TTL.');
        }
    }
}
