<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
final readonly class AuditEvidenceFieldDefinition
{
    public function __construct(
        public string $name,
        public AuditEvidenceType $type,
        public bool $required,
        public bool $nullable,
        public ?int $maxUtf8Bytes = null,
        public ?AuditEvidenceClassification $classification = null,
    ) {
        AuditValidationException::assertGrammarString($name, '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', 128);
        if ($name === 'version') {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
        if ($type === AuditEvidenceType::String) {
            if ($maxUtf8Bytes === null || $maxUtf8Bytes < 1 || $maxUtf8Bytes > 256 || $classification === null) {
                throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
            }
            if ($classification === AuditEvidenceClassification::OpaqueId && $maxUtf8Bytes !== 71) {
                throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
            }
            return;
        }
        if ($maxUtf8Bytes !== null || $classification !== null) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
    }

    /** @return array{name: string, type: string, required: bool, nullable: bool, maxUtf8Bytes: int|null, classification: string|null} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type->value,
            'required' => $this->required,
            'nullable' => $this->nullable,
            'maxUtf8Bytes' => $this->maxUtf8Bytes,
            'classification' => $this->classification?->value,
        ];
    }
}
