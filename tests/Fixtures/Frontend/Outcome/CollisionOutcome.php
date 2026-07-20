<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Frontend\Outcome;

use BlackOps\Core\Outcome;
use BlackOps\Tests\Fixtures\Frontend\Outcome\One\OrderItem as FirstOrderItem;
use BlackOps\Tests\Fixtures\Frontend\Outcome\Two\Orderitem as SecondOrderItem;

final readonly class CollisionOutcome implements Outcome
{
    public function __construct(
        public FirstOrderItem $first,
        public SecondOrderItem $second,
    ) {}
}
