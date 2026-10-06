<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\OperationalOutbox;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditRecordActor;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditFailure;
use BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditProjection;
use BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditSnapshot;
use BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditWriter;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionClass;
use ReflectionProperty;

final class GenericOutboxAuditWriterTest extends TestCase
{
    private const string RECORD_ID = '019f80ab-4000-7000-8000-000000000011';
    private const string BUILD_ID = 'generic-outbox-writer-test';
    private const string CANARY = 'generic-outbox-writer-private-canary';

    public function testTrustedBindingKeepsOnlyCurrentActorCorrelationRuntimeAndBuild(): void
    {
        $writer = $this->writer(new OutboxWriterClock());
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
        self::assertSame(AuditSourceRuntime::Relay, $value['runtime']);
        self::assertSame(self::BUILD_ID, $value['buildId']);
        self::assertArrayNotHasKey('origin', $value);
        self::assertArrayNotHasKey('claim', $value);
        self::assertArrayNotHasKey('actorContext', $value);
    }

    public function testConstructionRejectsNonRelayAndNonCliRuntime(): void
    {
        $this->expectException(AuditDeliveryException::class);
        $this->expectExceptionMessage('Audit delivery failed.');
        $this->writer(new OutboxWriterClock(), runtime: AuditSourceRuntime::Worker);
    }

