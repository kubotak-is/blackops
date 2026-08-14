<?php

declare(strict_types=1);

namespace BlackOps\Internal\Observability;

use BlackOps\Observability\OperationalHealthCheckProvider;
use Closure;

final readonly class ApplicationOperationalHealthChecks
{
    public const string COMPILED_ARTIFACT = 'compiled_artifact';
    public const string RUNTIME_CONFIGURATION = 'runtime_configuration';
    public const string DATABASE = 'database';
    public const string MIGRATION_COMPATIBILITY = 'migration_compatibility';
    public const string STORAGE_KEY_PROVIDER = 'storage_key_provider';
    public const string RUNTIME_SERVICES = 'runtime_services';

    /** @param array<string, Closure(): bool> $checks @return list<OperationalHealthCheckProvider> */
    public static function fromCallbacks(array $checks): array
    {
        $providers = [];
        foreach (self::codes() as $code) {
            $callback = $checks[$code] ?? null;
            if (!$callback instanceof Closure) {
                throw new \InvalidArgumentException('Operational health check composition is incomplete.');
            }
            $providers[] = new CallbackOperationalHealthCheck($code, $callback);
        }

        return $providers;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return [
            self::COMPILED_ARTIFACT,
            self::RUNTIME_CONFIGURATION,
            self::DATABASE,
            self::MIGRATION_COMPATIBILITY,
            self::STORAGE_KEY_PROVIDER,
            self::RUNTIME_SERVICES,
        ];
    }
}
