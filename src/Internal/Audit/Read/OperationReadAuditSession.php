<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Read;

use BlackOps\Core\Audit\AuditReadAuthorizationDecision;
use BlackOps\Core\Audit\AuditReadContext;
use BlackOps\Core\Audit\AuditReadRequestBasis;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;

/**
 * Keeps the one-terminal lifecycle and fail-closed snapshot checks cohesive.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class OperationReadAuditSession
{
    private \SensitiveParameterValue $snapshot;

    private bool $terminalAttempted = false;

    private function __construct(
        #[\SensitiveParameter]
        AuditReadEventWriter $writer,
        #[\SensitiveParameter]
        AuditReadRequestBasis $basis,
        #[\SensitiveParameter]
        AuditRecordDraft $accessed,
        #[\SensitiveParameter]
        AuditRecordDraft $unavailable,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'writer' => $writer,
            'basis' => $basis,
            'accessed' => $accessed,
            'unavailable' => $unavailable,
        ]);
    }

    public static function open(
        #[\SensitiveParameter]
        AuditReadEventWriter $writer,
        #[\SensitiveParameter]
        AuditReadRequestBasis $basis,
        #[\SensitiveParameter]
        AuditRecordDraft $requested,
        #[\SensitiveParameter]
        AuditRecordDraft $accessed,
        #[\SensitiveParameter]
        AuditRecordDraft $unavailable,
    ): self {
        try {
            $session = new self($writer, $basis, $accessed, $unavailable);
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $writer->requested($basis, $requested);
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }

        return $session;
    }

    public function accessed(): void
    {
        $this->terminal(AuditReadAuthorizationDecision::Allow, 'accessed');
    }

    public function unavailable(): void
    {
        $this->terminal(AuditReadAuthorizationDecision::ResourceUnavailable, 'unavailable');
    }

    public function __clone(): void
    {
        throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
    }

    /** @return array<never,never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<never,never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<never,never> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
    }

    private function terminal(AuditReadAuthorizationDecision $decision, #[\SensitiveParameter] string $draftKey): void
    {
        try {
            if ($this->terminalAttempted) {
                throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
            }
            $this->terminalAttempted = true;
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }

        try {
            $basis = $this->value('basis');
            $writer = $this->value('writer');
            $draft = $this->value($draftKey);
            if (
                !$basis instanceof AuditReadRequestBasis
                || !$writer instanceof AuditReadEventWriter
                || !$draft instanceof AuditRecordDraft
            ) {
                throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
            }
            $context = AuditReadContext::fromBasis($basis, $decision);
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }

        try {
            $returned = $writer->terminal($context, $draft);
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
        if ($returned !== $decision) {
            throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['writer', 'basis', 'accessed', 'unavailable']
            || !$value['writer'] instanceof AuditReadEventWriter
            || !$value['basis'] instanceof AuditReadRequestBasis
            || !$value['accessed'] instanceof AuditRecordDraft
            || !$value['unavailable'] instanceof AuditRecordDraft
            || !array_key_exists($key, $value)
        ) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }

        return $value[$key];
    }
}
