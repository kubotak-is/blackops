<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Record;

/**
 * A detached logical-record candidate used only at trusted validation seams.
 */
final class AuditLogicalRecordCandidate implements AuditLogicalRecord
{
    /** @var array<string,mixed> */
    private array $snapshot;

    /** @param array<string,mixed> $snapshot */
    public function __construct(#[\SensitiveParameter] array $snapshot)
    {
        // PHP arrays use copy-on-write; retaining this detached value avoids
        // traversing attacker-controlled unknown subtrees before the
        // validator's bounded shape checks run.
        $this->snapshot = $snapshot;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->snapshot;
    }
}
