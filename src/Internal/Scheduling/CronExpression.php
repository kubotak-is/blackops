<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduling;

use InvalidArgumentException;

final readonly class CronExpression
{
    public function __construct(
        public string $expression,
        public CronField $minute,
        public CronField $hour,
        public CronField $dayOfMonth,
        public CronField $month,
        public CronField $dayOfWeek,
        public bool $dayOfMonthDayOfWeekOr = true,
    ) {}

    public static function parse(string $expression): self
    {
        $parts = preg_split('/[ \t]+/', trim($expression));
        if ($parts === false || count($parts) !== 5 || trim($expression) === '') {
            throw new InvalidArgumentException('Schedule cron expression is invalid.');
        }

        $dayOfMonth = self::field($parts[2], 1, 31);
        $dayOfWeek = self::field($parts[4], 0, 7);
        $dayOfWeek = new CronField(
            array_values(array_unique(array_map(static fn(int $value): int => $value === 7
                ? 0
                : $value, $dayOfWeek->values))),
            $dayOfWeek->wildcard,
        );

        return new self(
            $expression,
            self::field($parts[0], 0, 59),
            self::field($parts[1], 0, 23),
            $dayOfMonth,
            self::field($parts[3], 1, 12),
            $dayOfWeek,
            !$dayOfMonth->wildcard && !$dayOfWeek->wildcard,
        );
    }

    public function usesDayOfMonthDayOfWeekOrSemantics(): bool
    {
        return $this->dayOfMonthDayOfWeekOr;
    }

    private static function field(string $part, int $minimum, int $maximum): CronField
    {
        if ($part === '') {
            throw new InvalidArgumentException('Schedule cron expression is invalid.');
        }
        $values = [];
        $wildcard = false;
        foreach (explode(',', $part) as $item) {
            if ($item === '') {
                throw new InvalidArgumentException('Schedule cron expression is invalid.');
            }
            $segments = explode('/', $item, 2);
            $base = $segments[0];
            $step = $segments[1] ?? null;
            if ($step !== null && $base !== '*' && !str_contains($base, '-')) {
                throw new InvalidArgumentException('Schedule cron expression is invalid.');
            }
            if ($step !== null && (!ctype_digit($step) || (int) $step < 1)) {
                throw new InvalidArgumentException('Schedule cron expression is invalid.');
            }
            $stepValue = $step === null ? 1 : (int) $step;
            if ($base === '*') {
                $wildcard = $step === null;
                $start = $minimum;
                $end = $maximum;
            } else {
                $matches = [];
                if (preg_match('/^(\d+)(?:-(\d+))?$/D', $base, $matches) !== 1) {
                    throw new InvalidArgumentException('Schedule cron expression is invalid.');
                }
                $start = (int) $matches[1];
                $end = isset($matches[2]) ? (int) $matches[2] : $start;
                if ($end < $start) {
                    throw new InvalidArgumentException('Schedule cron expression is invalid.');
                }
            }
            if ($start < $minimum || $end > $maximum) {
                throw new InvalidArgumentException('Schedule cron expression is invalid.');
            }
            for ($value = $start; $value <= $end; $value += $stepValue) {
                $values[$value] = $value;
            }
        }

        return new CronField(array_values($values), $wildcard);
    }
}
