<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\BuildSet;

enum ProjectionBuildSetValidationReason: string
{
    case MALFORMED = 'malformed';
    case UNSUPPORTED = 'unsupported';
    case STALE = 'stale';
    case BUILD_MISMATCH = 'build_mismatch';
}
