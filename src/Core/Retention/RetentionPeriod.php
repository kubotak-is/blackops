<?php

declare(strict_types=1);

namespace BlackOps\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;
use DateTimeImmutable;
use InvalidArgumentException;

#[PublicApi]
final readonly class RetentionPeriod
{
    private function __construct(
        private int $seconds,
    ) {
        if ($seconds < 1) {
            throw new InvalidArgumentException('Retention period must be positive.');
        }
    }

    public static function seconds(int $seconds): self
    {
        return new self($seconds);
    }

    public static function days(int $days): self
    {
        if ($days < 1) {
            throw new InvalidArgumentException('Retention period must be positive.');
        }

        return new self($days * 86_400);
    }

    public function secondsValue(): int
    {
        return $this->seconds;
    }

    public function expiresAt(DateTimeImmutable $baseTime): DateTimeImmutable
    {
        return $baseTime->modify('+' . $this->seconds . ' seconds');
    }
}
