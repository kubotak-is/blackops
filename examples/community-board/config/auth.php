<?php

declare(strict_types=1);

use App\AuthServiceProvider;
use App\Infrastructure\Identity\ApplicationSessionIdentityProvider;
use BlackOps\Application\Environment;
use BlackOps\Auth\Session\SessionConfiguration;
use BlackOps\Auth\Session\SessionServiceProvider;

return static fn(Environment $env): array => [
    'generator_version' => 1,
    'services' => [
        new AuthServiceProvider($env->bool('AUTH_REGISTRATION_ENABLED', true)),
        SessionServiceProvider::bearer(
            ApplicationSessionIdentityProvider::class,
            new SessionConfiguration(
                ttlSeconds: $env->positiveInt('AUTH_SESSION_TTL_SECONDS', 28_800),
                touchIntervalSeconds: $env->positiveInt('AUTH_SESSION_TOUCH_INTERVAL_SECONDS', 300),
            ),
        ),
    ],
];
