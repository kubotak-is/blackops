<?php

declare(strict_types=1);

namespace BlackOps\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface RetentionPurgeAuditPort
{
    public function record(RetentionPurgeAuditRecord $record): void;
}
