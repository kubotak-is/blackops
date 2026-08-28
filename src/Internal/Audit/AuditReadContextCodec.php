<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit;

use BlackOps\Core\Audit\AuditReadAuthorizationDecision;
use BlackOps\Core\Audit\AuditReadContext;
use BlackOps\Core\Audit\AuditReadPurpose;
use BlackOps\Core\Audit\AuditReadRequestBasis;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:no-boolean-flag-parameter
 * @mago-expect lint:prefer-early-continue
 * @mago-expect lint:no-else-clause
 */
final class AuditReadContextCodec
{
    public function __construct(
        private readonly AuditJcsCodec $jcs = new AuditJcsCodec(),
    ) {}

    public function encodeBasis(AuditReadRequestBasis $basis): string
    {
        return $this->jcs->canonicalJson($basis->toArray());
    }

    public function encodeContext(AuditReadContext $context): string
    {
        return $this->jcs->canonicalJson($context->toArray());
    }

    public static function requestRef(AuditReadRequestBasis $basis): string
    {
        return $basis->requestRef();
    }

    public function decodeBasis(string $bytes): AuditReadRequestBasis
    {
        $value = $this->decodeValue($bytes);
        $this->keys($value, ['purpose', 'actor', 'execution', 'tenant', 'requestedAt', 'nonce']);
        $this->validateClosedShape($value, context: false);
        $this->assertUtf8Observed();
        $this->validateText($value, context: false);
        $this->validateBounds($value, context: false);
        $this->validateGrammar($value, context: false);
        $this->assertCanonical($value, $bytes);
        $basis = $this->basisFromArray($value);
        if ($this->encodeBasis($basis) !== $bytes) {
            throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
        }
        return $basis;
    }

    public function decodeContext(string $bytes): AuditReadContext
    {
        $value = $this->decodeValue($bytes);
        $this->keys($value, [
            'schemaVersion',
            'kind',
            'purpose',
            'actor',
            'execution',
            'tenant',
            'requestedAt',
            'nonce',
            'requestRef',
            'authorizationDecision',
        ]);
        $this->validateClosedShape($value, context: true);
        $this->assertUtf8Observed();
        $this->validateText($value, context: true);
        $this->validateBounds($value, context: true);
        $this->validateGrammar($value, context: true);
        $this->assertCanonical($value, $bytes);

        $basis = $this->basisFromArray($value);
        $decision = is_string($value['authorizationDecision'])
            ? AuditReadAuthorizationDecision::tryFrom($value['authorizationDecision'])
            : null;
        if ($value['schemaVersion'] !== 1 || $value['kind'] !== 'audit_read_context' || $decision === null) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
        /** @var string $requestRef */
        $requestRef = $value['requestRef'];
        if ($requestRef !== self::requestRef($basis)) {
            throw new AuditValidationException(AuditValidationFailure::HashMismatch);
        }
        return AuditReadContext::fromBasis($basis, $decision);
    }

    /** @return array<string,mixed> */
    private function decodeValue(string $bytes): array
    {
        // Shape is deliberately checked while the decoder defers invalid UTF-8,
        // so an unknown member remains a shape failure even when its spelling
        // also contains malformed bytes.
        $this->jcs->deferUtf8Validation();
        $decoded = $this->jcs->decode($bytes);
        $value = $this->jcs->toNative($decoded);
        if (!is_array($value) || array_is_list($value)) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        /** @var array<string,mixed> $value */
        return $value;
    }

    private function assertUtf8Observed(): void
    {
        if ($this->jcs->invalidUtf8Observed() || $this->jcs->unpairedSurrogateObserved()) {
            throw new AuditValidationException(AuditValidationFailure::Utf8Invalid);
        }
    }

    /** @param array<string,mixed> $value @param list<string> $keys */
    private function keys(array $value, array $keys): void
    {
        if (array_diff(array_keys($value), $keys) !== [] || array_diff($keys, array_keys($value)) !== []) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
    }

    /** @param array<string,mixed> $value */
    private function validateClosedShape(array $value, bool $context): void
    {
        $stringMembers = $context
            ? ['kind', 'purpose', 'requestedAt', 'nonce', 'requestRef', 'authorizationDecision']
            : ['purpose', 'requestedAt', 'nonce'];
        foreach ($stringMembers as $member) {
            if (!is_string($value[$member])) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
        }
        if ($context && !is_int($value['schemaVersion'])) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        foreach (['actor', 'tenant'] as $member) {
            if ($value[$member] !== null) {
                $this->referenceShape($value[$member]);
            }
        }
        $this->referenceShape($value['execution']);
    }

