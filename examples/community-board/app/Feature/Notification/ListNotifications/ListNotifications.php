<?php

declare(strict_types=1);

namespace App\Feature\Notification\ListNotifications;

use App\Domain\Notification\NotificationService;
use App\Security\AuthenticatedUser;
use App\Security\AuthenticatedUserPolicy;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'GET', path: '/notifications')]
#[OperationType('board.notification.list')]
#[Authorize(AuthenticatedUserPolicy::class)]
final readonly class ListNotifications implements Operation
{
    public function __construct(
        private NotificationService $notifications,
    ) {}

    public function handle(ListNotificationsValue $value, ExecutionContext $context): ListNotificationsOutcome
    {
        $items = array_map(
            NotificationItem::fromDomain(...),
            $this->notifications->listForRecipient(AuthenticatedUser::id($context), $value->limit),
        );

        return new ListNotificationsOutcome($items);
    }
}
