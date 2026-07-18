<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Internal\Application\ApplicationDatabaseConnectionLifecycle;
use BlackOps\Internal\Application\ApplicationHttpRequestHandler;
use BlackOps\Internal\Application\ApplicationJournalObservations;
use BlackOps\Internal\Database\DoctrineDatabaseManager;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Internal\Journal\JournalObserverAggregator;
use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Journal\FlushableJournalObserver;
use BlackOps\Journal\JournalDeliveryPolicy;
use BlackOps\Journal\ObservedJournalRecord;
use Doctrine\DBAL\Connection;
use LogicException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

final class ApplicationHttpRequestHandlerTest extends TestCase
{
    private ApplicationRequestRecordingLogger $logger;
    private ExecutionScopeProvider $scope;

    public function testFlushesObserversAfterEverySuccessfulRequest(): void
    {
        $observer = new RecordingFlushableObserver();
        $handler = $this->handler(new ReferenceLifecycleRequestHandler([
            new Response(200),
            new Response(201),
        ]), $observer);

        self::assertSame(200, $handler->handle(new ServerRequest('GET', '/first'))->getStatusCode());
        self::assertSame(201, $handler->handle(new ServerRequest('GET', '/second'))->getStatusCode());
        self::assertSame(2, $observer->flushes);
    }

    public function testMiddlewareThrowableReturnsSafeErrorAndDoesNotPoisonNextRequest(): void
    {
        $observer = new RecordingFlushableObserver();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('fetchOne')->with('SELECT 1')->willReturn(1);
        $connection->expects(self::once())->method('close');
        $handler = $this->handler(
            new ReferenceLifecycleRequestHandler([
                new RuntimeException('middleware credential detail'),
                new Response(200),
            ]),
            $observer,
            $connection,
        );

        $failure = $handler->handle(new ServerRequest('GET', '/fails'));

        $this->assertSafeSystemFailure($failure, RuntimeException::class, 'middleware credential detail');
        self::assertSame(200, $handler->handle(new ServerRequest('GET', '/recovers'))->getStatusCode());
        self::assertSame(2, $observer->flushes);
        self::assertNull($this->scope->current());
    }

    public function testServerErrorResponseClosesConnectionAndDoesNotPoisonNextRequest(): void
    {
        $observer = new RecordingFlushableObserver();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('fetchOne')->with('SELECT 1')->willReturn(1);
        $connection->expects(self::once())->method('close');
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $handler = $this->handler(
            new ReferenceLifecycleRequestHandler([new Response(500), new Response(200)]),
            $observer,
            $connection,
        );

        self::assertSame(500, $handler->handle(new ServerRequest('GET', '/fails'))->getStatusCode());
        self::assertSame(200, $handler->handle(new ServerRequest('GET', '/recovers'))->getStatusCode());
        self::assertSame(2, $observer->flushes);
    }

    public function testActiveTransactionReturnsSafeErrorAndClosesConnection(): void
    {
        $observer = new RecordingFlushableObserver();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->with('SELECT 1')->willReturn(1);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('close');
        $handler = $this->handler(new ReferenceLifecycleRequestHandler([new Response(200)]), $observer, $connection);

        $response = $handler->handle(new ServerRequest('GET', '/transaction-leak'));

        $this->assertSafeSystemFailure($response, LogicException::class, 'left a database transaction active');
        self::assertSame(1, $observer->flushes);
    }

    public function testObserverCleanupFailureReturnsSafeErrorAndNextRequestRecovers(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('fetchOne')->with('SELECT 1')->willReturn(1);
        $connection->expects(self::once())->method('close');
        $observer = new FailsOnceFlushableObserver();
        $handler = $this->handler(
            new ReferenceLifecycleRequestHandler([new Response(200), new Response(204)]),
            $observer,
            $connection,
        );

        $failure = $handler->handle(new ServerRequest('GET', '/observer-failure'));

        $this->assertSafeSystemFailure($failure, RuntimeException::class, 'observer credential failed');
        self::assertSame(204, $handler->handle(new ServerRequest('GET', '/recovers'))->getStatusCode());
        self::assertSame(2, $observer->flushes);
        self::assertNull($this->scope->current());
    }

