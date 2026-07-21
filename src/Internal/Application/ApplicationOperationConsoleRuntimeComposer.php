<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Console\OperationConsoleCommandMetadata;
use BlackOps\Internal\Console\OperationConsoleRuntime;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Http\DeferredHttpOperationAcceptor;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use InvalidArgumentException;

final readonly class ApplicationOperationConsoleRuntimeComposer
{
    public function compose(
        ApplicationConfigurationSnapshot $configuration,
        OperationConsoleCommandMetadata $command,
    ): OperationConsoleRuntime {
        $runtime = new ApplicationOperationRuntimeComposer()->compose($configuration);
        if ($runtime->applicationBuildId !== ApplicationBuildId::fromConfiguration($configuration->configuration())) {
            throw new InvalidArgumentException('Operation console manifest application build ID does not match.');
        }
        $metadata = $runtime->operations->findByTypeId($command->typeId);
        if (
            $metadata === null
            || $metadata->definition !== $command->definition
            || $metadata->value !== $command->value
            || $metadata->outcome !== $command->outcome
            || $metadata->strategy !== $command->strategy
        ) {
            throw new InvalidArgumentException('Operation console metadata does not match the operation manifest.');
        }

        $contexts = new ExecutionContextFactory($runtime->identifiers, $runtime->clock);
        $records = new JournalRecordFactory($runtime->identifiers, $runtime->clock);
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
        );
        $sender = new PostgreSqlDeferredOperationSender($runtime->connection, $this->schema($configuration));
        $deferred = new DeferredHttpOperationAcceptor(
            $runtime->operations,
            $contexts,
            new ReflectionJsonOperationCodec(),
            new DeferredAcceptanceOrchestrator(
                $runtime->connection,
                $sender,
                $runtime->journal,
                $records,
                authorization: $runtime->authorization,
                scope: $runtime->scope,
            ),
        );

        return new OperationConsoleRuntime(
            $runtime->operations,
            $runtime->container,
            $inline,
            $deferred,
            $runtime->lifecycle,
            $runtime->scope,
            $runtime->logger,
        );
    }

    private function schema(ApplicationConfigurationSnapshot $configuration): string
    {
        return ApplicationDatabaseConfiguration::fromConfiguration($configuration->configuration())->schema;
    }
}
