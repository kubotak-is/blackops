<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime;

use BlackOps\Http\Binding\OperationValueBinder;
use BlackOps\Http\OperationRequestHandler;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Http\Status\OperationStatusJsonResponder;
use BlackOps\Http\Status\OperationStatusRequestHandler;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Authorization\AuthorizationPolicyResolver;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Http\HttpMiddlewarePipeline;
use BlackOps\Internal\Http\OperationFailureErrorBoundary;
use BlackOps\Internal\Idempotency\IdempotencyRecovery;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\SymfonyUuidv7Generator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use BlackOps\Internal\Logging\FrameworkOperationFailureReporter;
use BlackOps\Internal\Logging\MonologJsonlLoggerFactory;
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
        $logger = $dependencies->executionLogger ?? new ExecutionScopedLogger(
            new MonologJsonlLoggerFactory()->create('php://stderr'),
            $scope,
        );
        $authorization = new AuthorizationEvaluator(new AuthorizationPolicyResolver($artifacts->container));
        $responder = new JsonOperationResponder($dependencies->responses, $dependencies->streams);
        $recovery =
            $dependencies->idempotencyStore !== null && $dependencies->journalReader !== null
                ? new IdempotencyRecovery(
                    $dependencies->journalReader,
                    $dependencies->idempotencyStore,
                    $responder,
                    $dependencies->idempotencyConnection,
                    $dependencies->idempotencySchema,
                )
                : null;
        $dispatcher = new InlineDispatcher(
            $artifacts->operations,
            new ExecutionContextFactory($identifiers, $dependencies->clock),
            new HandlerResolver($artifacts->container),
            new JournalRecordFactory($identifiers, $dependencies->clock),
            $dependencies->journal,
            observations: $dependencies->journalObservations,
            scope: $scope,
            authorization: $authorization,
            transactions: $dependencies->operationTransactions,
            idempotency: $dependencies->idempotencyStore,
            idempotencyRetention: $dependencies->idempotencyRetention,
            idempotencyRecovery: $recovery,
        );
        $handlers = new HandlerResolver($artifacts->container);
        $routes = $artifacts->http->toRegistry($this->definitions->fromRegistry(
            $artifacts->operations,
            $handlers->resolve(...),
        ));

        $status = $dependencies->operationStatusQuery === null
            ? null
            : new OperationStatusRequestHandler(
                $dependencies->operationStatusQuery,
                new OperationStatusJsonResponder($dependencies->responses, $dependencies->streams),
            );
        $operationHandler = new OperationRequestHandler(
            $routes,
            new OperationValueBinder(),
            $dispatcher,
            $responder,
            $dependencies->responses,
            $dispatcher,
            $dependencies->deferredOperationAcceptor,
            $status,
            $dependencies->idempotencyStore,
        );
        $httpHandler = new OperationFailureErrorBoundary(
            $operationHandler,
            $responder,
            new FrameworkOperationFailureReporter($logger, $scope),
            $dependencies->idempotencyStore,
        );

        return new ProductionRuntimeComposition(
            $dispatcher,
            $routes,
            $dependencies->httpMiddleware === []
                ? $httpHandler
                : new HttpMiddlewarePipeline($dependencies->httpMiddleware, $httpHandler),
            $scope,
            $logger,
        );
    }
}
