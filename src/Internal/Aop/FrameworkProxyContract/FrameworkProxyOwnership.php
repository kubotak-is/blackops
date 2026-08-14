<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyContract;

enum FrameworkProxyOwnership: string
{
    case SERVICE = 'service';
    case OPERATION = 'operation';
}
