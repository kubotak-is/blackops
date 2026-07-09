<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime;

use BlackOps\Execution\Dispatcher;
use BlackOps\Http\OperationRequestHandler;
use BlackOps\Http\Routing\HttpRouteRegistry;
use BlackOps\Internal\Execution\ExecutionScopeProvider;

final readonly class ProductionRuntimeComposition
{
    public function __construct(
        public Dispatcher $dispatcher,
        public HttpRouteRegistry $httpRoutes,
        public OperationRequestHandler $httpHandler,
        public ExecutionScopeProvider $executionScope,
    ) {}
}
