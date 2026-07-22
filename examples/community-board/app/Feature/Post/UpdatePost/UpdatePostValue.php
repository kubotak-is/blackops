<?php

declare(strict_types=1);

namespace App\Feature\Post\UpdatePost;

use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Http\Attribute\FromBody;
use BlackOps\Http\Attribute\FromPath;

final readonly class UpdatePostValue implements OperationValue
{
    public function __construct(
        #[FromPath]
        public string $postId,
        #[FromBody]
        #[NotBlank]
        #[Length(min: 1, max: 120)]
        public string $title,
        #[FromBody]
        #[NotBlank]
        #[Length(min: 1, max: 10_000)]
        public string $body,
    ) {}
}
