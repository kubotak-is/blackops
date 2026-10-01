<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationResult;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;
use Doctrine\DBAL\Connection;

final readonly class PostgreSqlStorageProtectionRotation
{
    private PostgreSqlStorageProtectionRotationEngine $engine;

    public function __construct(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        BopdEnvelopeCodec $codec,
        #[\SensitiveParameter]
        string $schema = 'blackops',
        #[\SensitiveParameter]
        ?PostgreSqlStorageProtectionRotationPhaseHook $phaseHook = null,
    ) {
        $this->engine = new PostgreSqlStorageProtectionRotationEngine($connection, $codec, $schema, $phaseHook);
    }

    public function plan(#[\SensitiveParameter] StorageProtectionRotationScope $scope): StorageProtectionRotationResult
    {
        return $this->engine->plan($scope);
    }

    public function rotate(
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
    ): StorageProtectionRotationResult {
        return $this->engine->rotate($scope);
    }
}
