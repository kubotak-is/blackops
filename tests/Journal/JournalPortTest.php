<?php

declare(strict_types=1);

namespace BlackOps\Tests\Journal;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Journal\CanonicalJournalReader;
use BlackOps\Journal\CanonicalJournalStore;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Journal\Exception\JournalObservationFailed;
use BlackOps\Journal\Exception\JournalWriteFailed;
use BlackOps\Journal\FlushableJournalObserver;
use BlackOps\Journal\JournalObserver;
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
            JournalObserver::class,
            FlushableJournalObserver::class,
            JournalObservationFailed::class,
            JournalWriteFailed::class,
        ] as $type) {
            self::assertCount(1, new ReflectionClass($type)->getAttributes(PublicApi::class));
        }

        $store = new ReflectionClass(CanonicalJournalStore::class);
        self::assertTrue($store->implementsInterface(CanonicalJournalWriter::class));
        self::assertTrue($store->implementsInterface(CanonicalJournalReader::class));

        $flushable = new ReflectionClass(FlushableJournalObserver::class);
        self::assertTrue($flushable->implementsInterface(JournalObserver::class));
    }
}
