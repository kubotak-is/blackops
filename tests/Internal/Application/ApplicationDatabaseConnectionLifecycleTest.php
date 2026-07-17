<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationDatabaseConnectionLifecycle;
use BlackOps\Internal\Database\DoctrineDatabaseManager;
use Doctrine\DBAL\Connection;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApplicationDatabaseConnectionLifecycleTest extends TestCase
{
    public function testHealthyConnectionIsReused(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->with('SELECT 1')->willReturn(1);
        $connection->expects(self::never())->method('close');

        $lifecycle = new ApplicationDatabaseConnectionLifecycle($this->manager(['app' => $connection]));
        $lifecycle->prepare();
        $lifecycle->finishSuccessfulInvocation();
    }

    public function testStaleConnectionIsClosedAndReconnected(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::exactly(2))
            ->method('fetchOne')
            ->with('SELECT 1')
            ->willReturnOnConsecutiveCalls(self::throwException(new RuntimeException('stale connection')), 1);
        $connection->expects(self::once())->method('close');

        new ApplicationDatabaseConnectionLifecycle($this->manager(['app' => $connection]))->prepare();
    }

    public function testReconnectFailureIsNotTreatedAsHealthy(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::exactly(2))
            ->method('fetchOne')
            ->with('SELECT 1')
            ->willThrowException(new RuntimeException('database unavailable'));
        $connection->expects(self::exactly(2))->method('close');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('database unavailable');

        new ApplicationDatabaseConnectionLifecycle($this->manager(['app' => $connection]))->prepare();
    }

    public function testReconnectCloseFailurePreservesHealthFailureAndDoesNotRunRetry(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchOne')
            ->with('SELECT 1')
            ->willThrowException(new RuntimeException('stale connection'));
        $connection
            ->expects(self::exactly(2))
            ->method('close')
            ->willThrowException(new RuntimeException('close failed'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('stale connection');

        new ApplicationDatabaseConnectionLifecycle($this->manager(['app' => $connection]))->prepare();
    }

    public function testFailedRequestClosesConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('close');

        new ApplicationDatabaseConnectionLifecycle($this->manager(['app' => $connection]))->finishFailedInvocation();
    }

    public function testActiveTransactionIsNeverCarriedToNextRequest(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('close');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('left a database transaction active');

        new ApplicationDatabaseConnectionLifecycle($this->manager([
            'app' => $connection,
        ]))->finishSuccessfulInvocation();
    }

    public function testOnlyGeneratedConnectionsAreHealthChecked(): void
    {
        $app = $this->createMock(Connection::class);
        $analytics = $this->createMock(Connection::class);
        $app->expects(self::once())->method('fetchOne')->with('SELECT 1')->willReturn(1);
        $analytics->expects(self::never())->method('fetchOne');
        $manager = $this->manager(['app' => $app, 'analytics' => $analytics], ['app']);

        new ApplicationDatabaseConnectionLifecycle($manager)->prepare();

        self::assertSame([$app], $manager->generatedConnections());
    }

    public function testConnectionClosedAfterFailureIsReconnectedByNextPrepare(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('close');
        $connection->expects(self::once())->method('fetchOne')->with('SELECT 1')->willReturn(1);
        $lifecycle = new ApplicationDatabaseConnectionLifecycle($this->manager(['app' => $connection]));

        $lifecycle->finishFailedInvocation();
        $lifecycle->prepare();
    }

    public function testSuccessfulInvocationInspectsConnectionsGeneratedAfterPrepare(): void
    {
        $app = $this->createMock(Connection::class);
        $analytics = $this->createMock(Connection::class);
        $app->expects(self::once())->method('fetchOne')->with('SELECT 1')->willReturn(1);
        $app->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $analytics->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $manager = $this->manager(['app' => $app, 'analytics' => $analytics], ['app']);
        $lifecycle = new ApplicationDatabaseConnectionLifecycle($manager);

        $lifecycle->prepare();
        $manager->connection('analytics');
        $lifecycle->finishSuccessfulInvocation();
    }

    public function testEveryLeakedConnectionIsClosedBeforeFailFast(): void
    {
        $app = $this->createMock(Connection::class);
        $analytics = $this->createMock(Connection::class);
        $healthy = $this->createMock(Connection::class);
        $app->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $analytics->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $healthy->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $app->expects(self::once())->method('close');
        $analytics->expects(self::once())->method('close');
        $healthy->expects(self::never())->method('close');
        $lifecycle = new ApplicationDatabaseConnectionLifecycle($this->manager([
            'app' => $app,
            'analytics' => $analytics,
            'healthy' => $healthy,
        ]));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Application invocation left a database transaction active.');

        $lifecycle->finishSuccessfulInvocation();
    }

    public function testFailedInvocationClosesEveryGeneratedConnectionBestEffort(): void
    {
        $app = $this->createMock(Connection::class);
        $analytics = $this->createMock(Connection::class);
        $app->expects(self::once())->method('close')->willThrowException(new RuntimeException('close failed'));
        $analytics->expects(self::once())->method('close');

        new ApplicationDatabaseConnectionLifecycle($this->manager([
            'app' => $app,
            'analytics' => $analytics,
        ]))->finishFailedInvocation();
    }

    public function testConnectionStateInspectionFailureClosesEveryConnectionAndPreservesFailure(): void
    {
        $app = $this->createMock(Connection::class);
        $analytics = $this->createMock(Connection::class);
        $app
            ->expects(self::once())
            ->method('isTransactionActive')
            ->willThrowException(new RuntimeException('state inspection failed'));
        $analytics->expects(self::never())->method('isTransactionActive');
        $app->expects(self::once())->method('close');
        $analytics->expects(self::once())->method('close');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('state inspection failed');

        new ApplicationDatabaseConnectionLifecycle($this->manager([
            'app' => $app,
            'analytics' => $analytics,
        ]))->finishSuccessfulInvocation();
    }

    /**
     * @param array<string, Connection> $connections
     * @param list<string>|null $generate
     */
    private function manager(array $connections, ?array $generate = null): DoctrineDatabaseManager
    {
        $parameters = [];
        foreach (array_keys($connections) as $name) {
            $parameters[$name] = ['name' => $name];
        }

        $manager = new DoctrineDatabaseManager(
            array_key_first($connections),
            $parameters,
            static fn(array $parameters): Connection => $connections[$parameters['name']],
        );

        foreach ($generate ?? array_keys($connections) as $name) {
            $manager->connection($name);
        }

        return $manager;
    }
}
