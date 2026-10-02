<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Replay;

use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Identifier\JournalRecordId;

final class ObserverReplayAuditWriterPolicy
{
    private function __construct() {}

    public static function assertSubject(
        #[\SensitiveParameter]
        string $eventType,
        #[\SensitiveParameter]
        string $subject,
    ): void {
        if ($eventType === 'replay.observer.progressed') {
            self::assertRecordId($subject);

            return;
        }
        AuditValidationException::assertSafeString($subject, 1, 128);
        if (preg_match('/\A[a-z0-9]+(?:[._-][a-z0-9]+)*\z/D', $subject) !== 1) {
            throw new \LogicException();
        }
    }

    public static function assertAuditId(#[\SensitiveParameter] string $auditId): void
    {
        AuditValidationException::assertSafeString($auditId, 32, 32);
        if (preg_match('/\A[a-f0-9]{32}\z/D', $auditId) !== 1) {
            throw new \LogicException();
        }
    }

    public static function assertRecordId(#[\SensitiveParameter] string $recordId): void
    {
        AuditValidationException::assertSafeString($recordId, 36, 36);
        if (JournalRecordId::fromString($recordId)->toString() !== $recordId) {
            throw new \LogicException();
        }
    }
}
