<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;

final readonly class PostgreSqlAuditClosedSegmentPages
{
    private const int MAX_SAFE_INTEGER = AuditValidationException::MAX_SAFE_INTEGER;

    public function __construct(
        private PostgreSqlAuditClosedSegmentQueries $queries,
        private PostgreSqlAuditClosedSegmentRecord $record,
    ) {}

    /**
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} $target
     * @return array{recordCount:int,nextSequence:int,firstRecordHash:?string,chainHeadHash:?string}
     */
    public function read(
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        array $target,
    ): array {
        $after = '0';
        $expected = $target['firstSequence'];
        $headHash = $target['predecessorRecordHash'];
        $firstHash = null;
        $count = 0;
        do {
            $rows = $this->queries->records($tenant, $target['partitionId'], $target['segmentId'], $after);
            if (!array_is_list($rows)) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            $pageCount = count($rows);
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
                }
                $recordHash = $this->record->read($row, $tenant, $target, $expected, $headHash);
                if ($firstHash === null) {
                    $firstHash = $recordHash;
                }
                $headHash = $recordHash;
                $after = (string) $expected;
                $count++;
                if ($expected >= self::MAX_SAFE_INTEGER) {
                    throw new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
                }
                $expected++;
            }
            unset($rows, $row, $recordHash);
        } while ($pageCount === 32);

        return [
            'recordCount' => $count,
            'nextSequence' => $expected,
            'firstRecordHash' => $firstHash,
            'chainHeadHash' => $headHash,
        ];
    }
}
