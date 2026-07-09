<?php

declare(strict_types=1);

namespace BlackOps\Core\Execution;

use BlackOps\Core\Attribute\PublicApi;
use DateTimeImmutable;

#[PublicApi]
final readonly class ClaimRequest
{
    public function __construct(
        private DateTimeImmutable $claimedAt,
    ) {}

    public function claimedAt(): DateTimeImmutable
    {
        return $this->claimedAt;
    }
}
