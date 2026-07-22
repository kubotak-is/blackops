<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Http\Routing\HttpOperationManifest;
use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Http\DeferredHttpOperationAcceptor;
use BlackOps\Internal\Http\OperationStatusAuthorizerResolver;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Runtime\ProductionRuntimeArtifacts;
use BlackOps\Internal\Runtime\ProductionRuntimeComposer;
use BlackOps\Internal\Runtime\ProductionRuntimeDependencies;
use BlackOps\Internal\Status\DefaultOperationStatusQuery;
use BlackOps\Internal\Status\PostgreSqlOperationStatusSource;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use InvalidArgumentException;
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
        $operation = new ApplicationOperationRuntimeComposer()->compose($configuration);
        $http = new HttpOperationManifestFile()->loadArtifact($build->httpManifest);
        if ($http->applicationBuildId !== $operation->applicationBuildId) {
            throw new InvalidArgumentException('HTTP runtime manifest application build ID does not match.');
        }
        $this->validateHttpOperations($operation->operations, $http->manifest);
        $artifacts = new ProductionRuntimeArtifacts($operation->operations, $http->manifest, $operation->container);
        $httpMiddleware = new ApplicationHttpMiddlewareResolver($artifacts->container)->resolve($middleware);
        $sender = new PostgreSqlDeferredOperationSender($operation->connection, $database->schema);
        $acceptor = new DeferredHttpOperationAcceptor(
            $artifacts->operations,
            new ExecutionContextFactory($operation->identifiers, $operation->clock),
            new ReflectionJsonOperationCodec(),
            new DeferredAcceptanceOrchestrator(
                $operation->connection,
                $sender,
                $operation->journal,
                new JournalRecordFactory($operation->identifiers, $operation->clock),
                authorization: $operation->authorization,
                scope: $operation->scope,
            ),
        );
        $psr17 = $this->psr17();
        $statusQuery = new DefaultOperationStatusQuery(
            new PostgreSqlOperationStatusSource($operation->connection, $artifacts->operations, $database->schema),
            new OperationStatusAuthorizerResolver($artifacts->container)->resolve(),
        );

        $runtime = new ProductionRuntimeComposer()->composeWithDependencies(
            $artifacts,
            new ProductionRuntimeDependencies(
                $operation->clock,
                $operation->journal,
                $psr17,
                $psr17,
                executionScope: $operation->scope,
                journalObservations: $operation->observations?->pipeline(),
                deferredOperationAcceptor: $acceptor,
                httpMiddleware: $httpMiddleware,
                operationTransactions: $operation->transactions,
                executionLogger: $operation->logger,
                operationStatusQuery: $statusQuery,
            ),
        );

        return new ApplicationHttpRequestHandler(
            $runtime->httpHandler,
            $operation->lifecycle,
            new JsonOperationResponder($psr17, $psr17),
            $operation->logger,
        );
    }

    private function validateHttpOperations(OperationRegistry $operations, HttpOperationManifest $http): void
    {
        foreach ($operations->all() as $metadata) {
            if (
                is_a($metadata->outcome, EphemeralOutcome::class, allow_string: true)
                && !array_key_exists($metadata->typeId, $http->operations)
            ) {
                throw new InvalidArgumentException('HTTP manifest is missing an ephemeral operation.');
            }
        }

        foreach ($http->operations as $typeId => $routeMetadata) {
            $metadata = $operations->findByTypeId($typeId);
            if (
                $metadata === null
                || $metadata->definition !== $routeMetadata['definition']
                || $metadata->value !== $routeMetadata['value']
                || $metadata->handler !== $routeMetadata['handler']
                || $metadata->outcome !== $routeMetadata['outcome']
                || $metadata->strategy !== $routeMetadata['strategy']
                || is_a($metadata->outcome, EphemeralOutcome::class, allow_string: true) !== $routeMetadata['ephemeral']
            ) {
                throw new InvalidArgumentException('HTTP operation metadata does not match the operation manifest.');
            }
        }
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
