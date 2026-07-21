<?php

declare(strict_types=1);

namespace App\Infrastructure\Seed;

use Doctrine\DBAL\Connection;

final readonly class SeedStateRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function userExists(SeedUser $user): bool
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT id::text AS id,
                       email_canonical,
                       email_display,
                       display_name,
                       password_hash,
                       to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS created_at,
                       to_char(updated_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS updated_at
                FROM public.board_users
                WHERE id = :id OR email_canonical = :email_canonical
                FOR UPDATE
                SQL,
            ['id' => $user->id, 'email_canonical' => strtolower($user->email)],
        );
        if ($rows === []) {
            return false;
        }

        if (count($rows) !== 1) {
            throw new SeedConflict('Seed user identity conflicts with existing application data.');
        }

        $row = $rows[0];
        $timestamp = $this->timestamp($user->createdAt);
        if (
            ($row['id'] ?? null) !== $user->id
            || ($row['email_canonical'] ?? null) !== strtolower($user->email)
            || ($row['email_display'] ?? null) !== $user->email
            || ($row['display_name'] ?? null) !== $user->displayName
            || ($row['created_at'] ?? null) !== $timestamp
            || ($row['updated_at'] ?? null) !== $timestamp
            || !is_string($row['password_hash'] ?? null)
            || !password_verify($user->password, $row['password_hash'])
        ) {
            throw new SeedConflict('Seed user conflicts with existing application data.');
        }

        return true;
    }

    public function postExists(SeedPost $post): bool
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT id::text AS id,
                       author_id::text AS author_id,
                       title,
                       body,
                       to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS created_at,
                       to_char(updated_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS updated_at
                FROM public.board_posts
                WHERE id = :id
                FOR UPDATE
                SQL,
            ['id' => $post->id],
        );
        if ($row === false) {
            return false;
        }

        $timestamp = $this->timestamp($post->createdAt);
        if (
            ($row['id'] ?? null) !== $post->id
            || ($row['author_id'] ?? null) !== $post->authorId
            || ($row['title'] ?? null) !== $post->title
            || ($row['body'] ?? null) !== $post->body
            || ($row['created_at'] ?? null) !== $timestamp
            || ($row['updated_at'] ?? null) !== $timestamp
        ) {
            throw new SeedConflict('Seed post conflicts with existing application data.');
        }

        return true;
    }

    public function commentExists(SeedComment $comment): bool
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT id::text AS id,
                       post_id::text AS post_id,
                       author_id::text AS author_id,
                       body,
                       to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS created_at
                FROM public.board_comments
                WHERE id = :id
                FOR UPDATE
                SQL,
            ['id' => $comment->id],
        );
        if ($row === false) {
            return false;
        }

        if (
            ($row['id'] ?? null) !== $comment->id
            || ($row['post_id'] ?? null) !== $comment->postId
            || ($row['author_id'] ?? null) !== $comment->authorId
            || ($row['body'] ?? null) !== $comment->body
            || ($row['created_at'] ?? null) !== $this->timestamp($comment->createdAt)
        ) {
            throw new SeedConflict('Seed comment conflicts with existing application data.');
        }

        return true;
    }

    private function timestamp(\DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d\TH:i:s.u\Z');
    }
}
