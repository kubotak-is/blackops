<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;

final class PostgreSqlAuditCheckpointSegmentBinding
{
    private const array BOUNDARY_STRICT_FIELDS = ['partitionId', 'segmentId'];

    private const array UNSIGNED_RANGE_FIELDS = [
        'partitionId',
        'segmentId',
        'firstSequence',
        'lastSequence',
        'recordCount',
    ];

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
        try {
            self::boundary($boundary, [
                'partitionId' => $proof['partitionId'],
                'segmentId' => $proof['segmentId'],
                'closeFencingToken' => $proof['closeFencingToken'],
            ]);
            self::range($unsigned, [
                'partitionId' => $proof['partitionId'],
                'segmentId' => $proof['segmentId'],
                'firstSequence' => $proof['firstSequence'],
                'lastSequence' => $proof['lastSequence'],
                'recordCount' => $proof['recordCount'],
            ]);
            if (!hash_equals($unsigned['firstRecordHash'], $proof['firstRecordHash'])) {
                throw new \RuntimeException();
            }
            self::tail($unsigned, $proof['chainHeadHash'], $proof['closedAt']);
            PostgreSqlAuditCheckpointStoredBinding::stateVersion(
                $proof['state'],
                $boundary['closedStateVersion'],
                $proof['stateVersion'],
            );
            $previousHash = $unsigned['previousCheckpointHash'];
            if (($previous === null) !== ($previousHash === null)) {
                throw new \RuntimeException();
            }
            if ($previous !== null) {
                if ($previousHash === null || !hash_equals($previousHash, $previous['hash'])) {
                    throw new \RuntimeException();
                }
            }
        } catch (AuditStoreException $failure) {
            throw new AuditStoreException($failure->failure());
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    /**
     * @param array{partitionId:string,segmentId:string,closedStateVersion:int,closeFencingToken:string} $boundary
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} $segment
     * @param array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} $unsigned
     */
    public static function predecessor(
        #[\SensitiveParameter]
        array $boundary,
        #[\SensitiveParameter]
        array $segment,
        #[\SensitiveParameter]
        array $unsigned,
    ): void {
        self::boundary($boundary, [
            'partitionId' => $segment['partitionId'],
            'segmentId' => $segment['segmentId'],
            'closeFencingToken' => $segment['closeFencingToken'],
        ]);
        self::range($unsigned, [
            'partitionId' => $segment['partitionId'],
            'segmentId' => $segment['segmentId'],
            'firstSequence' => $segment['firstSequence'],
            'lastSequence' => $segment['finalSequence'],
            'recordCount' => $segment['finalSequence'] - $segment['firstSequence'] + 1,
        ]);
        self::tail($unsigned, $segment['headRecordHash'], $segment['closedAt']);
    }

    /**
     * @param array{partitionId:string,segmentId:string,closedStateVersion:int,closeFencingToken:string} $boundary
     * @param array{partitionId:string,segmentId:string,closeFencingToken:string} $identity
     */
    private static function boundary(
        #[\SensitiveParameter]
        array $boundary,
        #[\SensitiveParameter]
        array $identity,
    ): void {
        foreach (self::BOUNDARY_STRICT_FIELDS as $field) {
            if ($boundary[$field] !== $identity[$field]) {
                throw new \RuntimeException();
            }
        }
        if (!hash_equals($boundary['closeFencingToken'], $identity['closeFencingToken'])) {
            throw new \RuntimeException();
        }
    }

    /**
     * @param array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} $unsigned
     * @param array{partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int} $reference
     */
    private static function range(#[\SensitiveParameter] array $unsigned, #[\SensitiveParameter] array $reference): void
    {
        foreach (self::UNSIGNED_RANGE_FIELDS as $field) {
            if ($unsigned[$field] !== $reference[$field]) {
                throw new \RuntimeException();
            }
        }
    }

    /** @param array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} $unsigned */
    private static function tail(
        #[\SensitiveParameter]
        array $unsigned,
        #[\SensitiveParameter]
        string $headHash,
        #[\SensitiveParameter]
        string $sealedAt,
    ): void {
        if (!hash_equals($unsigned['chainHeadHash'], $headHash) || !hash_equals($unsigned['sealedAt'], $sealedAt)) {
            throw new \RuntimeException();
        }
    }
}
