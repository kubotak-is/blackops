<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Authorization\AuthorizationPolicyResolver;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Database\RuntimeDatabaseServiceInjector;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Http\DeferredHttpOperationAcceptor;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\SymfonyUuidv7Generator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Runtime\ProductionRuntimeArtifactLoader;
use BlackOps\Internal\Runtime\ProductionRuntimeComposer;
use BlackOps\Internal\Runtime\ProductionRuntimeDependencies;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use BlackOps\Internal\Transaction\RuntimeTransactionServiceInjector;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlSystemClock;
use LogicException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;

final readonly class ApplicationHttpRuntimeComposer
{
    public function compose(ApplicationConfigurationSnapshot $configuration): RequestHandlerInterface
    {
        $build = ApplicationBuildConfiguration::fromConfiguration($configuration->configuration());
        $database = ApplicationDatabaseConfiguration::fromConfiguration($configuration->configuration());
        $middleware = ApplicationHttpMiddlewareConfiguration::fromConfiguration($configuration->configuration());
        $artifacts = new ProductionRuntimeArtifactLoader()->load(
            $build->operationManifest,
            $build->httpManifest,
            $build->container,
            $build->containerClass,
            $build->containerNamespace,
        );
        $databases = $database->databaseManager();
        new RuntimeDatabaseServiceInjector()->inject($artifacts->container, $databases);
        $executionScope = new ExecutionScopeProvider();
        $transactionRuntime = new RuntimeTransactionServiceInjector()->inject(
            $artifacts->container,
            $databases,
            $executionScope,
        );
        $connection = $databases->connection($database->frameworkConnection);
        $operationTransactions = new OperationTransactionCoordinator($transactionRuntime, $databases, $connection);
        $httpMiddleware = new ApplicationHttpMiddlewareResolver($artifacts->container)->resolve($middleware);
        $clock = new PostgreSqlSystemClock();
        $identifiers = new IdentifierFactory(new SymfonyUuidv7Generator(), $clock);
        $journal = new PostgreSqlCanonicalJournalStore($connection, $database->schema);
        $sender = new PostgreSqlDeferredOperationSender($connection, $database->schema);
        $acceptor = new DeferredHttpOperationAcceptor(
            $artifacts->operations,
            new ExecutionContextFactory($identifiers, $clock),
            new ReflectionJsonOperationCodec(),
            new DeferredAcceptanceOrchestrator(
                $connection,
                $sender,
                $journal,
                new JournalRecordFactory($identifiers, $clock),
                authorization: new AuthorizationEvaluator(new AuthorizationPolicyResolver($artifacts->container)),
            ),
        );
        $psr17 = $this->psr17();
        $observations = new ApplicationJournalObservationFactory()->create($configuration->configuration());

        $runtime = new ProductionRuntimeComposer()->composeWithDependencies(
            $artifacts,
            new ProductionRuntimeDependencies(
                $clock,
                $journal,
                $psr17,
                $psr17,
                executionScope: $executionScope,
                journalObservations: $observations?->pipeline(),
                deferredOperationAcceptor: $acceptor,
                httpMiddleware: $httpMiddleware,
                operationTransactions: $operationTransactions,
            ),
        );

        return new ApplicationHttpRequestHandler(
            $runtime->httpHandler,
            $runtime->executionScope,
            new ApplicationDatabaseConnectionLifecycle($connection),
            $observations,
        );
    }

    private function psr17(): ResponseFactoryInterface&StreamFactoryInterface
    {
        /** @var class-string $factoryClass */
        $factoryClass = implode('\\', ['Nyholm', 'Psr7', 'Factory', 'Psr17Factory']);
        $factory = new ReflectionClass($factoryClass)->newInstance();

        if (!$factory instanceof ResponseFactoryInterface || !$factory instanceof StreamFactoryInterface) {
            throw new LogicException('Framework PSR-17 factory is unavailable.');
        }

        return $factory;
    }
}
