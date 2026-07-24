<?php

declare(strict_types=1);

namespace App\Domain\Notification;

interface NotificationRepository
{
    public function saveIfAbsent(Notification $notification): bool;

    /** @return list<Notification> */
    public function listForRecipient(string $recipientUserId, int $limit = 50): array;
}
