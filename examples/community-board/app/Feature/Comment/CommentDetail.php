<?php

declare(strict_types=1);

namespace App\Feature\Comment;

use App\Domain\Board\CommentDetail as DomainCommentDetail;
use App\Feature\BoardTime;
use BlackOps\Core\OutcomeData;

final readonly class CommentDetail implements OutcomeData
{
    public function __construct(
        public string $id,
        public string $postId,
        public string $authorId,
        public string $authorDisplayName,
        public string $body,
        public string $createdAt,
    ) {}

    public static function fromDomain(DomainCommentDetail $comment): self
    {
        return new self(
            $comment->id,
            $comment->postId,
            $comment->authorId,
            $comment->authorDisplayName,
            $comment->body,
            BoardTime::http($comment->createdAt),
        );
    }
}
