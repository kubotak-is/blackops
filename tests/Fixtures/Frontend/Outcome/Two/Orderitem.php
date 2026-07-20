<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Frontend\Outcome\Two;

use BlackOps\Core\OutcomeData;

final readonly class Orderitem implements OutcomeData
{
    public function __construct(
        public string $id,
    ) {}
}
