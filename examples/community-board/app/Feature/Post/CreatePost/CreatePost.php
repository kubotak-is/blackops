<?php

declare(strict_types=1);

namespace App\Feature\Post\CreatePost;

use App\Domain\Board\BoardService;
use App\Feature\BoardTime;
use App\Security\AuthenticatedUser;
use App\Security\AuthenticatedUserPolicy;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/posts')]
#[OperationType('board.post.create')]
#[Authorize(AuthenticatedUserPolicy::class)]
readonly class CreatePost implements Operation
{
    public function __construct(
        private BoardService $board,
    ) {}

    #[Transactional]
    public function handle(CreatePostValue $value, ExecutionContext $context): PostCreated
    {
        $post = $this->board->createPost(AuthenticatedUser::id($context), $value->title, $value->body);

        return new PostCreated($post->postId, BoardTime::http($post->createdAt));
    }
}
