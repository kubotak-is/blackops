<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Scheduler\MaintenanceScheduler;
use BlackOps\Internal\Scheduler\RetentionMaintenanceTask;
use BlackOps\Transport\PostgreSql\PostgreSqlDeadLetterRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPlanner;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditStore;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeService;
use BlackOps\Transport\PostgreSql\PostgreSqlSystemClock;
use BlackOps\Transport\PostgreSql\PostgreSqlTransportPayloadTombstoneService;
use Psr\Clock\ClockInterface;

final readonly class ApplicationRetentionRuntime
{
    public ApplicationRetentionConfiguration $configuration;
    public PostgreSqlRetentionPlanner $planner;
    public PostgreSqlRetentionPurgeService $purge;
    public ClockInterface $clock;
    public MaintenanceScheduler $scheduler;

    public function __construct(ApplicationConfigurationSnapshot $snapshot)
    {
        $database = ApplicationDatabaseConfiguration::fromConfiguration($snapshot->configuration());
        $this->configuration = ApplicationRetentionConfiguration::fromConfiguration($snapshot->configuration());
        $connection = $database->databaseManager()->connection($database->frameworkConnection);
        $this->clock = new PostgreSqlSystemClock();
        $this->planner = new PostgreSqlRetentionPlanner($connection, $database->schema);
        $audit = new PostgreSqlRetentionPurgeAuditStore($connection, $database->schema);
        $this->purge = new PostgreSqlRetentionPurgeService(
            $this->planner,
            new PostgreSqlTransportPayloadTombstoneService($connection, $audit, $database->schema, $this->clock),
            new PostgreSqlOutcomeRetentionDeleteService($connection, $audit, $database->schema, $this->clock),
            new PostgreSqlDeadLetterRetentionDeleteService($connection, $audit, $database->schema, $this->clock),
            new PostgreSqlJournalRetentionDeleteService($connection, $audit, $database->schema, $this->clock),
        );
        $this->scheduler = new MaintenanceScheduler([
            new RetentionMaintenanceTask(
                $this->purge,
                $this->configuration->policy,
                $this->configuration->policyRef,
                $this->configuration->actor,
            ),
        ]);
    }
}
