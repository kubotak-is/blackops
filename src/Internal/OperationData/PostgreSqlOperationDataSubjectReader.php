<?php

declare(strict_types=1);

namespace BlackOps\Internal\OperationData;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusFailureKind;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusReader;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusReadFailed;

final readonly class PostgreSqlOperationDataSubjectReader implements OperationDataSubjectReader
{
    public function __construct(
        private PostgreSqlStatusReader $reader,
    ) {}

    public function findSubject(OperationId $operationId, ?TenantRef $tenant): ?OperationDataSubject
    {
        try {
            $subject = $this->reader->findSubjectForTenant($operationId, $tenant);
        } catch (PostgreSqlStatusReadFailed $exception) {
            throw $exception->kind === PostgreSqlStatusFailureKind::Integrity
                ? OperationDataSubjectReadFailure::integrity()
                : OperationDataSubjectReadFailure::storage();
        }
        if ($subject === null) {
            return null;
        }

        return new OperationDataSubject(
            OperationId::fromString($subject->operationId),
            $subject->operationType,
            $subject->originActorId === null
                ? null
                : new \BlackOps\Core\ActorRef($subject->originActorId, (string) $subject->originActorType),
            $subject->tenant,
        );
    }
}
