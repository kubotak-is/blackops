<?php

declare(strict_types=1);

namespace App\Domain\Board;

final readonly class BoardService
{
    public function __construct(
        private BoardRepository $posts,
        private BoardClock $clock,
        private BoardIdGenerator $identifiers,
    ) {}

    public function listPosts(int $page, int $perPage): PostPage
    {
        return $this->posts->listPosts($perPage, ($page - 1) * $perPage);
    }

    public function showPost(string $postId): PostThread
    {
        $this->requireValidPostId($postId);

        return $this->posts->findPost($postId) ?? throw new PostNotFound();
    }

    public function createPost(string $authorId, string $title, string $body): CreatedPost
    {
        $postId = $this->identifiers->generate();
        $now = $this->clock->now();
        $this->posts->createPost($postId, $authorId, $title, $body, $now);

        return new CreatedPost($postId, $now);
    }

    public function updatePost(string $postId, string $actorId, string $title, string $body): UpdatedPost
    {
        $this->requireLockedOwner($postId, $actorId);
        $now = $this->clock->now();
        $this->posts->updatePost($postId, $title, $body, $now);

        return new UpdatedPost($postId, $now);
    }

    public function deletePost(string $postId, string $actorId): void
    {
        $this->requireLockedOwner($postId, $actorId);
        $this->posts->deletePost($postId);
    }

    public function addComment(string $postId, string $authorId, string $body): AddedComment
    {
        $this->requireValidPostId($postId);
        if ($this->posts->lockPostAuthorId($postId) === null) {
            throw new PostNotFound();
        }

        $commentId = $this->identifiers->generate();
        $now = $this->clock->now();
        $this->posts->createComment($commentId, $postId, $authorId, $body, $now);

        return new AddedComment($commentId, $postId, $now);
    }

    private function requireLockedOwner(string $postId, string $actorId): void
    {
        $this->requireValidPostId($postId);
        if ($this->posts->lockPostAuthorId($postId) !== $actorId) {
            throw new PostNotFound();
        }
    }

    private function requireValidPostId(string $postId): void
    {
        if (!BoardId::isValid($postId)) {
            throw new PostNotFound();
        }
    }
}
