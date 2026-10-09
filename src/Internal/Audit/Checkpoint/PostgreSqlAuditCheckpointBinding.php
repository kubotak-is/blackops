<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;

final class PostgreSqlAuditCheckpointBinding
{
    private function __construct() {}

    /**
     * @param array{partitionId:string,segmentId:string,closedStateVersion:int,closeFencingToken:string} $boundary
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',stateVersion:int,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,predecessorRecordHash:?string,previousSegmentId:?string,successorSegmentId:string,closeFencingToken:string,closedAt:string} $proof
     * @param array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} $unsigned
     * @param array{bytes:string,hash:string,value:array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string,checkpointHash:string,signature:string}}|null $previous
     */
    public static function preparation(
        #[\SensitiveParameter]
        array $boundary,
        #[\SensitiveParameter]
        array $proof,
        #[\SensitiveParameter]
        array $unsigned,
        #[\SensitiveParameter]
        ?array $previous,
    ): void {
        PostgreSqlAuditCheckpointSegmentBinding::preparation($boundary, $proof, $unsigned, $previous);
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
        PostgreSqlAuditCheckpointBytes::persistUnsigned($supplied, $prepared);
    }

    /** @return array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string,checkpointHash:string,signature:string} */
    public static function completeInput(#[\SensitiveParameter] string $bytes): array
    {
        return PostgreSqlAuditCheckpointBytes::completeInput($bytes);
    }

    /**
     * @param array<array-key,mixed> $existing
     * @param array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} $unsigned
     */
    public static function sealedReplay(
        #[\SensitiveParameter]
        array $existing,
        #[\SensitiveParameter]
        array $unsigned,
        #[\SensitiveParameter]
        string $expectedId,
    ): void {
        try {
            $stored = self::completeRow($existing);
            $complete = self::completeInput($stored['bytes']);
            $candidate = $complete;
            unset($candidate['checkpointHash'], $candidate['signature']);
            /** @var array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} $candidate */
            PostgreSqlAuditCheckpointStoredBinding::identity(
                $stored['checkpointId'],
                $stored['checkpointHash'],
                $complete,
                $expectedId,
            );
            self::persistUnsigned($candidate, $unsigned);
        } catch (AuditStoreException $failure) {
            throw new AuditStoreException($failure->failure());
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    /**
     * @param array<array-key,mixed> $existing
     * @param array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string,checkpointHash:string,signature:string} $complete
     */
    public static function completeReplay(
        #[\SensitiveParameter]
        array $existing,
        #[\SensitiveParameter]
        array $complete,
        #[\SensitiveParameter]
        string $completeBytes,
        #[\SensitiveParameter]
        string $expectedId,
    ): void {
        try {
            $stored = self::completeRow($existing);
            PostgreSqlAuditCheckpointStoredBinding::identity(
                $stored['checkpointId'],
                $stored['checkpointHash'],
                $complete,
                $expectedId,
            );
            if (!hash_equals($stored['bytes'], $completeBytes)) {
                throw new \RuntimeException();
            }
        } catch (AuditStoreException $failure) {
            throw new AuditStoreException($failure->failure());
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    /** @param array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} $unsigned */
    public static function unsignedBytes(#[\SensitiveParameter] array $unsigned): string
    {
        return PostgreSqlAuditCheckpointBytes::unsignedBytes($unsigned);
    }

    public static function stateVersion(
        #[\SensitiveParameter]
        string $state,
        #[\SensitiveParameter]
        int $captured,
        #[\SensitiveParameter]
        int $current,
    ): void {
        PostgreSqlAuditCheckpointStoredBinding::stateVersion($state, $captured, $current);
    }

    /**
     * @param array<array-key,mixed> $existing
     * @return array{checkpointId:string,checkpointHash:string,bytes:string}
     */
    private static function completeRow(#[\SensitiveParameter] array $existing): array
    {
        return PostgreSqlAuditCheckpointStoredBinding::row($existing);
    }
}
