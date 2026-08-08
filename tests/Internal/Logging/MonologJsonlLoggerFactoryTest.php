<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Logging;

use BlackOps\Core\AttemptContext;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationValue;
use BlackOps\Core\ScheduleContext;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use BlackOps\Internal\Logging\MonologJsonlLoggerFactory;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use ReflectionMethod;
use stdClass;
use UnexpectedValueException;

final class MonologJsonlLoggerFactoryTest extends TestCase
{
    private const ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testCreatesPsrLoggerWithConfiguredChannelLevelMessageAndContext(): void
    {
        $stream = $this->stream();
        $logger = new MonologJsonlLoggerFactory()->create($stream, 'application', LogLevel::DEBUG);

        $logger->notice('order persisted', ['orderId' => 'order-123']);

        $contents = $this->contents($stream);
        $record = $this->record($contents);
        self::assertInstanceOf(LoggerInterface::class, $logger);
        self::assertSame(1, substr_count($contents, "\n"));
        self::assertStringEndsWith("\n", $contents);
        self::assertSame('application', $record['channel']);
        self::assertSame('notice', $record['level']);
        self::assertSame('order persisted', $record['message']);
        self::assertSame(['orderId' => 'order-123'], $record['context']);
    }

    public function testUsesDeterministicDefaultsAndFiltersBelowInfo(): void
    {
        $stream = $this->stream();
        $logger = new MonologJsonlLoggerFactory()->create($stream);

        $logger->debug('not written');
        $logger->info('written');

        $record = $this->record($this->contents($stream));
        self::assertSame(MonologJsonlLoggerFactory::DEFAULT_CHANNEL, $record['channel']);
        self::assertSame('info', $record['level']);
        self::assertSame('written', $record['message']);
        self::assertSame(LogLevel::INFO, MonologJsonlLoggerFactory::DEFAULT_LEVEL);
    }

    public function testWritesEveryRecordAsOneJsonLine(): void
    {
        $stream = $this->stream();
        $logger = new MonologJsonlLoggerFactory()->create($stream);

        $logger->info('first');
        $logger->warning('second');

        $contents = $this->contents($stream);
        $lines = explode("\n", rtrim($contents, "\n"));
        self::assertCount(2, $lines);
        self::assertSame('first', $this->record($lines[0])['message']);
        self::assertSame('second', $this->record($lines[1])['message']);
        self::assertSame(2, substr_count($contents, "\n"));
    }

    public function testContextIsAlwaysAnObjectWhileNestedListsRemainLists(): void
    {
        $stream = $this->stream();
        $logger = new MonologJsonlLoggerFactory()->create($stream, minimumLevel: LogLevel::DEBUG);

        $logger->info('list context', ['one', 'two']);

        $record = $this->record($this->contents($stream));
        self::assertSame(['0' => 'one', '1' => 'two'], $record['context']);
        self::assertIsArray($record['context']);
    }

    public function testEmptyContextUsesAnObjectWireShape(): void
    {
        $stream = $this->stream();
        new MonologJsonlLoggerFactory()
            ->create($stream)
            ->info('empty context');

        $decoded = json_decode($this->contents($stream), flags: JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $decoded->context);
    }

    public function testWritesToFilePath(): void
    {
        $path = sys_get_temp_dir() . '/blackops-monolog-jsonl-' . bin2hex(random_bytes(8)) . '.jsonl';
        $this->temporaryFiles[] = $path;
        $logger = new MonologJsonlLoggerFactory()->create($path);

        $logger->error('file output');

        $contents = file_get_contents($path);
        self::assertIsString($contents);
        self::assertSame('file output', $this->record($contents)['message']);
    }

    public function testInvalidStreamInitializationExceptionIsNotHidden(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MonologJsonlLoggerFactory()->create(new stdClass());
    }

    public function testStreamWriteExceptionIsNotHidden(): void
    {
        $parentFile = tempnam(sys_get_temp_dir(), 'blackops-monolog-parent-');
        self::assertIsString($parentFile);
        $this->temporaryFiles[] = $parentFile;
        $logger = new MonologJsonlLoggerFactory()->create($parentFile . '/application.jsonl');

        $this->expectException(UnexpectedValueException::class);

        $logger->error('cannot write');
    }

