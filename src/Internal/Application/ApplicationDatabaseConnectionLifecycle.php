<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use Doctrine\DBAL\Connection;
use LogicException;
use Throwable;

final readonly class ApplicationDatabaseConnectionLifecycle
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function prepare(): void
    {
        try {
            $this->healthCheck();
        } catch (Throwable) {
            $this->connection->close();
            $this->healthCheck();
        }
    }

    public function finishFailedRequest(): void
    {
        $this->connection->close();
    }

    public function finishSuccessfulRequest(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->close();

            throw new LogicException('Application HTTP request left a database transaction active.');
        }
    }

    private function healthCheck(): void
    {
        $this->connection->fetchOne('SELECT 1');
    }
}
