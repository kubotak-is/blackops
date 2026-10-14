<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authority\History;

enum AuditControlCapturePhase: int
{
    case ControlBootstrapCandidate = 10;
    case ControlRootRevisionCandidate = 11;
    case ControlSegmentAdvanceCandidate = 12;
    case ControlHeadRefreshCandidate = 13;
    case ControlPublicationRecovery = 14;
    case ControlPublishCommittedCandidate = 15;
}
