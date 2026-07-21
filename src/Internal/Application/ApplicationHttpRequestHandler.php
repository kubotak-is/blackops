<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final readonly class ApplicationHttpRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private RequestHandlerInterface $handler,
        private ApplicationOperationInvocationLifecycle $lifecycle,
        private JsonOperationResponder $responder,
        private ExecutionScopedLogger $logger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            return $this->lifecycle->run(
                fn(): ResponseInterface => $this->handler->handle($request),
                static fn(ResponseInterface $response): bool => $response->getStatusCode() >= 500,
            );
        } catch (Throwable $exception) {
            $this->logger->frameworkSystemError($exception::class);

            return $this->responder->respondUncorrelatedInternalError();
        }
    }
}
