<?php

declare(strict_types=1);

return [
    'transport_payload_days' => (int) ($_ENV['RETENTION_TRANSPORT_PAYLOAD_DAYS'] ?? '30'),
    'journal_days' => (int) ($_ENV['RETENTION_JOURNAL_DAYS'] ?? '90'),
    'outcome_days' => (int) ($_ENV['RETENTION_OUTCOME_DAYS'] ?? '30'),
    'dead_letter_days' => (int) ($_ENV['RETENTION_DEAD_LETTER_DAYS'] ?? '90'),
    'policy_ref' => $_ENV['RETENTION_POLICY_REF'] ?? 'quickstart-retention-v1',
    'actor' => $_ENV['RETENTION_ACTOR'] ?? 'quickstart-maintenance',
];
