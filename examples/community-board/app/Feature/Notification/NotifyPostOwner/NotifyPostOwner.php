<?php

declare(strict_types=1);

namespace App\Feature\Notification\NotifyPostOwner;

use App\Domain\Notification\NotificationService;
use BlackOps\Core\Attribute\Deferred;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;

#[OperationType('board.notification.notify')]
#[Deferred]
readonly class NotifyPostOwner implements Operation
{
    public function __construct(
        private NotificationService $notifications,
    ) {}

    #[Transactional]
    public function handle(NotifyPostOwnerValue $value, ExecutionContext $context): NotificationDelivered
    {
        return new NotificationDelivered($this->notifications->notifyPostOwner(
            $value->recipientUserId,
            $value->postId,
            $value->commentId,
            $context->operationId()->toString(),
        ));
    }
}
