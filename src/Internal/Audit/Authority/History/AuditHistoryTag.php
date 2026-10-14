<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authority\History;

enum AuditHistoryTag: int
{
    case Header = 1;
    case AcceptedAuthorityBytes = 2;
    case ExternalAnchor = 3;
    case FloorHint = 4;
    case InstalledNextPolicy = 5;
    case EmptyInstalledSource = 6;

    case PublishedSegmentsBegin = 48;
    case PublishedSegmentNative = 49;
    case PublishedSegmentEnvelope = 50;
    case PublishedSegmentsEnd = 51;
    case StagedSegmentsBegin = 52;
    case StagedSegmentNative = 53;
    case StagedSegmentEnvelope = 54;
    case StagedSegmentsEnd = 55;

    case Trailer = 255;
}
