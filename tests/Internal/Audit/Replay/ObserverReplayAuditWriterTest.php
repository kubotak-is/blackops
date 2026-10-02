<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Replay;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditRecordActor;
use BlackOps\Core\Audit\AuditRecordCorrelation;
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
use BlackOps\Internal\Audit\Replay\ObserverReplayAuditWriter;
use BlackOps\Internal\Audit\Replay\ObserverReplayAuditWriterBinding;
use BlackOps\Internal\Audit\Replay\ObserverReplayAuditWriterEmission;
use BlackOps\Internal\Audit\Replay\ObserverReplayAuditWriterPolicy;
use BlackOps\Internal\Audit\Replay\ObserverReplayAuditWriterRecord;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionProperty;

final class ObserverReplayAuditWriterTest extends TestCase
{
    private const string AUDIT_ID = '0123456789abcdef0123456789abcdef';
    private const string RECORD_ID = '019f80ab-4000-7000-8000-000000000011';
    private const string CHECKPOINT = 'observer-replay-checkpoint';
    private const string BUILD_ID = 'observer-replay-writer-test';
    private const string CANARY = 'observer-replay-writer-private-canary';

    public function testTrustedBindingKeepsOnlyDetachedActorCorrelationAndBuild(): void
    {
        $writer = $this->writer(new ObserverReplayWriterClock());
        $value = $this->snapshot($writer);

        self::assertInstanceOf(AuditRecordActor::class, $value['actor']);
        self::assertSame(
            [
                'principal' => ['type' => 'user', 'id' => 'principal-1'],
                'execution' => ['type' => 'worker', 'id' => 'execution-1'],
            ],
            $value['actor']->toArray(),
        );
        self::assertSame([], $value['correlation']->toArray());
        self::assertSame(self::BUILD_ID, $value['buildId']);
        self::assertArrayNotHasKey('origin', $value);
        self::assertArrayNotHasKey('actorContext', $value);
        self::assertArrayNotHasKey('payload', $value);
        self::assertArrayNotHasKey('runtime', $value);
    }

