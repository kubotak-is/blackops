<?php

declare(strict_types=1);

namespace BlackOps\Journal;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\AttemptId;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

#[PublicApi]
final readonly class JournalAttempt
{
    public DateTimeImmutable $startedAt;

    public function __construct(
        public AttemptId $id,
        public int $number,
        DateTimeImmutable $startedAt,
    ) {
        if ($number < 1) {
            throw new InvalidArgumentException('Journal attempt number must be positive.');
        }
        $this->startedAt = $startedAt->setTimezone(new DateTimeZone('UTC'));
    }
}
