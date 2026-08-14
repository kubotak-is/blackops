<?php

declare(strict_types=1);

namespace BlackOps\OperationData;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Journal\JournalRecord;

#[PublicApi]
final readonly class OperationJournalFound implements OperationJournalReadResult
{
    /** @param list<JournalRecord> $records */
    public function __construct(
        private array $records,
    ) {}

    /** @return list<JournalRecord> */
    public function records(): array
    {
        return $this->records;
    }
}
