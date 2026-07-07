<?php

declare(strict_types=1);

namespace BlackOps\Tests\Journal;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Journal\CanonicalJournalReader;
use BlackOps\Journal\CanonicalJournalStore;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Journal\Exception\JournalWriteFailed;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class JournalPortTest extends TestCase
{
    public function testPublicPortShapes(): void
    {
        foreach ([
            CanonicalJournalWriter::class,
            CanonicalJournalReader::class,
            CanonicalJournalStore::class,
            JournalWriteFailed::class,
        ] as $type) {
            self::assertCount(1, new ReflectionClass($type)->getAttributes(PublicApi::class));
        }

        $store = new ReflectionClass(CanonicalJournalStore::class);
        self::assertTrue($store->implementsInterface(CanonicalJournalWriter::class));
        self::assertTrue($store->implementsInterface(CanonicalJournalReader::class));
    }
}
