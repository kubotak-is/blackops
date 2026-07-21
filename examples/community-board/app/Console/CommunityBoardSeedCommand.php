<?php

declare(strict_types=1);

namespace App\Console;

use App\Infrastructure\Seed\CommunityBoardSeeder;
use App\Infrastructure\Seed\SeedResult;
use Closure;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class CommunityBoardSeedCommand extends Command
{
    /** @var Closure(): SeedResult */
    private Closure $seed;

    /** @param (Closure(): SeedResult)|null $seed */
    public function __construct(?Closure $seed = null)
    {
        parent::__construct('app:seed');
        $this->setDescription('Seed deterministic Community Board application data.');
        $this->seed =
            $seed ?? static fn(): SeedResult => CommunityBoardSeeder::fromEnvironment(self::environment())->seed();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $result = ($this->seed)();
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

    /** @return array<string, string> */
    private static function environment(): array
    {
        $environment = [];
        foreach ($_ENV as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $environment[$name] = $value;
            }
        }

        return $environment;
    }
}
