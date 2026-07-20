<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Board\BoardId;
use App\Domain\Board\DigestRepository;
use App\Domain\Board\DigestSnapshot;
use App\Domain\Board\GeneratedDigest;
use App\Domain\Board\IsoWeek;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use UnexpectedValueException;

final readonly class DoctrineDigestRepository implements DigestRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function snapshot(IsoWeek $week): DigestSnapshot
    {
        $parameters = [
            'starts_at' => $this->databaseTime($week->startsAt()),
            'ends_at' => $this->databaseTime($week->endsAt()),
        ];

        return new DigestSnapshot(
            $this->count($this->connection->fetchOne(
                'SELECT count(*) FROM public.board_posts WHERE created_at >= :starts_at AND created_at < :ends_at',
                $parameters,
            )),
            $this->count($this->connection->fetchOne(
                'SELECT count(*) FROM public.board_comments WHERE created_at >= :starts_at AND created_at < :ends_at',
                $parameters,
            )),
        );
    }

    public function save(GeneratedDigest $digest): void
    {
        $this->connection->insert('public.board_digests', [
            'id' => $digest->id,
            'requested_user_id' => $digest->requestedUserId,
            'week' => $digest->week,
            'content' => $digest->content,
            'post_count' => $digest->postCount,
            'comment_count' => $digest->commentCount,
            'created_at' => $this->databaseTime($digest->createdAt),
        ]);
    }

    public function findOwned(string $digestId, string $requestedUserId): ?GeneratedDigest
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT id::text AS id,
                       requested_user_id::text AS requested_user_id,
                       week,
                       content,
                       post_count,
                       comment_count,
                       to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS created_at
                FROM public.board_digests
                WHERE id = :id AND requested_user_id = :requested_user_id
                SQL,
            [
                'id' => $digestId,
                'requested_user_id' => $requestedUserId,
            ],
        );
        if ($row === false) {
            return null;
        }

        return new GeneratedDigest(
            $this->identifier($row, 'id'),
            $this->identifier($row, 'requested_user_id'),
            $this->text($row, 'week'),
            $this->text($row, 'content'),
            $this->count($row['post_count'] ?? null),
            $this->count($row['comment_count'] ?? null),
            $this->dateTime($row, 'created_at'),
        );
    }

    /** @param array<string, mixed> $row */
    private function identifier(array $row, string $field): string
    {
        $value = $this->text($row, $field);
        if (!BoardId::isValid($value)) {
            throw new UnexpectedValueException('Digest query returned an invalid identifier.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function text(array $row, string $field): string
    {
        $value = $row[$field] ?? null;
        if (!is_string($value)) {
            throw new UnexpectedValueException('Digest query returned an invalid text field.');
        }

        return $value;
    }

    private function count(mixed $value): int
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

        throw new UnexpectedValueException('Digest query returned an invalid count.');
    }

    /** @param array<string, mixed> $row */
    private function dateTime(array $row, string $field): DateTimeImmutable
    {
        $dateTime = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s.u\Z',
            $this->text($row, $field),
            new DateTimeZone('UTC'),
        );
        if ($dateTime === false) {
            throw new UnexpectedValueException('Digest query returned an invalid timestamp.');
        }

        return $dateTime;
    }

    private function databaseTime(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }
}
