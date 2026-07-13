<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime;

use BlackOps\Http\Binding\OperationValueBinder;
use BlackOps\Http\OperationRequestHandler;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\SymfonyUuidv7Generator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Registry\OperationDefinitionFactory;
use BlackOps\Journal\CanonicalJournalWriter;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class ProductionRuntimeComposer
{
    public function __construct(
        private OperationDefinitionFactory $definitions = new OperationDefinitionFactory(),
    ) {}

    public function compose(
        ProductionRuntimeArtifacts $artifacts,
        ClockInterface $clock,
        CanonicalJournalWriter $journal,
        ResponseFactoryInterface $responses,
        StreamFactoryInterface $streams,
    ): ProductionRuntimeComposition {
        return $this->composeWithDependencies(
            $artifacts,
            new ProductionRuntimeDependencies($clock, $journal, $responses, $streams),
        );
    }

    public function composeWithDependencies(
        ProductionRuntimeArtifacts $artifacts,
        ProductionRuntimeDependencies $dependencies,
    ): ProductionRuntimeComposition {
        $identifiers = new IdentifierFactory(new SymfonyUuidv7Generator(), $dependencies->clock);
        $scope = $dependencies->executionScope ?? new \BlackOps\Internal\Execution\ExecutionScopeProvider();
        $dispatcher = new InlineDispatcher(
            $artifacts->operations,
            new ExecutionContextFactory($identifiers, $dependencies->clock),
            new HandlerResolver($artifacts->container),
            new JournalRecordFactory($identifiers, $dependencies->clock),
            $dependencies->journal,
            observations: $dependencies->journalObservations,
            scope: $scope,
        );
        $handlers = new HandlerResolver($artifacts->container);
        $routes = $artifacts->http->toRegistry($this->definitions->fromRegistry(
            $artifacts->operations,
            $handlers->resolve(...),
        ));

        return new ProductionRuntimeComposition(
            $dispatcher,
            $routes,
            new OperationRequestHandler(
                $routes,
                new OperationValueBinder(),
                $dispatcher,
                new JsonOperationResponder($dependencies->responses, $dependencies->streams),
                $dependencies->responses,
                $dispatcher,
                $dependencies->deferredOperationAcceptor,
            ),
            $scope,
        );
    }
}
