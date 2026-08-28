<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

final readonly class AuditRecordEvent
{
    public function __construct(
        public string $type,
        public int $version,
        public AuditEventCategory $category,
        public string $catalogHash,
    ) {
        AuditValidationException::assertGrammarString($type, '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', 128);
        if ($version < 1 || $version > AuditValidationException::MAX_SAFE_INTEGER) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
        AuditValidationException::assertSafeString($catalogHash, 64, 64);
        if (preg_match('/^[a-f0-9]{64}$/', $catalogHash) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
    }

    /** @return array{type:string,version:int,category:string,catalogHash:string} */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'version' => $this->version,
            'category' => $this->category->value,
            'catalogHash' => $this->catalogHash,
        ];
    }
}
