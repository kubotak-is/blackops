<?php

declare(strict_types=1);

namespace App\Feature\Post;

use App\Domain\Board\PostDetail as DomainPostDetail;
use App\Feature\BoardTime;
use BlackOps\Core\OutcomeData;

final readonly class PostDetail implements OutcomeData
{
    public function __construct(
        public string $id,
        public string $authorId,
        public string $authorDisplayName,
        public string $title,
        public string $body,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromDomain(DomainPostDetail $post): self
    {
        return new self(
            $post->id,
            $post->authorId,
            $post->authorDisplayName,
            $post->title,
            $post->body,
            BoardTime::http($post->createdAt),
            BoardTime::http($post->updatedAt),
        );
    }
}
