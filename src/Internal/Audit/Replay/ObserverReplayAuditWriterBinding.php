<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Replay;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditRecordActor;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use Psr\Clock\ClockInterface;

final class ObserverReplayAuditWriterBinding
{
    private function __construct() {}

    /**
     * @param array{
     *     opaqueIds:HmacAuditOpaqueIdDeriver,
     *     selector:AuditDeliverySelector,
     *     delivery:AuditDeliveryCoordinator,
     *     clock:ClockInterface,
     *     actor:ActorContext,
     *     correlation:AuditRecordCorrelation,
     *     buildId:string
     * } $input
     */
    public static function capture(#[\SensitiveParameter] array $input): \SensitiveParameterValue
    {
        try {
            if (
                array_keys($input) !== [
                    'opaqueIds',
                    'selector',
                    'delivery',
                    'clock',
                    'actor',
                    'correlation',
                    'buildId',
                ]
            ) {
                throw new \LogicException();
            }
            $buildId = $input['buildId'];
            AuditValidationException::assertSafeString($buildId, 1, 256);
            $actor = $input['actor'];
            $principal = $actor->authorization();
            $execution = $actor->execution();
            $actorSnapshot = new AuditRecordActor(
                self::reference($principal),
                self::reference($execution) ?? throw new \LogicException(),
            );
            $correlationSnapshot = AuditRecordCorrelation::fromArray($input['correlation']->toArray());

            return new \SensitiveParameterValue([
                'opaqueIds' => $input['opaqueIds'],
                'selector' => $input['selector'],
                'delivery' => $input['delivery'],
                'clock' => $input['clock'],
                'actor' => $actorSnapshot,
                'correlation' => $correlationSnapshot,
                'buildId' => $buildId,
            ]);
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::ConfigurationInvalid);
        }
    }

    /**
     * @return array{
     *     opaqueIds:HmacAuditOpaqueIdDeriver,
     *     selector:AuditDeliverySelector,
     *     delivery:AuditDeliveryCoordinator,
     *     clock:ClockInterface,
     *     actor:AuditRecordActor,
     *     correlation:AuditRecordCorrelation,
     *     buildId:string
     * }
     */
    public static function values(#[\SensitiveParameter] \SensitiveParameterValue $snapshot): array
    {
        try {
            $value = $snapshot->getValue();
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::StorageUnavailable);
        }
        if (
            !is_array($value)
            || array_keys($value) !== [
                'opaqueIds',
                'selector',
                'delivery',
                'clock',
                'actor',
                'correlation',
                'buildId',
            ]
            || !$value['opaqueIds'] instanceof HmacAuditOpaqueIdDeriver
            || !$value['selector'] instanceof AuditDeliverySelector
            || !$value['delivery'] instanceof AuditDeliveryCoordinator
            || !$value['clock'] instanceof ClockInterface
            || !$value['actor'] instanceof AuditRecordActor
            || !$value['correlation'] instanceof AuditRecordCorrelation
            || !is_string($value['buildId'])
        ) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            AuditValidationException::assertSafeString($value['buildId'], 1, 256);
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::StorageUnavailable);
        }

        return [
            'opaqueIds' => $value['opaqueIds'],
            'selector' => $value['selector'],
            'delivery' => $value['delivery'],
            'clock' => $value['clock'],
            'actor' => $value['actor'],
            'correlation' => $value['correlation'],
            'buildId' => $value['buildId'],
        ];
    }

    public static function reference(#[\SensitiveParameter] ?ActorRef $reference): ?AuditTypedReference
    {
        if ($reference === null) {
            return null;
        }

        return new AuditTypedReference($reference->type(), $reference->id());
    }
}
