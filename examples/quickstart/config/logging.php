<?php

declare(strict_types=1);

return [
    'backend' => [
        'driver' => 'jsonl',
        'stream' => dirname(__DIR__) . '/var/log/application.jsonl',
        'channel' => 'blackops',
        'minimum_level' => 'info',
    ],
];
