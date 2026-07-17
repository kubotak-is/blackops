<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Database\DoctrineDatabaseManager;
use Doctrine\DBAL\Connection;
use LogicException;
use Throwable;

final readonly class ApplicationDatabaseConnectionLifecycle
{
    public function __construct(
        private DoctrineDatabaseManager $databases,
    ) {}

    public function prepare(): void
    {
        try {
            foreach ($this->databases->generatedConnections() as $connection) {
                $this->healthCheck($connection);
            }
        } catch (Throwable $failure) {
            $this->closeAll();

            throw $failure;
        }
    }

    public function finishFailedInvocation(): void
    {
        $this->closeAll();
    }

    public function finishSuccessfulInvocation(): void
    {
        $leaked = false;

        foreach ($this->databases->generatedConnections() as $connection) {
            try {
                $active = $connection->isTransactionActive();
            } catch (Throwable $failure) {
                $this->closeAll();

                throw $failure;
            }

            if (!$active) {
                continue;
            }

            $leaked = true;
            $this->close($connection);
        }

        if ($leaked) {
            throw new LogicException('Application invocation left a database transaction active.');
        }
    }

    private function healthCheck(Connection $connection): void
    {
        try {
            $connection->fetchOne('SELECT 1');

            return;
        } catch (Throwable $healthFailure) {
            try {
                $connection->close();
            } catch (Throwable) {
                throw $healthFailure;
            }
        }

        $connection->fetchOne('SELECT 1');
    }

    private function closeAll(): void
    {
        foreach ($this->databases->generatedConnections() as $connection) {
            $this->close($connection);
        }
    }

    private function close(Connection $connection): void
    {
        try {
            $connection->close();
        } catch (Throwable) {
            // Closing is best-effort; the original request or attempt failure remains authoritative.
            return;
        }
    }
}
