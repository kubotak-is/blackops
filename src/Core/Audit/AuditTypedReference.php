<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

/**
 * @mago-expect lint:prefer-early-continue
 */
final readonly class AuditTypedReference
{
    public function __construct(
        #[\SensitiveParameter]
        public string $type,
        #[\SensitiveParameter]
        public string $id,
    ) {
        // Validate the pair by stage, rather than validating one member to
        // completion before looking at the other. This keeps a compound
        // invalid reference deterministic at every construction boundary.
        foreach ([$type, $id] as $value) {
            AuditValidationException::assertUtf8($value);
        }
        foreach ([$type, $id] as $value) {
            if (preg_match('/[\p{Cc}\p{Cf}]/u', $value) === 1) {
                throw new AuditValidationException(AuditValidationFailure::ControlInvalid);
            }
        }
        foreach ([$type, $id] as $value) {
            if (class_exists('Normalizer')) {
                $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
                if ($normalized === false || $normalized !== $value) {
                    throw new AuditValidationException(AuditValidationFailure::NfcInvalid);
                }
            }
        }
        foreach ([$type, $id] as $value) {
            if (strlen($value) < 1 || strlen($value) > 256) {
                throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
            }
        }
    }

    /** @return array{type: string, id: string} */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }
}
