<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Internal\Audit\Artifact\AuditCheckpointCodec;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;

final class PostgreSqlAuditCheckpointBytes
{
    private const int MAX_BYTES = 1_048_575;

    private function __construct() {}

    public static function database(#[\SensitiveParameter] mixed $value): string
    {
        try {
            return self::bounded(PostgreSqlBytea::string($value));
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    /** @return array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string,checkpointHash:string,signature:string} */
    public static function completeInput(#[\SensitiveParameter] string $bytes): array
    {
        try {
            return AuditCheckpointCodec::decode(self::bounded($bytes));
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    /** @param array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} $unsigned */
    public static function unsignedBytes(#[\SensitiveParameter] array $unsigned): string
    {
        try {
            return AuditCheckpointCodec::signingInput($unsigned)['bytes'];
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    /**
     * @param array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} $supplied
     * @param array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} $prepared
     */
    public static function persistUnsigned(
        #[\SensitiveParameter]
        array $supplied,
        #[\SensitiveParameter]
        array $prepared,
    ): void {
        try {
            if (!hash_equals(self::unsignedBytes($supplied), self::unsignedBytes($prepared))) {
                throw new \RuntimeException();
            }
        } catch (AuditStoreException $failure) {
            throw new AuditStoreException($failure->failure());
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    private static function bounded(#[\SensitiveParameter] string $bytes): string
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES || str_contains($bytes, "\n")) {
            throw new \RuntimeException();
        }

        return $bytes;
    }
}
