<?php

declare(strict_types=1);

namespace BlackOps\Internal\Transaction;

use BlackOps\Database\AfterCommitFailure;
use BlackOps\Database\AfterCommitFailureReporter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final readonly class DefaultAfterCommitFailureReporter implements AfterCommitFailureReporter
{
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new Logger('blackops.after_commit', [new StreamHandler(
            'php://stderr',
            Level::Error,
        )]);
    }

    public function report(AfterCommitFailure $failure): void
    {
        $context = $failure->context();
        $attempt = $context?->attempt();

        $this->logger->error('An after-commit callback failed.', [
            'kind' => 'application',
            'service' => $failure->serviceClass(),
            'method' => $failure->method(),
            'operationId' => $context?->operationId()->toString(),
            'attemptId' => $attempt?->id()->toString(),
            'correlationId' => $context?->correlationId()->toString(),
            'causationId' => $context?->causationId()?->toString(),
        ]);
    }
}
