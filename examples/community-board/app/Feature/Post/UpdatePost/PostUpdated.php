<?php

declare(strict_types=1);

namespace App\Feature\Post\UpdatePost;

use BlackOps\Core\Outcome;

final readonly class PostUpdated implements Outcome
{
    public function __construct(
        public string $postId,
        public string $updatedAt,
    ) {}
}
