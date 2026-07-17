<?php

declare(strict_types=1);

namespace App\Feature\Order;

use Doctrine\DBAL\Connection;

final readonly class DoctrineOrderRepository implements OrderRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function create(string $reference): void
    {
        $this->connection->executeStatement('INSERT INTO public.quickstart_orders (reference) VALUES (:reference)', [
            'reference' => $reference,
        ]);
    }

    public function recordCommitted(string $reference): void
    {
        $this->connection->executeStatement('INSERT INTO public.quickstart_order_commits (reference) VALUES (:reference)', [
            'reference' => $reference,
        ]);
    }
}
