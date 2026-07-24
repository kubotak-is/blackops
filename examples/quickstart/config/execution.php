<?php

declare(strict_types=1);

use BlackOps\Application\Environment;

return static fn(Environment $env): array => [
    'worker' => [
        'id' => $env->string('WORKER_ID', 'quickstart-worker-1'),
        'lease_seconds' => $env->positiveInt('WORKER_LEASE_SECONDS', 60),
        'heartbeat_seconds' => $env->positiveInt('WORKER_HEARTBEAT_SECONDS', 10),
        'grace_seconds' => $env->positiveInt('WORKER_GRACE_SECONDS', 20),
        'continue_after_handler_failure' => $env->bool('WORKER_CONTINUE_AFTER_HANDLER_FAILURE', true),
    ],
    'outbox_relay' => [
        'id' => $env->string('OUTBOX_RELAY_ID', 'quickstart-relay-1'),
    ],
];
