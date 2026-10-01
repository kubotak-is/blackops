<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Authentication;

use BlackOps\Core\ActorRef;
use BlackOps\Core\TenantRef;
use BlackOps\Http\Authentication\AuthenticationResult;
use BlackOps\Identifier\Uuidv7Generator;
use BlackOps\Internal\Audit\Authentication\DurableAuthenticationAuditEventWriter;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionClass;
use RuntimeException;
use Throwable;

final class DurableAuthenticationAuditEventWriterTest extends TestCase
{
    private const string UUID = '019f80ab-4000-7000-8000-000000000011';
    private const string BUILD_ID = 'writer-build-1';
    private const string CANARY = 'authentication-writer-private-canary';

    public function testAnonymousIsRejectedBeforeAnyDurableIdentityWork(): void
    {
        $generator = new AuthenticationWriterUuidGenerator([self::UUID]);
        $clock = new AuthenticationWriterClock();
        $writer = $this->writer($generator, $clock);

        $this->assertFailure(
            static function () use ($writer): void {
                $writer->write(AuthenticationResult::anonymous());
            },
            AuditDeliveryFailure::PolicyInvalid,
        );
        self::assertSame(0, $generator->calls);
        self::assertSame(0, $clock->calls);
    }

    public function testInvalidBuildIdFailsAtConstruction(): void
    {
        $generator = new AuthenticationWriterUuidGenerator([self::UUID]);
        $clock = new AuthenticationWriterClock();

        $this->assertFailure(
            fn(): DurableAuthenticationAuditEventWriter => new DurableAuthenticationAuditEventWriter(
                $this->coordinator(),
                new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
                $generator,
                $clock,
                "bad\u{0000}build",
            ),
            AuditDeliveryFailure::ConfigurationInvalid,
        );
        self::assertSame(0, $generator->calls);
        self::assertSame(0, $clock->calls);
    }

