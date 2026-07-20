<?php

declare(strict_types=1);

namespace App\Feature\Comment\AddComment;

use BlackOps\Core\Outcome;

final readonly class CommentAdded implements Outcome
{
    public function __construct(
        public string $commentId,
        public string $postId,
        public string $createdAt,
    ) {}
}
