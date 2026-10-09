<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;

final readonly class PostgreSqlAuditClosedSegmentRecord
{
    public function __construct(
        private PostgreSqlAuditStoredRecordHydrator $hydrator,
        private AuditProtectedBodyCodec $protection,
    ) {}

    /**
     * @param array<array-key,mixed> $row
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} $target
     */
    public function read(
        #[\SensitiveParameter]
        array $row,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        array $target,
        #[\SensitiveParameter]
        int $expected,
        #[\SensitiveParameter]
        ?string $headHash,
    ): string {
        $keys = [
            'schema_version',
            'kind',
            'record_id',
            'partition_id',
            'tenant_type',
            'tenant_id',
            'segment_id',
            'sequence',
            'previous_record_hash',
            'content_hash',
            'record_hash',
            'recorded_at',
            'event_category',
            'protected_body',
            'action_type',
            'resource_type',
            'resource_id',
        ];
        if (array_keys($row) !== $keys) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $physical = $row;
        unset($physical['action_type'], $physical['resource_type'], $physical['resource_id']);
        $stored = $this->hydrator->hydrate($physical);
        $complete = $this->protection->unprotectRecord($stored, $tenant, $target['partitionId']);
        $logical = $complete->logicalSnapshot();
        $integrity = $complete->integrity()->toArray();
        /** @var array{id:string,type:string,phase:string} $action */
        $action = $logical['action'];
        /** @var array{type:string,id:string} $resource */
        $resource = $logical['resource'];
        /** @var array{occurredAt:string,recordedAt:string} $time */
        $time = $logical['time'];
        $recordedAt = $time['recordedAt'];
        $recordHash = $integrity['recordHash'];
        $previousHash = $integrity['previousRecordHash'];
        $edgeMatches = $headHash === null
            ? $previousHash === null
            : $previousHash !== null && hash_equals($previousHash, $headHash);
        if (
            $row['action_type'] !== $action['type']
            || $row['resource_type'] !== $resource['type']
            || $row['resource_id'] !== $resource['id']
            || $integrity['partitionId'] !== $target['partitionId']
            || $integrity['segmentId'] !== $target['segmentId']
            || $integrity['sequence'] !== $expected
            || $row['segment_id'] !== $target['segmentId']
            || $row['partition_id'] !== $target['partitionId']
            || strcmp($recordedAt, $target['closedAt']) > 0
            || !$edgeMatches
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        unset($complete, $stored, $physical, $logical, $integrity, $action, $resource, $time, $recordedAt);

        return $recordHash;
    }
}
