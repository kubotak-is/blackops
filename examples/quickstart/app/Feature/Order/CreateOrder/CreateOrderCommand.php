<?php

declare(strict_types=1);

namespace App\Feature\Order\CreateOrder;

use App\Feature\Order\OrderRepository;
use App\Feature\Order\RecordOrderCommit;
use BlackOps\Database\Attribute\Transactional;

readonly class CreateOrderCommand
{
    public function __construct(
        private OrderRepository $orders,
        private RecordOrderCommit $commits,
    ) {}

    #[Transactional]
    public function execute(string $reference): void
    {
        $this->orders->create($reference);
        $this->commits->record($reference);
    }
}
