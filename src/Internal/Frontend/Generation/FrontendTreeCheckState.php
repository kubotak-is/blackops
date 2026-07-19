<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend\Generation;

enum FrontendTreeCheckState
{
    case Fresh;
    case Missing;
    case Drift;
}
