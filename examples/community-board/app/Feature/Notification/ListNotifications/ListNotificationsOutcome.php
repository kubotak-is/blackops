<?php

declare(strict_types=1);

namespace App\Feature\Notification\ListNotifications;

use BlackOps\Core\Attribute\ListOf;
use BlackOps\Core\Outcome;

final readonly class ListNotificationsOutcome implements Outcome
{
    /** @param list<NotificationItem> $notifications */
    public function __construct(
        #[ListOf(NotificationItem::class)]
        public array $notifications,
    ) {}
}
