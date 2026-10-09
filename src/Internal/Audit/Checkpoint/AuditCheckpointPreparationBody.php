<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Internal\Audit\Artifact\AuditCheckpointCodec;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;

final class AuditCheckpointPreparationBody
{
    private function __construct() {}

    /**
     * @param string $bytes
     * @return array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string}
     */
    public static function read(#[\SensitiveParameter] string $bytes): array
    {
        try {
            if ($bytes === '' || strlen($bytes) > 1_048_575 || str_contains($bytes, "\n")) {
                throw new \RuntimeException();
            }

            $decoded = json_decode(
                $bytes,
                associative: true,
                depth: 2,
                flags: JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING,
            );
            if (!is_array($decoded) || array_is_list($decoded)) {
                throw new \RuntimeException();
            }

            /** @var array<string,mixed> $unsigned */
            $unsigned = $decoded;
            $input = AuditCheckpointCodec::signingInput($unsigned);
            if (!hash_equals($input['bytes'], $bytes)) {
                throw new \RuntimeException();
            }

            /**
             * The signingInput validator has validated the complete unsigned shape before
             * the canonical bytes were accepted. Keep the narrow postcondition
             * here so the bounded decoder remains the only untrusted parser.
             *
             * @var array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} $unsigned
             */
            return $unsigned;
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }
}