    public function testExecutionScopedLoggerWritesFilteredOperationContextToJson(): void
    {
        $stream = $this->stream();
        $backend = new MonologJsonlLoggerFactory()->create($stream, 'operations', LogLevel::DEBUG);
        $scope = new ExecutionScopeProvider();
        $logger = new ExecutionScopedLogger($backend, $scope);

        $scope->run(
            self::envelope(),
            static function () use ($logger): void {
                $logger->info('operation log', [
                    'orderId' => 'order-123',
                    'password' => 'plaintext-password',
                    'nested' => [
                        'safe' => 'visible',
                        'accessToken' => 'plaintext-token',
                    ],
                ]);
            },
            'logging.operation',
        );

        $contents = $this->contents($stream);
        $record = $this->record($contents);
        self::assertSame('operation log', $record['message']);
        self::assertSame(self::ID, $record['operation']['id']);
        self::assertSame('logging.operation', $record['operation']['type']);
        self::assertArrayNotHasKey('attemptId', $record['operation']);
        self::assertSame(self::ID, $record['attempt']['id']);
        self::assertSame('inline', $record['operation']['strategy']);
        self::assertSame('order-123', $record['context']['orderId']);
        self::assertSame('visible', $record['context']['nested']['safe']);
        self::assertArrayNotHasKey('password', $record['context']);
        self::assertArrayNotHasKey('accessToken', $record['context']['nested']);
        self::assertSame('application', $record['kind']);
        self::assertSame('info', $record['level']);
        self::assertArrayNotHasKey('datetime', $record);
        self::assertArrayNotHasKey('level_name', $record);
        self::assertArrayNotHasKey('extra', $record);
        self::assertStringNotContainsString('plaintext-password', $contents);
        self::assertStringNotContainsString('plaintext-token', $contents);
    }

    public function testOperationTenantAndScheduleAreMaskedAndUtc(): void
    {
        $stream = $this->stream();
        $scope = new ExecutionScopeProvider();
        $logger = new ExecutionScopedLogger(
            new MonologJsonlLoggerFactory()->create($stream, 'operations', LogLevel::DEBUG),
            $scope,
        );

        $scope->run(
            self::envelope(
                new TenantRef('account', 'tenant-secret-id'),
                new ScheduleContext(
                    'reports.daily',
                    new DateTimeImmutable('2026-07-11T09:10:11.123456', new \DateTimeZone('Asia/Tokyo')),
                    'Asia/Tokyo',
                ),
            ),
            static fn() => $logger->info('scheduled'),
            'logging.operation',
        );

        $jsonl = $this->contents($stream);
        $record = $this->record($jsonl);
        self::assertSame('[masked]', $record['operation']['tenant']['id']);
        self::assertSame('account', $record['operation']['tenant']['type']);
        self::assertSame('reports.daily', $record['operation']['schedule']['name']);
        self::assertSame('2026-07-11T00:10:11.123456Z', $record['operation']['schedule']['scheduledAt']);
        self::assertStringNotContainsString('tenant-secret-id', $jsonl);
    }

    public function testFactoryBoundaryExposesOnlyPsrLoggerAndScalarConfiguration(): void
    {
        $method = new ReflectionMethod(MonologJsonlLoggerFactory::class, 'create');

        self::assertSame(LoggerInterface::class, (string) $method->getReturnType());
        self::assertSame('mixed', (string) $method->getParameters()[0]->getType());
        self::assertSame('string', (string) $method->getParameters()[1]->getType());
        self::assertContains((string) $method->getParameters()[2]->getType(), ['int|string', 'string|int']);
    }

    /**
     * @return resource
     */
    private function stream()
    {
        $stream = fopen('php://temp', 'w+');
        self::assertIsResource($stream);

        return $stream;
    }

    /**
     * @param resource $stream
     */
    private function contents($stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);
        self::assertIsString($contents);

        return $contents;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function record(string $json): array
    {
        $record = json_decode(trim($json), associative: true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($record);

        return $record;
    }

    private static function envelope(?TenantRef $tenant = null, ?ScheduleContext $schedule = null): OperationEnvelope
    {
        return new OperationEnvelope(
            new MonologLoggingOperation(),
            new MonologLoggingValue(),
            new ExecutionContext(
                OperationId::fromString(self::ID),
                new DateTimeImmutable('2026-07-11T00:00:00Z'),
                CorrelationId::fromString(self::ID),
                attempt: new AttemptContext(
                    AttemptId::fromString(self::ID),
                    1,
                    new DateTimeImmutable('2026-07-11T00:00:01Z'),
                ),
                schedule: $schedule,
                tenant: $tenant,
            ),
            new Inline(),
        );
    }
}

final readonly class MonologLoggingOperation implements Operation {}

final readonly class MonologLoggingValue implements OperationValue {}
