<?php

declare(strict_types=1);

namespace BlackOps\Http;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Execution\Dispatcher;
use BlackOps\Execution\ValidationRejectionRecorder;
use BlackOps\Http\Binding\HttpProtocolException;
use BlackOps\Http\Binding\OperationValueBinder;
use BlackOps\Http\Binding\OperationValueBindingException;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Http\Routing\HttpRouteMatch;
use BlackOps\Http\Routing\HttpRouteRegistry;
use BlackOps\Http\Status\OperationStatusRequestHandler;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Internal\Idempotency\IdempotencyStore;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class OperationRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private HttpRouteRegistry $routes,
        private OperationValueBinder $binder,
        private Dispatcher $dispatcher,
        private JsonOperationResponder $responder,
        private ResponseFactoryInterface $responses,
        private ValidationRejectionRecorder $validation,
        private ?DeferredOperationAcceptor $deferred = null,
        private ?OperationStatusRequestHandler $status = null,
        private ?IdempotencyStore $idempotency = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->status?->matches($request) === true) {
            return $this->status->handle($request);
        }

        $match = $this->routes->match($request->getMethod(), $request->getUri()->getPath());

        if ($match === null) {
            return $this->responses->createResponse(404);
        }

        $idempotencyKey = $this->idempotencyKey($request);
        if ($idempotencyKey instanceof ResponseInterface) {
            return $idempotencyKey;
        }
        if ($idempotencyKey !== null && in_array($request->getMethod(), ['GET', 'HEAD'], strict: true)) {
            return $this->responder->respondProtocolError('idempotency_not_supported');
        }
        if ($idempotencyKey !== null && $match->route->ephemeral === true) {
            return $this->responder->respondProtocolError('idempotency_not_supported');
        }

        if ($this->hasForbiddenGetBody($request)) {
            return $this->responses->createResponse(400);
        }

        $bound = $this->bind($match, $request);
        if ($bound instanceof ResponseInterface) {
            return $bound;
        }

        $rejection = $this->rejectInvalidValue($match, $bound);
        if ($rejection !== null) {
            return $rejection;
        }

        return $this->execute($match, $bound, $this->actorContext($request), $idempotencyKey);
    }

    private function bind(HttpRouteMatch $match, ServerRequestInterface $request): OperationValue|ResponseInterface
    {
        try {
            return $this->binder->bind($match->route->value, $request, $match->pathParameters);
        } catch (HttpProtocolException $exception) {
            return $this->responder->respondProtocolError($exception->errorCode());
        } catch (OperationValueBindingException $exception) {
            $violations = $exception->violations();
            $operationId = $this->validation->rejectBinding($match->route->operation, $violations);

            return $this->responder->respondValidationRejection($operationId, $violations);
        }
    }

    private function rejectInvalidValue(HttpRouteMatch $match, OperationValue $value): ?ResponseInterface
    {
        $violations = $this->validation->validate($value);
        if ($violations === []) {
            return null;
        }

        $operationId = $this->validation->rejectValue($match->route->operation, $value, $violations);

        return $this->responder->respondValidationRejection($operationId, $violations);
    }

    /** @mago-expect lint:halstead */
    private function execute(
        HttpRouteMatch $match,
        OperationValue $value,
        ?ActorContext $actorContext,
        ?IdempotencyKey $idempotencyKey,
    ): ResponseInterface {
        if ($this->deferred !== null && $this->deferred->accepts($match->route->operation)) {
            $result = $idempotencyKey === null
                ? $this->deferred->accept($match->route->operation, $value, $actorContext)
                : $this->deferred->accept($match->route->operation, $value, $actorContext, $idempotencyKey);

            if ($result instanceof DeferredAcknowledgement) {
                $response = $this->responder->respondAcknowledgement($result);

                return $this->persistResponse(
                    $response,
                    $result->operationId(),
                    $idempotencyKey,
                    $result->isReplayed(),
                );
            }

            if ($result->isCompleted()) {
                throw new LogicException('Deferred operation acceptance cannot return a completed result.');
            }

            $response = $this->responder->respond($result);

            return $this->persistResponse($response, $result->operationId(), $idempotencyKey, $result->isReplayed());
        }

        $result = $this->dispatcher->dispatch($match->route->operation, $value, $actorContext, $idempotencyKey);

        $response = $this->responder->respondForRoute($result, $match->route);

        return $this->persistResponse($response, $result->operationId(), $idempotencyKey, $result->isReplayed());
    }

    /** @mago-expect lint:no-boolean-flag-parameter */
    private function persistResponse(
        ResponseInterface $response,
        ?OperationId $operationId,
        ?IdempotencyKey $key,
        bool $replayed,
    ): ResponseInterface {
        if ($key === null || $operationId === null || $this->idempotency === null) {
            return $response;
        }
        if ($replayed) {
            $snapshot = $this->idempotency->response($operationId);

            return $snapshot === null
                ? $this->responder->respond(OperationResult::rejected(RejectionReason::conflict('idempotency_expired')))
                : $this->responder->respondSnapshot($snapshot);
        }
        if (!$this->idempotency->attachResponse($operationId, $this->responder->snapshot($response))) {
            return $this->responder->respondInternalError($operationId);
        }

        return $response;
    }

    private function idempotencyKey(ServerRequestInterface $request): IdempotencyKey|ResponseInterface|null
    {
        $fields = $request->getHeader('Idempotency-Key');
        if ($fields === []) {
            return null;
        }
        if (count($fields) !== 1 || str_contains($fields[0], ',')) {
            return $this->responder->respondProtocolError('invalid_idempotency_key');
        }

        try {
            return new IdempotencyKey($fields[0]);
        } catch (InvalidArgumentException) {
            return $this->responder->respondProtocolError('invalid_idempotency_key');
        }
    }

    private function actorContext(ServerRequestInterface $request): ?ActorContext
    {
        /** @var mixed $attribute */
        $attribute = $request->getAttribute(ActorRef::class);

        return $attribute instanceof ActorRef ? new ActorContext($attribute, $attribute, $attribute) : null;
    }

    private function hasForbiddenGetBody(ServerRequestInterface $request): bool
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], strict: true)) {
            return false;
        }

        return trim((string) $request->getBody()) !== '';
    }
}
