<?php

declare(strict_types=1);

namespace App\Feature\Identity\Register;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\Email;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;

final readonly class RegisterValue implements OperationValue
{
    public function __construct(
        #[NotBlank]
        #[Email]
        #[Length(max: 254)]
        public string $email,
        #[NotBlank]
        #[Length(min: 1, max: 80)]
        public string $displayName,
        #[Sensitive]
        #[\SensitiveParameter]
        #[NotBlank]
        #[Length(min: 12, max: 128)]
        public string $password,
    ) {}
}
