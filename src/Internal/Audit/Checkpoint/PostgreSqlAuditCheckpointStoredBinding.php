<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;

final class PostgreSqlAuditCheckpointStoredBinding
{
    private const string UUID_V7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    private const string HASH = '/^[0-9a-f]{64}$/D';

    private const array COMPLETE_STRING_PATTERNS = [
        'checkpoint_id' => self::UUID_V7,
        'checkpoint_hash' => self::HASH,
    ];

    private const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    private function __construct() {}

    /**
     * @param array<array-key,mixed> $existing
     * @return array{checkpointId:string,checkpointHash:string,bytes:string}
     */
    public static function row(#[\SensitiveParameter] array $existing): array
    {
        try {
            if (array_keys($existing) !== ['checkpoint_id', 'checkpoint_hash', 'complete_bytes']) {
                throw new \RuntimeException();
            }
            foreach (self::COMPLETE_STRING_PATTERNS as $field => $pattern) {
                $value = $existing[$field];
                if (!is_string($value) || preg_match($pattern, $value) !== 1) {
                    throw new \RuntimeException();
                }
            }
            $id = $existing['checkpoint_id'];
            $hash = $existing['checkpoint_hash'];
            /** @var string $id */
            /** @var string $hash */
            $bytes = PostgreSqlAuditCheckpointBytes::database($existing['complete_bytes']);

            return ['checkpointId' => $id, 'checkpointHash' => $hash, 'bytes' => $bytes];
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    /**
     * @param array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string,checkpointHash:string,signature:string} $complete
     */
    public static function identity(
        #[\SensitiveParameter]
        string $storedId,
        #[\SensitiveParameter]
        mixed $storedHash,
        #[\SensitiveParameter]
        array $complete,
        #[\SensitiveParameter]
        string $expectedId,
    ): void {
        if (
            !is_string($storedHash)
            || preg_match(self::HASH, $storedHash) !== 1
            || !hash_equals($storedHash, $complete['checkpointHash'])
            || !hash_equals($complete['checkpointId'], $expectedId)
            || !hash_equals($complete['checkpointId'], $storedId)
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    public static function stateVersion(
        #[\SensitiveParameter]
        string $state,
        #[\SensitiveParameter]
        int $captured,
        #[\SensitiveParameter]
        int $current,
    ): void {
        if (
            $captured < 2
            || $captured >= self::MAX_SAFE_INTEGER
            || !in_array($state, ['closed', 'sealed'], strict: true)
            || $current !== ($state === 'closed' ? $captured : $captured + 1)
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }
}
