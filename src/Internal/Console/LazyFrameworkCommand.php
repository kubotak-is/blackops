<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use Closure;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class LazyFrameworkCommand extends Command
{
    /** @var Closure(): Command */
    private Closure $factory;

    /**
     * @param Closure(): Command $factory
     * @param Closure(Command): void $definition
     */
    public function __construct(string $name, string $description, Closure $factory, Closure $definition)
    {
        parent::__construct($name);
        $this->setDescription($description);
        $this->factory = $factory;
        $definition($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = ($this->factory)();
        $command->setApplication($this->getApplication());
        $helperSet = $this->getHelperSet();

        if ($helperSet !== null) {
            $command->setHelperSet($helperSet);
        }

        return $command->run($input, $output);
    }
}
