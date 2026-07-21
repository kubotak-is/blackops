<?php

declare(strict_types=1);

use BlackOps\Application\Environment;

return static fn(Environment $env): array => [
    'default' => 'app',
    'connections' => [
        'app' => [
            'driver' => 'pdo_pgsql',
            'host' => $env->string('POSTGRES_HOST', '127.0.0.1'),
            'port' => $env->positiveInt('POSTGRES_PORT', 5432),
            'dbname' => $env->string('POSTGRES_DB', 'community_board'),
            'user' => $env->string('POSTGRES_USER', 'blackops'),
            'password' => $env->string('POSTGRES_PASSWORD', 'blackops'),
        ],
    ],
    'framework' => [
        'connection' => 'app',
        'schema' => $env->string('BLACKOPS_SCHEMA', 'blackops'),
    ],
];
