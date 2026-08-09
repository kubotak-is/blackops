<?php

declare(strict_types=1);

namespace BlackOps\Observability;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
final class OperationalHealthQueryFactory
{
    /** @return list<string> */
    public static function requiredReadinessCheckCodes(): array
    {
        return [
            'compiled_artifact',
            'runtime_configuration',
            'database',
            'migration_compatibility',
            'storage_key_provider',
            'runtime_services',
        ];
    }

    /**
     * @param array<string, callable(): bool> $checks
     */
    public static function fromCallbacks(array $checks): OperationalHealthQuery
    {
        return new CallbackOperationalHealthQuery($checks);
    }
}
