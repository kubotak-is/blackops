<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyDefinition;

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyDiagnostic;
use InvalidArgumentException;

final class FrameworkProxyDefinitionException extends InvalidArgumentException
{
    public function __construct(
        public readonly FrameworkProxyDiagnostic $diagnostic,
    ) {
        parent::__construct($diagnostic->code);
    }
}
