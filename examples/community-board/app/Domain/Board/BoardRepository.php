<?php

declare(strict_types=1);

namespace App\Domain\Board;

use DateTimeImmutable;

interface BoardRepository
{
    public function listPosts(int $limit, int $offset): PostPage;

    public function findPost(string $postId): ?PostThread;

    public function createPost(
        string $postId,
        string $authorId,
        string $title,
        string $body,
        DateTimeImmutable $now,
    ): void;

    public function lockPostAuthorId(string $postId): ?string;

    public function updatePost(string $postId, string $title, string $body, DateTimeImmutable $updatedAt): void;

    public function deletePost(string $postId): void;

    public function createComment(
        string $commentId,
        string $postId,
        string $authorId,
        string $body,
        DateTimeImmutable $createdAt,
    ): void;
}
