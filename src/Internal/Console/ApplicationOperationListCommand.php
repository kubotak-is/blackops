<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationOperationDiscovery;
use BlackOps\Internal\Registry\OperationProviderCompiler;
use BlackOps\Internal\Registry\OperationProviderConfigLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ApplicationOperationListCommand extends Command
{
    public const NAME = 'blackops:operation:list';

    public function __construct(
        private readonly ApplicationConfigurationSnapshot $configuration,
        private readonly OperationProviderConfigLoader $providers = new OperationProviderConfigLoader(),
        private readonly OperationProviderCompiler $compiler = new OperationProviderCompiler(),
    ) {
        parent::__construct(self::NAME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $providers = $this->providers->fromEntries($this->configuration->operationProviders());
        $metadata = $this->compiler->compile(
            $providers,
            new ApplicationOperationDiscovery()->discover($this->configuration),
        )->all();
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
}
