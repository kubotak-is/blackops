<?php

declare(strict_types=1);

namespace App\Feature\Comment\AddComment;

use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Http\Attribute\FromBody;
use BlackOps\Http\Attribute\FromPath;

final readonly class AddCommentValue implements OperationValue
{
    public function __construct(
        #[FromPath]
        public string $postId,
        #[FromBody]
        #[NotBlank]
        #[Length(min: 1, max: 2000)]
        public string $body,
    ) {}
}
