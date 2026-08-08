<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Execution\Operations;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Authorization\AuthorizationPolicyResolver;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Database\DoctrineDatabaseManager;
use BlackOps\Internal\Database\RuntimeDatabaseServiceInjector;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\SymfonyUuidv7Generator;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use BlackOps\Internal\Logging\MonologJsonlLoggerFactory;
use BlackOps\Internal\Logging\RuntimeLoggingServiceInjector;
use BlackOps\Internal\Outbox\TransactionalOutboxRuntime;
use BlackOps\Internal\Registry\OperationManifestArtifact;
use BlackOps\Internal\Registry\OperationManifestFile;
use BlackOps\Internal\Runtime\RuntimeContainerArtifactLoader;
use BlackOps\Internal\Telemetry\TelemetryMetrics;
use BlackOps\Internal\Telemetry\TelemetryTracer;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use BlackOps\Internal\Transaction\RuntimeTransactionServiceInjector;
use BlackOps\Internal\Transaction\TransactionRuntime;
use BlackOps\Outbox\TransactionalOutbox;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use BlackOps\Transport\PostgreSql\PostgreSqlSystemClock;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;

final readonly class ApplicationOperationRuntimeComposer
{
    /** @mago-expect lint:no-empty-catch-clause */
    public function compose(ApplicationConfigurationSnapshot $configuration): ApplicationOperationRuntimeComposition
    {
        [$build, $operations, $container, $database, $databases] = $this->loadRuntimeInputs($configuration);
        [$telemetry, $metrics, $scope, $logger, $transactionRuntime] = $this->configureRuntimeServices(
            $configuration,
            $operations,
            $container,
            $databases,
        );
        $connection = $databases->connection($database->frameworkConnection);
        if (!$container instanceof Container) {
            throw new \InvalidArgumentException('Runtime container does not support outbox service injection.');
        }
        $protection = ApplicationStorageProtectionResolver::resolve($container, $metrics);
        try {
            $container->set(\BlackOps\Internal\StorageProtection\BopdEnvelopeCodec::class, $protection);
        } catch (\LogicException) {
            // An already initialized compiled service cannot be replaced; composition keeps the clone.
        }
        $clock = new PostgreSqlSystemClock();
        $identifiers = new IdentifierFactory(new SymfonyUuidv7Generator(), $clock);
        $outbox = new TransactionalOutboxRuntime(
            $operations->operations,
            new ReflectionJsonOperationCodec(),
            $scope,
            $transactionRuntime,
            $connection,
            $database->frameworkConnection,
            new PostgreSqlOutboxStore($connection, $protection, new ReflectionJsonOperationCodec(), $database->schema),
            new ExecutionContextFactory($identifiers, $clock),
            $identifiers,
            $clock,
            telemetry: $telemetry,
        );
        $container->set(TransactionalOutbox::class, $outbox);
        $container->set(Operations::class, $outbox);
        new OperationDataRuntimeInjector()->inject($container, $connection, $database->schema, $protection);
        $journal = new PostgreSqlCanonicalJournalStore($connection, $protection, $database->schema);
        $observations = new ApplicationJournalObservationFactory()->create($configuration->configuration(), $metrics);
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
            $telemetry,
            $metrics,
            $protection,
        );
    }

    /** @return array{ApplicationBuildConfiguration, OperationManifestArtifact, ContainerInterface, ApplicationDatabaseConfiguration, DoctrineDatabaseManager} */
    private function loadRuntimeInputs(ApplicationConfigurationSnapshot $configuration): array
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

        return [$build, $operations, $container, $database, $databases];
    }

    /** @return array{TelemetryTracer, TelemetryMetrics, ExecutionScopeProvider, ExecutionScopedLogger, TransactionRuntime} */
    private function configureRuntimeServices(
        ApplicationConfigurationSnapshot $configuration,
        OperationManifestArtifact $operations,
        ContainerInterface $container,
        DoctrineDatabaseManager $databases,
    ): array {
        $telemetry = new TelemetryTracer($configuration->tracerProvider());
        $metrics = new TelemetryMetrics($configuration->meterProvider(), array_map(
            static fn(\BlackOps\Core\Registry\OperationMetadata $metadata): string => $metadata->typeId,
            $operations->operations->all(),
        ));
        $scope = new ExecutionScopeProvider($telemetry, metrics: $metrics);
        $logging = ApplicationLoggingConfiguration::fromConfiguration($configuration->configuration());
        $logger = new RuntimeLoggingServiceInjector()->inject(
            $container,
            $scope,
            new MonologJsonlLoggerFactory()->create($logging->stream, $logging->channel, $logging->minimumLevel),
            telemetry: $telemetry,
        );
        $transactionRuntime = new RuntimeTransactionServiceInjector()->inject($container, $databases, $scope);

        return [$telemetry, $metrics, $scope, $logger, $transactionRuntime];
    }
}
