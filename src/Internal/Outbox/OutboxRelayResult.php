<?php

declare(strict_types=1);

namespace BlackOps\Internal\Outbox;

final class OutboxRelayResult
{
    public function __construct(
        public int $claimed = 0,
        public int $sent = 0,
        public int $retried = 0,
        public int $deadLettered = 0,
        public int $stale = 0,
    ) {}

    public function total(): int
    {
        return $this->claimed + $this->sent + $this->retried + $this->deadLettered + $this->stale;
    }
}
