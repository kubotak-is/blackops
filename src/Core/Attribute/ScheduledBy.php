<?php

declare(strict_types=1);

namespace BlackOps\Core\Attribute;

use DateTimeZone;
use InvalidArgumentException;

#[PublicApi]
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class ScheduledBy
{
    public string $name;
    public string $cron;
    public string $timezone;

    public function __construct(string $name, string $cron, string $timezone = 'UTC')
    {
        if (
            preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)*$/D', $name) !== 1
            || !self::validCron($cron)
            || !self::validTimezone($timezone)
        ) {
            throw new InvalidArgumentException('Scheduled operation metadata is invalid.');
        }

        $this->name = $name;
        $this->cron = $cron;
        $this->timezone = $timezone;
    }

    private static function validCron(string $cron): bool
    {
        $parts = preg_split('/[ \t]+/', trim($cron));
        if ($parts === false || count($parts) !== 5 || trim($cron) === '')
            return false;
        foreach ($parts as $index => $part) {
            $max = [59, 23, 31, 12, 7][$index];
            $min = $index === 2 || $index === 3 ? 1 : 0;
            foreach (explode(',', $part) as $item) {
                $segments = explode('/', $item, 2);
                if (count($segments) === 2 && $segments[0] !== '*' && !str_contains($segments[0], '-'))
                    return false;
                if (count($segments) === 2 && (!ctype_digit($segments[1]) || (int) $segments[1] < 1))
                    return false;
                $base = $segments[0];
                if ($base === '*')
                    continue;
                $m = [];
                if (preg_match('/^(\d+)(?:-(\d+))?$/D', $base, $m) !== 1)
                    return false;
                $end = isset($m[2]) ? (int) $m[2] : (int) $m[1];
                if ((int) $m[1] > $end || (int) $m[1] < $min || $end > $max)
                    return false;
            }
        }
        return true;
    }

    private static function validTimezone(string $timezone): bool
    {
        return $timezone === 'UTC' || in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }
}
