<?php

declare(strict_types=1);

namespace App\Feature\Post\ShowPost;

use BlackOps\Core\OperationValue;
use BlackOps\Http\Attribute\FromPath;

final readonly class ShowPostValue implements OperationValue
{
    public function __construct(
        #[FromPath]
        public string $postId,
    ) {}
}
