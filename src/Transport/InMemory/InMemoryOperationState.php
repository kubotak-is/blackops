<?php

declare(strict_types=1);

namespace BlackOps\Transport\InMemory;

enum InMemoryOperationState
{
    case Available;
    case Claimed;
    case Settled;
}
