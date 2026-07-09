<?php

declare(strict_types=1);

namespace BlackOps\Journal;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface FlushableJournalObserver extends JournalObserver
{
    public function flush(): void;
}
