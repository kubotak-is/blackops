<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Retention;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditRecordActor;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionHoldId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Retention\RetentionHoldAuditWriter;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionClass;
use ReflectionProperty;

final class RetentionHoldAuditWriterTest extends TestCase
{
    private const string HOLD_ID = '019f80ab-4000-7000-8000-000000000011';
    private const string OPERATION_ID = '019f80ab-4000-7000-8000-000000000012';
    private const string BUILD_ID = 'retention-writer-test';
    private const string CANARY = 'retention-writer-private-canary';

    public function testTrustedBindingIsReducedToBoundedActorAndCorrelationValues(): void
    {
        $writer = $this->writer(new RetentionWriterClock());
        $snapshot = $this->snapshot($writer);

        self::assertInstanceOf(AuditRecordActor::class, $snapshot['actor']);
        self::assertSame(
            [
                'principal' => ['type' => 'user', 'id' => 'principal-1'],
                'execution' => ['type' => 'worker', 'id' => 'execution-1'],
            ],
            $snapshot['actor']->toArray(),
        );
        self::assertSame([], $snapshot['correlation']->toArray());
        self::assertSame(AuditSourceRuntime::Worker, $snapshot['runtime']);
        self::assertSame(self::BUILD_ID, $snapshot['buildId']);
        self::assertArrayNotHasKey('actorContext', $snapshot);
        self::assertArrayNotHasKey('payload', $snapshot);
        self::assertArrayNotHasKey('hold', $snapshot);
    }

