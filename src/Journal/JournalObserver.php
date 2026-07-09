<?php

declare(strict_types=1);

namespace BlackOps\Journal;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface JournalObserver
{
    public function observe(ObservedJournalRecord $record): void;
}
