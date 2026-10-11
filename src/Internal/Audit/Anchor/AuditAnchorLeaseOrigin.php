<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

/** @phpstan-import-type IntentValue from AuditAnchorIntentShape */
final readonly class AuditAnchorLeaseOrigin
{
    /** @var list<string> */
    private const array OPERATIONS = [
        'external_submission_claim',
        'pre_call_retry_claim',
        'external_reconciliation_claim',
        'receipt_input_claim',
        'receipt_persist_claim',
        'acknowledgement_claim',
    ];

    private \SensitiveParameterValue $snapshot;

    private function __construct(#[\SensitiveParameter] array $value)
    {
        $this->snapshot = new \SensitiveParameterValue($value);
    }

    /** @param array<array-key,mixed> $value */
    public static function fromArray(#[\SensitiveParameter] array $value): self
    {
        try {
            AuditAnchorIntentShape::assertClosed($value, [
                'operation',
                'intentId',
                'attempt',
                'leaseFencingToken',
                'claimVersion',
                'claimStateHash',
            ]);
            if (!in_array($value['operation'], self::OPERATIONS, strict: true)) {
                throw new \RuntimeException();
            }
            $intentId = AuditAnchorIntentShape::assertText($value['intentId']);
            $leaseFencingToken = AuditAnchorIntentShape::assertText($value['leaseFencingToken']);
            $attempt = self::positiveInteger($value['attempt']);
            $claimVersion = self::positiveInteger($value['claimVersion']);
            if ($attempt > 32) {
                throw new \RuntimeException();
            }
            AuditAnchorIntentShape::assertHash($value['claimStateHash']);
            $claimStateHash = AuditAnchorIntentShape::assertText($value['claimStateHash']);
            return new self([
                'operation' => $value['operation'],
                'intentId' => $intentId,
                'attempt' => $attempt,
                'leaseFencingToken' => $leaseFencingToken,
                'claimVersion' => $claimVersion,
                'claimStateHash' => $claimStateHash,
            ]);
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
    }

    public static function fromClaim(string $operation, #[\SensitiveParameter] AuditAnchorIntent $intent): self
    {
        try {
            $value = $intent->toArray();
            if (!in_array($operation, self::OPERATIONS, strict: true)) {
                throw new \RuntimeException();
            }
            if (
                [
                    $value['leaseFencingToken'] === null,
                    $value['leaseExpiresAt'] === null,
                ] !== [false, false]
            ) {
                throw new \RuntimeException();
            }
            $attempt = self::positiveInteger($value['attempt']);
            $version = self::positiveInteger($value['version']);
            $token = AuditAnchorIntentShape::assertText($value['leaseFencingToken']);
            AuditAnchorIntentShape::assertHash($value['stateHash']);
            $stateHash = AuditAnchorIntentShape::assertText($value['stateHash']);
            return new self([
                'operation' => $operation,
                'intentId' => $value['intentId'],
                'attempt' => $attempt,
                'leaseFencingToken' => $token,
                'claimVersion' => $version,
                'claimStateHash' => $stateHash,
            ]);
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
    }

    private static function positiveInteger(#[\SensitiveParameter] mixed $value): int
    {
        return AuditAnchorScalar::integer($value, 1, \BlackOps\Internal\Audit\AuditJcsCodec::MAX_SAFE_INTEGER);
    }

    /** @return array{operation:string,intentId:string,attempt:int,leaseFencingToken:string,claimVersion:int,claimStateHash:string} */
    public function toArray(): array
    {
        /** @var array{operation:string,intentId:string,attempt:int,leaseFencingToken:string,claimVersion:int,claimStateHash:string} $value */
        $value = $this->snapshot->getValue();
        return [...$value];
    }

    /**
     * @param IntentValue $intent
     * @param array{operation:string,intentId:string,attempt:int,leaseFencingToken:string,claimVersion:int,claimStateHash:string}|null $origin
     */
    public static function assertAssociation(
        #[\SensitiveParameter]
        array $intent,
        #[\SensitiveParameter]
        ?array $origin,
    ): void {
        $token = $intent['leaseFencingToken'];
        if (($token === null) !== ($origin === null)) {
            throw new \RuntimeException();
        }
        if ($origin === null) {
            return;
        }
        $identityValid = [
            $origin['intentId'] === $intent['intentId'],
            $origin['attempt'] === $intent['attempt'],
            hash_equals($origin['leaseFencingToken'], (string) $token),
            $origin['claimVersion'] <= $intent['version'],
        ] === [true, true, true, true];
        $ruleKey = implode('|', [
            $origin['operation'],
            $intent['state'],
            $intent['externalCallPhase'] ?? '',
            $intent['preCallClaimMode'] ?? '',
            $intent['preCallLookupStatus'] ?? '',
        ]);
        $expectedDelta =
            [
                'external_submission_claim|prepared|pre_call|new_attempt|pending' => 0,
                'pre_call_retry_claim|prepared|pre_call|retry_same_attempt|pending' => 0,
                'external_submission_claim|prepared|pre_call|new_attempt|absent' => 1,
                'pre_call_retry_claim|prepared|pre_call|retry_same_attempt|absent' => 1,
                'external_submission_claim|prepared|post_call_uncertain||' => 2,
                'pre_call_retry_claim|prepared|post_call_uncertain||' => 2,
                'external_reconciliation_claim|prepared|post_call_uncertain||' => 0,
                'receipt_input_claim|externally_accepted|||' => 0,
                'receipt_persist_claim|receipt_inputs_persisted|||' => 0,
                'acknowledgement_claim|receipt_persisted|||' => 0,
            ][$ruleKey] ?? -1;
        $delta = $intent['version'] - $origin['claimVersion'];
        $hashChainValid = match ($delta) {
            0 => hash_equals($origin['claimStateHash'], $intent['stateHash']),
            1 => hash_equals((string) $intent['previousStateHash'], $origin['claimStateHash']),
            2 => is_string($intent['previousStateHash']),
            default => false,
        };
        if (in_array(
            false,
            [
                $identityValid,
                $expectedDelta === $delta,
                $hashChainValid,
            ],
            strict: true,
        )) {
            throw new \RuntimeException();
        }
    }

    /** @return array<string,never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<string,never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<string,mixed> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
    }
}
