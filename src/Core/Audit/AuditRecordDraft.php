<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

/** @mago-expect lint:excessive-parameter-list */
final readonly class AuditRecordDraft
{
    public function __construct(
        public AuditRecordEvent $event,
        public AuditRecordAction $action,
        public AuditRecordActor $actor,
        public AuditRecordResource $resource,
        public AuditRecordOutcome $outcome,
        public AuditRecordPolicy $policy,
        public ?AuditTypedReference $tenant,
        public string $occurredAt,
        public AuditRecordSource $source,
        public AuditRecordCorrelation $correlation,
        public AuditRecordEvidence $evidence,
    ) {
        AuditRecordTime::assertTimestamp($occurredAt);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'schemaVersion' => 1,
            'kind' => 'audit',
            'event' => $this->event->toArray(),
            'action' => $this->action->toArray(),
            'actor' => $this->actor->toArray(),
            'resource' => $this->resource->toArray(),
            'outcome' => $this->outcome->toArray(),
            'policy' => $this->policy->toArray(),
            'tenant' => $this->tenant?->toArray(),
            'occurredAt' => $this->occurredAt,
            'source' => $this->source->toArray(),
            'correlation' => $this->correlation->toArray(),
            'evidence' => $this->evidence->toArray(),
        ];
    }
}
