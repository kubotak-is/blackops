<?php

declare(strict_types=1);

namespace BlackOps\Journal\Data;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Journal\JournalData;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

#[PublicApi]
final readonly class AttemptRetryScheduledData implements JournalData
{
    public DateTimeImmutable $scheduledAt;

    public function __construct(
        public AttemptId $failedAttemptId,
        public int $nextAttemptNumber,
        DateTimeImmutable $scheduledAt,
        public int $delayMilliseconds,
    ) {
        if ($nextAttemptNumber < 1) {
            throw new InvalidArgumentException('Next attempt number must be greater than or equal to one.');
        }

        if ($delayMilliseconds < 0) {
            throw new InvalidArgumentException('Retry delay must be greater than or equal to zero.');
        }

        $this->scheduledAt = $this->toUtc($scheduledAt);
    }

    private function toUtc(DateTimeImmutable $time): DateTimeImmutable
    {
        if ($time->getTimezone()->getName() === 'UTC') {
            return $time;
        }

        return $time->setTimezone(new DateTimeZone('UTC'));
    }
}
