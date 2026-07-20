<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Board\BoardRepository;
use App\Domain\Board\CommentDetail;
use App\Domain\Board\PostDetail;
use App\Domain\Board\PostPage;
use App\Domain\Board\PostSummary;
use App\Domain\Board\PostThread;
use DateTimeImmutable;

final class InMemoryBoardRepository implements BoardRepository
{
    /** @var array<string, array{authorId: string, title: string, body: string, createdAt: DateTimeImmutable, updatedAt: DateTimeImmutable}> */
    public array $posts = [];

    /** @var array<string, array{postId: string, authorId: string, body: string, createdAt: DateTimeImmutable}> */
    public array $comments = [];

    /** @var list<string> */
    public array $lockedPostIds = [];

    public function listPosts(int $limit, int $offset): PostPage
    {
        $ids = array_keys($this->posts);
        rsort($ids);
        $summaries = [];
        foreach (array_slice($ids, $offset, $limit) as $id) {
            $post = $this->posts[$id];
            $summaries[] = new PostSummary(
                $id,
                $post['authorId'],
                $post['authorId'],
                $post['title'],
                $post['body'],
                $post['createdAt'],
                $post['updatedAt'],
                count(array_filter($this->comments, static fn(array $comment): bool => $comment['postId'] === $id)),
            );
        }

        return new PostPage($summaries, count($this->posts));
    }

    public function findPost(string $postId): ?PostThread
    {
        $post = $this->posts[$postId] ?? null;
        if ($post === null) {
            return null;
        }

        $comments = [];
        foreach ($this->comments as $id => $comment) {
            if ($comment['postId'] === $postId) {
                $comments[] = new CommentDetail(
                    $id,
                    $postId,
                    $comment['authorId'],
                    $comment['authorId'],
                    $comment['body'],
                    $comment['createdAt'],
                );
            }
        }

        return new PostThread(
            new PostDetail(
                $postId,
                $post['authorId'],
                $post['authorId'],
                $post['title'],
                $post['body'],
                $post['createdAt'],
                $post['updatedAt'],
            ),
            $comments,
        );
    }

    public function createPost(
        string $postId,
        string $authorId,
        string $title,
        string $body,
        DateTimeImmutable $now,
    ): void {
        $this->posts[$postId] = [
            'authorId' => $authorId,
            'title' => $title,
            'body' => $body,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
    }

    public function lockPostAuthorId(string $postId): ?string
    {
        $this->lockedPostIds[] = $postId;

        return $this->posts[$postId]['authorId'] ?? null;
    }

    public function updatePost(string $postId, string $title, string $body, DateTimeImmutable $updatedAt): void
    {
        $this->posts[$postId]['title'] = $title;
        $this->posts[$postId]['body'] = $body;
        $this->posts[$postId]['updatedAt'] = $updatedAt;
    }

    public function deletePost(string $postId): void
    {
        unset($this->posts[$postId]);
        foreach ($this->comments as $commentId => $comment) {
            if ($comment['postId'] === $postId) {
                unset($this->comments[$commentId]);
            }
        }
    }

    public function createComment(
        string $commentId,
        string $postId,
        string $authorId,
        string $body,
        DateTimeImmutable $createdAt,
    ): void {
        $this->comments[$commentId] = [
            'postId' => $postId,
            'authorId' => $authorId,
            'body' => $body,
            'createdAt' => $createdAt,
        ];
    }
}
