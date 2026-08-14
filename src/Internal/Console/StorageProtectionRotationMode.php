<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

enum StorageProtectionRotationMode
{
    case Plan;
    case Confirmed;
}
