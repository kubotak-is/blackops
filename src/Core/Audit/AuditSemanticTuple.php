<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

use BlackOps\Core\Attribute\PublicApi;

/** @mago-expect lint:kan-defect */
#[PublicApi]
final readonly class AuditSemanticTuple
{
    public function __construct(
        public string $actionType,
        public AuditActionPhase $phase,
        public string $resourceType,
        public AuditOutcomeStatus $outcomeStatus,
        public string $outcomeCode,
        public AuditPolicyDecision $policyDecision,
        public string $policyReasonCode,
    ) {
        $strings = [$actionType, $resourceType, $outcomeCode, $policyReasonCode];
        self::assertUtf8ForAll($strings);
        self::assertControlsForAll($strings);
        self::assertNfcForAll($strings);
        self::assertBoundsForAll($strings);
        self::assertGrammar($actionType, $resourceType, $outcomeCode, $policyReasonCode);
    }

    /** @param list<string> $strings */
    private static function assertUtf8ForAll(array $strings): void
    {
        foreach ($strings as $string) {
            AuditValidationException::assertUtf8($string);
        }
    }

    /** @param list<string> $strings */
    private static function assertControlsForAll(array $strings): void
    {
        foreach ($strings as $string) {
            if (preg_match('/[\p{Cc}\p{Cf}]/u', $string) === 1) {
                throw new AuditValidationException(AuditValidationFailure::ControlInvalid);
            }
        }
    }

    /** @param list<string> $strings */
    private static function assertNfcForAll(array $strings): void
    {
        foreach ($strings as $string) {
            if (!class_exists('Normalizer')) {
                continue;
            }
            $normalized = \Normalizer::normalize($string, \Normalizer::FORM_C);
            if ($normalized === false || $normalized !== $string) {
                throw new AuditValidationException(AuditValidationFailure::NfcInvalid);
            }
        }
    }

    /** @param list<string> $strings */
    private static function assertBoundsForAll(array $strings): void
    {
        foreach ($strings as $string) {
            if (strlen($string) < 1 || strlen($string) > 128) {
                throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
            }
        }
    }

    private static function assertGrammar(
        string $actionType,
        string $resourceType,
        string $outcomeCode,
        string $policyReasonCode,
    ): void {
        if (preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $actionType) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
        if (preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $resourceType) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
        if (preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $outcomeCode) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
        if (preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $policyReasonCode) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
    }

    /** @return array{actionType: string, phase: string, resourceType: string, outcomeStatus: string, outcomeCode: string, policyDecision: string, policyReasonCode: string} */
    public function toArray(): array
    {
        return [
            'actionType' => $this->actionType,
            'phase' => $this->phase->value,
            'resourceType' => $this->resourceType,
            'outcomeStatus' => $this->outcomeStatus->value,
            'outcomeCode' => $this->outcomeCode,
            'policyDecision' => $this->policyDecision->value,
            'policyReasonCode' => $this->policyReasonCode,
        ];
    }
}
