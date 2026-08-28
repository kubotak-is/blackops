<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit;

use BlackOps\Core\Audit\AuditOpaqueId;
use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;

/**
 * @mago-expect lint:block-statement
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:halstead
 * @mago-expect lint:prefer-early-continue
 */
final class HmacAuditOpaqueIdDeriver
{
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $key,
    ) {
        if (strlen($key) !== 32)
            throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
    }

    public function derive(AuditOpaqueIdPurpose $purpose, #[\SensitiveParameter] string $context): AuditOpaqueId
    {
        AuditValidationException::assertSafeString($context);
        if (in_array($purpose, [AuditOpaqueIdPurpose::Tenant, AuditOpaqueIdPurpose::Evidence], strict: true))
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        return $this->make($purpose, $context);
    }

    public function deriveScalar(AuditOpaqueIdPurpose $purpose, #[\SensitiveParameter] string $context): AuditOpaqueId
    {
        return $this->derive($purpose, $context);
    }

    public function deriveTenant(#[\SensitiveParameter] mixed $context): AuditOpaqueId
    {
        $reference = $this->tenantReference($context);
        $bytes = new AuditJcsCodec()->canonicalJson($reference->toArray());
        return $this->make(AuditOpaqueIdPurpose::Tenant, $bytes);
    }

    /** @param array{catalogHash:string,eventType:string,eventVersion:int,evidenceVersion:int,fieldName:string,value:string|int|bool|null} $context */
    public function deriveEvidence(#[\SensitiveParameter] array $context): AuditOpaqueId
    {
        $required = ['catalogHash', 'eventType', 'eventVersion', 'evidenceVersion', 'fieldName', 'value'];
        if (array_diff(array_keys($context), $required) !== [] || array_diff($required, array_keys($context)) !== [])
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        foreach (['catalogHash', 'eventType', 'fieldName'] as $key)
            if (!is_string($context[$key]))
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        if (
            !is_int($context['eventVersion'])
            || !is_int($context['evidenceVersion'])
            || $context['eventVersion'] < -AuditJcsCodec::MAX_SAFE_INTEGER
            || $context['eventVersion'] > AuditJcsCodec::MAX_SAFE_INTEGER
            || $context['evidenceVersion'] < -AuditJcsCodec::MAX_SAFE_INTEGER
            || $context['evidenceVersion'] > AuditJcsCodec::MAX_SAFE_INTEGER
        )
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        if (
            !is_string($context['value'])
            && !is_int($context['value'])
            && !is_bool($context['value'])
            && $context['value'] !== null
            || is_int($context['value'])
            && (
                $context['value'] < -AuditJcsCodec::MAX_SAFE_INTEGER
                || $context['value'] > AuditJcsCodec::MAX_SAFE_INTEGER
            )
        )
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        $strings = [$context['catalogHash'], $context['eventType'], $context['fieldName']];
        if (is_string($context['value']))
            $strings[] = $context['value'];
        foreach ($strings as $string)
            AuditValidationException::assertUtf8($string);
        foreach ($strings as $string)
            if (preg_match('/[\p{Cc}\p{Cf}]/u', $string) === 1)
                throw new AuditValidationException(AuditValidationFailure::ControlInvalid);
        foreach ($strings as $string) {
            if (class_exists('Normalizer')) {
                $normalized = \Normalizer::normalize($string, \Normalizer::FORM_C);
                if ($normalized === false || $normalized !== $string)
                    throw new AuditValidationException(AuditValidationFailure::NfcInvalid);
            }
        }
        if (is_string($context['value']) && (strlen($context['value']) < 1 || strlen($context['value']) > 256))
            throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
        if (strlen($context['catalogHash']) !== 64)
            throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
        if (
            strlen($context['eventType']) < 1
            || strlen($context['eventType']) > 128
            || strlen($context['fieldName']) < 1
            || strlen($context['fieldName']) > 128
        )
            throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
        if (preg_match('/^[a-f0-9]{64}$/', $context['catalogHash']) !== 1)
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        if (preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $context['eventType']) !== 1)
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        if (preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $context['fieldName']) !== 1)
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        if (
            $context['eventVersion'] < 1
            || $context['eventVersion'] > AuditJcsCodec::MAX_SAFE_INTEGER
            || $context['evidenceVersion'] < 1
            || $context['evidenceVersion'] > AuditJcsCodec::MAX_SAFE_INTEGER
        )
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        return $this->make(AuditOpaqueIdPurpose::Evidence, new AuditJcsCodec()->canonicalJson($context));
    }

    /** @return array<never,never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<never,never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<never,never> $data */
    public function __unserialize(array $data): void
    {
        throw new \LogicException('Opaque ID derivers cannot be unserialized.');
    }

    private function make(AuditOpaqueIdPurpose $purpose, #[\SensitiveParameter] string $contextBytes): AuditOpaqueId
    {
        $domain = 'blackops.audit.opaque-id.v1';
        $preimage =
            AuditLengthPrefix::encode($domain)
            . AuditLengthPrefix::encode($purpose->value)
            . AuditLengthPrefix::encode($contextBytes);
        return new AuditOpaqueId('oid:v1:' . hash_hmac('sha256', $preimage, $this->key));
    }

    private function tenantReference(#[\SensitiveParameter] mixed $context): AuditTypedReference
    {
        if ($context instanceof AuditTypedReference)
            return $context;
        if (
            !is_array($context)
            || array_diff(array_keys($context), ['type', 'id']) !== []
            || array_diff(['type', 'id'], array_keys($context)) !== []
            || !is_string($context['type'] ?? null)
            || !is_string($context['id'] ?? null)
        )
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        return new AuditTypedReference($context['type'], $context['id']);
    }
}
