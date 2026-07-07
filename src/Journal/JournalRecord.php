<?php

declare(strict_types=1);

namespace BlackOps\Journal;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\JournalRecordId;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

#[PublicApi]
final readonly class JournalRecord
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public JournalRecordId $recordId,
        public int $schemaVersion,
        public JournalEvent $event,
        DateTimeImmutable $occurredAt,
        public int $sequence,
        public JournalOperation $operation,
        public ?JournalAttempt $attempt,
        public JournalData $data,
    ) {
        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Journal schema version must be positive.');
        }
        if ($sequence < 1) {
            throw new InvalidArgumentException('Journal sequence must be positive.');
        }
        $this->occurredAt = $occurredAt->setTimezone(new DateTimeZone('UTC'));
    }
}
