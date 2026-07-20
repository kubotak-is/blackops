<?php

declare(strict_types=1);

namespace App\Feature\Post\DeletePost;

use App\Domain\Board\BoardService;
use App\Domain\Board\PostNotFound;
use App\Security\AuthenticatedUser;
use App\Security\AuthenticatedUserPolicy;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'DELETE', path: '/posts/{postId}')]
#[OperationType('board.post.delete')]
#[Authorize(AuthenticatedUserPolicy::class)]
readonly class DeletePost implements Operation
{
    public function __construct(
        private BoardService $board,
    ) {}

    #[Transactional]
    public function handle(DeletePostValue $value, ExecutionContext $context): void
    {
        try {
            $this->board->deletePost($value->postId, AuthenticatedUser::id($context));
        } catch (PostNotFound) {
            throw OperationRejectedException::notFound('board.post.not_found');
        }
    }
}
