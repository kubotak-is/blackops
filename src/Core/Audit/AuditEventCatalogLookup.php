<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

interface AuditEventCatalogLookup
{
    public function find(
        string $owner,
        string $catalogId,
        int $catalogVersion,
        string $catalogHash,
    ): ?AuditEventCatalog;

    public function resolve(string $catalogHash): ?AuditEventCatalog;
}
