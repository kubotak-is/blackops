<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;

final readonly class PostgreSqlAuditClosedSegmentRecordProof
{
    private PostgreSqlAuditClosedSegmentQueries $queries;

    private PostgreSqlAuditClosedSegmentPages $pages;

    public function __construct(
        PostgreSqlAuditClosedSegmentQueries $queries,
        PostgreSqlAuditStoredRecordHydrator $hydrator,
        AuditProtectedBodyCodec $protection,
    ) {
        $record = new PostgreSqlAuditClosedSegmentRecord($hydrator, $protection);
        $this->queries = $queries;
        $this->pages = new PostgreSqlAuditClosedSegmentPages($queries, $record);
    }

    /**
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} $target
     * @return array{recordCount:int,firstRecordHash:string,chainHeadHash:string}
     */
    public function prove(
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
        #[\SensitiveParameter]
        array $target,
    ): array {
        try {
            if ($target['partitionId'] !== $partitionId || $target['segmentId'] !== $segmentId) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            if ($this->queries->tenantMismatch($tenant, $partitionId, $segmentId)) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }

            $aggregate = $this->pages->read($tenant, $target);
            $count = $aggregate['recordCount'];
            if ($count === 0) {
                throw new AuditStoreException(AuditStoreFailure::SegmentConflict);
            }
            if (
                $count !== ($target['finalSequence'] - $target['firstSequence'] + 1)
                || $aggregate['nextSequence'] !== ($target['finalSequence'] + 1)
                || $aggregate['firstRecordHash'] === null
                || $aggregate['chainHeadHash'] === null
                || !hash_equals($aggregate['chainHeadHash'], $target['headRecordHash'])
            ) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }

            /** @var string $firstHash */
            $firstHash = $aggregate['firstRecordHash'];
            /** @var string $headHash */
            $headHash = $aggregate['chainHeadHash'];
            return [
                'recordCount' => $count,
                'firstRecordHash' => $firstHash,
                'chainHeadHash' => $headHash,
            ];
        } catch (AuditStoreException $failure) {
            throw new AuditStoreException($failure->failure());
        } catch (AuditProtectionException) {
            throw new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
    }

    private function safeFailure(#[\SensitiveParameter] \Throwable $failure): AuditStoreException
    {
        if ($failure instanceof AuditStoreException) {
            return new AuditStoreException($failure->failure());
        }
        if ($failure instanceof AuditProtectionException) {
            return new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
        }
        if ($failure instanceof AuditValidationException) {
            return new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return new AuditStoreException(AuditStoreFailure::StorageUnavailable);
    }
}
