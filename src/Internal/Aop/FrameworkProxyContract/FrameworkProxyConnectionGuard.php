<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyContract;

final readonly class FrameworkProxyConnectionGuard
{
    /**
     * @param array<string,true> $knownConnections
     * @mago-expect lint:excessive-parameter-list
     */
    public function resolve(
        ?string $requested,
        ?string $default,
        array $knownConnections,
        string $sourceClass,
        ?string $method = null,
        ?string $serviceId = null,
        ?string $buildId = null,
    ): string {
        $connection = $requested ?? $default;

        if ($connection === null || !array_key_exists($connection, $knownConnections)) {
            throw new FrameworkProxyContractException(
                new FrameworkProxyDiagnostic(
                    FrameworkProxyDiagnosticCode::CONNECTION_UNKNOWN,
                    serviceId: $serviceId,
                    sourceClass: $sourceClass,
                    method: $method,
                    buildId: $buildId,
                ),
            );
        }

        return $connection;
    }
}
