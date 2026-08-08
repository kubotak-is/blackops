<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use Doctrine\DBAL\Connection;

final readonly class PostgreSqlStorageProtectionRotationRowExecutor
{
    private PostgreSqlStorageProtectionRotationRowTransaction $transaction;

    public function __construct(
        Connection $connection,
        BopdEnvelopeCodec $codec,
        PostgreSqlStorageProtectionRotationQuery $query,
        PostgreSqlStorageProtectionRotationAudit $audit,
    ) {
        $this->transaction = new PostgreSqlStorageProtectionRotationRowTransaction(
            $connection,
            $audit,
            new PostgreSqlStorageProtectionRotationColumnExecutor($connection, $codec, $query),
        );
    }

    public function execute(PostgreSqlStorageProtectionRotationRowRequest $request): PostgreSqlStorageProtectionRotationRowResult
    {
        return $this->transaction->execute($request);
    }
}
