<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

enum AuditAnchorState: string
{
    case Prepared = 'prepared';
    case ExternallyAccepted = 'externally_accepted';
    case ExternallyRejected = 'externally_rejected';
    case ReceiptInputsPersisted = 'receipt_inputs_persisted';
    case ReceiptPersisted = 'receipt_persisted';
    case Acknowledged = 'acknowledged';
}
