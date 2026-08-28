<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationDatabaseConfiguration;
use BlackOps\Internal\Application\ApplicationOperationDiscovery;
use BlackOps\Internal\Registry\OperationMetadataCompiler;
use BlackOps\Internal\Registry\OperationProviderCompiler;
use BlackOps\Internal\Registry\OperationProviderConfigLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ApplicationOperationListCommand extends Command
{
    public const NAME = 'operation:list';

    public function __construct(
        private readonly ApplicationConfigurationSnapshot $configuration,
        private readonly OperationProviderConfigLoader $providers = new OperationProviderConfigLoader(),
        private readonly ?OperationProviderCompiler $compiler = null,
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->addOption(
            'strategy',
            null,
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Filter by exact built-in execution strategy.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $strategy = $this->requestedStrategy($input);
        if ($strategy === false) {
            return Command::INVALID;
        }

        $providers = $this->providers->fromEntries($this->configuration->operationProviders());
        $configuration = $this->configuration->configuration();
        $database = is_array($configuration['database'] ?? null)
            ? ApplicationDatabaseConfiguration::fromConfiguration($configuration)
            : null;
        $compiler = $this->compiler ?? new OperationProviderCompiler(new OperationMetadataCompiler(
            defaultTransactionConnection: $database?->default,
            knownTransactionConnections: $database === null ? [] : array_keys($database->connections),
        ));
        $metadata = $compiler->compile(
            $providers,
            new ApplicationOperationDiscovery()->discover($this->configuration),
        )->all();
        if ($strategy !== null) {
            $strategyClass = $strategy === 'inline' ? Inline::class : Deferred::class;
            $metadata = array_values(array_filter(
                $metadata,
                static fn(OperationMetadata $operation): bool => $operation->strategy === $strategyClass,
            ));
        }
        usort($metadata, static fn(OperationMetadata $left, OperationMetadata $right): int => strcmp(
            $left->typeId,
            $right->typeId,
        ));

        new Table($output)
            ->setHeaders(['Type ID', 'Definition', 'Execution Strategy'])
            ->setRows(array_map(static fn(OperationMetadata $operation): array => [
                $operation->typeId,
                $operation->definition,
                $operation->strategy,
            ], $metadata))
            ->render();

        return Command::SUCCESS;
    }

    private function requestedStrategy(InputInterface $input): string|false|null
    {
        $value = $input->getOption('strategy');
        if (!$input->hasParameterOption('--strategy', onlyParams: true) && ($value === null || $value === [])) {
            return null;
        }
        $values = is_array($value) ? array_values($value) : [$value];
        if (count($values) !== 1 || !is_string($values[0])) {
            return false;
        }

        return match ($values[0]) {
            'inline', 'deferred' => $values[0],
            default => false,
        };
    }
}
