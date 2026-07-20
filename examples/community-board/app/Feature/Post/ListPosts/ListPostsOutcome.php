<?php

declare(strict_types=1);

namespace App\Feature\Post\ListPosts;

use App\Domain\Board\PostPage;
use App\Feature\Post\PostSummary;
use BlackOps\Core\Attribute\ListOf;
use BlackOps\Core\Outcome;

final readonly class ListPostsOutcome implements Outcome
{
    /** @param list<PostSummary> $posts */
    public function __construct(
        #[ListOf(PostSummary::class)]
        public array $posts,
        public int $page,
        public int $perPage,
        public int $total,
    ) {}

    public static function fromDomain(PostPage $page, int $pageNumber, int $perPage): self
    {
        return new self(array_map(PostSummary::fromDomain(...), $page->posts), $pageNumber, $perPage, $page->total);
    }
}
