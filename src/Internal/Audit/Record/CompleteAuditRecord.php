<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Record;

use BlackOps\Core\Audit\AuditEventDefinition;

final class CompleteAuditRecord
{
    /** @var array<string,mixed> */
    private array $logicalSnapshot;

    private AuditEventDefinition $eventDefinition;

    private AuditRecordIntegrity $recordIntegrity;

    private \SensitiveParameterValue $completeBytes;

    /** @param array<string,mixed> $logicalSnapshot */
    public function __construct(
        #[\SensitiveParameter]
        array $logicalSnapshot,
        AuditEventDefinition $definition,
        AuditRecordIntegrity $integrity,
        #[\SensitiveParameter]
        string $bytes,
    ) {
        /** @var array<string,mixed> $detached */
        $detached = self::copy($logicalSnapshot);
        $this->logicalSnapshot = $detached;
        $this->eventDefinition = $definition;
        $this->recordIntegrity = $integrity;
        $this->completeBytes = new \SensitiveParameterValue($bytes);
    }

    /**
     * @return array<string,mixed>
     * @mago-expect lint:inline-variable-return
     */
    public function logicalSnapshot(): array
    {
        /** @var array<string,mixed> $detached */
        $detached = self::copy($this->logicalSnapshot);
        return $detached;
    }

    public function definition(): AuditEventDefinition
    {
        return $this->eventDefinition;
    }

    public function integrity(): AuditRecordIntegrity
    {
        return $this->recordIntegrity;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $logical = $this->logicalSnapshot();
        $logical['integrity'] = $this->recordIntegrity->toArray();
        return $logical;
    }

    public function bytes(): string
    {
        return (string) $this->completeBytes->getValue();
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
