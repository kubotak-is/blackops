<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final readonly class ApplicationHttpRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private RequestHandlerInterface $handler,
        private ExecutionScopeProvider $scope,
        private ApplicationDatabaseConnectionLifecycle $connection,
        private ?ApplicationJournalObservations $observations,
        private JsonOperationResponder $responder,
        private ExecutionScopedLogger $logger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = null;
        $failure = null;
        $prepared = false;

        try {
            $this->connection->prepare();
            $prepared = true;
            $response = $this->handler->handle($request);
        } catch (Throwable $exception) {
            $failure = $exception;
        }

        try {
            $this->finishRequestState();
        } catch (Throwable $exception) {
            $failure ??= $exception;
        }

        try {
            $failedInvocation =
                $failure !== null || $response instanceof ResponseInterface && $response->getStatusCode() >= 500;

            if ($failedInvocation && $prepared) {
                $this->connection->finishFailedInvocation();
            }

            if (!$failedInvocation) {
                $this->connection->finishSuccessfulInvocation();
            }
        } catch (Throwable $exception) {
            $failure ??= $exception;
        }

        if ($failure !== null) {
            $this->logger->frameworkSystemError($failure::class);

            return $this->responder->respondUncorrelatedInternalError();
        }

        if (!$response instanceof ResponseInterface) {
            $this->logger->frameworkSystemError(LogicException::class);

            return $this->responder->respondUncorrelatedInternalError();
        }

        return $response;
    }

    private function finishRequestState(): void
    {
        $failure = null;

        if ($this->scope->current() !== null || $this->scope->currentOperationTypeId() !== null) {
            $failure = new LogicException('Application HTTP request left an operation scope active.');
        }

        try {
            $this->observations?->flush();
        } catch (Throwable $exception) {
            $failure ??= $exception;
        }

        if ($failure !== null) {
            throw $failure;
        }
    }
}
