<?php

declare(strict_types=1);

namespace BlackOps\Tests\Outcome;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Outcome;
use BlackOps\Outcome\Exception\OutcomeStoreException;
use BlackOps\Outcome\OutcomeReader;
use BlackOps\Outcome\OutcomeRecord;
use BlackOps\Outcome\OutcomeStore;
use BlackOps\Outcome\OutcomeWriter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OutcomeRecordTest extends TestCase
{
    public function testPublicOutcomeContractsAreMarkedAndStoreCombinesPorts(): void
    {
        foreach ([
            OutcomeRecord::class,
            OutcomeReader::class,
            OutcomeWriter::class,
            OutcomeStore::class,
            OutcomeStoreException::class,
        ] as $type) {
            self::assertCount(1, new ReflectionClass($type)->getAttributes(PublicApi::class));
        }

        self::assertContains(OutcomeReader::class, class_implements(OutcomeStore::class));
        self::assertContains(OutcomeWriter::class, class_implements(OutcomeStore::class));
    }

    public function testRecordReturnsTypedOutcomeAndUtcCompletionTime(): void
    {
        $id = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687901');
        $outcome = new PublicStoredOutcome('done');
        $record = new OutcomeRecord($id, $outcome, new DateTimeImmutable('2026-07-12T09:30:00+09:00'));

        self::assertSame($id, $record->operationId());
        self::assertSame($outcome, $record->outcome());
        self::assertSame('2026-07-12T00:30:00.000000+00:00', $record->completedAt()->format('Y-m-d\TH:i:s.uP'));
    }
}

final readonly class PublicStoredOutcome implements Outcome
{
    public function __construct(
        public string $message,
    ) {}
}
