<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\Exception\ConfigurationFailure;
use Closure;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class StorageProtectionLazyCommand extends Command
{
    /** @var Closure(): Command */
    private Closure $factory;

    /** @param Closure(Command): void $definition @param Closure(): Command $factory */
    public function __construct(string $name, string $description, Closure $factory, Closure $definition)
    {
        parent::__construct($name);
        $this->setDescription($description);
        /** @var Closure(): Command $factory */
        $this->factory = $factory;
        $definition($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $command = ($this->factory)();
        } catch (\InvalidArgumentException|\LogicException|ConfigurationFailure) {
            return self::error($input, $output, 'configuration_error', 2);
        } catch (\Throwable) {
            return self::error($input, $output, 'storage_error', 1);
        }
        $command->setApplication($this->getApplication());
        $helperSet = $this->getHelperSet();
        if ($helperSet !== null) {
            $command->setHelperSet($helperSet);
        }

        return $command->run($input, $output);
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (ExceptionInterface) {
            return self::error($input, $output, 'input_error', 2);
        } catch (\Throwable) {
            return self::error($input, $output, 'storage_error', 1);
        }
    }

    private static function error(InputInterface $input, OutputInterface $output, string $code, int $status): int
    {
        if ($input->hasParameterOption('--json')) {
            $output->writeln(json_encode([
                'schemaVersion' => 1,
                'status' => 'failed',
                'code' => $code,
            ], JSON_THROW_ON_ERROR));
            return $status;
        }
        $output->writeln($code === 'input_error' ? 'Input error.' : 'Storage protection command failed safely.');
        return $status;
    }
}
