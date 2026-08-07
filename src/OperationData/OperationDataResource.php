<?php

declare(strict_types=1);

namespace BlackOps\OperationData;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum OperationDataResource: string
{
    case CanonicalJournal = 'canonical_journal';
    case Outcome = 'outcome';
}
