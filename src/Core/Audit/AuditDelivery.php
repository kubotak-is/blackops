<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

enum AuditDelivery: string
{
    case Required = 'required';
    case Durable = 'durable';
}
