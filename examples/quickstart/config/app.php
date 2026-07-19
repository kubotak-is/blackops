<?php

declare(strict_types=1);

use App\ApplicationServiceProvider;

return [
    'build' => [
        'application_build_id' => $_ENV['APP_BUILD_ID'] ?? 'quickstart-local',
        'operation_manifest' => dirname(__DIR__) . '/var/build/operations.php',
        'http_manifest' => dirname(__DIR__) . '/var/build/http.php',
        'frontend_manifest' => dirname(__DIR__) . '/var/build/frontend.php',
        'container' => dirname(__DIR__) . '/var/build/container.php',
        'container_class' => 'CompiledContainer',
        'container_namespace' => 'App\\Generated',
    ],
    'services' => [
        ApplicationServiceProvider::class,
    ],
];
