<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authority\History;

enum AuditHistoryCheckpointTag: int
{
    case PartitionNative = 16;
    case TargetSegmentNative = 17;
    case SuccessorSegmentNative = 18;
    case PredecessorSegmentNative = 19;
    case NoPredecessorSegment = 20;
    case PreviousRecordEndpoint = 21;
    case NoPreviousRecordEndpoint = 22;
    case CurrentOpenSegmentNative = 23;
    case OwnedClosedProof = 24;
    case CheckpointPresence = 25;
    case ReservationNative = 26;
    case ReservationEnvelope = 27;
    case WorkStepNative = 28;
    case WorkStepEnvelope = 29;
    case CompleteCandidateBytes = 30;
    case PreparationNative = 31;
    case PreparedUnsignedBytes = 32;
    case CompleteCheckpointNative = 33;
    case PersistedCompleteBytes = 34;
    case CheckpointEnd = 35;
}
