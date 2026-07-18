<?php

declare(strict_types=1);

namespace BlackOps\Internal\Http;

use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Internal\Execution\OperationExecutionFailed;
use BlackOps\Internal\Logging\FrameworkOperationFailureReporter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class OperationFailureErrorBoundary implements RequestHandlerInterface
{
    public function __construct(
        private RequestHandlerInterface $handler,
        private JsonOperationResponder $responder,
        private FrameworkOperationFailureReporter $failures,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            return $this->handler->handle($request);
        } catch (OperationExecutionFailed $failure) {
            $this->failures->report($failure);

            return $this->responder->respondInternalError($failure->operationId());
        }
    }
}
