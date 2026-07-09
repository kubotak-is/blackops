<?php

declare(strict_types=1);

namespace BlackOps\Journal;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum JournalDeliveryPolicy
{
    case BestEffort;
    case Required;
    case Durable;
}
