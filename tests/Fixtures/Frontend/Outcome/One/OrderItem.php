<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Frontend\Outcome\One;

use BlackOps\Core\OutcomeData;

final readonly class OrderItem implements OutcomeData
{
    public function __construct(
        public string $id,
    ) {}
}
