<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Orchestration;

use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditValidationException;

final readonly class AuditDeliveryPlan
{
    private function __construct(
        #[\SensitiveParameter]
        private string $catalogHash,
        #[\SensitiveParameter]
        private string $eventType,
        #[\SensitiveParameter]
        private int $eventVersion,
        #[\SensitiveParameter]
        private int $evidenceVersion,
        private AuditDelivery $delivery,
    ) {}

    public static function fromSelection(
        #[\SensitiveParameter]
        string $catalogHash,
        #[\SensitiveParameter]
        string $eventType,
        #[\SensitiveParameter]
        int $eventVersion,
        #[\SensitiveParameter]
        int $evidenceVersion,
        AuditDelivery $delivery,
    ): self {
        try {
            AuditValidationException::assertSafeString($catalogHash, 64, 64);
            if (preg_match('/\A[a-f0-9]{64}\z/D', $catalogHash) !== 1) {
                throw new \RuntimeException();
            }
            AuditValidationException::assertGrammarString($eventType, '/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/D', 128);
            if (
                $eventVersion < 1
                || $eventVersion > AuditValidationException::MAX_SAFE_INTEGER
                || $evidenceVersion < 1
                || $evidenceVersion > AuditValidationException::MAX_SAFE_INTEGER
            ) {
                throw new \RuntimeException();
            }
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
        }

        return new self($catalogHash, $eventType, $eventVersion, $evidenceVersion, $delivery);
    }

    public function catalogHash(): string
    {
        return $this->catalogHash;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function eventVersion(): int
    {
        return $this->eventVersion;
    }

    public function evidenceVersion(): int
    {
        return $this->evidenceVersion;
    }

    public function delivery(): AuditDelivery
    {
        return $this->delivery;
    }
}
