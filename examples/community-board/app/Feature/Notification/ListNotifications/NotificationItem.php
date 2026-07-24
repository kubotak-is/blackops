<?php

declare(strict_types=1);

namespace App\Feature\Notification\ListNotifications;

use App\Domain\Notification\Notification;
use App\Feature\BoardTime;
use BlackOps\Core\OutcomeData;

final readonly class NotificationItem implements OutcomeData
{
    public function __construct(
        public string $id,
        public string $sourcePostId,
        public string $sourceCommentId,
        public string $message,
        public string $createdAt,
    ) {}

    public static function fromDomain(Notification $notification): self
    {
        return new self(
            $notification->id,
            $notification->sourcePostId,
            $notification->sourceCommentId,
            'Someone commented on your post.',
            BoardTime::http($notification->createdAt),
        );
    }
}
