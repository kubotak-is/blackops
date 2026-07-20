<?php

declare(strict_types=1);

namespace App\Feature;

use DateTimeImmutable;
use DateTimeZone;

final readonly class BoardTime
{
    public static function http(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }
}
