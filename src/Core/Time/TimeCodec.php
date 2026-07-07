<?php

declare(strict_types=1);

namespace BlackOps\Core\Time;

use DateTimeImmutable;
use DateTimeZone;

final readonly class TimeCodec
{
    private DateTimeZone $utc;

    public function __construct()
    {
        $this->utc = new DateTimeZone('UTC');
    }

    public function toUtc(DateTimeImmutable $time): DateTimeImmutable
    {
        return $time->setTimezone($this->utc);
    }

    public function format(DateTimeImmutable $time): string
    {
        return $this->toUtc($time)->format('Y-m-d\TH:i:s.u\Z');
    }
}
