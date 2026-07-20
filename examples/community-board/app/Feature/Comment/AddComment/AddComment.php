<?php

declare(strict_types=1);

namespace App\Feature\Comment\AddComment;

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

#[Route(method: 'POST', path: '/posts/{postId}/comments')]
#[OperationType('board.comment.add')]
#[Authorize(AuthenticatedUserPolicy::class)]
readonly class AddComment implements Operation
{
    public function __construct(
        private BoardService $board,
    ) {}

    #[Transactional]
    public function handle(AddCommentValue $value, ExecutionContext $context): CommentAdded
    {
        try {
            $comment = $this->board->addComment($value->postId, AuthenticatedUser::id($context), $value->body);
        } catch (PostNotFound) {
            throw OperationRejectedException::notFound('board.post.not_found');
        }

        return new CommentAdded($comment->commentId, $comment->postId, BoardTime::http($comment->createdAt));
    }
}
