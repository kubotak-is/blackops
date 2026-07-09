<?php

declare(strict_types=1);

namespace BlackOps\Journal;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\JournalRecordId;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

#[PublicApi]
final readonly class ObservedJournalRecord
{
    public DateTimeImmutable $occurredAt;

    /**
     * @var array<string, mixed>
     */
    public array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public JournalRecordId $recordId,
        public int $schemaVersion,
        public JournalEvent $event,
        DateTimeImmutable $occurredAt,
        public int $sequence,
        public JournalOperation $operation,
        public ?JournalAttempt $attempt,
        array $data,
    ) {
        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Observed journal schema version must be positive.');
        }
        if ($sequence < 1) {
            throw new InvalidArgumentException('Observed journal sequence must be positive.');
        }

        $this->occurredAt = $occurredAt->setTimezone(new DateTimeZone('UTC'));
        $this->data = $data;
    }
}
