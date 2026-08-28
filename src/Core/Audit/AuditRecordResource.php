<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

final readonly class AuditRecordResource
{
    public function __construct(
        public string $type,
        public string $id,
    ) {
        AuditValidationException::assertGrammarString($type, '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', 128);
        AuditValidationException::assertSafeString($id);
    }

    /** @return array{type:string,id:string} */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }
}
