<?php

declare(strict_types=1);

namespace App\Feature\Post\UpdatePost;

use App\Domain\Board\BoardService;
use App\Domain\Board\PostNotFound;
use App\Feature\BoardTime;
use App\Security\AuthenticatedUser;
use App\Security\AuthenticatedUserPolicy;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'PUT', path: '/posts/{postId}')]
#[OperationType('board.post.update')]
#[Authorize(AuthenticatedUserPolicy::class)]
readonly class UpdatePost implements Operation
{
    public function __construct(
        private BoardService $board,
    ) {}

    #[Transactional]
    public function handle(UpdatePostValue $value, ExecutionContext $context): PostUpdated
    {
        try {
            $post = $this->board->updatePost(
                $value->postId,
                AuthenticatedUser::id($context),
                $value->title,
                $value->body,
            );
        } catch (PostNotFound) {
            throw OperationRejectedException::notFound('board.post.not_found');
        }

        return new PostUpdated($post->postId, BoardTime::http($post->updatedAt));
    }
}
