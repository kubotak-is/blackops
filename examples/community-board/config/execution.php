<?php

declare(strict_types=1);

use BlackOps\Application\Environment;

return static fn(Environment $env): array => [
    'worker' => [
        'id' => $env->string('WORKER_ID', 'community-board-worker-1'),
        'lease_seconds' => $env->positiveInt('WORKER_LEASE_SECONDS', 60),
        'heartbeat_seconds' => $env->positiveInt('WORKER_HEARTBEAT_SECONDS', 10),
        'grace_seconds' => $env->positiveInt('WORKER_GRACE_SECONDS', 20),
        'continue_after_handler_failure' => $env->bool('WORKER_CONTINUE_AFTER_HANDLER_FAILURE', true),
    ],
    'outbox_relay' => [
        'id' => $env->string('OUTBOX_RELAY_ID', 'community-board-relay-1'),
        'batch_size' => $env->positiveInt('OUTBOX_RELAY_BATCH_SIZE', 50),
        'lease_seconds' => $env->positiveInt('OUTBOX_RELAY_LEASE_SECONDS', 60),
        'heartbeat_seconds' => $env->positiveInt('OUTBOX_RELAY_HEARTBEAT_SECONDS', 10),
        'grace_seconds' => $env->positiveInt('OUTBOX_RELAY_GRACE_SECONDS', 20),
        'max_attempts' => $env->positiveInt('OUTBOX_RELAY_MAX_ATTEMPTS', 8),
        'initial_backoff_seconds' => $env->positiveInt('OUTBOX_RELAY_INITIAL_BACKOFF_SECONDS', 1),
        'max_backoff_seconds' => $env->positiveInt('OUTBOX_RELAY_MAX_BACKOFF_SECONDS', 300),
        'poll_interval_milliseconds' => $env->positiveInt('OUTBOX_RELAY_POLL_INTERVAL_MILLISECONDS', 1000),
    ],
];
