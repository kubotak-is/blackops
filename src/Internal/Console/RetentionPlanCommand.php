<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\Retention\RetentionPeriod;
use BlackOps\Core\Retention\RetentionPlanner;
use BlackOps\Core\Retention\RetentionPolicy;
use BlackOps\Core\Retention\RetentionTarget;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Build and print a retention purge plan without applying it.')]
final class RetentionPlanCommand extends Command
{
    public const NAME = 'retention:plan';

    public function __construct(
        private readonly RetentionPlanner $planner,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'transport-payload-days',
                null,
                InputOption::VALUE_REQUIRED,
                'Transport payload retention days.',
            )
            ->addOption('journal-days', null, InputOption::VALUE_REQUIRED, 'Canonical journal retention days.')
            ->addOption('outcome-days', null, InputOption::VALUE_REQUIRED, 'Outcome retention days.')
            ->addOption('dead-letter-days', null, InputOption::VALUE_REQUIRED, 'Dead letter retention days.')
            ->addOption(
                'idempotency-record-days',
                null,
                InputOption::VALUE_REQUIRED,
                'Idempotency record retention days.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $plan = $this->planner->plan($this->policy($input), $this->clock->now());

        $output->writeln('Retention plan');
        $output->writeln('Total: ' . $plan->count());
        $output->writeln('transport_payload: ' . count($plan->forTarget(RetentionTarget::TransportPayload)));
        $output->writeln('journal: ' . count($plan->forTarget(RetentionTarget::Journal)));
        $output->writeln('outcome: ' . count($plan->forTarget(RetentionTarget::Outcome)));
        $output->writeln('dead_letter: ' . count($plan->forTarget(RetentionTarget::DeadLetter)));
        $output->writeln('idempotency_record: ' . count($plan->forTarget(RetentionTarget::IdempotencyRecord)));

        foreach ($plan->items() as $item) {
            $output->writeln(sprintf(
                '%s %s basis=%s eligible=%s',
                $item->target()->value,
                $item->operationId()->toString(),
                $item->basisAt()->format(DATE_ATOM),
                $item->eligibleAt()->format(DATE_ATOM),
            ));
        }

        return Command::SUCCESS;
    }

    private function policy(InputInterface $input): RetentionPolicy
    {
        return new RetentionPolicy(
            RetentionPeriod::days($this->positiveIntOption($input, 'transport-payload-days')),
            RetentionPeriod::days($this->positiveIntOption($input, 'journal-days')),
            RetentionPeriod::days($this->positiveIntOption($input, 'outcome-days')),
            RetentionPeriod::days($this->positiveIntOption($input, 'dead-letter-days')),
            RetentionPeriod::days($this->idempotencyDays($input)),
        );
    }

    private function positiveIntOption(InputInterface $input, string $name): int
    {
        if (!is_string($input->getOption($name))) {
            throw new InvalidArgumentException('Retention plan command option must be a positive integer.');
        }

        $value = (string) $input->getOption($name);

        if (!ctype_digit($value) || (int) $value < 1) {
            throw new InvalidArgumentException('Retention plan command option must be a positive integer.');
        }

        return (int) $value;
    }

    private function idempotencyDays(InputInterface $input): int
    {
        /** @var mixed $value */
        $value = $input->getOption('idempotency-record-days');
        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return max(
            $this->positiveIntOption($input, 'transport-payload-days'),
            $this->positiveIntOption($input, 'journal-days'),
            $this->positiveIntOption($input, 'outcome-days'),
            $this->positiveIntOption($input, 'dead-letter-days'),
        );
    }
}