    public function testDatabasePrepareFailureReturnsSafeErrorAndNextRequestRecovers(): void
    {
        $connection = $this->createMock(Connection::class);
        $fetches = 0;
        $connection
            ->expects(self::exactly(3))
            ->method('fetchOne')
            ->with('SELECT 1')
            ->willReturnCallback(static function () use (&$fetches): int {
                ++$fetches;
                if ($fetches <= 2) {
                    throw new RuntimeException('database credential failed');
                }

                return 1;
            });
        $connection->expects(self::exactly(2))->method('close');
        $observer = new RecordingFlushableObserver();
        $handler = $this->handler(new ReferenceLifecycleRequestHandler([new Response(200)]), $observer, $connection);

        $failure = $handler->handle(new ServerRequest('GET', '/prepare-failure'));

        $this->assertSafeSystemFailure($failure, RuntimeException::class, 'database credential failed');
        self::assertSame(200, $handler->handle(new ServerRequest('GET', '/recovers'))->getStatusCode());
        self::assertSame(2, $observer->flushes);
        self::assertNull($this->scope->current());
    }

    private function handler(
        RequestHandlerInterface $handler,
        FlushableJournalObserver $observer,
        ?Connection $connection = null,
    ): ApplicationHttpRequestHandler {
        $connection ??= $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $aggregator = new JournalObserverAggregator([
            new JournalObserverBinding('recording', $observer, JournalDeliveryPolicy::Required),
        ]);

        $psr17 = new \Nyholm\Psr7\Factory\Psr17Factory();
        $this->logger = new ApplicationRequestRecordingLogger();
        $this->scope = new ExecutionScopeProvider();

        return new ApplicationHttpRequestHandler(
            $handler,
            $this->scope,
            new ApplicationDatabaseConnectionLifecycle($this->manager($connection)),
            new ApplicationJournalObservations(
                new JournalObservationPipeline(
                    new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
                    $aggregator,
                ),
                $aggregator,
            ),
            new JsonOperationResponder($psr17, $psr17),
            new ExecutionScopedLogger($this->logger, $this->scope),
        );
    }

    private function assertSafeSystemFailure(ResponseInterface $response, string $failureType, string $secret): void
    {
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('{"status":"error","code":"internal_error"}', (string) $response->getBody());
        self::assertStringNotContainsString($secret, (string) $response->getBody());
        self::assertCount(1, $this->logger->records);
        $context = $this->logger->records[0]['context'];
        self::assertSame('framework', $context['kind']);
        self::assertSame('internal_error', $context['context']['failure']['classification']);
        self::assertSame($failureType, $context['context']['failure']['type']);
        self::assertArrayNotHasKey('operation', $context);
        self::assertStringNotContainsString($secret, serialize($this->logger->records));
    }

    private function manager(Connection $connection): DoctrineDatabaseManager
    {
        $manager = new DoctrineDatabaseManager(
            'app',
            ['app' => []],
            static fn(array $parameters): Connection => $connection,
        );
        $manager->connection();

        return $manager;
    }
}

final class ReferenceLifecycleRequestHandler implements RequestHandlerInterface
{
    /** @param list<ResponseInterface|RuntimeException> $results */
    public function __construct(
        private array $results,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $result = array_shift($this->results);

        if ($result instanceof RuntimeException) {
            throw $result;
        }

        if (!$result instanceof ResponseInterface) {
            throw new RuntimeException('Missing reference response.');
        }

        return $result;
    }
}

final class RecordingFlushableObserver implements FlushableJournalObserver
{
    public int $flushes = 0;

    public function observe(ObservedJournalRecord $record): void {}

    public function flush(): void
    {
        ++$this->flushes;
    }
}

final class FailsOnceFlushableObserver implements FlushableJournalObserver
{
    public int $flushes = 0;

    public function observe(ObservedJournalRecord $record): void {}

    public function flush(): void
    {
        ++$this->flushes;
        if ($this->flushes === 1) {
            throw new RuntimeException('observer credential failed');
        }
    }
}

final class ApplicationRequestRecordingLogger extends AbstractLogger
{
    /** @var list<array{message: string|Stringable, context: array<array-key, mixed>}> */
    public array $records = [];

    /** @param array<array-key, mixed> $context */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['message' => $message, 'context' => $context];
    }
}
