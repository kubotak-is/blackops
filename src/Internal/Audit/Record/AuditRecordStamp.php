<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Record;

use BlackOps\Core\Audit\AuditRecordTime;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;

final readonly class AuditRecordStamp
{
    public function __construct(
        public string $recordId,
        public string $recordedAt,
    ) {
        AuditValidationException::assertSafeString($recordId, 36, 36);
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $recordId) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
        AuditRecordTime::assertTimestamp($recordedAt);
    }
}
