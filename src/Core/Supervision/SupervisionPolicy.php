<?php

declare(strict_types=1);

namespace BlackOps\Core\Supervision;

use BlackOps\Core\AttemptContext;
use BlackOps\Core\Attribute\PublicApi;
use Throwable;

#[PublicApi]
interface SupervisionPolicy
{
    public function decide(Throwable $error, AttemptContext $attempt): SupervisionDecision;
}
