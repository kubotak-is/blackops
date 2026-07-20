<?php

declare(strict_types=1);

namespace App\Feature\Post\CreatePost;

use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Http\Attribute\FromBody;

final readonly class CreatePostValue implements OperationValue
{
    public function __construct(
        #[FromBody]
        #[NotBlank]
        #[Length(min: 1, max: 120)]
        public string $title,
        #[FromBody]
        #[NotBlank]
        #[Length(min: 1, max: 10000)]
        public string $body,
    ) {}
}
