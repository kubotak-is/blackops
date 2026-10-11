<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

use BlackOps\Internal\Audit\AuditJcsCodec;

/** @phpstan-import-type IntentValue from AuditAnchorIntentShape */
final readonly class AuditAnchorFrame
{
    private \SensitiveParameterValue $snapshot;

    private function __construct(
        #[\SensitiveParameter]
        AuditAnchorIntent $intent,
        #[\SensitiveParameter]
        AuditAnchorBinding $binding,
        #[\SensitiveParameter]
        ?AuditAnchorLeaseOrigin $leaseOrigin,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'intent' => $intent,
            'binding' => $binding,
            'leaseOrigin' => $leaseOrigin,
        ]);
    }

    /** @param array<array-key,mixed> $request */
    public static function create(
        #[\SensitiveParameter]
        array $request,
        #[\SensitiveParameter]
        AuditAnchorBinding $binding,
        #[\SensitiveParameter]
        string $rawIdempotencyKey,
        #[\SensitiveParameter]
        AuditAnchorExpectation $expectation,
        #[\SensitiveParameter]
        string $trustedNow,
    ): self {
        try {
            AuditAnchorIntentShape::assertClosed($request, [
                'intentId',
                'partitionId',
                'checkpointId',
                'checkpointHash',
                'checkpointBytesHash',
                'anchorStoreRef',
                'anchorObjectRef',
                'expectedPredecessorVersion',
                'expectedPredecessorHash',
                'conditionalCreateToken',
            ]);
            AuditAnchorIntentShape::assertText($rawIdempotencyKey);
            $intentId = AuditAnchorIntentShape::assertText($request['intentId']);
            $partitionId = AuditAnchorIntentShape::assertPartition($request['partitionId']);
            $checkpointId = AuditAnchorIntentShape::assertText($request['checkpointId']);
            /** @var string $checkpointHash */
            $checkpointHash = AuditAnchorIntentShape::assertHash($request['checkpointHash']);
            /** @var string $checkpointBytesHash */
            $checkpointBytesHash = AuditAnchorIntentShape::assertHash($request['checkpointBytesHash']);
            $anchorStoreRef = AuditAnchorIntentShape::assertText($request['anchorStoreRef']);
            $anchorObjectRef = AuditAnchorIntentShape::assertText($request['anchorObjectRef']);
            $expectedPredecessorVersion = $request['expectedPredecessorVersion'];
            if (
                $expectedPredecessorVersion !== null
                && (
                    !is_int($expectedPredecessorVersion)
                    || $expectedPredecessorVersion < 1
                    || $expectedPredecessorVersion > AuditJcsCodec::MAX_SAFE_INTEGER
                )
            ) {
                throw new \RuntimeException();
            }
            $expectedPredecessorHash = AuditAnchorIntentShape::assertHash(
                $request['expectedPredecessorHash'],
                'nullable',
            );
            if (($expectedPredecessorVersion === null) !== ($expectedPredecessorHash === null)) {
                throw new \RuntimeException();
            }
            $conditionalCreateToken = AuditAnchorIntentShape::assertText($request['conditionalCreateToken']);
            $bindingArray = $binding->toArray();
            if (($expectedPredecessorHash === null) !== ($bindingArray['previousAnchorReceiptHash'] === null)) {
                throw new \RuntimeException();
            }
            if ($expectation->toArray()['version'] !== 0) {
                throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
            }
            AuditAnchorTime::assertTransitionTimestamp($trustedNow);
            /** @var IntentValue $intentValue */
            $intentValue = [
                'schemaVersion' => 1,
                'kind' => 'audit_anchor_intent',
                'intentId' => $intentId,
                'state' => 'prepared',
                'partitionId' => $partitionId,
                'checkpointId' => $checkpointId,
                'checkpointHash' => $checkpointHash,
                'checkpointBytesHash' => $checkpointBytesHash,
                'anchorStoreRef' => $anchorStoreRef,
                'anchorObjectRef' => $anchorObjectRef,
                'expectedPredecessorVersion' => $expectedPredecessorVersion,
                'expectedPredecessorHash' => $expectedPredecessorHash,
                'conditionalCreateToken' => $conditionalCreateToken,
                'idempotencyKeyHash' => AuditAnchorCanonical::idempotencyKeyHash($intentId, $rawIdempotencyKey),
                'acceptedObjectVersion' => null,
                'acceptedObjectHash' => null,
                'acceptedAnchoredAt' => null,
                'anchorReceiptId' => null,
                'segmentId' => null,
                'previousAnchorReceiptHash' => null,
                'receiptSigningKeyRef' => null,
                'receiptSignedAt' => null,
                'receiptPreimageHash' => null,
                'receiptBytesHash' => null,
                'anchorReceiptHash' => null,
                'externalCallPhase' => 'pre_call',
                'preCallClaimMode' => 'new_attempt',
                'preCallLookupStatus' => null,
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'attempt' => 0,
                'nextAvailableAt' => null,
                'terminalReasonCode' => null,
                'version' => 1,
                'previousStateHash' => null,
                'stateHash' => '',
                'updatedAt' => $trustedNow,
                'acknowledgedAt' => null,
            ];
            $intentValue['stateHash'] = AuditAnchorCanonical::stateHash($intentValue);
            $intent = AuditAnchorIntent::fromArray($intentValue);
            return new self($intent, $binding, null);
        } catch (AuditAnchorException $exception) {
            throw new AuditAnchorException($exception->failure());
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
    }

    /** @param array<array-key,mixed> $value */
    public static function restore(#[\SensitiveParameter] array $value): self
    {
        try {
            AuditAnchorIntentShape::assertClosed($value, ['intent', 'binding', 'leaseOrigin']);
            if (
                !is_array($value['intent'])
                || !is_array($value['binding'])
                || $value['leaseOrigin'] !== null && !is_array($value['leaseOrigin'])
            ) {
                throw new \RuntimeException();
            }
            /** @var array<array-key,mixed> $intentValue */
            $intentValue = $value['intent'];
            /** @var array<array-key,mixed> $bindingValue */
            $bindingValue = $value['binding'];
            /** @var null|array<array-key,mixed> $originValue */
            $originValue = $value['leaseOrigin'];
            $intent = AuditAnchorIntent::fromArray($intentValue);
            $binding = AuditAnchorBinding::fromArray($bindingValue);
            $origin = $originValue === null ? null : AuditAnchorLeaseOrigin::fromArray($originValue);
            $intentValue = $intent->toArray();
            $bindingValue = $binding->toArray();
            AuditAnchorBinding::assertIntentAssociation($intentValue, $bindingValue);
            AuditAnchorLeaseOrigin::assertAssociation($intentValue, $origin?->toArray());
            return new self($intent, $binding, $origin);
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
    }

    public function intent(): AuditAnchorIntent
    {
        /** @var array{intent:AuditAnchorIntent,binding:AuditAnchorBinding,leaseOrigin:?AuditAnchorLeaseOrigin} $snapshot */
        $snapshot = $this->snapshot->getValue();
        return $snapshot['intent'];
    }

    public function binding(): AuditAnchorBinding
    {
        /** @var array{intent:AuditAnchorIntent,binding:AuditAnchorBinding,leaseOrigin:?AuditAnchorLeaseOrigin} $snapshot */
        $snapshot = $this->snapshot->getValue();
        return $snapshot['binding'];
    }

    public function leaseOrigin(): ?AuditAnchorLeaseOrigin
    {
        /** @var array{intent:AuditAnchorIntent,binding:AuditAnchorBinding,leaseOrigin:?AuditAnchorLeaseOrigin} $snapshot */
        $snapshot = $this->snapshot->getValue();
        return $snapshot['leaseOrigin'];
    }

    /** @return array{intent:array<string,mixed>,binding:array<string,mixed>,leaseOrigin:?array<string,mixed>} */
    public function toArray(): array
    {
        return [
            'intent' => $this->intent()->toArray(),
            'binding' => $this->binding()->toArray(),
            'leaseOrigin' => $this->leaseOrigin()?->toArray(),
        ];
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
