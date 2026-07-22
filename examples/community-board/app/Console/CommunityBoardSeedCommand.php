<?php

declare(strict_types=1);

namespace App\Console;

use App\Infrastructure\Seed\CommunityBoardSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'app:seed', description: 'Seed deterministic Community Board application data.')]
final class CommunityBoardSeedCommand extends Command
{
    public function __construct(
        private readonly CommunityBoardSeeder $seeder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $result = $this->seeder->seed();
        } catch (Throwable) {
            $output->writeln(
                '<error>Community Board seed failed. Confirm the database is available and migrations are applied.</error>',
            );

            return self::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>Community Board seed is ready: %d users, %d posts, %d comments.</info>',
            $result->users,
            $result->posts,
            $result->comments,
        ));

        return self::SUCCESS;
    }
}
