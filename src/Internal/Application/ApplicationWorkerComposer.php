<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Supervision\ExponentialBackoffSupervisionPolicy;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Authorization\AuthorizationPolicyResolver;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Database\RuntimeDatabaseServiceInjector;
use BlackOps\Internal\Execution\DeferredLeaseExpiredRecovery;
use BlackOps\Internal\Execution\DeferredWorkerLoop;
use BlackOps\Internal\Execution\DeferredWorkerRuntime;
use BlackOps\Internal\Execution\DeferredWorkerRuntimeServices;
use BlackOps\Internal\Execution\DeferredWorkerRuntimeStorage;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\PcntlSignalHeartbeat;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\SymfonyUuidv7Generator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Logging\FrameworkOperationFailureReporter;
use BlackOps\Internal\Logging\MonologJsonlLoggerFactory;
use BlackOps\Internal\Logging\RuntimeLoggingServiceInjector;
use BlackOps\Internal\Outbox\TransactionalOutboxRuntime;
use BlackOps\Internal\Runtime\ProductionRuntimeArtifactLoader;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use BlackOps\Internal\Transaction\RuntimeTransactionServiceInjector;
use BlackOps\Outbox\TransactionalOutbox;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationLifecycleStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationReceiver;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeStore;
use BlackOps\Transport\PostgreSql\PostgreSqlSystemClock;
use Symfony\Component\DependencyInjection\Container;

final readonly class ApplicationWorkerComposer
{
    /** @mago-expect lint:halstead */
    public function compose(ApplicationConfigurationSnapshot $configuration): ApplicationWorkerComposition
    {
        $build = ApplicationBuildConfiguration::fromConfiguration($configuration->configuration());
        $database = ApplicationDatabaseConfiguration::fromConfiguration($configuration->configuration());
        $worker = ApplicationWorkerConfiguration::fromConfiguration($configuration->configuration());
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
        $logging = ApplicationLoggingConfiguration::fromConfiguration($configuration->configuration());
        $logger = new RuntimeLoggingServiceInjector()->inject(
            $artifacts->container,
            $executionScope,
            new MonologJsonlLoggerFactory()->create($logging->stream, $logging->channel, $logging->minimumLevel),
        );
        $transactionRuntime = new RuntimeTransactionServiceInjector()->inject(
            $artifacts->container,
            $databases,
            $executionScope,
        );
        $main = $databases->connection($database->frameworkConnection);
        $operationTransactions = new OperationTransactionCoordinator($transactionRuntime, $databases, $main);
        $heartbeat = $database->databaseManager()->connection($database->frameworkConnection);
        $clock = new PostgreSqlSystemClock();
        $identifiers = new IdentifierFactory(new SymfonyUuidv7Generator(), $clock);
        if (!$artifacts->container instanceof Container) {
            throw new \InvalidArgumentException('Runtime container does not support outbox service injection.');
        }
        $artifacts->container->set(
            TransactionalOutbox::class,
            new TransactionalOutboxRuntime(
                $artifacts->operations,
                new ReflectionJsonOperationCodec(),
                $executionScope,
                $transactionRuntime,
                $main,
                $database->frameworkConnection,
                new PostgreSqlOutboxStore($main, $database->schema),
                new ExecutionContextFactory($identifiers, $clock),
                $identifiers,
                $clock,
            ),
        );
        $services = new DeferredWorkerRuntimeServices(
            $artifacts->operations,
            new ReflectionJsonOperationCodec(),
            new ExecutionContextFactory($identifiers, $clock),
            new HandlerResolver($artifacts->container),
            new ActorRef($worker->id, 'system'),
            new AuthorizationEvaluator(new AuthorizationPolicyResolver($artifacts->container)),
            new ExponentialBackoffSupervisionPolicy(),
        );
        $storage = new DeferredWorkerRuntimeStorage(
            $main,
            new JournalRecordFactory($identifiers, $clock),
            new PostgreSqlCanonicalJournalStore($main, $database->schema),
            new PostgreSqlDeferredOperationLifecycleStore($main, $database->schema),
            $clock,
            new PostgreSqlOutcomeStore($main, $database->schema),
            scope: $executionScope,
            transactions: $operationTransactions,
            failureReporter: new FrameworkOperationFailureReporter($logger, $executionScope),
        );
        $receiver = new PostgreSqlDeferredOperationReceiver(
            $main,
            $database->schema,
            $worker->id,
            $worker->leaseSeconds,
            $clock,
        );
        $heartbeatReceiver = new PostgreSqlDeferredOperationReceiver(
            $heartbeat,
            $database->schema,
            $worker->id,
            $worker->leaseSeconds,
            $clock,
        );
        $signals = new PcntlSignalHeartbeat(
            $heartbeatReceiver,
            $worker->heartbeatSeconds,
            $worker->leaseSeconds,
            $worker->graceSeconds,
        );
        $runtime = new DeferredWorkerRuntime(
            $services,
            $storage,
            $signals,
            connections: new ApplicationDatabaseConnectionLifecycle($databases),
        );
        $loop = new DeferredWorkerLoop(
            new DeferredLeaseExpiredRecovery($services, $storage),
            $receiver,
            $runtime,
            $receiver,
            $signals,
            $clock,
            continueAfterHandlerFailure: $worker->continueAfterHandlerFailure,
        );

        return new ApplicationWorkerComposition($loop, $main, $heartbeat, $signals);
    }
}
