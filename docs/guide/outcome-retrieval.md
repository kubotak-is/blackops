# Outcome Retrieval

Deferred operations that complete successfully store a typed outcome by operation ID. Applications read it through the public `OutcomeReader` contract; they do not decode persistence payloads or inspect PostgreSQL schema versions.

```php
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Outcome\OutcomeReader;

function reportResult(OutcomeReader $outcomes, string $operationId): ?ReportGenerated
{
    $record = $outcomes->find(OperationId::fromString($operationId));

    if ($record === null) {
        return null;
    }

    $outcome = $record->outcome();

    return $outcome instanceof ReportGenerated ? $outcome : null;
}
```

`OutcomeRecord` contains the operation ID, a restored `Outcome`, and the completion time normalized to UTC. A missing record returns `null`. This can mean the operation is unknown, has not completed successfully, or its independently configured outcome retention period has elapsed. Callers that need to distinguish those cases should combine the reader with an operation-status view; an HTTP outcome endpoint is not part of the current runtime.

Only completed results are stored. Rejected, failed, retry-scheduled, dead-lettered, claim-lost, and grace-timeout executions do not create outcome records. `EmptyOutcome` remains a typed outcome and is stored for a value-less successful completion.

PostgreSQL rejects duplicate saves instead of overwriting the first completed result. Unsupported schema versions, corrupt payloads, stored type mismatches, and values that do not implement `Outcome` raise `OutcomeStoreException`.

## Retention

Outcome retention is independent from transport payload, journal, and dead-letter retention. `RetentionPolicy::outcomeRetention()` determines eligibility from `OutcomeRecord::completedAt()`. An active operation hold excludes the outcome from both planning and deletion. Successful deletion records a payload-free purge audit in the same database transaction and increments `RetentionPurgeResult::outcomesDeleted()`.
