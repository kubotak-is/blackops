<?php

declare(strict_types=1);

namespace App\Feature\Post;

use App\Domain\Board\PostSummary as DomainPostSummary;
use App\Feature\BoardTime;
use BlackOps\Core\OutcomeData;

final readonly class PostSummary implements OutcomeData
{
    public function __construct(
        public string $id,
        public string $authorId,
        public string $authorDisplayName,
        public string $title,
        public string $bodyPreview,
        public string $createdAt,
        public string $updatedAt,
        public int $commentCount,
    ) {}

    public static function fromDomain(DomainPostSummary $post): self
    {
        return new self(
            $post->id,
            $post->authorId,
            $post->authorDisplayName,
            $post->title,
            $post->bodyPreview,
            BoardTime::http($post->createdAt),
            BoardTime::http($post->updatedAt),
            $post->commentCount,
        );
    }
}
