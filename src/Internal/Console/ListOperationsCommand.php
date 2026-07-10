<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Registry\OperationProviderCompiler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'List operation metadata discovered from application source.')]
final class ListOperationsCommand extends Command
{
    public const NAME = 'blackops:operation:list';

    public function __construct(
        private readonly DevelopmentDiscoveryInput $discovery = new DevelopmentDiscoveryInput(),
        private readonly OperationProviderCompiler $compiler = new OperationProviderCompiler(),
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->discovery->configure($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $metadata = $this->compiler->compileDefinitions($this->discovery->requiredDefinitions($input))->all();
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
