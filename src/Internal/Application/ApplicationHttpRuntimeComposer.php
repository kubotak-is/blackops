<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Http\OperationRequestHandler;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Http\DeferredHttpOperationAcceptor;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\SymfonyUuidv7Generator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Runtime\ProductionRuntimeArtifactLoader;
use BlackOps\Internal\Runtime\ProductionRuntimeComposer;
use BlackOps\Internal\Runtime\ProductionRuntimeDependencies;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlSystemClock;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use LogicException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use ReflectionClass;

final readonly class ApplicationHttpRuntimeComposer
{
    public function compose(ApplicationConfigurationSnapshot $configuration): OperationRequestHandler
    {
        $build = ApplicationBuildConfiguration::fromConfiguration($configuration->configuration());
        $database = ApplicationDatabaseConfiguration::fromConfiguration($configuration->configuration());
        $artifacts = new ProductionRuntimeArtifactLoader()->load(
            $build->operationManifest,
            $build->httpManifest,
            $build->container,
            $build->containerClass,
            $build->containerNamespace,
        );
        $connection = $this->connection($database->connection);
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
            ),
        );
        $psr17 = $this->psr17();
        $observations = new ApplicationJournalObservationFactory()->create($configuration->configuration());

        return new ProductionRuntimeComposer()->composeWithDependencies(
            $artifacts,
            new ProductionRuntimeDependencies(
                $clock,
                $journal,
                $psr17,
                $psr17,
                journalObservations: $observations,
                deferredOperationAcceptor: $acceptor,
            ),
        )->httpHandler;
    }

    /** @param array<string, mixed> $parameters */
    private function connection(array $parameters): Connection
    {
        /** @var callable(array<string, mixed>): Connection $factory */
        $factory = [DriverManager::class, 'getConnection'];

        return $factory($parameters);
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
