<?php

declare(strict_types=1);

namespace App\Domain\Board;

final readonly class PostThread
{
    /** @param list<CommentDetail> $comments */
    public function __construct(
        public PostDetail $post,
        public array $comments,
    ) {}
}
