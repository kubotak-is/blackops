<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotation;

final readonly class ApplicationStorageProtectionRuntime
{
    public PostgreSqlStorageProtectionRotation $rotation;

    public function __construct(ApplicationConfigurationSnapshot $snapshot)
    {
        $runtime = new ApplicationOperationRuntimeComposer()->compose($snapshot);
        $database = ApplicationDatabaseConfiguration::fromConfiguration($snapshot->configuration());
        $codec = $runtime->protection;
        $this->rotation = new PostgreSqlStorageProtectionRotation($runtime->connection, $codec, $database->schema);
    }
}
