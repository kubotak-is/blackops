<?php

declare(strict_types=1);

namespace BlackOps\Journal;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface CanonicalJournalWriter
{
    public function append(JournalRecord $record): void;
}
