<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\OperationalOutbox;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;

final class GenericOutboxAuditRow
{
    private function __construct() {}

    /**
     * DBAL supplies an untrusted carrier. Runtime admission requires exactly
     * the four RETURNING fields; the returned shape is the checked value.
     *
     * @param array<string,mixed> $row
     * @return array{record_id:string,state_version:string,tenant:TenantRef|null}
     */
    public static function read(#[\SensitiveParameter] array $row): array
    {
        try {
            $required = ['record_id', 'tenant_type', 'tenant_id', 'state_version'];
            $keys = array_keys($row);
            if (count($row) !== 4 || array_diff($keys, $required) !== [] || array_diff($required, $keys) !== []) {
                throw new \LogicException();
            }
            $recordId = $row['record_id'];
            $stateVersion = $row['state_version'];
            $tenantType = $row['tenant_type'];
            $tenantId = $row['tenant_id'];
            if (!is_string($recordId) || !is_string($stateVersion)) {
                throw new \LogicException();
            }

            return [
                'record_id' => GenericOutboxAuditIdentity::recordId($recordId),
                'state_version' => GenericOutboxAuditIdentity::stateVersion($stateVersion),
                'tenant' => self::tenant($tenantType, $tenantId),
            ];
        } catch (AuditDeliveryException $failure) {
            throw GenericOutboxAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private static function tenant(#[\SensitiveParameter] mixed $type, #[\SensitiveParameter] mixed $id): ?TenantRef
    {
        if ($type === null && $id === null) {
            return null;
        }
        if (!is_string($type) || !is_string($id) || [$type, $id] !== [trim($type), trim($id)]) {
            throw new \LogicException();
        }
        new AuditTypedReference($type, $id);

        return new TenantRef($type, $id);
    }
}
