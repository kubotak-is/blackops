<?php

declare(strict_types=1);

namespace BlackOps\Internal\OperationData;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\OperationData\Exception\OperationJournalQueryException;
use BlackOps\OperationData\OperationDataPurpose;
use BlackOps\OperationData\OperationDataReadAuthorizationDecision;
use BlackOps\OperationData\OperationDataReadAuthorizationRequest;
use BlackOps\OperationData\OperationDataReadAuthorizer;
use BlackOps\OperationData\OperationDataResource;
use BlackOps\OperationData\OperationJournalFound;
use BlackOps\OperationData\OperationJournalQuery;
use BlackOps\OperationData\OperationJournalReadResult;
use BlackOps\OperationData\OperationJournalUnavailable;
use Throwable;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class DefaultOperationJournalQuery implements OperationJournalQuery
{
    public function __construct(
        private OperationDataSubjectReader $subjects,
        private OperationDataReadAuthorizer $authorizer,
        private TenantScopedCanonicalJournalReader $reader,
    ) {}

    public function records(
        OperationId $operationId,
        ?ActorRef $currentActor,
        ?TenantRef $currentTenant,
        OperationDataPurpose $purpose,
    ): OperationJournalReadResult {
        $subject = $this->subject($operationId, $currentTenant);
        if ($subject === null || !$subject->operationId->equals($operationId)) {
            return new OperationJournalUnavailable();
        }
        $decision = $this->authorize($subject, $operationId, $currentActor, $currentTenant, $purpose);
        if (!$decision->isAllowed()) {
            return new OperationJournalUnavailable();
        }

        try {
            $records = array_values(iterator_to_array(
                $this->reader->recordsForTenant($operationId, $subject->originTenant),
                preserve_keys: false,
            ));
        } catch (OperationJournalQueryException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw OperationJournalQueryException::storageFailed();
        }
        foreach ($records as $record) {
            if (
                !$record->operation->id->equals($subject->operationId)
                || $record->operation->type !== $subject->operationType
                || !$this->sameTenant($record->operation->tenant, $subject->originTenant)
                || !$this->sameActor($record->operation->actorContext?->origin(), $subject->originActor)
            ) {
                throw OperationJournalQueryException::integrityFailed();
            }
        }
        if ($records === []) {
            return new OperationJournalUnavailable();
        }

        return new OperationJournalFound($records);
    }

    private function sameTenant(?TenantRef $left, ?TenantRef $right): bool
    {
        return $left?->type() === $right?->type() && $left?->id() === $right?->id();
    }

    private function sameActor(?ActorRef $left, ?ActorRef $right): bool
    {
        return $left?->type() === $right?->type() && $left?->id() === $right?->id();
    }

    private function subject(OperationId $operationId, ?TenantRef $tenant): ?OperationDataSubject
    {
        try {
            return $this->subjects->findSubject($operationId, $tenant);
        } catch (OperationDataSubjectReadFailure $exception) {
            throw $exception->kind === OperationDataSubjectReadFailure::INTEGRITY
                ? OperationJournalQueryException::integrityFailed()
                : OperationJournalQueryException::storageFailed();
        } catch (Throwable) {
            throw OperationJournalQueryException::storageFailed();
        }
    }

    private function authorize(
        OperationDataSubject $subject,
        OperationId $operationId,
        ?ActorRef $currentActor,
        ?TenantRef $currentTenant,
        OperationDataPurpose $purpose,
    ): OperationDataReadAuthorizationDecision {
        try {
            $decision = $this->authorizer->decide(
                new OperationDataReadAuthorizationRequest(
                    OperationDataResource::CanonicalJournal,
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
            throw OperationJournalQueryException::authorizationFailed();
        }
        return $decision;
    }
}
