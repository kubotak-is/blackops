<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Execution\Operations;
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
use BlackOps\Internal\Transaction\RuntimeTransactionServiceInjector;
use BlackOps\Outbox\TransactionalOutbox;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use BlackOps\Transport\PostgreSql\PostgreSqlSystemClock;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Container;
use Throwable;

final class ApplicationCommandContainerResolver
{
    private ?ContainerInterface $container = null;

    public function __construct(
        private readonly ApplicationConfigurationSnapshot $configuration,
        private readonly ApplicationBuildConfiguration $build,
        private readonly RuntimeContainerArtifactLoader $containers = new RuntimeContainerArtifactLoader(),
    ) {}

    public function resolve(string $class): Command
    {
        try {
            /** @var object $service */
            $service = $this->container()->get($class);
        } catch (Throwable) {
            throw new InvalidArgumentException('Application command service could not be resolved.');
        }

        if (!$service instanceof Command || $service::class !== $class) {
            throw new InvalidArgumentException('Application command service has an invalid runtime type.');
        }

        return $service;
    }

    private function container(): ContainerInterface
    {
        if ($this->container !== null) {
            return $this->container;
        }

        $container = $this->containers->load(
            $this->build->container,
            $this->build->containerClass,
            $this->build->containerNamespace,
        );
        $scope = new ExecutionScopeProvider();
        $logging = ApplicationLoggingConfiguration::fromConfiguration($this->configuration->configuration());
        new RuntimeLoggingServiceInjector()->inject(
            $container,
            $scope,
            new MonologJsonlLoggerFactory()->create($logging->stream, $logging->channel, $logging->minimumLevel),
        );

        if (array_key_exists('database', $this->configuration->configuration())) {
            $database = ApplicationDatabaseConfiguration::fromConfiguration($this->configuration->configuration());
            $databases = $database->databaseManager();
            new RuntimeDatabaseServiceInjector()->inject($container, $databases);
            $transactions = new RuntimeTransactionServiceInjector()->inject($container, $databases, $scope);
            $connection = $databases->connection($database->frameworkConnection);
            $clock = new PostgreSqlSystemClock();
            $identifiers = new IdentifierFactory(new SymfonyUuidv7Generator(), $clock);
            $operations = new OperationManifestFile()->loadArtifact($this->build->operationManifest);
            if (!$container instanceof Container) {
                throw new InvalidArgumentException('Runtime container does not support outbox service injection.');
            }
            $outbox = new TransactionalOutboxRuntime(
                $operations->operations,
                new ReflectionJsonOperationCodec(),
                $scope,
                $transactions,
                $connection,
                $database->frameworkConnection,
                new PostgreSqlOutboxStore($connection, $database->schema),
                new ExecutionContextFactory($identifiers, $clock),
                $identifiers,
                $clock,
            );
            $container->set(TransactionalOutbox::class, $outbox);
            $container->set(Operations::class, $outbox);
        }

        return $this->container = $container;
    }
}
