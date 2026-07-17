<?php

declare(strict_types=1);

namespace App\Feature\Order\CreateOrder;

use BlackOps\Core\Outcome;

final readonly class OrderCreated implements Outcome
{
    public function __construct(
        public string $reference,
        public string $status,
    ) {}
}
