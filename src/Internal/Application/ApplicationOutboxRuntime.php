<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Execution\PcntlSignalSupport;
use BlackOps\Internal\Outbox\OutboxRelayConfiguration;
use BlackOps\Internal\Outbox\OutboxRelayRuntime;
use BlackOps\Internal\Outbox\PcntlOutboxSignalHeartbeat;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxClaim;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use BlackOps\Transport\PostgreSql\PostgreSqlSystemClock;
use Psr\Clock\ClockInterface;

final readonly class ApplicationOutboxRuntime
{
    public OutboxRelayRuntime $relay;
    public PostgreSqlOutboxStore $store;
    public ClockInterface $clock;

    public function __construct(ApplicationConfigurationSnapshot $snapshot)
    {
        $database = ApplicationDatabaseConfiguration::fromConfiguration($snapshot->configuration());
        $connection = $database->databaseManager()->connection($database->frameworkConnection);
        $this->clock = new PostgreSqlSystemClock();
        $this->store = new PostgreSqlOutboxStore($connection, $database->schema);
        $configuration = $this->configuration($snapshot);
        $heartbeatStore = new PostgreSqlOutboxStore(
            $database->databaseManager()->connection($database->frameworkConnection),
            $database->schema,
        );
        $this->relay = new OutboxRelayRuntime(
            $this->store,
            new PostgreSqlDeferredOperationSender($connection, $database->schema),
            $configuration,
            $this->clock,
            $heartbeatStore,
            PcntlSignalSupport::available()
                ? new PcntlOutboxSignalHeartbeat(
                    function (PostgreSqlOutboxClaim $claim) use ($heartbeatStore, $configuration): void {
                        $heartbeatStore->heartbeat($claim, $this->clock->now(), $configuration->leaseSeconds);
                    },
                    $configuration->heartbeatSeconds,
                    $configuration->leaseSeconds,
                    $configuration->graceSeconds,
                )
                : null,
        );
    }

    private function configuration(ApplicationConfigurationSnapshot $snapshot): OutboxRelayConfiguration
    {
        $configuration = ApplicationOutboxRelayConfiguration::fromConfiguration($snapshot->configuration());
        return new OutboxRelayConfiguration(
            $configuration->id,
            $configuration->batchSize,
            $configuration->leaseSeconds,
            $configuration->heartbeatSeconds,
            $configuration->graceSeconds,
            $configuration->maxAttempts,
            $configuration->initialBackoffSeconds,
            $configuration->maxBackoffSeconds,
            $configuration->pollIntervalMilliseconds,
        );
    }
}
