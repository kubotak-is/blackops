<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationDatabaseConnectionLifecycle;
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

        $lifecycle = new ApplicationDatabaseConnectionLifecycle($connection);
        $lifecycle->prepare();
        $lifecycle->finishSuccessfulRequest();
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

        new ApplicationDatabaseConnectionLifecycle($connection)->prepare();
    }

    public function testReconnectFailureIsNotTreatedAsHealthy(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::exactly(2))
            ->method('fetchOne')
            ->with('SELECT 1')
            ->willThrowException(new RuntimeException('database unavailable'));
        $connection->expects(self::once())->method('close');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('database unavailable');

        new ApplicationDatabaseConnectionLifecycle($connection)->prepare();
    }

    public function testFailedRequestClosesConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('close');

        new ApplicationDatabaseConnectionLifecycle($connection)->finishFailedRequest();
    }

    public function testActiveTransactionIsNeverCarriedToNextRequest(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('close');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('left a database transaction active');

        new ApplicationDatabaseConnectionLifecycle($connection)->finishSuccessfulRequest();
    }
}
