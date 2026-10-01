<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authentication;

use BlackOps\Http\Authentication\AuthenticationResult;

interface AuthenticationAuditEventWriter
{
    public function write(#[\SensitiveParameter] AuthenticationResult $result): void;
}
