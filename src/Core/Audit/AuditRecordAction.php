<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

final readonly class AuditRecordAction
{
    public function __construct(
        public string $id,
        public string $type,
        public AuditActionPhase $phase,
    ) {
        AuditValidationException::assertSafeString($id);
        AuditValidationException::assertGrammarString($type, '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', 128);
    }

    /** @return array{id:string,type:string,phase:string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'phase' => $this->phase->value];
    }
}
