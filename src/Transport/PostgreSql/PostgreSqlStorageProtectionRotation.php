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

    public function __construct(Connection $connection, BopdEnvelopeCodec $codec, string $schema = 'blackops')
    {
        $this->engine = new PostgreSqlStorageProtectionRotationEngine($connection, $codec, $schema);
    }

    public function plan(StorageProtectionRotationScope $scope): StorageProtectionRotationResult
    {
        return $this->engine->plan($scope);
    }

    public function rotate(StorageProtectionRotationScope $scope): StorageProtectionRotationResult
    {
        return $this->engine->rotate($scope);
    }
}