    public function testConnectionIdentityAndRuntimeRejectBeforeClockUse(): void
    {
        $clock = new OutboxWriterClock();
        $connection = $this->createStub(Connection::class);
        $writer = $this->writer($clock, $connection);

        try {
            $writer->assertConnection($this->createStub(Connection::class), AuditSourceRuntime::Relay);
            self::fail('a different connection was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        try {
            $writer->assertConnection($connection, AuditSourceRuntime::Cli);
            self::fail('a runtime mismatch was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
        }
        self::assertSame(0, $clock->calls);
    }

    public function testMalformedReturnedIdentityTenantVersionAndRetryIdFailBeforeClock(): void
    {
        $cases = [
            static function (GenericOutboxAuditWriter $writer): void {
                $writer->retryScheduled(strtoupper(self::RECORD_ID), '1', null);
            },
            static function (GenericOutboxAuditWriter $writer): void {
                $writer->retryScheduled('019f80ab-4000-4000-8000-000000000011', '1', null);
            },
            static function (GenericOutboxAuditWriter $writer): void {
                $writer->retryScheduled(self::RECORD_ID, '01', null);
            },
            static function (GenericOutboxAuditWriter $writer): void {
                $writer->retryScheduled(self::RECORD_ID, '0', null);
            },
            static function (GenericOutboxAuditWriter $writer): void {
                $writer->retryScheduled(self::RECORD_ID, '1', new TenantRef(str_repeat('a', 257), 'tenant-1'));
            },
        ];

        foreach ($cases as $case) {
            $clock = new OutboxWriterClock();
            try {
                $case($this->writer($clock));
                self::fail('malformed native metadata was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
                self::assertNull($failure->getPrevious());
            }
            self::assertSame(0, $clock->calls);
        }
    }

    public function testFullPostgresBigintVersionRemainsStringUntilDelivery(): void
    {
        $clock = new OutboxWriterClock();
        $connection = $this->createStub(Connection::class);
        $connection->method('isAutoCommit')->willReturn(true);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->method('getTransactionNestingLevel')->willReturn(1);
        try {
            $this->writer($clock, $connection)->retryScheduled(self::RECORD_ID, '9223372036854775807', null);
            self::fail('an uninitialized delivery binding was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
        }
        self::assertSame(1, $clock->calls);
    }

    public function testAllAcceptedBigintStringsRemainExactBeforeCurrentTransactionDelivery(): void
    {
        foreach (['1', '9007199254740991', '9007199254740992', '9223372036854775807'] as $version) {
            $clock = new OutboxWriterClock();
            $connection = $this->createStub(Connection::class);
            $connection->method('isAutoCommit')->willReturn(true);
            $connection->method('isTransactionActive')->willReturn(true);
            $connection->method('getTransactionNestingLevel')->willReturn(1);
            try {
                $this->writer($clock, $connection)->retryScheduled(self::RECORD_ID, $version, null);
                self::fail('an uninitialized delivery binding was accepted for ' . $version);
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
                self::assertNull($failure->getPrevious());
            }
            self::assertSame(1, $clock->calls, $version);
        }
    }

    public function testManualUuidv4IsAcceptedOnceAndInvalidVersionsFailBeforeClock(): void
    {
        $clock = new OutboxWriterClock();
        $connection = $this->createStub(Connection::class);
        $connection->method('isAutoCommit')->willReturn(true);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->method('getTransactionNestingLevel')->willReturn(1);
        try {
            $this->writer($clock, $connection, AuditSourceRuntime::Cli)->deadLetterRetryRequested(
                self::RECORD_ID,
                '019f80ab-4000-4000-8000-000000000011',
                null,
            );
            self::fail('a valid manual UUID was rejected before delivery');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        self::assertSame(1, $clock->calls);

        foreach (['019F80AB-4000-4000-8000-000000000011', '019f80ab-4000-7000-8000-000000000011'] as $retryId) {
            $clock = new OutboxWriterClock();
            try {
                $this->writer($clock, runtime: AuditSourceRuntime::Cli)->deadLetterRetryRequested(
                    self::RECORD_ID,
                    $retryId,
                    null,
                );
                self::fail('an invalid manual UUID was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
                self::assertNull($failure->getPrevious());
            }
            self::assertSame(0, $clock->calls);
        }
    }

    public function testClockFailureIsFreshAndDoesNotExposeTheNativeCanary(): void
    {
        $clock = new OutboxWriterClock(failure: new \RuntimeException(self::CANARY));
        try {
            $this->writer($clock)->deadLettered(self::RECORD_ID, '9007199254740992', null);
            self::fail('a clock failure was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $failure->failure());
            self::assertSame('Audit delivery failed.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString(self::CANARY, $failure->getTraceAsString());
        }
        self::assertSame(1, $clock->calls);
    }

    public function testDirectHelpersMaskTypedCorruptionProjectionAndClockMarkers(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        $marker = 'writer-helper-private-marker';
        try {
            $writer = $this->writer(new OutboxWriterClock());
            $snapshot = $this->snapshot($writer);
            foreach ([
                'actor' => AuditRecordActor::class,
                'correlation' => AuditRecordCorrelation::class,
            ] as $field => $type) {
                $candidate = $snapshot;
                $candidate[$field] = new ReflectionClass($type)->newInstanceWithoutConstructor();
                $candidate['buildId'] = $marker;
                try {
                    GenericOutboxAuditSnapshot::read(new \SensitiveParameterValue($candidate));
                    self::fail('corrupt typed snapshot was accepted');
                } catch (AuditDeliveryException $failure) {
                    self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
                    self::assertNull($failure->getPrevious());
                    $this->assertSensitiveHelperFrame(
                        $failure,
                        GenericOutboxAuditSnapshot::class,
                        'read',
                        1,
                        [0],
                        [$marker],
                    );
                }
            }

            try {
                GenericOutboxAuditProjection::occurredAt(
                    new OutboxWriterClock(failure: new \RuntimeException($marker)),
                );
                self::fail('clock failure was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $failure->failure());
                self::assertNull($failure->getPrevious());
                $this->assertSensitiveHelperFrame(
                    $failure,
                    GenericOutboxAuditProjection::class,
                    'occurredAt',
                    1,
                    [0],
                    [$marker],
                );
            }

            try {
                GenericOutboxAuditProjection::opaqueId(
                    new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
                    AuditOpaqueIdPurpose::Action,
                    "invalid\x01" . $marker,
                );
                self::fail('invalid opaque context was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::KeyUnavailable, $failure->failure());
                self::assertNull($failure->getPrevious());
                $this->assertSensitiveHelperFrame(
                    $failure,
                    GenericOutboxAuditProjection::class,
                    'opaqueId',
                    3,
                    [0, 1, 2],
                    [$marker],
                );
            }

            $snapshot = $this->snapshot($writer);
            self::assertInstanceOf(AuditRecordActor::class, $snapshot['actor']);
            self::assertInstanceOf(AuditRecordCorrelation::class, $snapshot['correlation']);
            /** @var AuditRecordActor $actor */
            $actor = $snapshot['actor'];
            /** @var AuditRecordCorrelation $correlation */
            $correlation = $snapshot['correlation'];
            $definition = $this->definition('outbox.delivery.retry_scheduled');
            try {
                GenericOutboxAuditProjection::draft(
                    $definition,
                    $actor,
                    $correlation,
                    ['runtime' => AuditSourceRuntime::Relay, 'buildId' => $marker],
                    ['actionId' => $marker],
                );
                self::fail('malformed projection material was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
                self::assertNull($failure->getPrevious());
                $this->assertSensitiveHelperFrame(
                    $failure,
                    GenericOutboxAuditProjection::class,
                    'draft',
                    5,
                    [0, 1, 2, 3, 4],
                    [$marker],
                );
            }
        } finally {
            if ($previous !== false) {
                self::assertNotFalse(ini_set('zend.exception_ignore_args', $previous));
            }
        }
        self::assertSame($previous, ini_get('zend.exception_ignore_args'));
    }

    public function testKnownAndUnreadableFailuresRemapToFreshFiniteCodes(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            $knownInput = new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
            $known = GenericOutboxAuditFailure::remap($knownInput);
            self::assertNotSame($knownInput, $known);
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $known->failure());
            self::assertSame('Audit delivery failed.', $known->getMessage());
            self::assertNull($known->getPrevious());

            $marker = 'writer-remap-private-marker';
            try {
                GenericOutboxAuditProjection::opaqueId(
                    new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
                    AuditOpaqueIdPurpose::Action,
                    "invalid\x01" . $marker,
                );
                self::fail('marker-bearing opaque failure was accepted');
            } catch (AuditDeliveryException $caught) {
                self::assertSame(AuditDeliveryFailure::KeyUnavailable, $caught->failure());
                $this->assertSafeFailureSurface($caught, [$marker]);
                $this->assertSensitiveHelperFrame(
                    $caught,
                    GenericOutboxAuditProjection::class,
                    'opaqueId',
                    3,
                    [0, 1, 2],
                    [$marker],
                );
                $remapped = GenericOutboxAuditFailure::remap($caught);
                self::assertNotSame($caught, $remapped);
                self::assertSame(AuditDeliveryFailure::KeyUnavailable, $remapped->failure());
                self::assertSame('Audit delivery failed.', $remapped->getMessage());
                self::assertNull($remapped->getPrevious());
                $this->assertSafeFailureSurface($remapped, [$marker]);
                $this->assertSensitiveHelperFrame(
                    $remapped,
                    GenericOutboxAuditFailure::class,
                    'remap',
                    1,
                    [0],
                    [$marker],
                );
            }

            /** @var AuditDeliveryException $unreadable */
            $unreadable = new ReflectionClass(AuditDeliveryException::class)->newInstanceWithoutConstructor();
            $fresh = GenericOutboxAuditFailure::remap($unreadable);
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $fresh->failure());
            self::assertSame('Audit delivery failed.', $fresh->getMessage());
            self::assertNull($fresh->getPrevious());
            self::assertSame([], $fresh->__debugInfo());
            self::assertSame([], $fresh->__serialize());
            self::assertNotSame($unreadable, $fresh);
            $this->assertSensitiveHelperFrame($fresh, GenericOutboxAuditFailure::class, 'remap', 1, [0], []);
        } finally {
            if ($previous !== false) {
                self::assertNotFalse(ini_set('zend.exception_ignore_args', $previous));
            }
        }
        self::assertSame($previous, ini_get('zend.exception_ignore_args'));
    }

    public function testSnapshotAndObjectSurfacesFailClosed(): void
    {
        $clock = new OutboxWriterClock(self::CANARY);
        $writer = $this->writer($clock);
        self::assertSame([], $writer->__debugInfo());
        self::assertSame([], $writer->__serialize());
        self::assertStringNotContainsString(self::CANARY, print_r($writer, true) . var_export($writer, true));

        new ReflectionProperty($writer, 'snapshot')->setValue($writer, new \SensitiveParameterValue([
            'buildId' => self::CANARY,
        ]));
        try {
            $writer->retryScheduled(self::RECORD_ID, '1', null);
            self::fail('a malformed writer snapshot was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString(self::CANARY, $failure->getTraceAsString());
        }
        self::assertSame(0, $clock->calls);
        try {
            $writer->__unserialize([]);
            self::fail('writer unserialization was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
    }

    public function testAbsentWriterStateFailsBeforeClockOrDelivery(): void
    {
        /** @var GenericOutboxAuditWriter $writer */
        $writer = new ReflectionClass(GenericOutboxAuditWriter::class)->newInstanceWithoutConstructor();
        try {
            $writer->retryScheduled(self::RECORD_ID, '1', null);
            self::fail('constructor-bypassed writer was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
    }

    public function testBigintContextsAndDurableKeysRemainLiteralAndDistinct(): void
    {
        $deriver = new HmacAuditOpaqueIdDeriver(str_repeat('a', 32));
        $expectedContexts = [
            '1' => 'generic-outbox:outbox.delivery.retry_scheduled:019f80ab-4000-7000-8000-000000000011:1',
            '9007199254740991' => 'generic-outbox:outbox.delivery.retry_scheduled:019f80ab-4000-7000-8000-000000000011:9007199254740991',
            '9007199254740992' => 'generic-outbox:outbox.delivery.retry_scheduled:019f80ab-4000-7000-8000-000000000011:9007199254740992',
            '9223372036854775807' => 'generic-outbox:outbox.delivery.retry_scheduled:019f80ab-4000-7000-8000-000000000011:9223372036854775807',
        ];
        $writerSnapshot = $this->snapshot($this->writer(new OutboxWriterClock()));
        self::assertInstanceOf(AuditRecordActor::class, $writerSnapshot['actor']);
        self::assertInstanceOf(AuditRecordCorrelation::class, $writerSnapshot['correlation']);
        /** @var AuditRecordActor $actor */
        $actor = $writerSnapshot['actor'];
        /** @var AuditRecordCorrelation $correlation */
        $correlation = $writerSnapshot['correlation'];
        $definition = $this->definition('outbox.delivery.retry_scheduled');
        $resourceId = (string) $deriver->derive(
            AuditOpaqueIdPurpose::Resource,
            'generic-outbox:record:019f80ab-4000-7000-8000-000000000011',
        );
        $actionIds = [];
        foreach ($expectedContexts as $version => $context) {
            $expectedAction = (string) $deriver->derive(AuditOpaqueIdPurpose::Action, $context);
            $actionId = GenericOutboxAuditProjection::opaqueId($deriver, AuditOpaqueIdPurpose::Action, $context);
            self::assertSame($expectedAction, $actionId);
            self::assertMatchesRegularExpression('/\Aoid:v1:[a-f0-9]{64}\z/D', $actionId);
            $draft = GenericOutboxAuditProjection::draft(
                $definition,
                $actor,
                $correlation,
                ['runtime' => AuditSourceRuntime::Relay, 'buildId' => self::BUILD_ID],
                [
                    'actionId' => $actionId,
                    'resourceId' => $resourceId,
                    'occurredAt' => '2026-09-21T03:34:56.123456Z',
                    'tenant' => null,
                ],
            );
            $request = new AuditDeliveryRequest($draft, 'generic-outbox:' . $actionId);
            self::assertSame($actionId, $request->draft()->action->id);
            self::assertSame('generic-outbox:' . $actionId, $request->idempotencyKey());
            $plan = $this->selector()->forDraft($draft);
            self::assertSame(AuditDelivery::Durable, $plan->delivery());
            self::assertSame(AuditDelivery::Durable, $this->selector()->validate($plan, $request));
            self::assertStringEndsWith(':' . $version, $context);
            $actionIds[] = $actionId;
        }
        self::assertCount(4, array_unique($actionIds));
    }

    public function testCorruptActorAndCorrelationSnapshotsFailBeforeClockOrOpaqueDerivation(): void
    {
        foreach (['actor' => 'corrupt-actor', 'correlation' => 'corrupt-correlation'] as $field => $value) {
            $clock = new OutboxWriterClock(self::CANARY);
            $writer = $this->writer($clock);
            $snapshot = $this->snapshot($writer);
            $snapshot[$field] = $value;
            new ReflectionProperty($writer, 'snapshot')->setValue($writer, new \SensitiveParameterValue($snapshot));
            try {
                $writer->retryScheduled(self::RECORD_ID, '1', null);
                self::fail('a corrupt trusted snapshot was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
                self::assertNull($failure->getPrevious());
                self::assertStringNotContainsString(self::CANARY, $failure->getTraceAsString());
            }
            self::assertSame(0, $clock->calls);
        }
    }

    public function testOwnedSensitiveSnapshotStaysOutOfFailureSurfaceWithExceptionArgumentsEnabled(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        $marker = 'writer-owned-sensitive-marker';
        try {
            $clock = new OutboxWriterClock();
            $writer = $this->writer($clock);
            $snapshot = $this->snapshot($writer);
            $snapshot['clock'] = $marker;
            new ReflectionProperty($writer, 'snapshot')->setValue($writer, new \SensitiveParameterValue($snapshot));

            $failure = null;
            try {
                $writer->retryScheduled($marker, '1', null);
            } catch (AuditDeliveryException $exception) {
                $failure = $exception;
            }
            self::assertInstanceOf(AuditDeliveryException::class, $failure);
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
            $surface =
                print_r($failure, true)
                . var_export($failure, true)
                . serialize($failure)
                . $failure->getTraceAsString()
                . print_r($failure->getTrace(), true)
                . var_export($failure->getTrace(), true);
            self::assertStringNotContainsString($marker, $surface);
            $this->assertSensitiveFrame($failure, 'write', 4, [$marker]);
        } finally {
            if ($previous !== false) {
                self::assertNotFalse(ini_set('zend.exception_ignore_args', $previous));
            }
        }
        self::assertSame($previous, ini_get('zend.exception_ignore_args'));
    }

    private function writer(
        OutboxWriterClock $clock,
        ?Connection $connection = null,
        AuditSourceRuntime $runtime = AuditSourceRuntime::Relay,
    ): GenericOutboxAuditWriter {
        $connection ??= $this->createStub(Connection::class);

        return new GenericOutboxAuditWriter(
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
            $runtime,
            self::BUILD_ID,
        );
    }

    private function selector(): AuditDeliverySelector
    {
        $catalog = FrameworkOperationalAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);

        return new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
    }

    private function definition(string $eventType): AuditEventDefinition
    {
        foreach (FrameworkOperationalAuditEventCatalog::catalog()->events as $definition) {
            if ($definition->type === $eventType) {
                return $definition;
            }
        }

        self::fail('required operational definition is missing');
    }

    private function coordinator(Connection $connection): AuditDeliveryCoordinator
    {
        $records = new ReflectionClass(\BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore::class)->newInstanceWithoutConstructor();
        $outbox = new ReflectionClass(PostgreSqlAuditOutboxStore::class)->newInstanceWithoutConstructor();

        return new AuditDeliveryCoordinator($connection, $this->selector(), $records, $outbox);
    }

    /** @return array<string,mixed> */
    private function snapshot(GenericOutboxAuditWriter $writer): array
    {
        $value = new ReflectionProperty($writer, 'snapshot')
            ->getValue($writer)
            ->getValue();
        self::assertIsArray($value);

        return $value;
    }

    /** @param list<string> $canaries */
    private function assertSafeFailureSurface(\Throwable $failure, array $canaries): void
    {
        $surface =
            $failure->getMessage()
            . print_r($failure, true)
            . var_export($failure, true)
            . serialize($failure)
            . $failure->getTraceAsString()
            . print_r($failure->getTrace(), true)
            . var_export($failure->getTrace(), true);
        if ($failure instanceof AuditDeliveryException) {
            self::assertSame([], $failure->__debugInfo());
            self::assertSame([], $failure->__serialize());
        }
        foreach ($canaries as $canary) {
            self::assertStringNotContainsString($canary, $surface);
        }
    }

    /** @param list<string> $canaries */
    private function assertSensitiveFrame(
        \Throwable $failure,
        string $method,
        int $argumentCount,
        array $canaries,
    ): void {
        $frames = array_values(array_filter(
            $failure->getTrace(),
            static fn(array $frame): bool => (
                ($frame['class'] ?? null) === GenericOutboxAuditWriter::class
                && ($frame['function'] ?? null) === $method
            ),
        ));
        self::assertNotEmpty($frames);
        $frame = $frames[0];
        self::assertArrayHasKey('args', $frame);
        self::assertIsArray($frame['args']);
        self::assertCount($argumentCount, $frame['args']);
        foreach ($frame['args'] as $argument) {
            self::assertInstanceOf(\SensitiveParameterValue::class, $argument);
        }
        $ownedTrace = print_r($frame, true) . var_export($frame, true);
        self::assertStringContainsString('SensitiveParameterValue', $ownedTrace);
        foreach ($canaries as $canary) {
            self::assertStringNotContainsString($canary, $ownedTrace);
            self::assertStringNotContainsString($canary, $failure->getTraceAsString());
        }
    }

    /**
     * @param list<int> $sensitiveArguments
     * @param list<string> $canaries
     */
    private function assertSensitiveHelperFrame(
        \Throwable $failure,
        string $class,
        string $method,
        int $argumentCount,
        array $sensitiveArguments,
        array $canaries,
    ): void {
        $frames = array_values(array_filter(
            $failure->getTrace(),
            static fn(array $frame): bool => (
                ($frame['class'] ?? null) === $class
                && ($frame['function'] ?? null) === $method
            ),
        ));
        self::assertNotEmpty($frames);
        $frame = $frames[0];
        self::assertArrayHasKey('args', $frame);
        self::assertIsArray($frame['args']);
        self::assertCount($argumentCount, $frame['args']);
        foreach ($sensitiveArguments as $index) {
            self::assertInstanceOf(\SensitiveParameterValue::class, $frame['args'][$index]);
        }
        $helperTrace = print_r($frame, true) . var_export($frame, true);
        foreach ($canaries as $canary) {
            self::assertStringNotContainsString($canary, $helperTrace);
            self::assertStringNotContainsString($canary, $failure->getTraceAsString());
        }
        $this->assertSafeFailureSurface($failure, $canaries);
    }
}

final class OutboxWriterClock implements ClockInterface
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
