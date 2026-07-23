<?php

declare(strict_types=1);

namespace BlackOps\Internal\Http;

use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Internal\Execution\OperationExecutionFailed;
use BlackOps\Internal\Idempotency\IdempotencyReplayFailure;
use BlackOps\Internal\Idempotency\IdempotencyStore;
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
        private ?IdempotencyStore $idempotency = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            return $this->handler->handle($request);
        } catch (IdempotencyReplayFailure $failure) {
            return $this->responder
                ->respondInternalError($failure->operationId())
                ->withHeader('Idempotency-Replayed', 'true')
                ->withHeader('Cache-Control', 'private, no-store');
        } catch (OperationExecutionFailed $failure) {
            $this->failures->report($failure);

            $response = $this->responder->respondInternalError($failure->operationId());
            if ($this->idempotency !== null && $request->getHeader('Idempotency-Key') !== []) {
                $this->idempotency->attachResponse($failure->operationId(), $this->responder->snapshot($response));
            }

            return $response;
        }
    }
}
