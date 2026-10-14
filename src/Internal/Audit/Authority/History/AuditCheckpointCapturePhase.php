<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authority\History;

enum AuditCheckpointCapturePhase: int
{
    case InspectOrdinaryCheckpoint = 1;
    case InspectControlCheckpoint = 2;
    case ReserveOrdinaryCheckpoint = 3;
    case ReserveControlCheckpoint = 4;
    case BeforeCheckpointPrepare = 5;
    case BindCheckpointPreparation = 6;
    case CheckpointCandidate = 7;
    case BeforeCheckpointPersist = 8;
    case CheckpointPersistedOutcome = 9;
}
