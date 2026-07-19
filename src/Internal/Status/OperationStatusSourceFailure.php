<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

enum OperationStatusSourceFailure
{
    case Storage;
    case Decode;
    case Integrity;
}
