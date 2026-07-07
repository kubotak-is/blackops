<?php

declare(strict_types=1);

namespace BlackOps\Journal;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface CanonicalJournalStore extends CanonicalJournalWriter, CanonicalJournalReader {}
