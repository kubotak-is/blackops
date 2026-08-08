<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Scheduling\PostgreSqlScheduledOccurrenceLifecycle;
use BlackOps\Internal\Scheduling\PostgreSqlScheduleStore;
use BlackOps\Internal\Scheduling\ScheduledOperationDefinitionResolver;
use BlackOps\Internal\Scheduling\ScheduledOperationEnvelopeFactory;
use BlackOps\Internal\Scheduling\ScheduledOperationRunner;
use BlackOps\Internal\Scheduling\ScheduledOperationRuntime;
use BlackOps\Internal\Scheduling\ScheduleEvaluator;
use BlackOps\Scheduling\ScheduledActorProvider;
use BlackOps\Scheduling\ScheduledTenantProvider;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;

final readonly class ApplicationScheduledOperationRuntimeComposer
{
    public function compose(ApplicationConfigurationSnapshot $configuration): ApplicationScheduledOperationRuntime
    {
        $runtime = new ApplicationOperationRuntimeComposer()->compose($configuration);
        $this->assertBuildId($runtime->applicationBuildId, $configuration);
        $database = ApplicationDatabaseConfiguration::fromConfiguration($configuration->configuration());
        $scheduledOccurrences = new PostgreSqlScheduledOccurrenceLifecycle($runtime->connection, $database->schema);
        $provider = $this->provider($runtime->container, $runtime->operations);
        $tenantProvider = $runtime->container->has(ScheduledTenantProvider::class)
            ? $this->tenantProvider($runtime->container->get(ScheduledTenantProvider::class))
            : null;
        $contexts = new ExecutionContextFactory($runtime->identifiers, $runtime->clock);
        $records = new JournalRecordFactory($runtime->identifiers, $runtime->clock, $runtime->telemetry);
        $inline = new InlineDispatcher(
            $runtime->operations,
            $contexts,
            new HandlerResolver($runtime->container),
            $records,
            $runtime->journal,
            observations: $runtime->observations?->pipeline(),
            scope: $runtime->scope,
            authorization: $runtime->authorization,
            transactions: $runtime->transactions,
            scheduledOccurrences: $scheduledOccurrences,
            clock: $runtime->clock,
        );
        $deferred = new DeferredAcceptanceOrchestrator(
            $runtime->connection,
            new PostgreSqlDeferredOperationSender($runtime->connection, $runtime->protection, $database->schema),
            $runtime->journal,
            $records,
            authorization: $runtime->authorization,
            scope: $runtime->scope,
            scheduledOccurrences: $scheduledOccurrences,
        );
        $scheduledRuntime = new ScheduledOperationRuntime(
            new ScheduledOperationEnvelopeFactory($contexts, $provider, $tenantProvider),
            $inline,
            $deferred,
            new ReflectionJsonOperationCodec(),
            $runtime->clock,
            $scheduledOccurrences,
            $runtime->telemetry,
        );

        return new ApplicationScheduledOperationRuntime(
            new ScheduledOperationRunner(
                $runtime->operations,
                new PostgreSqlScheduleStore($runtime->connection, $database->schema),
                new ScheduleEvaluator($runtime->connection, $runtime->clock, $runtime->identifiers, $database->schema),
                $scheduledRuntime,
                new ScheduledOperationDefinitionResolver($runtime->container),
                $scheduledOccurrences,
                $runtime->clock,
                $runtime->telemetry,
                $runtime->metrics,
            ),
        );
    }

    private function tenantProvider(mixed $provider): ScheduledTenantProvider
    {
        if (!$provider instanceof ScheduledTenantProvider) {
            throw new InvalidArgumentException('Scheduled tenant provider configuration is invalid.');
        }
        return $provider;
    }

    /** @return ScheduledActorProvider|null */
    private function provider(
        ContainerInterface $container,
        \BlackOps\Core\Registry\OperationRegistry $operations,
    ): ?ScheduledActorProvider {
        $authorized = array_any(
            $operations->all(),
            static fn(\BlackOps\Core\Registry\OperationMetadata $metadata): bool => (
                $metadata->schedule !== null
                && $metadata->authorizationPolicy !== null
            ),
        );
        if (!$container->has(ScheduledActorProvider::class)) {
            if ($authorized) {
                throw new InvalidArgumentException('Scheduled actor provider configuration is required.');
            }

            return null;
        }

        return $this->resolveProvider($container);
    }

    private function assertBuildId(string $compiledBuildId, ApplicationConfigurationSnapshot $configuration): void
    {
        if ($compiledBuildId !== ApplicationBuildId::fromConfiguration($configuration->configuration())) {
            throw new InvalidArgumentException('Scheduled operation manifest application build ID does not match.');
        }
    }

    private function resolveProvider(ContainerInterface $container): ScheduledActorProvider
    {
        return $this->assertProvider($container->get(ScheduledActorProvider::class));
    }

    private function assertProvider(mixed $provider): ScheduledActorProvider
    {
        if (!$provider instanceof ScheduledActorProvider) {
            throw new InvalidArgumentException('Scheduled actor provider configuration is invalid.');
        }

        return $provider;
    }
}
