<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Notification\Notification;
use App\Domain\Notification\NotificationRepository;

final class InMemoryNotificationRepository implements NotificationRepository
{
    /** @var array<string, Notification> */
    public array $notifications = [];

    public function saveIfAbsent(Notification $notification): bool
    {
        if (isset($this->notifications[$notification->deliveryOperationId])) {
            return false;
        }

        $this->notifications[$notification->deliveryOperationId] = $notification;

        return true;
    }

    public function listForRecipient(string $recipientUserId, int $limit = 50): array
    {
        $items = array_values(array_filter(
            $this->notifications,
            static fn(Notification $notification): bool => $notification->recipientUserId === $recipientUserId,
        ));
        usort($items, static fn(Notification $a, Notification $b): int => $b->createdAt <=> $a->createdAt);

        return array_slice($items, 0, $limit);
    }
}