    public function testWrongConnectionFailsBeforeClockOrDeliveryWork(): void
    {
        $clock = new RetentionWriterClock();
        $writer = $this->writer($clock);

        try {
            $writer->assertConnection($this->createStub(Connection::class));
            self::fail('a different connection was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        self::assertSame(0, $clock->calls);
    }

    public function testPlacedCapturesOneClockValueAndUsesCurrentTransactionDelivery(): void
    {
        $clock = new RetentionWriterClock();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(1);
        $writer = $this->writer($clock, $connection);

        try {
            $writer->placed(
                RetentionHoldId::fromString(self::HOLD_ID),
                OperationId::fromString(self::OPERATION_ID),
                new TenantRef('account', 'tenant-1'),
            );
            self::fail('uninitialized delivery stores must fail closed');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        self::assertSame(1, $clock->calls);
    }

    public function testMalformedSnapshotFailsClosedBeforeClockUse(): void
    {
        $clock = new RetentionWriterClock();
        $writer = $this->writer($clock);
        $snapshot = new \SensitiveParameterValue(['buildId' => self::CANARY]);
        $property = new ReflectionProperty($writer, 'snapshot');
        $property->setValue($writer, $snapshot);

        try {
            $writer->released(
                RetentionHoldId::fromString(self::HOLD_ID),
                OperationId::fromString(self::OPERATION_ID),
                null,
            );
            self::fail('a malformed binding snapshot was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        self::assertSame(0, $clock->calls);
    }

    public function testDebugSerializationAndUnserializationDoNotExposeBindingData(): void
    {
        $writer = $this->writer(new RetentionWriterClock(self::CANARY), canary: true);
        $rawSnapshot = var_export($this->snapshot($writer), true);
        self::assertStringContainsString(self::CANARY, $rawSnapshot);

        self::assertSame([], $writer->__debugInfo());
        self::assertSame([], $writer->__serialize());
        $surface = print_r($writer, true) . var_export($writer, true) . serialize($writer);
        self::assertStringNotContainsString(self::CANARY, $surface);

        try {
            unserialize(serialize($writer), ['allowed_classes' => true]);
            self::fail('unserialization must fail closed');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString(self::CANARY, $failure->getTraceAsString());
            self::assertStringNotContainsString(self::CANARY, var_export($failure->getTrace(), true));
        }
    }

    public function testMalformedTypedIdentifiersFailBeforeClockUse(): void
    {
        foreach ([RetentionHoldId::class => 'holdId', OperationId::class => 'operationId'] as $type => $name) {
            $invalid = new ReflectionClass($type)->newInstanceWithoutConstructor();
            $value = new ReflectionProperty($invalid, 'value');
            $value->setValue($invalid, str_repeat('x', 36));
            $clock = new RetentionWriterClock();
            $writer = $this->writer($clock);

            try {
                $writer->placed(
                    $name === 'holdId' ? $invalid : RetentionHoldId::fromString(self::HOLD_ID),
                    $name === 'operationId' ? $invalid : OperationId::fromString(self::OPERATION_ID),
                    null,
                );
                self::fail('a malformed typed identifier was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
                self::assertNull($failure->getPrevious());
            }
            self::assertSame(0, $clock->calls);
        }
    }

    public function testInvalidTenantReferenceFailsBeforeClockUse(): void
    {
        $clock = new RetentionWriterClock();
        $writer = $this->writer($clock);

        try {
            $writer->placed(
                RetentionHoldId::fromString(self::HOLD_ID),
                OperationId::fromString(self::OPERATION_ID),
                new TenantRef(str_repeat('a', 257), 'tenant-1'),
            );
            self::fail('an invalid tenant reference was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        self::assertSame(0, $clock->calls);
    }

    public function testInvalidBuildIdFailsAtConstruction(): void
    {
        try {
            new RetentionHoldAuditWriter(
                new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
                $this->selector(),
                $this->coordinator($this->createStub(Connection::class)),
                new RetentionWriterClock(),
                new ActorContext(null, null, new ActorRef('execution-1', 'worker')),
                AuditRecordCorrelation::fromArray([]),
                AuditSourceRuntime::Worker,
                "bad\u{0000}build",
            );
            self::fail('an unsafe build identifier was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
    }

    private function writer(
        RetentionWriterClock $clock,
        ?Connection $connection = null,
        bool $canary = false,
    ): RetentionHoldAuditWriter {
        $connection ??= $this->createStub(Connection::class);
        $actor = new ActorContext(
            $canary ? new ActorRef('origin-1', self::CANARY) : new ActorRef('origin-1', 'user'),
            $canary ? new ActorRef('principal-1', self::CANARY) : new ActorRef('principal-1', 'user'),
            $canary ? new ActorRef('execution-1', self::CANARY) : new ActorRef('execution-1', 'worker'),
        );
        $correlation = AuditRecordCorrelation::fromArray($canary ? ['operationId' => self::CANARY] : []);

        return new RetentionHoldAuditWriter(
            new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
            $this->selector(),
            $this->coordinator($connection),
            $clock,
            $actor,
            $correlation,
            AuditSourceRuntime::Worker,
            $canary ? self::CANARY : self::BUILD_ID,
        );
    }

    private function selector(): AuditDeliverySelector
    {
        $catalog = FrameworkOperationalAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);

        return new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
    }

    private function coordinator(Connection $connection): AuditDeliveryCoordinator
    {
        $records = new ReflectionClass(PostgreSqlAuditRecordStore::class)->newInstanceWithoutConstructor();
        $outbox = new ReflectionClass(PostgreSqlAuditOutboxStore::class)->newInstanceWithoutConstructor();

        return new AuditDeliveryCoordinator($connection, $this->selector(), $records, $outbox);
    }

    /** @return array<string,mixed> */
    private function snapshot(RetentionHoldAuditWriter $writer): array
    {
        $property = new ReflectionProperty($writer, 'snapshot');
        $value = $property->getValue($writer)->getValue();
        self::assertIsArray($value);

        return $value;
    }
}

final class RetentionWriterClock implements ClockInterface
{
    public int $calls = 0;

    public function __construct(
        public readonly string $canary = '',
    ) {}

    public function now(): DateTimeImmutable
    {
        ++$this->calls;

        return new DateTimeImmutable('2026-09-21T12:34:56.123456+09:00');
    }
}
