<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Replay;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Journal\JournalRecord;
use DateTimeImmutable;

final class ObserverReplayAuditHookLineage
{
    private function __construct() {}

    /** @return array{recordId:string,tenant:?TenantRef} */
    public static function validate(
        #[\SensitiveParameter]
        string $cursor,
        #[\SensitiveParameter]
        JournalRecord $source,
    ): array {
        try {
            $recordId = $source->recordId;
            if (!$recordId instanceof JournalRecordId) {
                throw new \LogicException();
            }
            $recordIdValue = $recordId->toString();
            if (JournalRecordId::fromString($recordIdValue)->toString() !== $recordIdValue) {
                throw new \LogicException();
            }
            $sequence = $source->sequence;
            if (!is_int($sequence) || $sequence < 1) {
                throw new \LogicException();
            }
            $occurredAt = $source->occurredAt;
            if (!$occurredAt instanceof DateTimeImmutable || $occurredAt->getTimezone()->getName() !== 'UTC') {
                throw new \LogicException();
            }
            $operation = $source->operation;
            $tenant = $operation->tenant;
            if ($tenant !== null) {
                if (!$tenant instanceof TenantRef) {
                    throw new \LogicException();
                }
                $type = $tenant->type();
                $id = $tenant->id();
                if (trim($type) !== $type || trim($id) !== $id) {
                    throw new \LogicException();
                }
                $roundTrip = new TenantRef($type, $id);
                if ($roundTrip->type() !== $type || $roundTrip->id() !== $id) {
                    throw new \LogicException();
                }
                new AuditTypedReference($type, $id);
                $tenant = $roundTrip;
            }
            $timeCursor = $occurredAt->format('Y-m-d H:i:s.uP') . '|' . $recordIdValue;
            if (!in_array(
                $cursor,
                [
                    $recordIdValue,
                    $sequence . '|' . $recordIdValue,
                    $timeCursor,
                ],
                strict: true,
            )) {
                throw new \LogicException();
            }

            return ['recordId' => $recordIdValue, 'tenant' => $tenant];
        } catch (AuditDeliveryException $failure) {
            throw ObserverReplayAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }
}
