<?php

declare(strict_types=1);

namespace BlackOps\Internal\OperationData;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\OperationData\Exception\OperationOutcomeQueryException;
use BlackOps\OperationData\OperationDataPurpose;
use BlackOps\OperationData\OperationDataReadAuthorizationRequest;
use BlackOps\OperationData\OperationDataReadAuthorizer;
use BlackOps\OperationData\OperationDataResource;
use BlackOps\OperationData\OperationOutcomeFound;
use BlackOps\OperationData\OperationOutcomeQuery;
use BlackOps\OperationData\OperationOutcomeReadResult;
use BlackOps\OperationData\OperationOutcomeUnavailable;
use Throwable;

final readonly class DefaultOperationOutcomeQuery implements OperationOutcomeQuery
{
    public function __construct(
        private OperationDataSubjectReader $subjects,
        private OperationDataReadAuthorizer $authorizer,
        private TenantScopedOutcomeReader $reader,
    ) {}

    public function find(
        OperationId $operationId,
        ?ActorRef $currentActor,
        ?TenantRef $currentTenant,
        OperationDataPurpose $purpose,
    ): OperationOutcomeReadResult {
        try {
            $subject = $this->subjects->findSubject($operationId, $currentTenant);
        } catch (OperationDataSubjectReadFailure $exception) {
            throw $exception->kind === OperationDataSubjectReadFailure::INTEGRITY
                ? OperationOutcomeQueryException::integrityFailed()
                : OperationOutcomeQueryException::storageFailed();
        } catch (Throwable) {
            throw OperationOutcomeQueryException::storageFailed();
        }
        if ($subject === null || !$subject->operationId->equals($operationId)) {
            return new OperationOutcomeUnavailable();
        }
        try {
            $decision = $this->authorizer->decide(
                new OperationDataReadAuthorizationRequest(
                    OperationDataResource::Outcome,
                    $purpose,
                    $operationId,
                    $subject->operationType,
                    $currentActor,
                    $currentTenant,
                    $subject->originActor,
                    $subject->originTenant,
                ),
            );
        } catch (Throwable) {
            throw OperationOutcomeQueryException::authorizationFailed();
        }
        if (!$decision->isAllowed()) {
            return new OperationOutcomeUnavailable();
        }
        try {
            $record = $this->reader->findForTenant($operationId, $subject->originTenant);
        } catch (OperationOutcomeQueryException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw OperationOutcomeQueryException::storageFailed();
        }
        if ($record !== null && !$record->operationId()->equals($subject->operationId)) {
            throw OperationOutcomeQueryException::integrityFailed();
        }
        return $record === null ? new OperationOutcomeUnavailable() : new OperationOutcomeFound($record);
    }
}
