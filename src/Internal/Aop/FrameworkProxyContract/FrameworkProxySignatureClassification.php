<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyContract;

enum FrameworkProxySignatureClassification: string
{
    case SUPPORT = 'support';
    case REJECT = 'reject';
    case NOT_APPLICABLE = 'not-applicable';
}
