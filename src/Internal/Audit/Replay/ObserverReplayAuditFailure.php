<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Replay;

use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;

final class ObserverReplayAuditFailure
{
    private function __construct() {}

    public static function fresh(AuditDeliveryFailure $failure): AuditDeliveryException
    {
        return new AuditDeliveryException($failure);
    }

    public static function remap(#[\SensitiveParameter] AuditDeliveryException $failure): AuditDeliveryException
    {
        try {
            return self::fresh($failure->failure());
        } catch (\Throwable) {
            return self::fresh(AuditDeliveryFailure::StorageUnavailable);
        }
    }
}
