<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Console\RetentionPlanCommand;
use BlackOps\Internal\Console\RetentionPurgeCommand;
use BlackOps\Internal\Console\SchedulerDaemonCommand;
use BlackOps\Internal\Console\SchedulerRunCommand;
use Symfony\Component\Console\Command\Command;

final class ApplicationRetentionCommandFactory
{
    private ?ApplicationRetentionRuntime $runtime = null;

    public function __construct(
        private readonly ApplicationConfigurationSnapshot $configuration,
    ) {}

    public function plan(): Command
    {
        $runtime = $this->runtime();
        $command = new RetentionPlanCommand($runtime->planner, $runtime->clock);
        $this->defaults($command, $runtime->configuration);

        return $command;
    }

    public function purge(): Command
    {
        $runtime = $this->runtime();
        $command = new RetentionPurgeCommand($runtime->planner, $runtime->purge, $runtime->clock);
        $this->defaults($command, $runtime->configuration);
        $command->getDefinition()->getOption('policy-ref')->setDefault($runtime->configuration->policyRef->toString());
        $command->getDefinition()->getOption('actor')->setDefault($runtime->configuration->actor->toString());

        return $command;
    }

    public function schedulerRun(): Command
    {
        $runtime = $this->runtime();

        return new SchedulerRunCommand($runtime->scheduler, $runtime->clock);
    }

    public function schedulerDaemon(): Command
    {
        $runtime = $this->runtime();

        return new SchedulerDaemonCommand($runtime->scheduler, $runtime->clock);
    }

    private function runtime(): ApplicationRetentionRuntime
    {
        return $this->runtime ??= new ApplicationRetentionRuntime($this->configuration);
    }

    private function defaults(Command $command, ApplicationRetentionConfiguration $configuration): void
    {
        foreach ([
            'transport-payload-days' => $configuration->transportPayloadDays,
            'journal-days' => $configuration->journalDays,
            'outcome-days' => $configuration->outcomeDays,
            'dead-letter-days' => $configuration->deadLetterDays,
        ] as $name => $days) {
            $command
                ->getDefinition()
                ->getOption($name)
                ->setDefault((string) $days);
        }
    }
}
