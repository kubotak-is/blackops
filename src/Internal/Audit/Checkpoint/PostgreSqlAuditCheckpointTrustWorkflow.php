<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditRecordTime;
use BlackOps\Internal\Audit\Artifact\AuditArtifactVerifier;
use BlackOps\Internal\Audit\Artifact\AuditCheckpointCodec;
use BlackOps\Internal\Audit\Artifact\AuditTrustSnapshotCodec;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

final readonly class PostgreSqlAuditCheckpointTrustWorkflow
{
    public function __construct(
        private ClockInterface $clock,
        private Uuidv7Generator $ids,
    ) {}

    /**
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',stateVersion:int,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,predecessorRecordHash:?string,previousSegmentId:?string,successorSegmentId:string,closeFencingToken:string,closedAt:string} $proof
     * @param array{bytes:string,hash:string,value:array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string,checkpointHash:string,signature:string}}|null $previous
     * @param array<string,mixed> $trustedSnapshot
     * @return array{unsigned:array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string},unsignedBytes:string,signingKeyFingerprint:string}
     */
    public function newPreparation(
        #[\SensitiveParameter]
        array $proof,
        #[\SensitiveParameter]
        ?array $previous,
        #[\SensitiveParameter]
        string $signingKeyRef,
        #[\SensitiveParameter]
        array $trustedSnapshot,
    ): array {
        try {
            [$now, $signedAt] = $this->canonicalNow($proof['closedAt']);
            $entry = AuditTrustSnapshotCodec::key($trustedSnapshot, $signingKeyRef, 'checkpoint', $signedAt);
            if ($previous !== null) {
                AuditArtifactVerifier::checkpoint($previous['bytes'], $trustedSnapshot, $signedAt);
            }
            $checkpointId = $this->generatedId($now);
            $unsigned = [
                'schemaVersion' => 1,
                'kind' => 'audit_checkpoint',
                'checkpointId' => $checkpointId,
                'partitionId' => $proof['partitionId'],
                'segmentId' => $proof['segmentId'],
                'firstSequence' => $proof['firstSequence'],
                'lastSequence' => $proof['lastSequence'],
                'recordCount' => $proof['recordCount'],
                'firstRecordHash' => $proof['firstRecordHash'],
                'chainHeadHash' => $proof['chainHeadHash'],
                'previousCheckpointHash' => $previous['hash'] ?? null,
                'sealedAt' => $proof['closedAt'],
                'signedAt' => $signedAt,
                'hashSuite' => 'audit-sha256-jcs-v1',
                'signatureAlgorithm' => 'ed25519-v1',
                'signingKeyRef' => $signingKeyRef,
            ];
            $input = AuditCheckpointCodec::signingInput($unsigned);

            return [
                'unsigned' => $unsigned,
                'unsignedBytes' => $input['bytes'],
                'signingKeyFingerprint' => $entry['publicKeyFingerprint'],
            ];
        } catch (AuditStoreException $failure) {
            throw new AuditStoreException($failure->failure());
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    /**
     * @param array{bytes:string,hash:string,value:array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string,checkpointHash:string,signature:string}}|null $previous
     * @param array<string,mixed> $trustedSnapshot
     */
    public function verifyPersistence(
        #[\SensitiveParameter]
        AuditCheckpointPreparation $preparation,
        #[\SensitiveParameter]
        ?array $previous,
        #[\SensitiveParameter]
        string $completeBytes,
        #[\SensitiveParameter]
        array $trustedSnapshot,
    ): void {
        try {
            $unsigned = $preparation->unsigned();
            $fingerprint = $preparation->signingKeyFingerprint();
            [, $verifiedAt] = $this->canonicalNow($unsigned['sealedAt']);
            $entry = AuditTrustSnapshotCodec::key(
                $trustedSnapshot,
                $unsigned['signingKeyRef'],
                'checkpoint',
                $verifiedAt,
            );
            if (!hash_equals($entry['publicKeyFingerprint'], $fingerprint)) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            AuditArtifactVerifier::checkpoint($completeBytes, $trustedSnapshot, $verifiedAt);
            if ($previous !== null) {
                AuditArtifactVerifier::checkpoint($previous['bytes'], $trustedSnapshot, $verifiedAt);
            }
        } catch (AuditStoreException $failure) {
            throw new AuditStoreException($failure->failure());
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    /** @return array{0:DateTimeImmutable,1:string} */
    private function canonicalNow(#[\SensitiveParameter] string $closedAt): array
    {
        try {
            AuditRecordTime::assertTimestamp($closedAt);
            $now = $this->clock->now();
            if (!$now instanceof DateTimeImmutable) {
                throw new \RuntimeException();
            }
            $utc = $now->setTimezone(new DateTimeZone('UTC'));
            $signedAt = $utc->format('Y-m-d\TH:i:s.u\Z');
            AuditRecordTime::assertTimestamp($signedAt);
            if (strcmp($signedAt, $closedAt) < 0) {
                throw new \RuntimeException();
            }

            return [$utc, $signedAt];
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
        }
    }

    private function generatedId(#[\SensitiveParameter] DateTimeImmutable $now): string
    {
        try {
            $id = $this->ids->generate($now);
            if (
                !is_string($id)
                || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $id) !== 1
            ) {
                throw new \RuntimeException();
            }

            return $id;
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
        }
    }
}
