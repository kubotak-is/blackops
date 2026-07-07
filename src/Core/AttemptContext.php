<?php

declare(strict_types=1);

namespace BlackOps\Core;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\AttemptId;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Attempt固有の不変Metadata。Attempt ID、1始まりのAttempt番号、UTC開始時刻を必須で保持する。
 *
 * Attempt開始前のExecutionContextはAttemptを持たず、Attempt開始後はAttemptContextが必ず揃う。
 */
#[PublicApi]
final readonly class AttemptContext
{
    private DateTimeImmutable $startedAt;

    public function __construct(
        private AttemptId $id,
        private int $number,
        DateTimeImmutable $startedAt,
    ) {
        if ($number < 1) {
            throw new InvalidArgumentException('AttemptContext requires an attempt number greater than or equal to 1.');
        }

        $this->startedAt = $this->toUtc($startedAt);
    }

    public function id(): AttemptId
    {
        return $this->id;
    }

    public function number(): int
    {
        return $this->number;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    private function toUtc(DateTimeImmutable $time): DateTimeImmutable
    {
        if ($time->getTimezone()->getName() === 'UTC') {
            return $time;
        }

        return $time->setTimezone(new DateTimeZone('UTC'));
    }
}
