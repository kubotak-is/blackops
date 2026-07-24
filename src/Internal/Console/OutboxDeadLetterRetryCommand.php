<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\Identifier\OutboxRecordId;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Retry one dead-lettered transactional outbox record.')]
final class OutboxDeadLetterRetryCommand extends Command
{
    public const NAME = 'outbox:dead-letter:retry';

    public function __construct(
        private readonly PostgreSqlOutboxStore $store,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('record-id', InputArgument::REQUIRED)
            ->addOption('actor', null, InputOption::VALUE_REQUIRED)
            ->addOption('reason', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var mixed $actor */
        $actor = $input->getOption('actor');
        /** @var mixed $reason */
        $reason = $input->getOption('reason');
        if (!is_string($actor) || trim($actor) === '' || !is_string($reason) || trim($reason) === '') {
            throw new \InvalidArgumentException('Dead-letter retry actor and reason are required.');
        }
        $this->store->retryDeadLetter(
            OutboxRecordId::fromString((string) $input->getArgument('record-id')),
            $actor,
            $reason,
            $this->clock->now(),
        );
        $output->writeln('dead-letter retry scheduled');
        return Command::SUCCESS;
    }
}
