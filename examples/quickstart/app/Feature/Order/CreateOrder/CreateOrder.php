<?php

declare(strict_types=1);

namespace App\Feature\Order\CreateOrder;

use App\Security\SampleUserAuthorizationPolicy;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/orders')]
#[OperationType('order.create')]
#[Authorize(SampleUserAuthorizationPolicy::class)]
readonly class CreateOrder implements Operation
{
    public function __construct(
        private CreateOrderCommand $command,
    ) {}

    #[Transactional]
    public function handle(CreateOrderValue $value): OrderCreated
    {
        $this->command->execute($value->reference);

        return new OrderCreated($value->reference, 'created');
    }
}
