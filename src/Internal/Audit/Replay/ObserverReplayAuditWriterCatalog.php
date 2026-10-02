<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Replay;

use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;

final class ObserverReplayAuditWriterCatalog
{
    private const string CATALOG_HASH = '9b04a5af68feb9091903282a5d99309bcd7a52cd0cbe2eb9e2901bfacbea083b';
    private const string CATALOG_ID = 'blackops.framework.operations';

    private function __construct() {}

    public static function catalog(): AuditEventCatalog
    {
        try {
            $catalog = FrameworkOperationalAuditEventCatalog::catalog();
            if (
                !$catalog instanceof AuditEventCatalog
                || $catalog->catalogId !== self::CATALOG_ID
                || $catalog->catalogVersion !== 1
                || $catalog->catalogHash !== self::CATALOG_HASH
                || $catalog->owner !== AuditSourceOwner::Framework
            ) {
                throw new \LogicException();
            }

            return $catalog;
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }
}
