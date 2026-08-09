<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyContract;

use InvalidArgumentException;

final class FrameworkProxyContractException extends InvalidArgumentException
{
    public function __construct(
        public readonly FrameworkProxyDiagnostic $diagnostic,
    ) {
        parent::__construct($diagnostic->code);
    }
}
