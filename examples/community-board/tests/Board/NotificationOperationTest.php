<?php

declare(strict_types=1);

namespace App\Tests\Board;

use App\Domain\Board\BoardClock;
use App\Domain\Notification\NotificationService;
use App\Feature\Notification\ListNotifications\ListNotifications;
use App\Feature\Notification\ListNotifications\ListNotificationsValue;
use App\Feature\Notification\NotifyPostOwner\NotifyPostOwner;
use App\Feature\Notification\NotifyPostOwner\NotifyPostOwnerValue;
use App\Tests\Support\InMemoryNotificationRepository;
use App\Tests\Support\SequenceBoardIdGenerator;
use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class NotificationOperationTest extends TestCase
{
    private const string RECIPIENT = '019b1000-0000-7000-8000-000000000001';
    private const string POST = '019b2000-0000-7000-8000-000000000001';
    private const string COMMENT = '019b3000-0000-7000-8000-000000000001';
    private const string DELIVERY = '019b4000-0000-7000-8000-000000000001';

    public function testDeferredChildDeliveryIsIdempotentAndStoresNoBodySnapshot(): void
    {
        $repository = new InMemoryNotificationRepository();
        $service = new NotificationService(
            $repository,
            new SequenceBoardIdGenerator([
                '019b5000-0000-7000-8000-000000000001',
                '019b5000-0000-7000-8000-000000000002',
            ]),
            new FrozenClock(),
        );
        $operation = new NotifyPostOwner($service);
        $value = new NotifyPostOwnerValue(self::RECIPIENT, self::POST, self::COMMENT);

        self::assertTrue($operation->handle($value, $this->context())->created);
        self::assertFalse($operation->handle($value, $this->context())->created);
        self::assertCount(1, $repository->notifications);
        self::assertSame(self::RECIPIENT, array_values($repository->notifications)[0]->recipientUserId);
    }

    public function testListUsesAuthorizationActorAsRecipientAndCannotSwitchActorByRequest(): void
    {
        $repository = new InMemoryNotificationRepository();
        $service = new NotificationService(
            $repository,
            new SequenceBoardIdGenerator([
                '019b5000-0000-7000-8000-000000000011',
                '019b5000-0000-7000-8000-000000000012',
            ]),
            new FrozenClock(),
        );
        $service->notifyPostOwner(self::RECIPIENT, self::POST, self::COMMENT, '019b4000-0000-7000-8000-000000000011');
        $service->notifyPostOwner(
            '019b1000-0000-0000-0000-000000000002',
            self::POST,
            self::COMMENT,
            '019b4000-0000-7000-8000-000000000012',
        );
        $operation = new ListNotifications($service);

        $alice = $operation->handle(new ListNotificationsValue(50), $this->actorContext(self::RECIPIENT));
        self::assertCount(1, $alice->notifications);
        $bob = $operation->handle(
            new ListNotificationsValue(50),
            $this->actorContext('019b1000-0000-0000-0000-000000000002'),
        );
        self::assertCount(1, $bob->notifications);
        self::assertNotSame($alice->notifications[0]->id, $bob->notifications[0]->id);
    }

    private function context(): ExecutionContext
    {
        return new ExecutionContext(
            OperationId::fromString(self::DELIVERY),
            new DateTimeImmutable('2026-07-24T00:00:00Z'),
            CorrelationId::fromString('019b4000-0000-7000-8000-000000000002'),
        );
    }

    private function actorContext(string $userId): ExecutionContext
    {
        $actor = new ActorRef($userId, 'user');

        return new ExecutionContext(
            OperationId::fromString('019b4000-0000-7000-8000-000000000010'),
            new DateTimeImmutable('2026-07-24T00:00:00Z'),
            CorrelationId::fromString('019b4000-0000-7000-8000-000000000013'),
            actorContext: new ActorContext($actor, $actor, $actor),
        );
    }
}

final class FrozenClock implements BoardClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-24T00:00:00Z');
    }
}
