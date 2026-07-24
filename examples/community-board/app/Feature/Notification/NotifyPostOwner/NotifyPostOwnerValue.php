<?php

declare(strict_types=1);

namespace App\Feature\Notification\NotifyPostOwner;

use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\NotBlank;

final readonly class NotifyPostOwnerValue implements OperationValue
{
    public function __construct(
        #[NotBlank]
        public string $recipientUserId,
        #[NotBlank]
        public string $postId,
        #[NotBlank]
        public string $commentId,
    ) {}
}
