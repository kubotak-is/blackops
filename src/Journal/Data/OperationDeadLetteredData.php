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
final readonly class OperationDeadLetteredData implements JournalData
{
    public DateTimeImmutable $movedAt;

    public function __construct(
        public ?AttemptId $finalAttemptId,
        public ?int $finalAttemptNumber,
        public string $reasonType,
        public string $reasonMessage,
        DateTimeImmutable $movedAt,
    ) {
        if ($finalAttemptNumber !== null && $finalAttemptNumber < 1) {
            throw new InvalidArgumentException('Final attempt number must be greater than or equal to one.');
        }

        if ($reasonType === '') {
            throw new InvalidArgumentException('Dead letter reason type must not be empty.');
        }

        $this->movedAt = $this->toUtc($movedAt);
    }

    private function toUtc(DateTimeImmutable $time): DateTimeImmutable
    {
        if ($time->getTimezone()->getName() === 'UTC') {
            return $time;
        }

        return $time->setTimezone(new DateTimeZone('UTC'));
    }
}
