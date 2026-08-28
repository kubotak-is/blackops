<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

final readonly class AuditRecordActor
{
    public function __construct(
        public ?AuditTypedReference $principal,
        public AuditTypedReference $execution,
    ) {}

    /** @return array{principal:array{type:string,id:string}|null,execution:array{type:string,id:string}} */
    public function toArray(): array
    {
        return [
            'principal' => $this->principal?->toArray(),
            'execution' => $this->execution->toArray(),
        ];
    }
}
