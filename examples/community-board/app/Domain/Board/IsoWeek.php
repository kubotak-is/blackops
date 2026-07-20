<?php

declare(strict_types=1);

namespace App\Domain\Board;

use DateTimeImmutable;
use DateTimeZone;

final readonly class IsoWeek
{
    private function __construct(
        private string $value,
        private DateTimeImmutable $startsAt,
        private DateTimeImmutable $endsAt,
    ) {}

    public static function fromString(string $value): self
    {
        if (preg_match('/\A([0-9]{4})-W([0-9]{2})\z/D', $value, $matches) !== 1) {
            throw new InvalidDigestWeek();
        }

        $year = (int) $matches[1];
        $week = (int) $matches[2];
        if ($year < 1 || $week < 1 || $week > 53) {
            throw new InvalidDigestWeek();
        }

        $utc = new DateTimeZone('UTC');
        $startsAt = new DateTimeImmutable('now', $utc)
            ->setISODate($year, $week, 1)
            ->setTime(0, 0);
        if ($startsAt->format('o-\WW') !== $value) {
            throw new InvalidDigestWeek();
        }

        return new self($value, $startsAt, $startsAt->modify('+7 days'));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function endsAt(): DateTimeImmutable
    {
        return $this->endsAt;
    }
}
