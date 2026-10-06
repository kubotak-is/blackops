<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum AuditDelivery: string
{
    case Required = 'required';
    case Durable = 'durable';
}
