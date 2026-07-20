<?php

declare(strict_types=1);

namespace App\Feature\Post\ListPosts;

use App\Domain\Board\BoardService;
use App\Security\AuthenticatedUserPolicy;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'GET', path: '/posts')]
#[OperationType('board.post.list')]
#[Authorize(AuthenticatedUserPolicy::class)]
final readonly class ListPosts implements Operation
{
    public function __construct(
        private BoardService $board,
    ) {}

    public function handle(ListPostsValue $value): ListPostsOutcome
    {
        $page = $this->board->listPosts($value->page, $value->perPage);

        return ListPostsOutcome::fromDomain($page, $value->page, $value->perPage);
    }
}
