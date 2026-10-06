<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\OperationalOutbox;

use BlackOps\Core\Audit\AuditRecordActor;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use Psr\Clock\ClockInterface;

final class GenericOutboxAuditSnapshot
{
    private function __construct() {}

    /**
     * @return array{
     *     opaqueIds:HmacAuditOpaqueIdDeriver,
     *     selector:AuditDeliverySelector,
     *     delivery:AuditDeliveryCoordinator,
     *     clock:ClockInterface,
     *     actor:AuditRecordActor,
     *     correlation:AuditRecordCorrelation,
     *     runtime:AuditSourceRuntime,
     *     buildId:string
     * }
     */
    public static function read(#[\SensitiveParameter] ?\SensitiveParameterValue $snapshot): array
    {
        try {
            if (!$snapshot instanceof \SensitiveParameterValue) {
                throw new \LogicException();
            }
            $value = $snapshot->getValue();
            if (
                !is_array($value)
                || array_keys($value) !== [
                    'opaqueIds',
                    'selector',
                    'delivery',
                    'clock',
                    'actor',
                    'correlation',
                    'runtime',
                    'buildId',
                ]
            ) {
                throw new \LogicException();
            }
            $opaqueIds = $value['opaqueIds'];
            $selector = $value['selector'];
            $delivery = $value['delivery'];
            $clock = $value['clock'];
            $actor = $value['actor'];
            $correlation = $value['correlation'];
            $runtime = $value['runtime'];
            $buildId = $value['buildId'];
            if (
                !$opaqueIds instanceof HmacAuditOpaqueIdDeriver
                || !$selector instanceof AuditDeliverySelector
                || !$delivery instanceof AuditDeliveryCoordinator
                || !$clock instanceof ClockInterface
                || !$actor instanceof AuditRecordActor
                || !$correlation instanceof AuditRecordCorrelation
                || !$runtime instanceof AuditSourceRuntime
                || !is_string($buildId)
                || !in_array($runtime, [AuditSourceRuntime::Relay, AuditSourceRuntime::Cli], strict: true)
            ) {
                throw new \LogicException();
            }
            $principal = $actor->principal?->toArray();
            $execution = $actor->execution->toArray();
            $actorSnapshot = new AuditRecordActor(
                $principal === null ? null : new AuditTypedReference($principal['type'], $principal['id']),
                new AuditTypedReference($execution['type'], $execution['id']),
            );
            $correlationSnapshot = AuditRecordCorrelation::fromArray($correlation->toArray());
            AuditValidationException::assertSafeString($buildId, 1, 256);

            return [
                'opaqueIds' => $opaqueIds,
                'selector' => $selector,
                'delivery' => $delivery,
                'clock' => $clock,
                'actor' => $actorSnapshot,
                'correlation' => $correlationSnapshot,
                'runtime' => $runtime,
                'buildId' => $buildId,
            ];
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::StorageUnavailable);
        }
    }
}
