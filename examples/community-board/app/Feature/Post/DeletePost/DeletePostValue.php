<?php

declare(strict_types=1);

namespace App\Feature\Post\DeletePost;

use BlackOps\Core\OperationValue;
use BlackOps\Http\Attribute\FromPath;

final readonly class DeletePostValue implements OperationValue
{
    public function __construct(
        #[FromPath]
        public string $postId,
    ) {}
}
