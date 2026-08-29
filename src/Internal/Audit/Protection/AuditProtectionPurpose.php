<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Protection;

enum AuditProtectionPurpose: string
{
    case AuditRecord = 'audit_record';
    case AuditOutboxRecord = 'audit_outbox_record';
}
