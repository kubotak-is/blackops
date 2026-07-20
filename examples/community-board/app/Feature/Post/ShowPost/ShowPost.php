<?php

declare(strict_types=1);

namespace App\Feature\Post\ShowPost;

use App\Domain\Board\BoardService;
use App\Domain\Board\PostNotFound;
use App\Security\AuthenticatedUserPolicy;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'GET', path: '/posts/{postId}')]
#[OperationType('board.post.show')]
#[Authorize(AuthenticatedUserPolicy::class)]
final readonly class ShowPost implements Operation
{
    public function __construct(
        private BoardService $board,
    ) {}

    public function handle(ShowPostValue $value): ShowPostOutcome
    {
        try {
            $thread = $this->board->showPost($value->postId);
        } catch (PostNotFound) {
            throw OperationRejectedException::notFound('board.post.not_found');
        }

        return ShowPostOutcome::fromDomain($thread);
    }
}
