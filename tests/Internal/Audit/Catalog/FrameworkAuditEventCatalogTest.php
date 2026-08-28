<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Catalog;

use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use PHPUnit\Framework\TestCase;

final class FrameworkAuditEventCatalogTest extends TestCase
{
    public function testExactEventOrderAndEvidenceRule(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $types = array_map(static fn($event): string => $event->type, $catalog->events);
        self::assertSame($types, array_values($types));
        foreach ($catalog->events as $event) {
            self::assertSame([$event->type], $event->actionTypes);
            self::assertSame([], $event->evidenceFields);
            self::assertSame(13, $event->maxEvidenceBytes);
        }
        self::assertSame(13_878, strlen(new AuditJcsCodec()->canonicalJson($catalog->unsignedArray())));
    }
}
