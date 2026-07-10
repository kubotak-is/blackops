<?php

declare(strict_types=1);

namespace BlackOps\Http;

use BlackOps\Execution\Dispatcher;
use BlackOps\Http\Binding\OperationValueBinder;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Http\Routing\HttpRouteRegistry;
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

        $value = $this->binder->bind($match->route->value, $request, $match->pathParameters);

        if ($this->deferred !== null && $this->deferred->accepts($match->route->operation)) {
            return $this->responder->respondAcknowledgement($this->deferred->accept($match->route->operation, $value));
        }

        $result = $this->dispatcher->dispatch($match->route->operation, $value);

        return $this->responder->respond($result);
    }

    private function hasForbiddenGetBody(ServerRequestInterface $request): bool
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], strict: true)) {
            return false;
        }

        return trim((string) $request->getBody()) !== '';
    }
}
