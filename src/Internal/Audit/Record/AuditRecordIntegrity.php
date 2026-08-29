<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Record;

use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;

final readonly class AuditRecordIntegrity
{
    public function __construct(
        public string $hashSuite,
        public string $partitionId,
        public string $segmentId,
        public int $sequence,
        public ?string $previousRecordHash,
        public string $contentHash,
        public string $recordHash,
    ) {
        // Keep every textual grammar check ahead of suite/chain semantics and
        // the later content/record hash comparisons at the decode boundary.
        AuditValidationException::assertSafeString($hashSuite, 1, 128);
        self::assertPartitionGrammar($partitionId);
        self::assertSegmentGrammar($segmentId);
        self::assertHashGrammar($previousRecordHash);
        self::assertHashGrammar($contentHash);
        self::assertHashGrammar($recordHash);
        if ($hashSuite !== 'audit-sha256-jcs-v1') {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
        new AuditChainPosition($partitionId, $segmentId, $sequence, $previousRecordHash);
    }

    /** @return array{hashSuite:string,partitionId:string,segmentId:string,sequence:int,previousRecordHash:string|null,contentHash:string,recordHash:string} */
    public function toArray(): array
    {
        return [
            'hashSuite' => $this->hashSuite,
            'partitionId' => $this->partitionId,
            'segmentId' => $this->segmentId,
            'sequence' => $this->sequence,
            'previousRecordHash' => $this->previousRecordHash,
            'contentHash' => $this->contentHash,
            'recordHash' => $this->recordHash,
        ];
    }

    private static function assertPartitionGrammar(string $value): void
    {
        AuditValidationException::assertSafeString($value, 1, 71);
        if (preg_match('/^(?:system:global|tenant:[a-f0-9]{64})$/D', $value) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
    }

    private static function assertSegmentGrammar(string $value): void
    {
        AuditValidationException::assertSafeString($value, 36, 36);
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
    }

    private static function assertHashGrammar(?string $value): void
    {
        if ($value === null) {
            return;
        }
        AuditValidationException::assertSafeString($value, 64, 64);
        if (preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
    }
}
