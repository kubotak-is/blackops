<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Identifier;

use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CausationId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\OutboxRecordId;
use BlackOps\Core\Identifier\RetentionHoldId;
use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\SymfonyUuidv7Generator;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class IdentifierFactoryTest extends TestCase
{
    public function testFactoryProducesValidUuidV7ForAllIdentifierTypes(): void
    {
        $factory = new IdentifierFactory(
            new SymfonyUuidv7Generator(),
            $this->fixedClock('2026-07-02T12:34:56.123456Z'),
        );

        $operation = $factory->newOperationId();
        $attempt = $factory->newAttemptId();
        $journal = $factory->newJournalRecordId();
        $correlation = $factory->newCorrelationId();
        $causation = $factory->newCausationId();
        $retentionHold = $factory->newRetentionHoldId();
        $retentionPurgeAudit = $factory->newRetentionPurgeAuditId();
        $outboxRecord = $factory->newOutboxRecordId();

        self::assertInstanceOf(OperationId::class, $operation);
        self::assertInstanceOf(AttemptId::class, $attempt);
        self::assertInstanceOf(JournalRecordId::class, $journal);
        self::assertInstanceOf(CorrelationId::class, $correlation);
        self::assertInstanceOf(CausationId::class, $causation);
        self::assertInstanceOf(RetentionHoldId::class, $retentionHold);
        self::assertInstanceOf(RetentionPurgeAuditId::class, $retentionPurgeAudit);
        self::assertInstanceOf(OutboxRecordId::class, $outboxRecord);

        foreach ([
            $operation,
            $attempt,
            $journal,
            $correlation,
            $causation,
            $retentionHold,
            $retentionPurgeAudit,
            $outboxRecord,
        ] as $id) {
            $value = $id->toString();
            self::assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $value,
                $id::class . ' must produce a lowercase UUID version 7 string.',
            );
            self::assertSame($value, (string) $id);
        }
    }

    public function testGeneratedIdentifierRoundTripsViaFromString(): void
    {
        $factory = new IdentifierFactory(
            new SymfonyUuidv7Generator(),
            $this->fixedClock('2026-07-02T12:34:56.123456Z'),
        );

        $generated = $factory->newOperationId();
        $restored = OperationId::fromString($generated->toString());

        self::assertTrue($generated->equals($restored));
    }

    public function testFactoryUsesInjectedClockForTimestamp(): void
    {
        $fixedTime = '2026-07-02T12:34:56.123456Z';
        $clock = $this->fixedClock($fixedTime);
        $factory = new IdentifierFactory(new SymfonyUuidv7Generator(), $clock);

        $id = $factory->newOperationId();

        $embedded = $this->extractUnixMillisFromUuidV7($id->toString());
        $expected = new DateTimeImmutable($fixedTime, new DateTimeZone('UTC'))->getTimestamp();
        self::assertEqualsWithDelta(
            $expected * 1000,
            $embedded,
            1000,
            'Generated UUIDv7 timestamp must derive from the injected clock.',
        );
    }

    public function testFactoryAcceptsCustomUuidv7Generator(): void
    {
        $fixed = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';
        $generator = new readonly class($fixed) implements Uuidv7Generator {
            public function __construct(
                private readonly string $value,
            ) {}

            public function generate(DateTimeImmutable $time): string
            {
                return $this->value;
            }
        };

        $factory = new IdentifierFactory($generator, $this->fixedClock('now'));

        self::assertSame($fixed, $factory->newOperationId()->toString());
        self::assertSame($fixed, $factory->newAttemptId()->toString());
        self::assertSame($fixed, $factory->newCausationId()->toString());
        self::assertSame($fixed, $factory->newRetentionHoldId()->toString());
        self::assertSame($fixed, $factory->newRetentionPurgeAuditId()->toString());
        self::assertSame($fixed, $factory->newOutboxRecordId()->toString());
    }

    private function fixedClock(string $time): ClockInterface
    {
        $now = new DateTimeImmutable($time, new DateTimeZone('UTC'));

        return new readonly class($now) implements ClockInterface {
            public function __construct(
                private readonly DateTimeImmutable $now,
            ) {}

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };
    }

    private function extractUnixMillisFromUuidV7(string $uuid): int
    {
        // UUIDv7先頭48bitがUnix timestamp (ms)。
        $hex = substr(str_replace('-', '', $uuid), 0, 12);

        return (int) hexdec($hex);
    }
}
