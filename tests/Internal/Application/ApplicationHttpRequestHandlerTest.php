<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationDatabaseConnectionLifecycle;
use BlackOps\Internal\Application\ApplicationHttpRequestHandler;
use BlackOps\Internal\Application\ApplicationJournalObservations;
use BlackOps\Internal\Database\DoctrineDatabaseManager;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Internal\Journal\JournalObserverAggregator;
use BlackOps\Internal\Journal\JournalObserverBinding;
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
use RuntimeException;

final class ApplicationHttpRequestHandlerTest extends TestCase
{
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

    public function testThrowableClosesConnectionFlushesObserversAndDoesNotPoisonNextRequest(): void
    {
        $observer = new RecordingFlushableObserver();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('fetchOne')->with('SELECT 1')->willReturn(1);
        $connection->expects(self::once())->method('close');
        $handler = $this->handler(
            new ReferenceLifecycleRequestHandler([new RuntimeException('request failed'), new Response(200)]),
            $observer,
            $connection,
        );

        try {
            $handler->handle(new ServerRequest('GET', '/fails'));
            self::fail('The request failure must escape the PSR-15 boundary.');
        } catch (RuntimeException $exception) {
            self::assertSame('request failed', $exception->getMessage());
        }

        self::assertSame(200, $handler->handle(new ServerRequest('GET', '/recovers'))->getStatusCode());
        self::assertSame(2, $observer->flushes);
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

    public function testActiveTransactionClosesConnectionAndBlocksSuccessfulResponse(): void
    {
        $observer = new RecordingFlushableObserver();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->with('SELECT 1')->willReturn(1);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('close');
        $handler = $this->handler(new ReferenceLifecycleRequestHandler([new Response(200)]), $observer, $connection);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('left a database transaction active');

        try {
            $handler->handle(new ServerRequest('GET', '/transaction-leak'));
        } finally {
            self::assertSame(1, $observer->flushes);
        }
    }

    public function testObserverCleanupFailureClosesConnectionAndEscapesRequest(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->with('SELECT 1')->willReturn(1);
        $connection->expects(self::once())->method('close');
        $handler = $this->handler(
            new ReferenceLifecycleRequestHandler([new Response(200)]),
            new FailingFlushableObserver(),
            $connection,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('observer flush failed');

        $handler->handle(new ServerRequest('GET', '/observer-failure'));
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

        return new ApplicationHttpRequestHandler(
            $handler,
            new ExecutionScopeProvider(),
            new ApplicationDatabaseConnectionLifecycle($this->manager($connection)),
            new ApplicationJournalObservations(
                new JournalObservationPipeline(
                    new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
                    $aggregator,
                ),
                $aggregator,
            ),
        );
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

final class FailingFlushableObserver implements FlushableJournalObserver
{
    public function observe(ObservedJournalRecord $record): void {}

    public function flush(): void
    {
        throw new RuntimeException('observer flush failed');
    }
}
