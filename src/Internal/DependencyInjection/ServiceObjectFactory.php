<?php

declare(strict_types=1);

namespace BlackOps\Internal\DependencyInjection;

use BlackOps\Auth\Session\SessionConfiguration;
use BlackOps\Auth\Session\SessionCookieName;
use Symfony\Component\DependencyInjection\Definition;

final readonly class ServiceObjectFactory
{
    public function definition(object $service): ?Definition
    {
        if ($service instanceof SessionConfiguration) {
            return new Definition(SessionConfiguration::class, [
                $service->ttlSeconds,
                $service->touchIntervalSeconds,
            ]);
        }

        if ($service instanceof SessionCookieName) {
            return new Definition(SessionCookieName::class, [$service->value()]);
        }

        return null;
    }
}
