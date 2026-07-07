<?php

declare(strict_types=1);

namespace BlackOps\Journal;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
final readonly class EmptyJournalData implements JournalData {}
