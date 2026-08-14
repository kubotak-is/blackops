<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Diagnostics\OperationDiagnosticsQuery;
use BlackOps\Internal\Diagnostics\PostgreSqlDiagnosticsSourceReader;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDiagnosticsReader;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeStore;

final readonly class ApplicationDiagnosticsQueryFactory
{
    public function __construct(
        private ApplicationConfigurationSnapshot $configuration,
    ) {}

    public function create(): OperationDiagnosticsQuery
    {
        $database = ApplicationDatabaseConfiguration::fromConfiguration($this->configuration->configuration());
        $connection = $database->databaseManager()->connection($database->frameworkConnection);
        $runtime = new ApplicationOperationRuntimeComposer()->compose($this->configuration);
        $protection = $runtime->protection;

        return new OperationDiagnosticsQuery(
            new PostgreSqlCanonicalJournalStore($connection, $protection, $database->schema),
            new PostgreSqlOutcomeStore($connection, $protection, $database->schema),
            new PostgreSqlDiagnosticsSourceReader(new PostgreSqlDiagnosticsReader($connection, $database->schema)),
        );
    }
}
