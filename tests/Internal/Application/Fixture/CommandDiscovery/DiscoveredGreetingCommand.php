<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application\Fixture\CommandDiscovery;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'fixture:greet',
    description: 'Run the discovered command fixture.',
    aliases: ['fixture:hello'],
    help: 'The fixture proves lazy container-backed command resolution.',
    usages: ['fixture:greet'],
)]
final class DiscoveredGreetingCommand extends Command
{
    public static int $constructions = 0;

    public function __construct(
        private readonly CommandGreeting $greeting,
    ) {
        self::$constructions++;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->greeting->message());

        return self::SUCCESS;
    }
}
