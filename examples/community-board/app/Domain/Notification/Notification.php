<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use DateTimeImmutable;

final readonly class Notification
{
    public function __construct(
        public string $id,
        public string $recipientUserId,
        public string $sourcePostId,
        public string $sourceCommentId,
        public string $deliveryOperationId,
        public DateTimeImmutable $createdAt,
    ) {}
}
