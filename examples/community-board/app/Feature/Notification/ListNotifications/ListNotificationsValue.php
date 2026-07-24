<?php

declare(strict_types=1);

namespace App\Feature\Notification\ListNotifications;

use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\Range;
use BlackOps\Http\Attribute\FromQuery;

final readonly class ListNotificationsValue implements OperationValue
{
    public function __construct(
        #[FromQuery]
        #[Range(min: 1, max: 50)]
        public int $limit = 50,
    ) {}
}
