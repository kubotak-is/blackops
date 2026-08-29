<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Record;

use BlackOps\Core\Audit\AuditEventDefinition;

/**
 * The trusted detached logical record produced by the finalizer.
 */
final readonly class CanonicalAuditLogicalRecord implements AuditLogicalRecord
{
    /** @var array<string,mixed> */
    private array $snapshot;

    private AuditEventDefinition $definition;

    public function __construct(ValidatedAuditLogicalRecord $validated)
    {
        /** @var array<string,mixed> $detached */
        $detached = self::copy($validated->snapshot);
        $this->snapshot = $detached;
        $this->definition = $validated->definition;
    }

    /**
     * @return array<string,mixed>
     * @mago-expect lint:inline-variable-return
     */
    public function toArray(): array
    {
        /** @var array<string,mixed> $detached */
        $detached = self::copy($this->snapshot);
        return $detached;
    }

    public function definition(): AuditEventDefinition
    {
        return $this->definition;
    }

    private static function copy(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $copy = [];
        foreach ($value as $key => $member) {
            $copy[$key] = self::copy($member);
        }
        return $copy;
    }
}
