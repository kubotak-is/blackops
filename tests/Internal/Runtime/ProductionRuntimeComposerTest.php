<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Runtime;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Http\Routing\HttpOperationManifest;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Internal\Journal\JournalObserverAggregator;
use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Internal\Registry\OperationMetadataCompiler;
use BlackOps\Internal\Runtime\ProductionRuntimeArtifacts;
use BlackOps\Internal\Runtime\ProductionRuntimeComposer;
use BlackOps\Internal\Runtime\ProductionRuntimeDependencies;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Logging\JsonlJournalObserver;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use Stringable;

final class ProductionRuntimeComposerTest extends TestCase
{
    public function testComposesHttpHandlerDispatcherAndJournalWriterFromRuntimeArtifacts(): void
    {
        $journal = new RuntimeCompositionJournalWriter();
        $psr17 = new Psr17Factory();
        $composition = new ProductionRuntimeComposer()->compose(
            $this->artifacts(),
            new RuntimeCompositionClock(),
            $journal,
            $psr17,
            $psr17,
        );

        $match = $composition->httpRoutes->match('GET', '/composition');
        $response = $composition->httpHandler->handle($psr17->createServerRequest('GET', '/composition'));

        self::assertNotNull($match);
        self::assertSame(204, $response->getStatusCode());
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptSucceeded,
                JournalEvent::OperationCompleted,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $journal->records),
        );
        self::assertInstanceOf(ExecutionScopeProvider::class, $composition->executionScope);
    }

    public function testComposesSharedLoggingScopeAndJournalObservationPipeline(): void
    {
        $journal = new RuntimeCompositionJournalWriter();
        $scope = new ExecutionScopeProvider();
        $innerLogger = new RuntimeCompositionPsrLogger();
        $stream = self::stream();
        $psr17 = new Psr17Factory();
        $handler = new RuntimeLoggingCompositionHandler(new ExecutionScopedLogger($innerLogger, $scope));

        new ProductionRuntimeComposer()
            ->composeWithDependencies(
                $this->artifacts($handler),
                new ProductionRuntimeDependencies(
                    new RuntimeCompositionClock(),
                    $journal,
                    $psr17,
                    $psr17,
                    $scope,
                    new JournalObservationPipeline(
                        new ObservedJournalRecordProjector(new SensitiveProjectionFilter('projection-key')),
                        new JournalObserverAggregator([
                            new JournalObserverBinding('jsonl', new JsonlJournalObserver($stream)),
                        ]),
                    ),
                ),
            )
            ->httpHandler->handle($psr17->createServerRequest('GET', '/composition'));

        rewind($stream);
        $lines = array_values(array_filter(explode("\n", stream_get_contents($stream))));

        self::assertCount(4, $lines);
        self::assertCount(1, $innerLogger->records);
        self::assertSame('runtime.composition', $innerLogger->records[0]['context']['operation']['type']);
        self::assertSame(
            'runtime.composition',
            json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR)['operation']['type'],
        );
    }

    private function artifacts(?OperationHandler $handler = null): ProductionRuntimeArtifacts
    {
        return new ProductionRuntimeArtifacts(
            new OperationRegistry([new OperationMetadataCompiler()->compile(RuntimeCompositionOperation::class)]),
            new HttpOperationManifest([
                'GET' => [
                    '/composition' => 'runtime.composition',
                ],
            ], [
                'runtime.composition' => [
                    'definition' => RuntimeCompositionOperation::class,
                    'value' => RuntimeCompositionValue::class,
                    'handler' => RuntimeCompositionHandler::class,
                    'outcome' => EmptyOutcome::class,
                    'strategy' => Inline::class,
                ],
            ]),
            new RuntimeCompositionContainer($handler ?? new RuntimeCompositionHandler()),
        );
    }

    /**
     * @return resource
     */
    private static function stream(): mixed
    {
        $stream = fopen('php://temp', 'r+b');
        self::assertIsResource($stream);

        return $stream;
    }
}

final readonly class RuntimeCompositionClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-10T00:00:00+00:00');
    }
}

final class RuntimeCompositionJournalWriter implements CanonicalJournalWriter
{
    /** @var list<JournalRecord> */
    public array $records = [];

    public function append(JournalRecord $record): void
    {
        $this->records[] = $record;
    }
}

final readonly class RuntimeCompositionContainer implements ContainerInterface
{
    public function __construct(
        private OperationHandler $handler,
    ) {}

    public function get(string $id): mixed
    {
        return $this->handler;
    }

    public function has(string $id): bool
    {
        return $id === RuntimeCompositionHandler::class;
    }
}

#[OperationType('runtime.composition')]
#[Accepts(RuntimeCompositionValue::class)]
#[HandledBy(RuntimeCompositionHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class RuntimeCompositionOperation implements Operation {}

final readonly class RuntimeCompositionValue implements OperationValue {}

final readonly class RuntimeCompositionHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}

final readonly class RuntimeLoggingCompositionHandler implements OperationHandler
{
    public function __construct(
        private ExecutionScopedLogger $logger,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        $this->logger->info('runtime handler', ['safe' => 'ok']);

        return OperationResult::completed();
    }
}

final class RuntimeCompositionPsrLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string|Stringable, context: array<array-key, mixed>}>
     */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
