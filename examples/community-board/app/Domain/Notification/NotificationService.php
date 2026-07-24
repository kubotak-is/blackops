<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use App\Domain\Board\BoardClock;
use App\Domain\Board\BoardIdGenerator;
use DateTimeImmutable;

final readonly class NotificationService
{
    public function __construct(
        private NotificationRepository $notifications,
        private BoardIdGenerator $identifiers,
        private BoardClock $clock,
    ) {}

    public function notifyPostOwner(
        string $recipientUserId,
        string $sourcePostId,
        string $sourceCommentId,
        string $deliveryOperationId,
        ?DateTimeImmutable $createdAt = null,
    ): bool {
        return $this->notifications->saveIfAbsent(
            new Notification(
                $this->identifiers->generate(),
                $recipientUserId,
                $sourcePostId,
                $sourceCommentId,
                $deliveryOperationId,
                $createdAt ?? $this->clock->now(),
            ),
        );
    }

    /** @return list<Notification> */
    public function listForRecipient(string $recipientUserId, int $limit = 50): array
    {
        return $this->notifications->listForRecipient($recipientUserId, min(max($limit, 1), 50));
    }
}
