<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPeriod;
use BlackOps\Core\Retention\RetentionPlanner;
use BlackOps\Core\Retention\RetentionPolicy;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Core\Retention\RetentionPurgeService;
use BlackOps\Core\Retention\RetentionTarget;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Dry-run or apply retention purge.')]
final class RetentionPurgeCommand extends Command
{
    public const NAME = 'blackops:retention:purge';

    public function __construct(
        private readonly RetentionPlanner $planner,
        private readonly RetentionPurgeService $purge,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the plan without applying purge.')
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Apply purge.')
            ->addOption(
                'transport-payload-days',
                null,
                InputOption::VALUE_REQUIRED,
                'Transport payload retention days.',
            )
            ->addOption('journal-days', null, InputOption::VALUE_REQUIRED, 'Canonical journal retention days.')
            ->addOption('outcome-days', null, InputOption::VALUE_REQUIRED, 'Outcome retention days.')
            ->addOption('dead-letter-days', null, InputOption::VALUE_REQUIRED, 'Dead letter retention days.')
            ->addOption('policy-ref', null, InputOption::VALUE_REQUIRED, 'Retention policy reference.')
            ->addOption('actor', null, InputOption::VALUE_REQUIRED, 'Retention purge actor reference.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = $input->getOption('dry-run') === true;
        $confirm = $input->getOption('confirm') === true;

        if ($dryRun === $confirm) {
            throw new InvalidArgumentException(
                'Retention purge command requires exactly one of --dry-run or --confirm.',
            );
        }

        $policy = $this->policy($input);
        $now = $this->clock->now();

        if ($dryRun) {
            $plan = $this->planner->plan($policy, $now);
            $this->writePlan($output, $plan->count(), [
                'transport_payload' => count($plan->forTarget(RetentionTarget::TransportPayload)),
                'journal' => count($plan->forTarget(RetentionTarget::Journal)),
                'outcome' => count($plan->forTarget(RetentionTarget::Outcome)),
                'dead_letter' => count($plan->forTarget(RetentionTarget::DeadLetter)),
            ]);

            return Command::SUCCESS;
        }

        $result = $this->purge->purge(
            $policy,
            RetentionPolicyRef::fromString($this->stringOption($input, 'policy-ref')),
            RetentionActorRef::fromString($this->stringOption($input, 'actor')),
            $now,
        );

        $output->writeln('Retention purge applied');
        $output->writeln('planned: ' . $result->plan()->count());
        $output->writeln('transport_payload_purged: ' . $result->transportPayloadsPurged());
        $output->writeln('dead_letters_deleted: ' . $result->deadLettersDeleted());
        $output->writeln('total_affected: ' . $result->totalAffected());

        return Command::SUCCESS;
    }

    /**
     * @param array<string, int> $counts
     */
    private function writePlan(OutputInterface $output, int $total, array $counts): void
    {
        $output->writeln('Retention purge dry run');
        $output->writeln('Total: ' . $total);

        foreach ($counts as $target => $count) {
            $output->writeln($target . ': ' . $count);
        }
    }

    private function policy(InputInterface $input): RetentionPolicy
    {
        return new RetentionPolicy(
            RetentionPeriod::days($this->positiveIntOption($input, 'transport-payload-days')),
            RetentionPeriod::days($this->positiveIntOption($input, 'journal-days')),
            RetentionPeriod::days($this->positiveIntOption($input, 'outcome-days')),
            RetentionPeriod::days($this->positiveIntOption($input, 'dead-letter-days')),
        );
    }

    private function positiveIntOption(InputInterface $input, string $name): int
    {
        if (!is_string($input->getOption($name))) {
            throw new InvalidArgumentException('Retention purge command option must be a positive integer.');
        }

        $value = (string) $input->getOption($name);

        if (!ctype_digit($value) || (int) $value < 1) {
            throw new InvalidArgumentException('Retention purge command option must be a positive integer.');
        }

        return (int) $value;
    }

    private function stringOption(InputInterface $input, string $name): string
    {
        if (!is_string($input->getOption($name)) || $input->getOption($name) === '') {
            throw new InvalidArgumentException('Retention purge command option must be a non-empty string.');
        }

        return (string) $input->getOption($name);
    }
}
