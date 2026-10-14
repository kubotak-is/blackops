<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authority\History;

enum AuditHistoryControlTag: int
{
    case TransitionsBegin = 64;
    case TransitionNative = 65;
    case TransitionEnvelope = 66;
    case TransitionsEnd = 67;
    case TransitionAckNative = 68;
    case TransitionAckEnvelope = 69;
    case NoTransitionAcknowledgement = 70;

    case PublicationsBegin = 72;
    case PublicationNative = 73;
    case PublicationDocument = 74;
    case PublicationsEnd = 75;
    case CandidatePublicationNative = 76;
    case CandidatePublicationDocument = 77;
    case NoNextPublication = 78;
    case PublicationEnd = 79;
}
