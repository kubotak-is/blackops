<?php

declare(strict_types=1);

use App\ApplicationServiceProvider;
use BlackOps\Application\Environment;

return static fn(Environment $env): array => [
    'build' => [
        'application_build_id' => $env->string('APP_BUILD_ID', 'community-board-local'),
        'operation_manifest' => dirname(__DIR__) . '/var/build/operations.php',
        'http_manifest' => dirname(__DIR__) . '/var/build/http.php',
        'frontend_manifest' => dirname(__DIR__) . '/var/build/frontend.php',
        'command_manifest' => dirname(__DIR__) . '/var/build/commands.php',
        'container' => dirname(__DIR__) . '/var/build/container.php',
        'container_class' => 'CompiledContainer',
        'container_namespace' => 'App\\Generated',
    ],
    'services' => [new ApplicationServiceProvider($env->bool('DIGEST_FAIL_FIRST_ATTEMPT', false))],
    'command_discovery' => [dirname(__DIR__) . '/app/Console'],
];
