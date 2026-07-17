<?php

declare(strict_types=1);

namespace App\Feature\Order;

use BlackOps\Database\Attribute\AfterCommit;

readonly class RecordOrderCommit
{
    public function __construct(
        private OrderRepository $orders,
    ) {}

    #[AfterCommit]
    public function record(string $reference): void
    {
        $this->orders->recordCommitted($reference);
    }
}
