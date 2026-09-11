<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Status;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Identifier\Uuidv7Generator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Status\OperationStatusReadExecutionSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class OperationStatusReadExecutionSourceTest extends TestCase
{
    private const string FIRST_UUID = '019f80ab-1000-7000-8000-000000000011';
    private const string SECOND_UUID = '019f80ab-1000-7000-8000-000000000012';
    private const string SENSITIVE_CANARY = 'execution-generator-sensitive-canary';

    public function testConstructionDoesNotAllocateAndEachReadGetsOneValidatedReference(): void
    {
        $generator = new StatusExecutionUuidGenerator([self::FIRST_UUID, self::SECOND_UUID]);
        $source = new OperationStatusReadExecutionSource($generator);

        self::assertSame(0, $generator->calls);
        $first = $source->current();
        $second = $source->current();

        self::assertSame(2, $generator->calls);
        self::assertSame(
            new AuditTypedReference('operation_status_read', self::FIRST_UUID)->toArray(),
            $first->toArray(),
        );
        self::assertSame(
            new AuditTypedReference('operation_status_read', self::SECOND_UUID)->toArray(),
            $second->toArray(),
        );
        self::assertNotSame($first->id, $second->id);
    }

    #[DataProvider('invalidValues')]
    public function testInvalidUuidAndGeneratorFailureBecomeFreshResourceUnavailable(
        ?string $value,
        ?RuntimeException $failure,
    ): void {
        $generator = new StatusExecutionUuidGenerator($value === null ? [] : [$value], $failure);
        $source = new OperationStatusReadExecutionSource($generator);

        try {
            $source->current();
            self::fail('Invalid execution identity was accepted.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
            self::assertSame('Audit delivery failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('execution secret', $exception->getTraceAsString());
        }
        self::assertSame(1, $generator->calls);
    }

    /** @return iterable<string, array{string|null, RuntimeException|null}> */
    public static function invalidValues(): iterable
    {
        yield 'invalid syntax' => ['not-a-uuid', null];
        yield 'uppercase syntax' => ['019F80AB-1000-7000-8000-000000000011', null];
        yield 'generator failure' => [null, new RuntimeException('execution secret')];
    }

    public function testDebugSerializationAndCorruptSnapshotsNeverExposeOrUseDependency(): void
    {
        $generator = new StatusExecutionUuidGenerator([self::FIRST_UUID, self::SENSITIVE_CANARY]);
        $source = new OperationStatusReadExecutionSource($generator);

        self::assertSame([], $source->__debugInfo());
        self::assertSame([], $source->__serialize());
        self::assertStringNotContainsString(self::SENSITIVE_CANARY, print_r($source, return: true));
        self::assertStringNotContainsString(self::SENSITIVE_CANARY, var_export($source, return: true));
        self::assertStringNotContainsString(self::SENSITIVE_CANARY, serialize($source));

        try {
            unserialize(serialize($source), ['allowed_classes' => true]);
            self::fail('Serialized execution source was accepted.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        $broken = new ReflectionClass(OperationStatusReadExecutionSource::class)->newInstanceWithoutConstructor();
        new ReflectionClass(OperationStatusReadExecutionSource::class)
            ->getProperty('snapshot')
            ->setValue($broken, new \SensitiveParameterValue(['wrong' => 'shape']));
        try {
            $broken->current();
            self::fail('Malformed execution source snapshot was accepted.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame(0, $generator->calls);

        $unwrapped = new ReflectionClass(OperationStatusReadExecutionSource::class)->newInstanceWithoutConstructor();
        new ReflectionClass(OperationStatusReadExecutionSource::class)
            ->getProperty('snapshot')
            ->setValue($unwrapped, new \SensitiveParameterValue([
                'identifiers' => $generator,
            ]));
        try {
            $unwrapped->current();
            self::fail('An unwrapped generator was accepted.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame(0, $generator->calls);
    }

    public function testDeclarationAndSensitiveBoundaryAreExact(): void
    {
        $reflection = new ReflectionClass(OperationStatusReadExecutionSource::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertSame(
            ['snapshot'],
            array_map(
                static fn(\ReflectionProperty $property): string => $property->getName(),
                $reflection->getProperties(),
            ),
        );
        self::assertSame('SensitiveParameterValue', $reflection->getProperty('snapshot')->getType()?->getName());
        self::assertCount(
            1,
            $reflection->getMethod('__construct')->getParameters()[0]->getAttributes(\SensitiveParameter::class),
        );
        self::assertCount(
            1,
            $reflection->getMethod('__unserialize')->getParameters()[0]->getAttributes(\SensitiveParameter::class),
        );
    }
}

final class StatusExecutionUuidGenerator implements Uuidv7Generator
{
    public const string CANARY = 'execution-generator-canary';

    public int $calls = 0;

    public function __construct(
        private readonly array $values,
        private readonly ?RuntimeException $failure = null,
    ) {}

    public function generate(): string
    {
        ++$this->calls;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->values[$this->calls - 1] ?? self::CANARY;
    }
}
