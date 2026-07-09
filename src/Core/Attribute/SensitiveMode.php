<?php

declare(strict_types=1);

namespace BlackOps\Core\Attribute;

#[PublicApi]
enum SensitiveMode
{
    case Omit;
    case Mask;
    case Hash;
}