    public function testConnectionIdentityRejectsBeforeClockUse(): void
    {
        $clock = new ObserverReplayWriterClock();
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

    public function testScopeAndProgressSubjectsUseStrictHistoricalShapes(): void
    {
        $cases = [
            static function (ObserverReplayAuditWriter $writer): void {
                $writer->started('Bad.Checkpoint', self::AUDIT_ID);
            },
            static function (ObserverReplayAuditWriter $writer): void {
                $writer->progressed(self::AUDIT_ID, strtoupper(self::RECORD_ID), null);
            },
            static function (ObserverReplayAuditWriter $writer): void {
                $writer->progressed(self::AUDIT_ID, '019f80ab-4000-4000-8000-000000000011', null);
            },
            static function (ObserverReplayAuditWriter $writer): void {
                $writer->started(self::CHECKPOINT, strtoupper(self::AUDIT_ID));
            },
            static function (ObserverReplayAuditWriter $writer): void {
                $writer->progressed(self::AUDIT_ID, self::RECORD_ID, new TenantRef('account', str_repeat('a', 257)));
            },
        ];

        foreach ($cases as $case) {
            $clock = new ObserverReplayWriterClock();
            $writer = $this->writer($clock);
            try {
                $case($writer);
                self::fail('invalid replay identity was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
                self::assertNull($failure->getPrevious());
            }
            self::assertSame(0, $clock->calls);
        }
    }

    public function testValidProgressReadsOneCurrentClockBeforeC3Delivery(): void
    {
        $clock = new ObserverReplayWriterClock();
        $connection = $this->createStub(Connection::class);
        $connection->method('isAutoCommit')->willReturn(true);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->method('getTransactionNestingLevel')->willReturn(1);
        $writer = $this->writer($clock, $connection);

        try {
            $writer->progressed(self::AUDIT_ID, self::RECORD_ID, new TenantRef('account', 'tenant-1'));
            self::fail('an uninitialized delivery binding was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        self::assertSame(1, $clock->calls);
    }

    public function testClockFailureIsFreshAndValueFree(): void
    {
        $clock = new ObserverReplayWriterClock(failure: new \RuntimeException(self::CANARY));
        $writer = $this->writer($clock);

        try {
            $writer->started(self::CHECKPOINT, self::AUDIT_ID);
            self::fail('a clock failure was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $failure->failure());
            self::assertSame('Audit delivery failed.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString(self::CANARY, $failure->getTraceAsString());
        }
        self::assertSame(1, $clock->calls);
    }

    public function testCorruptSnapshotAndObjectSurfacesFailClosed(): void
    {
        $clock = new ObserverReplayWriterClock(self::CANARY);
        $writer = $this->writer($clock);
        self::assertSame([], $writer->__debugInfo());
        self::assertSame([], $writer->__serialize());
        self::assertStringNotContainsString(self::CANARY, print_r($writer, true) . var_export($writer, true));

        new ReflectionProperty($writer, 'snapshot')->setValue($writer, new \SensitiveParameterValue([
            'buildId' => self::CANARY,
        ]));
        try {
            $writer->started(self::CHECKPOINT, self::AUDIT_ID);
            self::fail('a malformed writer snapshot was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString(self::CANARY, $failure->getTraceAsString());
        }
        self::assertSame(0, $clock->calls);

        try {
            clone $writer;
            self::fail('writer cloning must fail closed');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
    }

    public function testOwnedWriterFramesRedactSensitivePhaseArguments(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            $writer = $this->writer(new ObserverReplayWriterClock());
            try {
                $writer->started(self::CANARY . '!', self::AUDIT_ID);
                self::fail('a sensitive checkpoint was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
                self::assertNull($failure->getPrevious());
                $frames = array_values(array_filter(
                    $failure->getTrace(),
                    static fn(array $frame): bool => (
                        ($frame['class'] ?? null) === ObserverReplayAuditWriter::class
                        && ($frame['function'] ?? null) === 'started'
                    ),
                ));
                $this->assertSensitiveFrame($frames, 2);
                $emissionFrames = array_values(array_filter(
                    $failure->getTrace(),
                    static fn(array $frame): bool => (
                        ($frame['class'] ?? null) === ObserverReplayAuditWriterEmission::class
                        && ($frame['function'] ?? null) === 'write'
                    ),
                ));
                $this->assertSensitiveFrame($emissionFrames, 5);
                self::assertStringNotContainsString(self::CANARY, print_r([
                    'writer' => $frames,
                    'emission' => $emissionFrames,
                ], true));
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testOwnedWriterHelpersExposeOnlySensitiveFailureFrames(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            $cases = [
                'capture' => function (): void {
                    $this->writer(new ObserverReplayWriterClock(), buildId: '');
                },
                'values' => static function (): void {
                    ObserverReplayAuditWriterBinding::values(new \SensitiveParameterValue(['buildId' => self::CANARY]));
                },
                'subject' => static function (): void {
                    ObserverReplayAuditWriterPolicy::assertSubject('replay.observer.progressed', self::CANARY);
                },
                'occurredAt' => static function (): void {
                    ObserverReplayAuditWriterRecord::occurredAt([]);
                },
                'opaqueId' => static function (): void {
                    ObserverReplayAuditWriterRecord::opaqueId(
                        [],
                        \BlackOps\Core\Audit\AuditOpaqueIdPurpose::Action,
                        self::CANARY,
                    );
                },
                'selector' => static function (): void {
                    ObserverReplayAuditWriterRecord::selector([]);
                },
            ];
            foreach ($cases as $method => $case) {
                $failure = null;
                try {
                    $case();
                } catch (\Throwable $exception) {
                    $failure = $exception;
                }
                self::assertInstanceOf(\Throwable::class, $failure);
                $helper = match ($method) {
                    'capture' => ObserverReplayAuditWriterBinding::class,
                    'values' => ObserverReplayAuditWriterBinding::class,
                    'subject' => ObserverReplayAuditWriterPolicy::class,
                    default => ObserverReplayAuditWriterRecord::class,
                };
                $helperMethod = $method === 'subject' ? 'assertSubject' : $method;
                $argumentCount = match ($method) {
                    'opaqueId' => 3,
                    'subject' => 2,
                    default => 1,
                };
                $this->assertSensitiveFrame(
                    array_values(array_filter(
                        $failure->getTrace(),
                        static fn(array $frame): bool => (
                            ($frame['class'] ?? null) === $helper
                            && ($frame['function'] ?? null) === $helperMethod
                        ),
                    )),
                    $argumentCount,
                );
                self::assertStringNotContainsString(self::CANARY, print_r($failure->getTrace(), true));
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    /** @param list<array<string,mixed>> $frames */
    private function assertSensitiveFrame(array $frames, int $argumentCount): void
    {
        self::assertNotEmpty($frames);
        $frame = $frames[0];
        self::assertArrayHasKey('args', $frame);
        self::assertIsArray($frame['args']);
        self::assertCount($argumentCount, $frame['args']);
        foreach ($frame['args'] as $argument) {
            self::assertInstanceOf(\SensitiveParameterValue::class, $argument);
        }
    }

    private function writer(
        ObserverReplayWriterClock $clock,
        ?Connection $connection = null,
        string $buildId = self::BUILD_ID,
    ): ObserverReplayAuditWriter {
        $connection ??= $this->createStub(Connection::class);

        return new ObserverReplayAuditWriter(
            new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
            $this->selector(),
            $this->coordinator($connection),
            $clock,
            new ActorContext(
                new ActorRef('origin-1', 'service'),
                new ActorRef('principal-1', 'user'),
                new ActorRef('execution-1', 'worker'),
            ),
            AuditRecordCorrelation::fromArray([]),
            $buildId,
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
    private function snapshot(ObserverReplayAuditWriter $writer): array
    {
        $value = new ReflectionProperty($writer, 'snapshot')
            ->getValue($writer)
            ->getValue();
        self::assertIsArray($value);

        return $value;
    }
}

final class ObserverReplayWriterClock implements ClockInterface
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
