<?php

declare(strict_types=1);

$continueAfterFailure = match (strtolower($_ENV['WORKER_CONTINUE_AFTER_HANDLER_FAILURE'] ?? 'true')) {
    '1', 'true', 'yes', 'on' => true,
    '0', 'false', 'no', 'off' => false,
    default => true,
};

return [
    'worker' => [
        'id' => $_ENV['WORKER_ID'] ?? 'quickstart-worker-1',
        'lease_seconds' => (int) ($_ENV['WORKER_LEASE_SECONDS'] ?? '60'),
        'heartbeat_seconds' => (int) ($_ENV['WORKER_HEARTBEAT_SECONDS'] ?? '10'),
        'grace_seconds' => (int) ($_ENV['WORKER_GRACE_SECONDS'] ?? '20'),
        'continue_after_handler_failure' => $continueAfterFailure,
    ],
];
