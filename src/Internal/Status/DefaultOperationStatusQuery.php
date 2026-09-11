<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Status\Exception\OperationStatusQueryException;
use BlackOps\Status\OperationStatusAuthorizationRequest;
use BlackOps\Status\OperationStatusAuthorizer;
use BlackOps\Status\OperationStatusExpired;
use BlackOps\Status\OperationStatusFound;
use BlackOps\Status\OperationStatusResult;
use BlackOps\Status\OperationStatusUnavailable;
use BlackOps\Status\TenantAwareOperationStatusQuery;
use Throwable;

final readonly class DefaultOperationStatusQuery implements TenantAwareOperationStatusQuery
{
    public function __construct(
        #[\SensitiveParameter]
        private OperationStatusSource $source,
        #[\SensitiveParameter]
        private OperationStatusAuthorizer $authorizer,
    ) {}

    public function find(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?ActorRef $currentActor = null,
        #[\SensitiveParameter]
        ?TenantRef $currentTenant = null,
    ): OperationStatusResult {
        return $this->findForTenant($operationId, $currentActor, $currentTenant);
    }

    public function findForTenant(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?ActorRef $currentActor = null,
        #[\SensitiveParameter]
        ?TenantRef $currentTenant = null,
    ): OperationStatusResult {
        $subject = $this->findSubject($operationId);
        if ($subject === null) {
            return new OperationStatusUnavailable();
        }

        if (!$subject->operationId->equals($operationId)) {
            throw OperationStatusQueryException::integrityFailed();
        }

        if (!$this->isAllowed($subject, $currentActor, $currentTenant)) {
            return new OperationStatusUnavailable();
        }

        $detail = $this->readDetail($subject);
        if ($detail instanceof OperationStatusDetailUnavailable) {
            return new OperationStatusUnavailable();
        }
        if ($detail instanceof OperationStatusDetailExpired) {
            return new OperationStatusExpired();
        }

        if (!$detail instanceof OperationStatusDetail) {
            throw OperationStatusQueryException::integrityFailed();
        }
        if (
            !$detail->status->operationId()->equals($subject->operationId)
            || $detail->status->operationType() !== $subject->operationType
        ) {
            throw OperationStatusQueryException::integrityFailed();
        }

        return new OperationStatusFound($detail->status);
    }

    private function findSubject(#[\SensitiveParameter] OperationId $operationId): ?OperationStatusSubject
    {
        try {
            return $this->source->findSubject($operationId);
        } catch (OperationStatusSourceException $exception) {
            throw $this->sourceFailure($exception);
        } catch (Throwable) {
            throw OperationStatusQueryException::storageFailed();
        }
    }

    private function isAllowed(
        #[\SensitiveParameter]
        OperationStatusSubject $subject,
        #[\SensitiveParameter]
        ?ActorRef $currentActor,
        #[\SensitiveParameter]
        ?TenantRef $currentTenant,
    ): bool {
        try {
            return $this->authorizer
                ->decide(
                    new OperationStatusAuthorizationRequest(
                        $subject->operationId,
                        $subject->operationType,
                        $currentActor,
                        $subject->originActor,
                        $currentTenant,
                        $subject->tenant,
                    ),
                )
                ->isAllowed();
        } catch (Throwable) {
            throw OperationStatusQueryException::authorizationFailed();
        }
    }

    private function readDetail(#[\SensitiveParameter] OperationStatusSubject $subject): OperationStatusDetailResult
    {
        try {
            return $this->source->readDetail($subject);
        } catch (OperationStatusSourceException $exception) {
            throw $this->sourceFailure($exception);
        } catch (Throwable) {
            throw OperationStatusQueryException::storageFailed();
        }
    }

    private function sourceFailure(
        #[\SensitiveParameter]
        OperationStatusSourceException $exception,
    ): OperationStatusQueryException {
        return match ($exception->failure) {
            OperationStatusSourceFailure::Storage => OperationStatusQueryException::storageFailed(),
            OperationStatusSourceFailure::Decode => OperationStatusQueryException::decodeFailed(),
            OperationStatusSourceFailure::Integrity => OperationStatusQueryException::integrityFailed(),
        };
    }
}