    public function testGeneratorFailureAndInvalidIdentityAreFreshAndOrdered(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            $throwing = new AuthenticationWriterUuidGenerator([], new RuntimeException(self::CANARY));
            $clock = new AuthenticationWriterClock();
            $writer = $this->writer($throwing, $clock);
            try {
                $writer->write(AuthenticationResult::invalid('credentials.invalid'));
                self::fail('generator failure was accepted');
            } catch (AuditDeliveryException $failure) {
                $this->assertFailureValueFree($failure, AuditDeliveryFailure::ResourceUnavailable);
            }
            self::assertSame(1, $throwing->calls);
            self::assertSame(0, $clock->calls);

            $invalid = new AuthenticationWriterUuidGenerator(['019f80ab-4000-6000-8000-000000000011']);
            $clock = new AuthenticationWriterClock();
            $writer = $this->writer($invalid, $clock);
            $this->assertFailure(
                static function () use ($writer): void {
                    $writer->write(AuthenticationResult::invalid('credentials.invalid'));
                },
                AuditDeliveryFailure::ConfigurationInvalid,
            );
            self::assertSame(1, $invalid->calls);
            self::assertSame(0, $clock->calls);
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testClockFailureIsFreshAndDoesNotReachTheUninitializedCoordinator(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            $generator = new AuthenticationWriterUuidGenerator([self::UUID]);
            $clock = new AuthenticationWriterClock(new RuntimeException(self::CANARY));
            $writer = $this->writer($generator, $clock);

            try {
                $writer->write(AuthenticationResult::invalid('credentials.invalid'));
                self::fail('clock failure was accepted');
            } catch (AuditDeliveryException $failure) {
                $this->assertFailureValueFree($failure, AuditDeliveryFailure::ResourceUnavailable);
            }
            self::assertSame(1, $generator->calls);
            self::assertSame(1, $clock->calls);
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testKnownDeliveryFailureIsRemappedFreshWithoutItsLegacyTrace(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            $original = null;
            $generator = new AuthenticationWriterUuidGenerator([self::UUID]);
            $clock = new AuthenticationWriterClock();
            $writer = new DurableAuthenticationAuditEventWriter(
                $this->coordinatorWithKnownFailure($original, self::CANARY),
                new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
                $generator,
                $clock,
                self::BUILD_ID,
            );

            try {
                $writer->write(AuthenticationResult::invalid('credentials.invalid'));
                self::fail('known delivery failure was accepted');
            } catch (AuditDeliveryException $mapped) {
                self::assertInstanceOf(AuditDeliveryException::class, $original);
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $original->failure());
                self::assertNull($original->getPrevious());
                $originalFrame = $this->traceFrame($original->getTrace(), 'deliveryFailureWithCanary');
                self::assertSame(self::class, $originalFrame['class'] ?? null);
                self::assertSame(self::CANARY, $originalFrame['args'][0] ?? null);
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $mapped->failure());
                self::assertNotSame($original, $mapped);
                self::assertNull($mapped->getPrevious());
                $mappedTrace = var_export($mapped->getTrace(), true);
                self::assertStringNotContainsString(self::CANARY, $mappedTrace);
                self::assertStringNotContainsString('deliveryFailureWithCanary', $mappedTrace);
                self::assertStringNotContainsString(AuditDeliveryException::class, $mappedTrace);
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testAuthenticatedGlobalAndTenantResultsReachOneStandaloneDeliveryAttempt(): void
    {
        foreach ([
            AuthenticationResult::authenticated(new ActorRef('actor-global', 'user')),
            AuthenticationResult::authenticated(
                new ActorRef('actor-tenant', 'user'),
                new TenantRef('account', 'tenant-a'),
            ),
            AuthenticationResult::invalid('credentials.invalid'),
        ] as $result) {
            $generator = new AuthenticationWriterUuidGenerator([self::UUID]);
            $clock = new AuthenticationWriterClock();
            $writer = $this->writer($generator, $clock);

            $this->assertFailure(
                static function () use ($writer, $result): void {
                    $writer->write($result);
                },
                AuditDeliveryFailure::StorageUnavailable,
            );
            self::assertSame(1, $generator->calls);
            self::assertSame(1, $clock->calls);
        }
    }

    public function testMalformedStoredSnapshotFailsClosedBeforeDependencyUse(): void
    {
        $generator = new AuthenticationWriterUuidGenerator([self::UUID]);
        $clock = new AuthenticationWriterClock();
        $writer = $this->writer($generator, $clock);
        $reflection = new ReflectionClass($writer);
        $reflection->getProperty('snapshot')->setValue($writer, new \SensitiveParameterValue([
            'wrong' => self::CANARY,
        ]));

        $this->assertFailure(
            static function () use ($writer): void {
                $writer->write(AuthenticationResult::invalid('credentials.invalid'));
            },
            AuditDeliveryFailure::StorageUnavailable,
        );
        self::assertSame(0, $generator->calls);
        self::assertSame(0, $clock->calls);
    }

    public function testBuildIdCorruptionFailsAsStorageWithoutGeneratingAnEvent(): void
    {
        $generator = new AuthenticationWriterUuidGenerator([self::UUID]);
        $clock = new AuthenticationWriterClock();
        $writer = $this->writer($generator, $clock);
        $reflection = new ReflectionClass($writer);
        $reflection->getProperty('snapshot')->setValue($writer, new \SensitiveParameterValue([
            'delivery' => $this->coordinator(),
            'opaqueIds' => new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
            'executions' => $generator,
            'clock' => $clock,
            'buildId' => self::CANARY . "\u{0000}",
        ]));

        $this->assertFailure(
            static function () use ($writer): void {
                $writer->write(AuthenticationResult::invalid('credentials.invalid'));
            },
            AuditDeliveryFailure::StorageUnavailable,
        );
        self::assertSame(0, $generator->calls);
        self::assertSame(0, $clock->calls);
    }

    public function testSnapshotsAndUnserializationCannotExposeDependencies(): void
    {
        $generator = new AuthenticationWriterUuidGenerator([], null, self::CANARY);
        $clock = new AuthenticationWriterClock(null, self::CANARY);
        $writer = $this->writer($generator, $clock);

        self::assertSame([], $writer->__debugInfo());
        self::assertSame([], $writer->__serialize());
        $surface =
            print_r($writer, true)
            . var_export($writer, true)
            . serialize($writer)
            . json_encode($writer, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString(self::CANARY, $surface);

        try {
            unserialize(serialize($writer), ['allowed_classes' => true]);
            self::fail('writer unserialization was accepted');
        } catch (AuditDeliveryException $failure) {
            $this->assertFailureValueFree($failure, AuditDeliveryFailure::StorageUnavailable);
        }
        self::assertSame(0, $generator->calls);
    }

    private static function deliveryFailureWithCanary(
        string $canary,
        AuditDeliveryFailure $failure,
    ): AuditDeliveryException {
        if ($canary === '') {
            throw new RuntimeException('missing delivery failure canary');
        }

        return new AuditDeliveryException($failure);
    }

    private function coordinatorWithKnownFailure(
        ?AuditDeliveryException &$original,
        string $canary,
    ): AuditDeliveryCoordinator {
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('isAutoCommit')
            ->willReturnCallback(function () use (&$original, $canary): bool {
                $failure = self::deliveryFailureWithCanary($canary, AuditDeliveryFailure::PolicyInvalid);
                $original = $failure;
                throw $failure;
            });
        $catalog = FrameworkAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $selector = new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
        $records = new ReflectionClass(PostgreSqlAuditRecordStore::class)->newInstanceWithoutConstructor();
        $outbox = new ReflectionClass(PostgreSqlAuditOutboxStore::class)->newInstanceWithoutConstructor();

        return new AuditDeliveryCoordinator($connection, $selector, $records, $outbox);
    }

    private function writer(
        AuthenticationWriterUuidGenerator $generator,
        AuthenticationWriterClock $clock,
    ): DurableAuthenticationAuditEventWriter {
        return new DurableAuthenticationAuditEventWriter(
            $this->coordinator(),
            new HmacAuditOpaqueIdDeriver(str_repeat('a', 32)),
            $generator,
            $clock,
            self::BUILD_ID,
        );
    }

    private function coordinator(): AuditDeliveryCoordinator
    {
        return new ReflectionClass(AuditDeliveryCoordinator::class)->newInstanceWithoutConstructor();
    }

    private function assertFailure(\Closure $operation, AuditDeliveryFailure $expected): void
    {
        try {
            $operation();
            self::fail('expected audit delivery failure');
        } catch (AuditDeliveryException $failure) {
            $this->assertFailureValueFree($failure, $expected);
        }
    }

    private function assertFailureValueFree(AuditDeliveryException $failure, AuditDeliveryFailure $expected): void
    {
        self::assertSame($expected, $failure->failure());
        self::assertSame('Audit delivery failed.', $failure->getMessage());
        self::assertSame(0, $failure->getCode());
        self::assertNull($failure->getPrevious());
        self::assertStringNotContainsString(self::CANARY, $failure->getTraceAsString());
        self::assertStringNotContainsString(self::CANARY, var_export($failure->getTrace(), true));
    }

    /** @param list<array<string,mixed>> $trace */
    private function traceFrame(array $trace, string $function): array
    {
        foreach ($trace as $frame) {
            if (($frame['function'] ?? null) === $function) {
                return $frame;
            }
        }

        throw new RuntimeException('Expected trace frame was not found.');
    }
}

final class AuthenticationWriterUuidGenerator implements Uuidv7Generator
{
    public int $calls = 0;

    /** @param list<string> $values */
    public function __construct(
        private readonly array $values,
        private readonly ?Throwable $failure = null,
        public readonly string $canary = '',
    ) {}

    public function generate(): string
    {
        ++$this->calls;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->values[$this->calls - 1] ?? '019f80ab-4000-7000-8000-000000000099';
    }
}

final class AuthenticationWriterClock implements ClockInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly ?Throwable $failure = null,
        public readonly string $canary = '',
    ) {}

    public function now(): \DateTimeImmutable
    {
        ++$this->calls;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new \DateTimeImmutable('2026-09-15T12:34:56.123456+09:00');
    }
}
