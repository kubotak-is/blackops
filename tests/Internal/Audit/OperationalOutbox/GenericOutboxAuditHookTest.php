<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\OperationalOutbox;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditRecordActor;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditHook;
use BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditProjection;
use BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditRow;
use BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditSnapshot;
use BlackOps\Internal\Audit\OperationalOutbox\GenericOutboxAuditWriter;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class GenericOutboxAuditHookTest extends TestCase
{
    private const string RECORD_ID = '019f80ab-4000-7000-8000-000000000011';
    private const string RETRY_ID = '019f80ab-4000-4000-8000-000000000011';

    public function testRelayAdmissionRequiresIdleBoundConnectionWithoutReadingClock(): void
    {
        $connection = $this->connection(true, false, 0);
        $clock = new OutboxHookClock();
        $hook = $this->hook($connection, $clock);

        $hook->assertRelayEntry($connection);

        self::assertSame(0, $clock->calls);
    }

    public function testOperatorAdmissionUsesTheSeparateCliRuntimeBinding(): void
    {
        $connection = $this->connection(true, false, 0);
        $hook = $this->hook($connection, new OutboxHookClock(), AuditSourceRuntime::Cli);

        $hook->assertOperatorEntry($connection);

        try {
            $hook->assertRelayEntry($connection);
            self::fail('Relay admission crossed the Cli-bound writer');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
        }
    }

    public function testForeignOrAmbientEntryIsRejectedBeforeRootEffects(): void
    {
        $connection = $this->connection(true, true, 1);
        $hook = $this->hook($connection, new OutboxHookClock());

        try {
            $hook->assertRelayEntry($connection);
            self::fail('ambient transaction was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        try {
            $hook->assertRelayEntry($this->connection(true, false, 0));
            self::fail('a different connection was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
        }
    }

    public function testReturnedRowRequiresAllFourFieldsAndACompleteTenantPair(): void
    {
        $connection = $this->connection(true, true, 1);
        $clock = new OutboxHookClock();
        $hook = $this->hook($connection, $clock);
        $valid = GenericOutboxAuditRow::read([
            'state_version' => '9223372036854775807',
            'tenant_id' => 'tenant-a',
            'record_id' => self::RECORD_ID,
            'tenant_type' => 'account',
        ]);
        self::assertSame([
            'record_id' => self::RECORD_ID,
            'state_version' => '9223372036854775807',
            'tenant' => ['type' => 'account', 'id' => 'tenant-a'],
        ], [
            'record_id' => $valid['record_id'],
            'state_version' => $valid['state_version'],
            'tenant' => $valid['tenant'] === null
                ? null
                : ['type' => $valid['tenant']->type(), 'id' => $valid['tenant']->id()],
        ]);
        $otherTenant = GenericOutboxAuditRow::read([
            'tenant_type' => 'account',
            'record_id' => self::RECORD_ID,
            'state_version' => '1',
            'tenant_id' => 'tenant-b',
        ]);
        self::assertNotNull($valid['tenant']);
        self::assertNotNull($otherTenant['tenant']);
        self::assertNotSame($valid['tenant']->id(), $otherTenant['tenant']->id());
        $cases = [
            ['record_id' => self::RECORD_ID, 'tenant_type' => null, 'tenant_id' => null],
            [
                'record_id' => self::RECORD_ID,
                'tenant_type' => 'account',
                'tenant_id' => 'tenant-a',
                'state_version' => '1',
                'extra' => 'reject',
            ],
            [
                'record_id' => self::RECORD_ID,
                'tenant_type' => 'account',
                'tenant_id' => null,
                'state_version' => '1',
            ],
            [
                'record_id' => self::RECORD_ID,
                'tenant_type' => 'account',
                'tenant_id' => 'tenant-a',
                'state_version' => 1,
            ],
            [
                'record_id' => self::RECORD_ID,
                'tenant_type' => "account\n",
                'tenant_id' => 'tenant-a',
                'state_version' => '1',
            ],
            [
                'record_id' => self::RECORD_ID,
                'tenant_type' => "account\xC3\x28",
                'tenant_id' => 'tenant-a',
                'state_version' => '1',
            ],
            [
                'record_id' => self::RECORD_ID,
                'tenant_type' => 'account',
                'tenant_id' => "tenant-a\n",
                'state_version' => '1',
            ],
            [
                'record_id' => self::RECORD_ID,
                'tenant_type' => 'account',
                'tenant_id' => "tenant-a\xC3\x28",
                'state_version' => '1',
            ],
            [
                'record_id' => self::RECORD_ID,
                'tenant_type' => '',
                'tenant_id' => 'tenant-a',
                'state_version' => '1',
            ],
            [
                'record_id' => self::RECORD_ID,
                'tenant_type' => 'account',
                'tenant_id' => '',
                'state_version' => '1',
            ],
            [
                'record_id' => self::RECORD_ID,
                'tenant_type' => ' account',
                'tenant_id' => 'tenant-a',
                'state_version' => '1',
            ],
            [
                'record_id' => self::RECORD_ID,
                'tenant_type' => 'account',
                'tenant_id' => 'tenant-a ',
                'state_version' => '1',
            ],
            [
                'record_id' => self::RECORD_ID,
                'tenant_type' => 'account',
                'tenant_id' => str_repeat('a', 257),
                'state_version' => '1',
            ],
            [
                'record_id' => self::RECORD_ID,
                'tenant_type' => str_repeat('t', 257),
                'tenant_id' => 'tenant-a',
                'state_version' => '1',
            ],
            [
                'record_id' => self::RECORD_ID,
                'tenant_type' => "account\x01",
                'tenant_id' => 'tenant-a',
                'state_version' => '1',
            ],
        ];
        if (class_exists('Normalizer')) {
            $cases[] = [
                'record_id' => self::RECORD_ID,
                'tenant_type' => "e\u{0301}",
                'tenant_id' => 'tenant-a',
                'state_version' => '1',
            ];
            $cases[] = [
                'record_id' => self::RECORD_ID,
                'tenant_type' => 'account',
                'tenant_id' => "e\u{0301}",
                'state_version' => '1',
            ];
        }
        foreach ([
            '0',
            '+1',
            ' 1',
            '1 ',
            '1.0',
            '1e2',
            '9223372036854775808',
            '92233720368547758070',
            1,
            1.5,
            true,
            false,
            null,
        ] as $version) {
            $cases[] = [
                'record_id' => self::RECORD_ID,
                'tenant_type' => null,
                'tenant_id' => null,
                'state_version' => $version,
            ];
        }
        foreach ($cases as $row) {
            try {
                $hook->retryScheduled($connection, $row);
                self::fail('malformed returned row was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
                self::assertNull($failure->getPrevious());
            }
        }
        self::assertSame(0, $clock->calls);
    }

    public function testPostBeginStateAndCorruptSnapshotRejectBeforeClockOrDelivery(): void
    {
        foreach ([[false, true, 1], [true, true, 2], [false, false, 0]] as [$autoCommit, $active, $level]) {
            $clock = new OutboxHookClock();
            $connection = $this->connection($autoCommit, $active, $level);
            $hook = $this->hook($connection, $clock);
            try {
                $hook->retryScheduled($connection, [
                    'record_id' => self::RECORD_ID,
                    'tenant_type' => null,
                    'tenant_id' => null,
                    'state_version' => '1',
                ]);
                self::fail('invalid post-begin state was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
                self::assertNull($failure->getPrevious());
            }
            self::assertSame(0, $clock->calls);
        }

        foreach (['actor' => 'corrupt-actor', 'correlation' => 'corrupt-correlation'] as $field => $value) {
            $clock = new OutboxHookClock();
            $connection = $this->connection(true, true, 1);
            $writer = $this->writer($connection, $clock, AuditSourceRuntime::Relay);
            $snapshot = new ReflectionProperty($writer, 'snapshot')
                ->getValue($writer)
                ->getValue();
            self::assertIsArray($snapshot);
            $snapshot[$field] = $value;
            new ReflectionProperty($writer, 'snapshot')->setValue($writer, new \SensitiveParameterValue($snapshot));
            $hook = new GenericOutboxAuditHook($writer);
            try {
                $hook->retryScheduled($connection, [
                    'record_id' => self::RECORD_ID,
                    'tenant_type' => null,
                    'tenant_id' => null,
                    'state_version' => '1',
                ]);
                self::fail('corrupt trusted snapshot was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
                self::assertNull($failure->getPrevious());
            }
            self::assertSame(0, $clock->calls);
        }
    }

    public function testValidGlobalRowReachesWriterOnlyInsideActiveRoot(): void
    {
        $connection = $this->connection(true, true, 1);
        $clock = new OutboxHookClock();
        $hook = $this->hook($connection, $clock);

        try {
            $hook->retryScheduled($connection, [
                'record_id' => self::RECORD_ID,
                'tenant_type' => null,
                'tenant_id' => null,
                'state_version' => '9223372036854775807',
            ]);
            self::fail('an uninitialized C3 binding was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        self::assertSame(1, $clock->calls);
    }

    public function testManualPhaseCarriesOneNativeUuid4AndTenant(): void
    {
        $connection = $this->connection(true, true, 1);
        $hook = $this->hook($connection, new OutboxHookClock(), AuditSourceRuntime::Cli);

        try {
            $hook->deadLetterRetryRequested(
                $connection,
                [
                    'record_id' => self::RECORD_ID,
                    'tenant_type' => 'account',
                    'tenant_id' => 'tenant-a',
                    'state_version' => '1',
                ],
                self::RETRY_ID,
            );
            self::fail('an uninitialized C3 binding was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
    }

    public function testOwnedSensitiveSnapshotStaysOutOfFailureSurfaceWithExceptionArgumentsEnabled(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        $marker = 'hook-owned-sensitive-marker';
        try {
            $connection = $this->connection(true, true, 1);
            $clock = new OutboxHookClock();
            $writer = $this->writer($connection, $clock, AuditSourceRuntime::Relay);
            $snapshot = new ReflectionProperty($writer, 'snapshot')
                ->getValue($writer)
                ->getValue();
            self::assertIsArray($snapshot);
            $snapshot['actor'] = $marker;
            new ReflectionProperty($writer, 'snapshot')->setValue($writer, new \SensitiveParameterValue($snapshot));
            $hook = new GenericOutboxAuditHook($writer);

            $failure = null;
            try {
                $hook->retryScheduled($connection, [
                    'record_id' => $marker,
                    'tenant_type' => null,
                    'tenant_id' => null,
                    'state_version' => '1',
                ]);
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
            $this->assertSensitiveFrame($failure, 'retryScheduled', 2, [$marker]);
        } finally {
            if ($previous !== false) {
                self::assertNotFalse(ini_set('zend.exception_ignore_args', $previous));
            }
        }
        self::assertSame($previous, ini_get('zend.exception_ignore_args'));
    }

    public function testObjectSurfacesAreEmptyAndUnserializationFailsClosed(): void
    {
        $hook = $this->hook($this->connection(true, false, 0), new OutboxHookClock());
        self::assertSame([], $hook->__debugInfo());
        self::assertSame([], $hook->__serialize());
        try {
            clone $hook;
            self::fail('hook cloning must fail closed');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        try {
            $hook->__unserialize([]);
            self::fail('hook unserialization was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
    }

    public function testDirectHelpersMaskTypedCorruptionAndProjectionMarkers(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        $marker = 'hook-helper-private-marker';
        try {
            $clock = new OutboxHookClock();
            $connection = $this->connection(true, true, 1);
            $writer = $this->writer($connection, $clock, AuditSourceRuntime::Relay);
            $snapshot = new ReflectionProperty($writer, 'snapshot')
                ->getValue($writer)
                ->getValue();
            self::assertIsArray($snapshot);
            $captured = GenericOutboxAuditProjection::capture(
                new ActorContext(
                    new ActorRef('origin-helper', 'service'),
                    new ActorRef('principal-helper', 'user'),
                    new ActorRef('execution-helper', 'worker'),
                ),
                AuditRecordCorrelation::fromArray(['correlationId' => 'correlation-helper']),
                AuditSourceRuntime::Relay,
                'hook-helper-build',
            );
            self::assertSame(
                [
                    'principal' => ['type' => 'user', 'id' => 'principal-helper'],
                    'execution' => ['type' => 'worker', 'id' => 'execution-helper'],
                ],
                $captured['actor']->toArray(),
            );
            self::assertSame(['correlationId' => 'correlation-helper'], $captured['correlation']->toArray());
            self::assertSame(AuditSourceRuntime::Relay, $captured['runtime']);
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
                    $this->assertSafeFailureSurface($failure, [$marker]);
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
                GenericOutboxAuditRow::read([
                    'record_id' => self::RECORD_ID,
                    'tenant_type' => 'account',
                    'tenant_id' => $marker . "\n",
                    'state_version' => '1',
                ]);
                self::fail('marker-bearing invalid tenant row was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
                self::assertNull($failure->getPrevious());
                $this->assertSafeFailureSurface($failure, [$marker]);
                $this->assertSensitiveHelperFrame($failure, GenericOutboxAuditRow::class, 'read', 1, [0], [$marker]);
            }

            try {
                GenericOutboxAuditProjection::capture(
                    new ActorContext(
                        new ActorRef($marker, 'service'),
                        new ActorRef($marker, 'user'),
                        new ActorRef($marker, 'worker'),
                    ),
                    AuditRecordCorrelation::fromArray([]),
                    AuditSourceRuntime::Worker,
                    $marker,
                );
                self::fail('unsupported projection runtime was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $failure->failure());
                self::assertNull($failure->getPrevious());
                $this->assertSafeFailureSurface($failure, [$marker]);
                $this->assertSensitiveHelperFrame(
                    $failure,
                    GenericOutboxAuditProjection::class,
                    'capture',
                    4,
                    [0, 1, 3],
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

    public function testDirectPrivateReferenceAndTenantFramesMaskTypedAndMixedMarkers(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        $referenceMarker = 'hook-reference-private-marker';
        $tenantMarker = 'hook-tenant-private-marker';
        try {
            $overBoundId = $referenceMarker . str_repeat('x', 257 - strlen($referenceMarker));
            try {
                new ReflectionMethod(GenericOutboxAuditProjection::class, 'reference')->invoke(
                    null,
                    new ActorRef($overBoundId, 'service'),
                );
                self::fail('over-bound private actor reference was accepted');
            } catch (AuditValidationException $failure) {
                self::assertSame(AuditValidationFailure::ByteBoundInvalid, $failure->failure());
                $this->assertSensitiveHelperFrame(
                    $failure,
                    GenericOutboxAuditProjection::class,
                    'reference',
                    1,
                    [0],
                    [$referenceMarker],
                );
            }

            try {
                new ReflectionMethod(GenericOutboxAuditRow::class, 'tenant')->invoke(
                    null,
                    'account',
                    $tenantMarker . "\n",
                );
                self::fail('invalid private tenant pair was accepted');
            } catch (\LogicException $failure) {
                self::assertSame(\LogicException::class, $failure::class);
                self::assertSame('', $failure->getMessage());
                self::assertNull($failure->getPrevious());
                $this->assertSensitiveHelperFrame(
                    $failure,
                    GenericOutboxAuditRow::class,
                    'tenant',
                    2,
                    [0, 1],
                    [$tenantMarker],
                );
            }
        } finally {
            if ($previous !== false) {
                self::assertNotFalse(ini_set('zend.exception_ignore_args', $previous));
            }
        }
        self::assertSame($previous, ini_get('zend.exception_ignore_args'));
    }

    public function testAbsentHookStateFailsClosed(): void
    {
        /** @var GenericOutboxAuditHook $hook */
        $hook = new ReflectionClass(GenericOutboxAuditHook::class)->newInstanceWithoutConstructor();
        try {
            $hook->retryScheduled($this->connection(true, true, 1), [
                'record_id' => self::RECORD_ID,
                'tenant_type' => null,
                'tenant_id' => null,
                'state_version' => '1',
            ]);
            self::fail('constructor-bypassed hook was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
    }

    private function hook(
        Connection $connection,
        OutboxHookClock $clock,
        AuditSourceRuntime $runtime = AuditSourceRuntime::Relay,
    ): GenericOutboxAuditHook {
        return new GenericOutboxAuditHook($this->writer($connection, $clock, $runtime));
    }

    private function writer(
        Connection $connection,
        OutboxHookClock $clock,
        AuditSourceRuntime $runtime,
    ): GenericOutboxAuditWriter {
        $catalog = FrameworkOperationalAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $selector = new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
        $records = new ReflectionClass(\BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore::class)->newInstanceWithoutConstructor();
        $outbox = new ReflectionClass(PostgreSqlAuditOutboxStore::class)->newInstanceWithoutConstructor();

        return new GenericOutboxAuditWriter(
            new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
            $selector,
            new AuditDeliveryCoordinator($connection, $selector, $records, $outbox),
            $clock,
            new ActorContext(
                new ActorRef('origin-1', 'service'),
                new ActorRef('principal-1', 'user'),
                new ActorRef('execution-1', 'worker'),
            ),
            AuditRecordCorrelation::fromArray([]),
            $runtime,
            'generic-outbox-hook-test',
        );
    }

    private function connection(bool $autoCommit, bool $active, int $level): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('isAutoCommit')->willReturn($autoCommit);
        $connection->method('isTransactionActive')->willReturn($active);
        $connection->method('getTransactionNestingLevel')->willReturn($level);

        return $connection;
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
                ($frame['class'] ?? null) === GenericOutboxAuditHook::class
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
    }
}

final class OutboxHookClock implements ClockInterface
{
    public int $calls = 0;

    public function now(): DateTimeImmutable
    {
        ++$this->calls;

        return new DateTimeImmutable('2026-09-21T12:34:56.123456+09:00');
    }
}
