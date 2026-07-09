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
use BlackOps\Internal\Registry\OperationMetadataCompiler;
use BlackOps\Internal\Runtime\ProductionRuntimeArtifacts;
use BlackOps\Internal\Runtime\ProductionRuntimeComposer;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

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
    }

    private function artifacts(): ProductionRuntimeArtifacts
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
            new RuntimeCompositionContainer(new RuntimeCompositionHandler()),
        );
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
