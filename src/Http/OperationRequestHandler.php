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
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $this->routes->match($request->getMethod(), $request->getUri()->getPath());

        if ($route === null) {
            return $this->responses->createResponse(404);
        }

        if ($this->hasForbiddenGetBody($request)) {
            return $this->responses->createResponse(400);
        }

        $value = $this->binder->bind($route->value, $request);
        $result = $this->dispatcher->dispatch($route->operation, $value);

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
