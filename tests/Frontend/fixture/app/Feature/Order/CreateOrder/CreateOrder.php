<?php

declare(strict_types=1);

namespace BlackOpsFrontendFixture\Feature\Order\CreateOrder;

use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/accounts/{accountId}/orders')]
#[OperationType('order.create')]
final readonly class CreateOrder implements Operation
{
    public function handle(CreateOrderValue $value): OrderCreated
    {
        $owner = new OrderOwner('Alice', 'owner-1');

        return new OrderCreated(
            new EmptyMetadata(),
            new EmptyMetadata(),
            [new EmptyMetadata(), new EmptyMetadata()],
            new EmptyMetadata(),
            'order-' . $value->reference,
            7,
            $value->amount,
            $value->active,
            $owner,
            null,
            [new OrderLine($owner, 'product-1', 1)],
        );
    }
}
