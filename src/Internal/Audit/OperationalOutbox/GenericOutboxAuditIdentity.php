<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\OperationalOutbox;

use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;

final class GenericOutboxAuditIdentity
{
    private function __construct() {}

    public static function recordId(#[\SensitiveParameter] string $recordId): string
    {
        try {
            AuditValidationException::assertGrammarString(
                $recordId,
                '/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D',
                36,
            );

            return $recordId;
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    public static function stateVersion(#[\SensitiveParameter] string $stateVersion): string
    {
        try {
            AuditValidationException::assertGrammarString($stateVersion, '/\A[1-9][0-9]{0,18}\z/D', 19);
            if (strlen($stateVersion) === 19 && strcmp($stateVersion, '9223372036854775807') > 0) {
                throw new \LogicException();
            }

            return $stateVersion;
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    public static function retryAuditId(#[\SensitiveParameter] string $retryAuditId): string
    {
        try {
            AuditValidationException::assertGrammarString(
                $retryAuditId,
                '/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D',
                36,
            );

            return $retryAuditId;
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    public static function discriminator(AuditSourceRuntime $runtime, #[\SensitiveParameter] string $value): string
    {
        return match ($runtime) {
            AuditSourceRuntime::Relay => self::stateVersion($value),
            AuditSourceRuntime::Cli => self::retryAuditId($value),
            default => throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::TransactionInvalid),
        };
    }

    public static function tenantReference(#[\SensitiveParameter] ?TenantRef $tenant): ?AuditTypedReference
    {
        if ($tenant === null) {
            return null;
        }
        try {
            $type = $tenant->type();
            $id = $tenant->id();
            if (trim($type) !== $type || trim($id) !== $id) {
                throw new \LogicException();
            }

            return new AuditTypedReference($type, $id);
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }
}
