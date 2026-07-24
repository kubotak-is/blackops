<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Execution\Operations;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Authorization\AuthorizationPolicyResolver;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Database\RuntimeDatabaseServiceInjector;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\SymfonyUuidv7Generator;
use BlackOps\Internal\Logging\MonologJsonlLoggerFactory;
use BlackOps\Internal\Logging\RuntimeLoggingServiceInjector;
use BlackOps\Internal\Outbox\TransactionalOutboxRuntime;
use BlackOps\Internal\Registry\OperationManifestFile;
use BlackOps\Internal\Runtime\RuntimeContainerArtifactLoader;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use BlackOps\Internal\Transaction\RuntimeTransactionServiceInjector;
use BlackOps\Outbox\TransactionalOutbox;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use BlackOps\Transport\PostgreSql\PostgreSqlSystemClock;
use Symfony\Component\DependencyInjection\Container;

final readonly class ApplicationOperationRuntimeComposer
{
    public function compose(ApplicationConfigurationSnapshot $configuration): ApplicationOperationRuntimeComposition
    {
        $build = ApplicationBuildConfiguration::fromConfiguration($configuration->configuration());
        $operations = new OperationManifestFile()->loadArtifact($build->operationManifest);
        $container = new RuntimeContainerArtifactLoader()->load(
            $build->container,
            $build->containerClass,
            $build->containerNamespace,
        );
        $database = ApplicationDatabaseConfiguration::fromConfiguration($configuration->configuration());
        $databases = $database->databaseManager();
        new RuntimeDatabaseServiceInjector()->inject($container, $databases);
        $scope = new ExecutionScopeProvider();
        $logging = ApplicationLoggingConfiguration::fromConfiguration($configuration->configuration());
        $logger = new RuntimeLoggingServiceInjector()->inject(
            $container,
            $scope,
            new MonologJsonlLoggerFactory()->create($logging->stream, $logging->channel, $logging->minimumLevel),
        );
        $transactionRuntime = new RuntimeTransactionServiceInjector()->inject($container, $databases, $scope);
        $connection = $databases->connection($database->frameworkConnection);
        $clock = new PostgreSqlSystemClock();
        $identifiers = new IdentifierFactory(new SymfonyUuidv7Generator(), $clock);
        if (!$container instanceof Container) {
            throw new \InvalidArgumentException('Runtime container does not support outbox service injection.');
        }
        $outbox = new TransactionalOutboxRuntime(
            $operations->operations,
            new ReflectionJsonOperationCodec(),
            $scope,
            $transactionRuntime,
            $connection,
            $database->frameworkConnection,
            new PostgreSqlOutboxStore($connection, $database->schema),
            new ExecutionContextFactory($identifiers, $clock),
            $identifiers,
            $clock,
        );
        $container->set(TransactionalOutbox::class, $outbox);
        $container->set(Operations::class, $outbox);
        $journal = new PostgreSqlCanonicalJournalStore($connection, $database->schema);
        $observations = new ApplicationJournalObservationFactory()->create($configuration->configuration());
        $authorization = new AuthorizationEvaluator(new AuthorizationPolicyResolver($container));

        return new ApplicationOperationRuntimeComposition(
            $operations->applicationBuildId,
            $operations->operations,
            $container,
            $databases,
            $connection,
            $clock,
            $identifiers,
            $journal,
            $scope,
            $logger,
            $authorization,
            new OperationTransactionCoordinator($transactionRuntime, $databases, $connection),
            $observations,
            new ApplicationOperationInvocationLifecycle(
                $scope,
                new ApplicationDatabaseConnectionLifecycle($databases),
                $observations,
            ),
        );
    }
}
