<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

/** @phpstan-import-type IntentValue from AuditAnchorIntentShape */
final readonly class AuditAnchorBinding
{
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
                'segmentId',
                'previousAnchorReceiptHash',
                'checkpointSignedAt',
                'anchorReceiptId',
            ]);
            $segmentId = AuditAnchorIntentShape::assertText($value['segmentId'], 36, 36);
            if (
                preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $segmentId) !== 1
            ) {
                throw new \RuntimeException();
            }
            AuditAnchorIntentShape::assertHash($value['previousAnchorReceiptHash'], 'nullable');
            $checkpointSignedAt = AuditAnchorTime::assertTimestamp($value['checkpointSignedAt']);
            AuditAnchorIntentShape::assertText($value['anchorReceiptId']);
            return new self([
                'segmentId' => $value['segmentId'],
                'previousAnchorReceiptHash' => $value['previousAnchorReceiptHash'],
                'checkpointSignedAt' => $checkpointSignedAt,
                'anchorReceiptId' => $value['anchorReceiptId'],
            ]);
        } catch (AuditAnchorException $exception) {
            throw new AuditAnchorException($exception->failure());
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
    }

    /** @return array{segmentId:string,previousAnchorReceiptHash:?string,checkpointSignedAt:string,anchorReceiptId:string} */
    public function toArray(): array
    {
        /** @var array{segmentId:string,previousAnchorReceiptHash:?string,checkpointSignedAt:string,anchorReceiptId:string} $value */
        $value = $this->snapshot->getValue();
        return [...$value];
    }

    /**
     * @param IntentValue $intent
     * @param array{segmentId:string,previousAnchorReceiptHash:?string,checkpointSignedAt:string,anchorReceiptId:string} $binding
     */
    public static function assertIntentAssociation(
        #[\SensitiveParameter]
        array $intent,
        #[\SensitiveParameter]
        array $binding,
    ): void {
        if (($intent['expectedPredecessorHash'] === null) !== ($binding['previousAnchorReceiptHash'] === null)) {
            throw new \RuntimeException();
        }
        $accepted = !in_array($intent['state'], ['prepared', 'externally_rejected'], strict: true);
        $valid = $accepted
            ? [
                $intent['anchorReceiptId'] !== null,
                $intent['segmentId'] !== null,
                $intent['anchorReceiptId'] === $binding['anchorReceiptId'],
                $intent['segmentId'] === $binding['segmentId'],
                hash_equals(
                    (string) $intent['previousAnchorReceiptHash'],
                    (string) $binding['previousAnchorReceiptHash'],
                ),
                AuditAnchorTime::compare(
                    $binding['checkpointSignedAt'],
                    AuditAnchorTime::assertTimestamp($intent['acceptedAnchoredAt']),
                ) <= 0,
            ] === [true, true, true, true, true, true]
            : [
                $intent['anchorReceiptId'] === null,
                $intent['segmentId'] === null,
                $intent['previousAnchorReceiptHash'] === null,
            ] === [true, true, true];
        if (!$valid) {
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
