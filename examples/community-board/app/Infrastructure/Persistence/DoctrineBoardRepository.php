<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Board\BoardId;
use App\Domain\Board\BoardRepository;
use App\Domain\Board\CommentDetail;
use App\Domain\Board\PostDetail;
use App\Domain\Board\PostPage;
use App\Domain\Board\PostSummary;
use App\Domain\Board\PostThread;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use RuntimeException;
use UnexpectedValueException;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final readonly class DoctrineBoardRepository implements BoardRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function listPosts(int $limit, int $offset): PostPage
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT posts.id::text AS id,
                   posts.author_id::text AS author_id,
                   users.display_name AS author_display_name,
                   posts.title,
                   left(posts.body, 240) AS body_preview,
                   to_char(posts.created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS created_at,
                   to_char(posts.updated_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS updated_at,
                   (SELECT count(*) FROM public.board_comments comments WHERE comments.post_id = posts.id) AS comment_count
            FROM public.board_posts posts
            INNER JOIN public.board_users users ON users.id = posts.author_id
            ORDER BY posts.created_at DESC, posts.id DESC
            LIMIT :limit OFFSET :offset
            SQL, [
            'limit' => $limit,
            'offset' => $offset,
        ], ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]);

        $posts = [];
        foreach ($rows as $row) {
            $posts[] = $this->postSummary($row);
        }

        return new PostPage($posts, $this->integer(
            $this->connection->fetchOne('SELECT count(*) FROM public.board_posts'),
            'total',
        ));
    }

    public function findPost(string $postId): ?PostThread
    {
        $post = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT posts.id::text AS id,
                       posts.author_id::text AS author_id,
                       users.display_name AS author_display_name,
                       posts.title,
                       posts.body,
                       to_char(posts.created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS created_at,
                       to_char(posts.updated_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS updated_at
                FROM public.board_posts posts
                INNER JOIN public.board_users users ON users.id = posts.author_id
                WHERE posts.id = :post_id
                SQL,
            ['post_id' => $postId],
        );
        if ($post === false) {
            return null;
        }

        $commentRows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT comments.id::text AS id,
                       comments.post_id::text AS post_id,
                       comments.author_id::text AS author_id,
                       users.display_name AS author_display_name,
                       comments.body,
                       to_char(comments.created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS created_at
                FROM public.board_comments comments
                INNER JOIN public.board_users users ON users.id = comments.author_id
                WHERE comments.post_id = :post_id
                ORDER BY comments.created_at ASC, comments.id ASC
                SQL,
            ['post_id' => $postId],
        );

        $comments = [];
        foreach ($commentRows as $row) {
            $comments[] = $this->commentDetail($row);
        }

        return new PostThread($this->postDetail($post), $comments);
    }

    public function createPost(
        string $postId,
        string $authorId,
        string $title,
        string $body,
        DateTimeImmutable $now,
    ): void {
        $timestamp = $this->databaseTime($now);
        $this->connection->insert('public.board_posts', [
            'id' => $postId,
            'author_id' => $authorId,
            'title' => $title,
            'body' => $body,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    public function lockPostAuthorId(string $postId): ?string
    {
        $authorId = $this->connection->fetchOne('SELECT author_id::text FROM public.board_posts WHERE id = :post_id FOR UPDATE', [
            'post_id' => $postId,
        ]);
        if ($authorId === false) {
            return null;
        }
        if (!is_string($authorId) || !BoardId::isValid($authorId)) {
            throw new UnexpectedValueException('Board post author identifier is invalid.');
        }

        return $authorId;
    }

    public function updatePost(string $postId, string $title, string $body, DateTimeImmutable $updatedAt): void
    {
        $affected = $this->connection->update(
            'public.board_posts',
            ['title' => $title, 'body' => $body, 'updated_at' => $this->databaseTime($updatedAt)],
            ['id' => $postId],
        );
        if ($affected !== 1) {
            throw new RuntimeException('Locked board post could not be updated.');
        }
    }

    public function deletePost(string $postId): void
    {
        if ($this->connection->delete('public.board_posts', ['id' => $postId]) !== 1) {
            throw new RuntimeException('Locked board post could not be deleted.');
        }
    }

    public function createComment(
        string $commentId,
        string $postId,
        string $authorId,
        string $body,
        DateTimeImmutable $createdAt,
    ): void {
        $this->connection->insert('public.board_comments', [
            'id' => $commentId,
            'post_id' => $postId,
            'author_id' => $authorId,
            'body' => $body,
            'created_at' => $this->databaseTime($createdAt),
        ]);
    }

    /** @param array<string, mixed> $row */
    private function postSummary(array $row): PostSummary
    {
        return new PostSummary(
            $this->identifier($row, 'id'),
            $this->identifier($row, 'author_id'),
            $this->text($row, 'author_display_name'),
            $this->text($row, 'title'),
            $this->text($row, 'body_preview'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
            $this->integer($row['comment_count'] ?? null, 'comment_count'),
        );
    }

    /** @param array<string, mixed> $row */
    private function postDetail(array $row): PostDetail
    {
        return new PostDetail(
            $this->identifier($row, 'id'),
            $this->identifier($row, 'author_id'),
            $this->text($row, 'author_display_name'),
            $this->text($row, 'title'),
            $this->text($row, 'body'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
        );
    }

    /** @param array<string, mixed> $row */
    private function commentDetail(array $row): CommentDetail
    {
        return new CommentDetail(
            $this->identifier($row, 'id'),
            $this->identifier($row, 'post_id'),
            $this->identifier($row, 'author_id'),
            $this->text($row, 'author_display_name'),
            $this->text($row, 'body'),
            $this->dateTime($row, 'created_at'),
        );
    }

    /** @param array<string, mixed> $row */
    private function identifier(array $row, string $field): string
    {
        $value = $this->text($row, $field);
        if (!BoardId::isValid($value)) {
            throw new UnexpectedValueException('Board query returned an invalid identifier.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function text(array $row, string $field): string
    {
        $value = $row[$field] ?? null;
        if (!is_string($value)) {
            throw new UnexpectedValueException('Board query returned an invalid text field.');
        }

        return $value;
    }

    private function integer(mixed $value, string $field): int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) === 1) {
            $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if (is_int($integer)) {
                return $integer;
            }
        }

        throw new UnexpectedValueException(sprintf('Board query returned an invalid %s count.', $field));
    }

    /** @param array<string, mixed> $row */
    private function dateTime(array $row, string $field): DateTimeImmutable
    {
        $value = $this->text($row, $field);
        $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u\Z', $value, new DateTimeZone('UTC'));
        if ($dateTime === false) {
            throw new UnexpectedValueException('Board query returned an invalid timestamp.');
        }

        return $dateTime;
    }

    private function databaseTime(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }
}