    private function referenceShape(mixed $value): void
    {
        if (
            !is_array($value)
            || array_is_list($value)
            || array_diff(array_keys($value), ['type', 'id']) !== []
            || array_diff(['type', 'id'], array_keys($value)) !== []
            || !is_string($value['type'])
            || !is_string($value['id'])
        ) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
    }

    /** @param array<string,mixed> $value */
    private function validateText(array $value, bool $context): void
    {
        /** @var list<string> $strings */
        $strings = $this->strings($value);
        foreach ($strings as $string) {
            AuditValidationException::assertUtf8($string);
        }
        foreach ($strings as $string) {
            if (preg_match('/[\p{Cc}\p{Cf}]/u', $string) === 1) {
                throw new AuditValidationException(AuditValidationFailure::ControlInvalid);
            }
        }
        foreach ($strings as $string) {
            if (class_exists('Normalizer')) {
                $normalized = \Normalizer::normalize($string, \Normalizer::FORM_C);
                if ($normalized === false || $normalized !== $string) {
                    throw new AuditValidationException(AuditValidationFailure::NfcInvalid);
                }
            }
        }
    }

    /** @param array<string,mixed> $value */
    private function validateBounds(array $value, bool $context): void
    {
        foreach (['actor', 'execution', 'tenant'] as $member) {
            $reference = $value[$member];
            if ($reference === null) {
                continue;
            }
            if (!is_array($reference)) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
            /** @var array{type:string,id:string} $reference */
            foreach ([$reference['type'], $reference['id']] as $part) {
                /** @var string $part */
                if (strlen($part) < 1 || strlen($part) > 256) {
                    throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
                }
            }
        }
        /** @var string $requestedAt */
        $requestedAt = $value['requestedAt'];
        /** @var string $nonce */
        $nonce = $value['nonce'];
        if (strlen($requestedAt) !== 27 || strlen($nonce) < 1 || strlen($nonce) > 128) {
            throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
        }
        if ($context) {
            /** @var string $requestRef */
            $requestRef = $value['requestRef'];
            if (strlen($requestRef) < 1 || strlen($requestRef) > 71) {
                throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
            }
        }
    }

    /** @param array<string,mixed> $value */
    private function validateGrammar(array $value, bool $context): void
    {
        /** @var string $requestedAt */
        $requestedAt = $value['requestedAt'];
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $requestedAt) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
        if ($context) {
            /** @var string $requestRef */
            $requestRef = $value['requestRef'];
            if (preg_match('/^oid:v1:[a-f0-9]{64}$/', $requestRef) !== 1) {
                throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
            }
        }
    }

    /** @param array<string,mixed> $value */
    private function assertCanonical(array $value, string $bytes): void
    {
        if ($this->jcs->canonicalJson($value) !== $bytes) {
            throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
        }
    }

    /** @param array<array-key,mixed> $value @return list<string> */
    private function strings(array $value): array
    {
        $strings = [];
        foreach ($value as $member) {
            if (is_string($member)) {
                $strings[] = $member;
            } elseif (is_array($member)) {
                $strings = [...$strings, ...$this->strings($member)];
            }
        }
        return $strings;
    }

    /** @param array<string,mixed> $value */
    private function basisFromArray(array $value): AuditReadRequestBasis
    {
        $purpose = is_string($value['purpose'] ?? null) ? AuditReadPurpose::tryFrom($value['purpose']) : null;
        if ($purpose === null) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
        $requestedAt = $value['requestedAt'] ?? null;
        $nonce = $value['nonce'] ?? null;
        if (!is_string($requestedAt) || !is_string($nonce)) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        return new AuditReadRequestBasis(
            $purpose,
            $this->nullableReference($value['actor'] ?? null),
            $this->requiredReference($value['execution'] ?? null),
            $this->nullableReference($value['tenant'] ?? null),
            $requestedAt,
            $nonce,
        );
    }

    private function nullableReference(mixed $value): ?AuditTypedReference
    {
        if ($value === null) {
            return null;
        }
        return $this->requiredReference($value);
    }

    private function requiredReference(mixed $value): AuditTypedReference
    {
        if (
            !is_array($value)
            || array_diff(array_keys($value), ['type', 'id']) !== []
            || array_diff(['type', 'id'], array_keys($value)) !== []
            || !is_string($value['type'] ?? null)
            || !is_string($value['id'] ?? null)
        ) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        return new AuditTypedReference($value['type'], $value['id']);
    }
}
