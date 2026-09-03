<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;

final readonly class ApplicationAuditConfiguration
{
    private function __construct(
        #[\SensitiveParameter]
        private AuditOutboxConfiguration $outbox,
    ) {}

    public static function fromConfiguration(#[\SensitiveParameter] array $configuration): self
    {
        try {
            if (!array_key_exists('audit', $configuration)) {
                return new self(new AuditOutboxConfiguration());
            }
            $audit = $configuration['audit'];
            if (!is_array($audit) || array_diff(array_keys($audit), ['outbox']) !== []) {
                throw new \RuntimeException();
            }

            if (!array_key_exists('outbox', $audit)) {
                return new self(new AuditOutboxConfiguration());
            }
            $outbox = $audit['outbox'];
            if (!is_array($outbox)) {
                throw new \RuntimeException();
            }
            $allowed = [
                'lease_duration_seconds',
                'max_attempts',
                'backoff_base_seconds',
                'backoff_cap_seconds',
            ];
            if (array_diff(array_keys($outbox), $allowed) !== []) {
                throw new \RuntimeException();
            }
            foreach ($outbox as $value) {
                if (!is_int($value)) {
                    throw new \RuntimeException();
                }
            }

            $leaseDurationSeconds = $outbox['lease_duration_seconds'] ?? 300;
            $maxAttempts = $outbox['max_attempts'] ?? 5;
            $backoffBaseSeconds = $outbox['backoff_base_seconds'] ?? 1;
            $backoffCapSeconds = $outbox['backoff_cap_seconds'] ?? 3600;
            /** @var int $leaseDurationSeconds */
            /** @var int $maxAttempts */
            /** @var int $backoffBaseSeconds */
            /** @var int $backoffCapSeconds */

            return new self(new AuditOutboxConfiguration(
                leaseDurationSeconds: $leaseDurationSeconds,
                maxAttempts: $maxAttempts,
                backoffBaseSeconds: $backoffBaseSeconds,
                backoffCapSeconds: $backoffCapSeconds,
            ));
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::ConfigurationInvalid);
        }
    }

    public function outbox(): AuditOutboxConfiguration
    {
        return $this->outbox;
    }
}
