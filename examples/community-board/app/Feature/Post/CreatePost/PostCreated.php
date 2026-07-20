<?php

declare(strict_types=1);

namespace App\Feature\Post\CreatePost;

use BlackOps\Core\Outcome;

final readonly class PostCreated implements Outcome
{
    public function __construct(
        public string $postId,
        public string $createdAt,
    ) {}
}
