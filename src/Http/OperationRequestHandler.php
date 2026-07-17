<?php

declare(strict_types=1);

namespace BlackOps\Http;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\OperationValue;
use BlackOps\Execution\Dispatcher;
use BlackOps\Execution\ValidationRejectionRecorder;
use BlackOps\Http\Binding\HttpProtocolException;
use BlackOps\Http\Binding\OperationValueBinder;
use BlackOps\Http\Binding\OperationValueBindingException;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Http\Routing\HttpRouteMatch;
use BlackOps\Http\Routing\HttpRouteRegistry;
use LogicException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $match = $this->routes->match($request->getMethod(), $request->getUri()->getPath());

        if ($match === null) {
            return $this->responses->createResponse(404);
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

        return $this->execute($match, $bound, $this->actorContext($request));
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

    private function execute(
        HttpRouteMatch $match,
        OperationValue $value,
        ?ActorContext $actorContext,
    ): ResponseInterface {
        if ($this->deferred !== null && $this->deferred->accepts($match->route->operation)) {
            $result = $this->deferred->accept($match->route->operation, $value, $actorContext);

            if ($result instanceof DeferredAcknowledgement) {
                return $this->responder->respondAcknowledgement($result);
            }

            if ($result->isCompleted()) {
                throw new LogicException('Deferred operation acceptance cannot return a completed result.');
            }

            return $this->responder->respond($result);
        }

        $result = $this->dispatcher->dispatch($match->route->operation, $value, $actorContext);

        return $this->responder->respond($result);
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
