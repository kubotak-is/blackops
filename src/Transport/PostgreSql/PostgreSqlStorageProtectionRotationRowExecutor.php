<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use Doctrine\DBAL\Connection;

final readonly class PostgreSqlStorageProtectionRotationRowExecutor
{
    private PostgreSqlStorageProtectionRotationRowTransaction $transaction;

    public function __construct(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        BopdEnvelopeCodec $codec,
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationQuery $query,
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationAudit $audit,
        #[\SensitiveParameter]
        ?PostgreSqlStorageProtectionRotationPhaseHook $phaseHook = null,
    ) {
        $this->transaction = new PostgreSqlStorageProtectionRotationRowTransaction(
            $connection,
            $audit,
            new PostgreSqlStorageProtectionRotationColumnExecutor($connection, $codec, $query),
            $phaseHook,
        );
    }

    public function execute(
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationRowRequest $request,
    ): PostgreSqlStorageProtectionRotationRowResult {
        return $this->transaction->execute($request);
    }
}
