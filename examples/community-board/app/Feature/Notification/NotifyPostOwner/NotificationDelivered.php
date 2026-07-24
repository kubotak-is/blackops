<?php

declare(strict_types=1);

namespace App\Feature\Notification\NotifyPostOwner;

use BlackOps\Core\Outcome;

final readonly class NotificationDelivered implements Outcome
{
    public function __construct(
        public bool $created,
    ) {}
}
