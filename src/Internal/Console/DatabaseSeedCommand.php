<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Seeder\CompiledSeederRuntime;
use BlackOps\Internal\Seeder\SeederRuntimeException;
use Closure;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class DatabaseSeedCommand extends Command
{
    public const NAME = 'database:seed';

    /** @var Closure(): CompiledSeederRuntime */
    private Closure $runtime;

    /** @param Closure(): CompiledSeederRuntime $runtime */
    public function __construct(Closure $runtime)
    {
        parent::__construct(self::NAME);
        $this->runtime = $runtime;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $runtime = ($this->runtime)();
        } catch (DatabaseSeedRuntimeException $exception) {
            $output->writeln(
                $exception->artifactFailure
                    ? 'Database seeding artifacts are unavailable.'
                    : 'Database seeding runtime could not be resolved.',
            );

            return Command::FAILURE;
        } catch (Throwable) {
            $output->writeln('Database seeding artifacts are unavailable.');

            return Command::FAILURE;
        }

        try {
            $runtime->run();
        } catch (SeederRuntimeException $exception) {
            $output->writeln($this->safeRuntimeFailure($exception));

            return Command::FAILURE;
        } catch (Throwable) {
            $output->writeln('Database seeding failed.');

            return Command::FAILURE;
        }

        $output->writeln('Database seeding completed.');

        return Command::SUCCESS;
    }

    private function safeRuntimeFailure(SeederRuntimeException $exception): string
    {
        return match ($exception->getMessage()) {
            'Database seeding is not configured.' => 'Database seeding is not configured.',
            'Seeder is not available in the compiled application.',
            'Compiled seeder service is invalid.',
                => 'Database seeding runtime could not be resolved.',
            default => 'Database seeding failed.',
        };
    }
}
