<?php

declare(strict_types=1);

namespace BlackOps\Core\Outbox;

use DateTimeImmutable;

interface OutboxStatusSource
{
    public function read(DateTimeImmutable $observedAt): OutboxStatusSnapshot;
}
