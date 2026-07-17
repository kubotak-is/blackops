<?php

declare(strict_types=1);

return [
    'default' => 'app',
    'connections' => [
        'app' => [
            'driver' => 'pdo_pgsql',
            'host' => $_ENV['POSTGRES_HOST'] ?? '127.0.0.1',
            'port' => (int) ($_ENV['POSTGRES_PORT'] ?? '5432'),
            'dbname' => $_ENV['POSTGRES_DB'] ?? 'blackops',
            'user' => $_ENV['POSTGRES_USER'] ?? 'blackops',
            'password' => $_ENV['POSTGRES_PASSWORD'] ?? 'blackops',
        ],
    ],
    'framework' => [
        'connection' => 'app',
        'schema' => $_ENV['BLACKOPS_SCHEMA'] ?? 'blackops',
    ],
];
