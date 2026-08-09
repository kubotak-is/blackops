<?php

declare(strict_types=1);

namespace BlackOps\Observability;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface OperationalHealthCheckProvider
{
    public function code(): string;

    public function check(): bool;
}
