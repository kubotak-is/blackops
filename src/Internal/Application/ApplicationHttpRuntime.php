<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use Psr\Http\Server\RequestHandlerInterface;

final class ApplicationHttpRuntime
{
    private ?RequestHandlerInterface $handler = null;

    public function __construct(
        private readonly ApplicationConfigurationSnapshot $configuration,
    ) {}

    public function handler(): RequestHandlerInterface
    {
        return $this->handler ??= new ApplicationHttpRuntimeComposer()->compose($this->configuration);
    }
}
