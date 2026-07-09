<?php

declare(strict_types=1);

namespace BlackOps\Core\Execution;

use BlackOps\Core\Attribute\PublicApi;
use DateTimeImmutable;

#[PublicApi]
interface ClaimSettlement
{
    public function acknowledge(OperationClaim $claim): void;

    public function release(OperationClaim $claim, DateTimeImmutable $availableAt): void;
}
