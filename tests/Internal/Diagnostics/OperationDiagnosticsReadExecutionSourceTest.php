<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Diagnostics;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Identifier\Uuidv7Generator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsReadExecutionSource;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Throwable;

final class OperationDiagnosticsReadExecutionSourceTest extends TestCase
{
    private const string FIRST_UUID = '019f80ab-4000-7000-8000-000000000011';
    private const string SECOND_UUID = '019f80ab-4000-7000-8000-000000000012';
    private const string DEPENDENCY_CANARY = 'diagnostics-execution-private-canary';

    public function testEachReadGeneratesOneIndependentValidatedDiagnosticsReference(): void
    {
        $generator = new DiagnosticsExecutionUuidGenerator(
            [
                self::FIRST_UUID,
                self::SECOND_UUID,
            ],
            self::DEPENDENCY_CANARY,
        );
        $source = new OperationDiagnosticsReadExecutionSource($generator);

        self::assertSame(0, $generator->calls);
        $first = $source->current();
        $second = $source->current();

        self::assertSame(2, $generator->calls);
        self::assertSame(
            new AuditTypedReference('operation_diagnostics_read', self::FIRST_UUID)->toArray(),
            $first->toArray(),
        );
        self::assertSame(
            new AuditTypedReference('operation_diagnostics_read', self::SECOND_UUID)->toArray(),
            $second->toArray(),
        );
        self::assertNotSame($first->id, $second->id);
    }

    public function testInvalidAndThrowingGeneratorsBecomeFreshResourceUnavailable(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        $throwingFailure = new RuntimeException(self::DEPENDENCY_CANARY);
        $cases = [
            new DiagnosticsExecutionUuidGenerator(['not-a-uuid'], self::DEPENDENCY_CANARY),
            new DiagnosticsExecutionUuidGenerator(
                ['019f80ab-4000-7000-8000-000000000013'],
                self::DEPENDENCY_CANARY,
                $throwingFailure,
            ),
        ];

        try {
            foreach ($cases as $generator) {
                $source = new OperationDiagnosticsReadExecutionSource($generator);

                try {
                    $source->current();
                    self::fail('An invalid diagnostics execution reference was accepted.');
                } catch (AuditDeliveryException $exception) {
                    $this->assertFreshFailure($exception);
                    self::assertNotSame($throwingFailure, $exception);
                    $this->assertFailureSurfaceOmits($exception, self::DEPENDENCY_CANARY);
                }
                self::assertSame(1, $generator->calls);
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testSnapshotSurfacesRoundTripCorruptionAndCloneDoNotExposeOrUseDependency(): void
    {
        $generator = new DiagnosticsExecutionUuidGenerator(
            [
                self::FIRST_UUID,
                self::SECOND_UUID,
            ],
            self::DEPENDENCY_CANARY,
        );
        $source = new OperationDiagnosticsReadExecutionSource($generator);

        self::assertSame([], $source->__debugInfo());
        self::assertSame([], $source->__serialize());
        $surface =
            print_r($source, true)
            . var_export($source, true)
            . serialize($source)
            . json_encode($source, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString(self::DEPENDENCY_CANARY, $surface);
        self::assertSame(0, $generator->calls);

        try {
            unserialize(serialize($source), ['allowed_classes' => true]);
            self::fail('Execution source unserialization was accepted.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame(0, $generator->calls);

        $reflection = new ReflectionClass($source);
        $snapshot = $reflection->getProperty('snapshot')->getValue($source);
        self::assertInstanceOf(\SensitiveParameterValue::class, $snapshot);
        $values = $snapshot->getValue();
        self::assertIsArray($values);
        /** @var array<string, mixed> $values */
        $values['extra'] = self::DEPENDENCY_CANARY;
        $broken = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('snapshot')->setValue($broken, new \SensitiveParameterValue($values));
        $uninitialized = $reflection->newInstanceWithoutConstructor();
        try {
            $uninitialized->current();
            self::fail('An uninitialized execution source snapshot was accepted.');
        } catch (AuditDeliveryException $exception) {
            $this->assertFreshFailure($exception);
        }
        self::assertSame(0, $generator->calls);
        try {
            $broken->current();
            self::fail('A malformed execution source snapshot was accepted.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame(0, $generator->calls);

        $first = $source->current();
        $clone = clone $source;
        self::assertSame([], $clone->__debugInfo());
        self::assertSame([], $clone->__serialize());
        self::assertSame(1, $generator->calls);
        self::assertSame(
            new AuditTypedReference('operation_diagnostics_read', self::FIRST_UUID)->toArray(),
            $first->toArray(),
        );
        self::assertSame(
            new AuditTypedReference('operation_diagnostics_read', self::SECOND_UUID)->toArray(),
            $clone->current()->toArray(),
        );
        self::assertSame(2, $generator->calls);
    }

    private function assertFreshFailure(AuditDeliveryException $exception): void
    {
        self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
        self::assertSame('Audit delivery failed.', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }

    private function assertFailureSurfaceOmits(Throwable $failure, string $canary): void
    {
        $surface =
            print_r($failure, true)
            . var_export($failure, true)
            . serialize($failure)
            . (string) json_encode($failure, JSON_THROW_ON_ERROR)
            . $failure->getTraceAsString()
            . print_r($failure->getTrace(), true)
            . var_export($failure->getTrace(), true);
        self::assertStringNotContainsString($canary, $surface);
    }
}

final class DiagnosticsExecutionUuidGenerator implements Uuidv7Generator
{
    public int $calls = 0;

    /** @param list<string> $values */
    public function __construct(
        private readonly array $values,
        public readonly string $canary,
        private readonly ?Throwable $failure = null,
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
