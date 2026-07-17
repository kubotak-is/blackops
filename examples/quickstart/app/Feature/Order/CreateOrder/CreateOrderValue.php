<?php

declare(strict_types=1);

namespace App\Feature\Order\CreateOrder;

use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;

final readonly class CreateOrderValue implements OperationValue
{
    public function __construct(
        #[NotBlank]
        #[Length(max: 64)]
        public string $reference,
    ) {}
}
