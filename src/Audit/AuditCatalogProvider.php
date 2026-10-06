<?php

declare(strict_types=1);

namespace BlackOps\Audit;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface AuditCatalogProvider
{
    /** @return list<AuditCatalogDefinition> */
    public function catalogs(): array;
}
