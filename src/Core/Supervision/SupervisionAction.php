<?php

declare(strict_types=1);

namespace BlackOps\Core\Supervision;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum SupervisionAction: string
{
    case Retry = 'retry';
    case Fail = 'fail';
    case DeadLetter = 'dead_letter';
}
