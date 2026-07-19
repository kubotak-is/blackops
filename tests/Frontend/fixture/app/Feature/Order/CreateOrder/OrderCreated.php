<?php

declare(strict_types=1);

namespace BlackOpsFrontendFixture\Feature\Order\CreateOrder;

use BlackOps\Core\Outcome;

final readonly class OrderCreated implements Outcome
{
    public function __construct(
        public string $orderId,
        public int $sequence,
        public float $total,
        public bool $active,
    ) {}
}
