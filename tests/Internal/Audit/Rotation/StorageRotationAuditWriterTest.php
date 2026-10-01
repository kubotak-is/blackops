<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Rotation;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditRecordActor;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditSourceRuntime;
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
use BlackOps\Internal\Audit\Rotation\StorageRotationAuditWriter;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionProperty;

final class StorageRotationAuditWriterTest extends TestCase
{
    private const string AUDIT_ID = '0123456789abcdef0123456789abcdef';
    private const string SCOPE_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string CURSOR = '019f80ab-4000-7000-8000-000000000011';
    private const string BUILD_ID = 'rotation-writer-test';
    private const string CANARY = 'rotation-writer-private-canary';

    public function testTrustedBindingIsReducedAndGlobalSubjectRemainsNull(): void
    {
        $writer = $this->writer(new RotationWriterClock());
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
    }

    public function testConnectionIdentityRejectsBeforeClockUse(): void
    {
        $clock = new RotationWriterClock();
        $connection = $this->createStub(Connection::class);
        $writer = $this->writer($clock, $connection);

        try {
            $writer->assertConnection($this->createStub(Connection::class));
            self::fail('a different connection was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        self::assertSame(0, $clock->calls);
    }

    public function testMalformedNativeIdentitiesAndTenantFailBeforeClockUse(): void
    {
        $cases = [
            static function (StorageRotationAuditWriter $writer): void {
                $writer->started('bad', self::SCOPE_HASH, null);
            },
            static function (StorageRotationAuditWriter $writer): void {
                $writer->progressed(self::AUDIT_ID, self::SCOPE_HASH, 'bad-cursor', null);
            },
            static function (StorageRotationAuditWriter $writer): void {
                $writer->failed(self::AUDIT_ID, self::SCOPE_HASH, new TenantRef(str_repeat('a', 257), 'tenant-1'));
            },
        ];
        foreach ($cases as $case) {
            $clock = new RotationWriterClock();
            $writer = $this->writer($clock);
            try {
                $case($writer);
                self::fail('invalid native projection input was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
                self::assertNull($failure->getPrevious());
            }
            self::assertSame(0, $clock->calls);
        }
    }

    public function testValidPhaseReadsOneClockBeforeCurrentTransactionDelivery(): void
    {
        $clock = new RotationWriterClock();
        $connection = $this->createStub(Connection::class);
        $connection->method('isAutoCommit')->willReturn(true);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->method('getTransactionNestingLevel')->willReturn(1);
        $writer = $this->writer($clock, $connection);

        try {
            $writer->progressed(self::AUDIT_ID, self::SCOPE_HASH, self::CURSOR, null);
            self::fail('an uninitialized delivery binding was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        self::assertSame(1, $clock->calls);
    }

    public function testClockFailureIsFreshAndClassifiedBeforeDelivery(): void
    {
        $clock = new RotationWriterClock(failure: new \RuntimeException(self::CANARY));
        $writer = $this->writer($clock);

        try {
            $writer->started(self::AUDIT_ID, self::SCOPE_HASH, null);
            self::fail('a clock failure was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $failure->failure());
            self::assertSame('Audit delivery failed.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString(self::CANARY, $failure->getTraceAsString());
        }
        self::assertSame(1, $clock->calls);
    }

    public function testKeyFailureIsFreshAndClassifiedAfterOneClockRead(): void
    {
        $clock = new RotationWriterClock();
        $writer = $this->writer($clock);
        $snapshot = $this->snapshot($writer);
        $snapshot['opaqueIds'] = new \ReflectionClass(HmacAuditOpaqueIdDeriver::class)->newInstanceWithoutConstructor();
        new ReflectionProperty($writer, 'snapshot')->setValue($writer, new \SensitiveParameterValue($snapshot));

        try {
            $writer->started(self::AUDIT_ID, self::SCOPE_HASH, null);
            self::fail('a key failure was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::KeyUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        self::assertSame(1, $clock->calls);
    }

    public function testMalformedSnapshotAndObjectSurfacesFailClosed(): void
    {
        $clock = new RotationWriterClock(self::CANARY);
        $writer = $this->writer($clock, canary: true);
        self::assertSame([], $writer->__debugInfo());
        self::assertSame([], $writer->__serialize());
        $surface = print_r($writer, true) . var_export($writer, true) . serialize($writer);
        self::assertStringNotContainsString(self::CANARY, $surface);

        $property = new ReflectionProperty($writer, 'snapshot');
        $property->setValue($writer, new \SensitiveParameterValue(['buildId' => self::CANARY]));
        try {
            $writer->started(self::AUDIT_ID, self::SCOPE_HASH, null);
            self::fail('a malformed writer snapshot was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString(self::CANARY, $failure->getTraceAsString());
        }
        self::assertSame(0, $clock->calls);

        $writer = $this->writer(new RotationWriterClock(self::CANARY), canary: true);
        try {
            $clone = clone $writer;
            self::fail('writer cloning must fail closed');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
    }

    private function writer(
        RotationWriterClock $clock,
        ?Connection $connection = null,
        bool $canary = false,
    ): StorageRotationAuditWriter {
        $connection ??= $this->createStub(Connection::class);
        $actor = new ActorContext(
            $canary ? new ActorRef('origin-1', self::CANARY) : new ActorRef('origin-1', 'user'),
            $canary ? new ActorRef('principal-1', self::CANARY) : new ActorRef('principal-1', 'user'),
            $canary ? new ActorRef('execution-1', self::CANARY) : new ActorRef('execution-1', 'worker'),
        );

        return new StorageRotationAuditWriter(
            new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
            $this->selector(),
            $this->coordinator($connection),
            $clock,
            $actor,
            AuditRecordCorrelation::fromArray($canary ? ['operationId' => self::CANARY] : []),
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
        $records = new \ReflectionClass(\BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore::class)->newInstanceWithoutConstructor();
        $outbox = new \ReflectionClass(PostgreSqlAuditOutboxStore::class)->newInstanceWithoutConstructor();

        return new AuditDeliveryCoordinator($connection, $this->selector(), $records, $outbox);
    }

    /** @return array<string,mixed> */
    private function snapshot(StorageRotationAuditWriter $writer): array
    {
        $value = new ReflectionProperty($writer, 'snapshot')
            ->getValue($writer)
            ->getValue();
        self::assertIsArray($value);

        return $value;
    }
}

final class RotationWriterClock implements ClockInterface
{
    public int $calls = 0;

    public function __construct(
        public readonly string $canary = '',
        public readonly ?\Throwable $failure = null,
    ) {}

    public function now(): DateTimeImmutable
    {
        ++$this->calls;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new DateTimeImmutable('2026-09-21T12:34:56.123456+09:00');
    }
}
