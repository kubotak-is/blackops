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
        $identifiers = new IdentifierFactory(new SymfonyUuidv7Generator(), $clock);
        $dispatcher = new InlineDispatcher(
            $artifacts->operations,
            new ExecutionContextFactory($identifiers, $clock),
            new HandlerResolver($artifacts->container),
            new JournalRecordFactory($identifiers, $clock),
            $journal,
        );
        $routes = $artifacts->http->toRegistry($this->definitions->fromRegistry($artifacts->operations));

        return new ProductionRuntimeComposition(
            $dispatcher,
            $routes,
            new OperationRequestHandler(
                $routes,
                new OperationValueBinder(),
                $dispatcher,
                new JsonOperationResponder($responses, $streams),
                $responses,
            ),
        );
    }
}
