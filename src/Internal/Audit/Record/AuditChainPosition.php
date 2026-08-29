<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Record;

use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;

/**
 * @mago-expect lint:block-statement
 */
final readonly class AuditChainPosition
{
    public function __construct(
        public string $partitionId,
        public string $segmentId,
        public int $sequence,
        public ?string $previousRecordHash,
    ) {
        self::assertPartitionGrammar($partitionId);
        self::assertSegmentGrammar($segmentId);
        self::assertHashGrammar($previousRecordHash);
        if ($sequence < 1 || $sequence > AuditValidationException::MAX_SAFE_INTEGER)
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        if ($sequence === 1 && $previousRecordHash !== null || $sequence > 1 && $previousRecordHash === null) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
    }

    private static function assertPartitionGrammar(string $value): void
    {
        AuditValidationException::assertSafeString($value, 1, 71);
        if (preg_match('/^(?:system:global|tenant:[a-f0-9]{64})$/D', $value) !== 1)
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
    }

    private static function assertSegmentGrammar(string $value): void
    {
        AuditValidationException::assertSafeString($value, 36, 36);
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value) !== 1)
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
    }

    private static function assertHashGrammar(?string $value): void
    {
        if ($value === null)
            return;
        AuditValidationException::assertSafeString($value, 64, 64);
        if (preg_match('/^[a-f0-9]{64}$/D', $value) !== 1)
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
    }
}
