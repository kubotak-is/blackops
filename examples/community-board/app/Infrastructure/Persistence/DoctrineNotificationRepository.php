<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Notification\Notification;
use App\Domain\Notification\NotificationRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use UnexpectedValueException;

final readonly class DoctrineNotificationRepository implements NotificationRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function saveIfAbsent(Notification $notification): bool
    {
        $inserted = $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO public.board_notifications (
                    id, recipient_user_id, source_post_id, source_comment_id, delivery_operation_id, created_at
                ) VALUES (:id, :recipient_user_id, :source_post_id, :source_comment_id, :delivery_operation_id, :created_at)
                ON CONFLICT (delivery_operation_id) DO NOTHING
                SQL,
            [
                'id' => $notification->id,
                'recipient_user_id' => $notification->recipientUserId,
                'source_post_id' => $notification->sourcePostId,
                'source_comment_id' => $notification->sourceCommentId,
                'delivery_operation_id' => $notification->deliveryOperationId,
                'created_at' => $this->databaseTime($notification->createdAt),
            ],
        );

        return $inserted === 1;
    }

    public function listForRecipient(string $recipientUserId, int $limit = 50): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT id::text AS id,
                   recipient_user_id::text AS recipient_user_id,
                   source_post_id::text AS source_post_id,
                   source_comment_id::text AS source_comment_id,
                   delivery_operation_id::text AS delivery_operation_id,
                   to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS created_at
            FROM public.board_notifications
            WHERE recipient_user_id = :recipient_user_id
            ORDER BY created_at DESC, id DESC
            LIMIT :limit
            SQL, [
            'recipient_user_id' => $recipientUserId,
            'limit' => $limit,
        ], ['limit' => ParameterType::INTEGER]);

        return array_map(
            fn(array $row): Notification => new Notification(
                $this->text($row, 'id'),
                $this->text($row, 'recipient_user_id'),
                $this->text($row, 'source_post_id'),
                $this->text($row, 'source_comment_id'),
                $this->text($row, 'delivery_operation_id'),
                $this->dateTime($row, 'created_at'),
            ),
            $rows,
        );
    }

    /** @param array<string, mixed> $row */
    private function text(array $row, string $field): string
    {
        $value = $row[$field] ?? null;
        if (!is_string($value) || $value === '') {
            throw new UnexpectedValueException('Notification query returned an invalid field.');
        }

        return $value;
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
            throw new UnexpectedValueException('Notification query returned an invalid timestamp.');
        }

        return $dateTime;
    }

    private function databaseTime(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }
}
