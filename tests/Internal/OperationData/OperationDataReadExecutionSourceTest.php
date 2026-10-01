<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\OperationData;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Identifier\Uuidv7Generator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\OperationData\OperationJournalReadExecutionSource;
use BlackOps\Internal\OperationData\OperationOutcomeReadExecutionSource;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeCodec;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Throwable;

final class OperationDataReadExecutionSourceTest extends TestCase
{
    private const string FIRST_UUID = '019f80ab-1000-7000-8000-000000000011';
    private const string SECOND_UUID = '019f80ab-1000-7000-8000-000000000012';

    public function testEachResourceSourceAllocatesOnlyOnReadAndUsesIndependentReferences(): void
    {
        $journalGenerator = new OperationDataExecutionUuidGenerator([
            self::FIRST_UUID,
            self::SECOND_UUID,
        ]);
        $journal = new OperationJournalReadExecutionSource($journalGenerator);
        self::assertSame(0, $journalGenerator->calls);

        $first = $journal->current();
        $second = $journal->current();

        self::assertSame(2, $journalGenerator->calls);
        self::assertSame(
            new AuditTypedReference('operation_journal_read', self::FIRST_UUID)->toArray(),
            $first->toArray(),
        );
        self::assertSame(
            new AuditTypedReference('operation_journal_read', self::SECOND_UUID)->toArray(),
            $second->toArray(),
        );
        self::assertNotSame($first->id, $second->id);

        $outcomeGenerator = new OperationDataExecutionUuidGenerator([self::FIRST_UUID]);
        $outcome = new OperationOutcomeReadExecutionSource($outcomeGenerator);
        self::assertSame(0, $outcomeGenerator->calls);
        self::assertSame(
            new AuditTypedReference('operation_outcome_read', self::FIRST_UUID)->toArray(),
            $outcome->current()->toArray(),
        );
        self::assertSame(1, $outcomeGenerator->calls);
    }

    #[DataProvider('invalidValues')]
    public function testInvalidUuidAndGeneratorFailureBecomeFreshResourceUnavailable(
        ?string $value,
        ?Throwable $failure,
    ): void {
        $generator = new OperationDataExecutionUuidGenerator($value === null ? [] : [$value], $failure);
        $source = new OperationJournalReadExecutionSource($generator);

        try {
            $source->current();
            self::fail('An invalid execution identity was accepted.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
            self::assertSame('Audit delivery failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame(1, $generator->calls);
    }

    /** @return iterable<string, array{string|null, Throwable|null}> */
    public static function invalidValues(): iterable
    {
        yield 'invalid syntax' => ['not-a-uuid', null];
        yield 'uppercase syntax' => ['019F80AB-1000-7000-8000-000000000011', null];
        yield 'generator failure' => [null, new RuntimeException('execution source secret')];
    }

    public function testDebugSerializationAndCorruptSnapshotsDoNotExposeOrUseDependency(): void
    {
        $generator = new OperationDataExecutionUuidGenerator([self::FIRST_UUID, self::SECOND_UUID]);
        $source = new OperationOutcomeReadExecutionSource($generator);

        self::assertSame([], $source->__debugInfo());
        self::assertSame([], $source->__serialize());
        $serialized =
            print_r($source, return: true)
            . var_export($source, return: true)
            . serialize($source)
            . json_encode($source, JSON_THROW_ON_ERROR);
        foreach ([self::FIRST_UUID, self::SECOND_UUID] as $canary) {
            self::assertStringNotContainsString($canary, $serialized);
        }

        try {
            unserialize(serialize($source), ['allowed_classes' => true]);
            self::fail('Execution source unserialization was accepted.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame(0, $generator->calls);

        $broken = new ReflectionClass(OperationOutcomeReadExecutionSource::class)->newInstanceWithoutConstructor();
        new ReflectionClass(OperationOutcomeReadExecutionSource::class)
            ->getProperty('snapshot')
            ->setValue($broken, new \SensitiveParameterValue(['wrong' => 'shape']));
        try {
            $broken->current();
            self::fail('A malformed execution source snapshot was accepted.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame(0, $generator->calls);
    }

    public function testOutcomeCodecAutoloaderThrowableHidesDecodePayloadInFrameworkTrace(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        $type = __NAMESPACE__ . '\\UnloadedOutcomeTraceCanary';
        $payloadCanary = 'outcome-payload-private-canary';
        $payload = json_encode(['__class' => $type, 'private' => $payloadCanary], JSON_THROW_ON_ERROR);
        $loader = static function (string $class) use ($type, $payloadCanary): void {
            if ($class === $type) {
                throw new RuntimeException($payloadCanary);
            }
        };
        spl_autoload_register($loader);

        try {
            try {
                new PostgreSqlOutcomeCodec()->decode($type, PostgreSqlOutcomeCodec::SCHEMA_VERSION, $payload);
                self::fail('The autoloader throwable was swallowed.');
            } catch (RuntimeException $exception) {
                self::assertSame($payloadCanary, $exception->getMessage());
                $frameworkFrames = array_filter($exception->getTrace(), static fn(array $frame): bool => str_starts_with(
                    (string) ($frame['class'] ?? ''),
                    'BlackOps\\Transport\\PostgreSql\\',
                ));
                self::assertNotEmpty($frameworkFrames);
                $trace = print_r($frameworkFrames, return: true) . var_export($frameworkFrames, true);
                self::assertStringNotContainsString($payloadCanary, $trace);
            }
        } finally {
            spl_autoload_unregister($loader);
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }
}

final class OperationDataExecutionUuidGenerator implements Uuidv7Generator
{
    public int $calls = 0;

    /** @param list<string> $values */
    public function __construct(
        private readonly array $values,
        private readonly ?Throwable $failure = null,
    ) {}

    public function generate(): string
    {
        ++$this->calls;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->values[$this->calls - 1] ?? '019f80ab-1000-7000-8000-000000000099';
    }
}
