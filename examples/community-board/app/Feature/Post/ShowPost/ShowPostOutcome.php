<?php

declare(strict_types=1);

namespace App\Feature\Post\ShowPost;

use App\Domain\Board\PostThread;
use App\Feature\Comment\CommentDetail;
use App\Feature\Post\PostDetail;
use BlackOps\Core\Attribute\ListOf;
use BlackOps\Core\Outcome;

final readonly class ShowPostOutcome implements Outcome
{
    /** @param list<CommentDetail> $comments */
    public function __construct(
        public PostDetail $post,
        #[ListOf(CommentDetail::class)]
        public array $comments,
    ) {}

    public static function fromDomain(PostThread $thread): self
    {
        return new self(
            PostDetail::fromDomain($thread->post),
            array_map(CommentDetail::fromDomain(...), $thread->comments),
        );
    }
}
