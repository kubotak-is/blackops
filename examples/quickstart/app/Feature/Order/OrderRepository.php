<?php

declare(strict_types=1);

namespace App\Feature\Order;

interface OrderRepository
{
    public function create(string $reference): void;

    public function recordCommitted(string $reference): void;
}
