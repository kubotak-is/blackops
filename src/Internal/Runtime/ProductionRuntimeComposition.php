<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime;

use BlackOps\Execution\Dispatcher;
use BlackOps\Http\Routing\HttpRouteRegistry;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ProductionRuntimeComposition
{
    public function __construct(
        public Dispatcher $dispatcher,
        public HttpRouteRegistry $httpRoutes,
        public RequestHandlerInterface $httpHandler,
        public ExecutionScopeProvider $executionScope,
        public ExecutionScopedLogger $logger,
    ) {}
}
