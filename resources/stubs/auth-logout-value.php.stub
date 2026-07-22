<?php

declare(strict_types=1);

namespace App\Feature\Identity\Logout;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;

final readonly class LogoutValue implements OperationValue
{
    public function __construct(
        #[Sensitive]
        #[NotBlank]
        #[Length(min: 43, max: 43)]
        public string $token,
    ) {}
}
