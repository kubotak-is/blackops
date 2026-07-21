<?php

declare(strict_types=1);

use BlackOps\Application\Environment;

return static fn(Environment $env): array => [
    'transport_payload_days' => $env->positiveInt('RETENTION_TRANSPORT_PAYLOAD_DAYS', 30),
    'journal_days' => $env->positiveInt('RETENTION_JOURNAL_DAYS', 90),
    'outcome_days' => $env->positiveInt('RETENTION_OUTCOME_DAYS', 30),
    'dead_letter_days' => $env->positiveInt('RETENTION_DEAD_LETTER_DAYS', 90),
    'policy_ref' => $env->string('RETENTION_POLICY_REF', 'quickstart-retention-v1'),
    'actor' => $env->string('RETENTION_ACTOR', 'quickstart-maintenance'),
];
