<?php

declare(strict_types=1);

namespace BlackOps\Core\Queue;

use DateTimeImmutable;

interface QueueStatusSource
{
    public function read(DateTimeImmutable $observedAt): QueueStatusSnapshot;
}
