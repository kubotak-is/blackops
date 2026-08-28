<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Projection;

use BlackOps\Core\Audit\AuditEvidenceClassification;
use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditProjectionException;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Record\AuditLogicalRecord;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\ValidatedAuditLogicalRecord;

/**
 * Builds the deliberately lossy observed view from one validator snapshot.
 * Every failure is reduced to the same safe public exception.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:block-statement
 */
final class ObservedAuditRecordProjector
{
    public function __construct(
        private readonly AuditRecordValidator $validator,
        private readonly HmacAuditOpaqueIdDeriver $deriver,
        private readonly AuditJcsCodec $jcs = new AuditJcsCodec(),
        private readonly AuditProjectionByteGuard $byteGuard = new AuditProjectionByteGuard(),
    ) {}

    public function project(#[\SensitiveParameter] AuditLogicalRecord $record): string
    {
        try {
            $validated = $this->validator->validateLogical($record);
            $projection = $this->projection($validated);
            return $this->byteGuard->guard($this->jcs->canonicalJson($projection) . "\n");
        } catch (\Throwable) {
            throw new AuditProjectionException();
        }
    }

    /** @return array<string,mixed> */
    private function projection(ValidatedAuditLogicalRecord $validated): array
    {
        $snapshot = $validated->snapshot;
        /** @var array<string,mixed> $event */
        $event = $snapshot['event'];
        /** @var array<string,mixed> $action */
        $action = $snapshot['action'];
        /** @var array<string,mixed> $actor */
        $actor = $snapshot['actor'];
        /** @var array<string,mixed> $resource */
        $resource = $snapshot['resource'];
        /** @var array<string,mixed> $outcome */
        $outcome = $snapshot['outcome'];
        /** @var array<string,mixed> $policy */
        $policy = $snapshot['policy'];
        /** @var array<string,mixed> $source */
        $source = $snapshot['source'];
        /** @var array<string,mixed> $correlation */
        $correlation = $snapshot['correlation'];
        /** @var array<string,mixed> $time */
        $time = $snapshot['time'];
        /** @var string $recordId */
        $recordId = $snapshot['recordId'];
        /** @var string $actionId */
        $actionId = $action['id'];
        /** @var string $resourceId */
        $resourceId = $resource['id'];

        return [
            'schemaVersion' => 1,
            'kind' => 'audit',
            'projection' => 'observed_v1',
            'recordRef' => $this->scalarRef($recordId, AuditOpaqueIdPurpose::Record),
            'event' => [
                'type' => $event['type'],
                'version' => $event['version'],
                'category' => $event['category'],
                'catalogHash' => $event['catalogHash'],
            ],
            'action' => [
                'ref' => $this->scalarRef($actionId, AuditOpaqueIdPurpose::Action),
                'type' => $action['type'],
                'phase' => $action['phase'],
            ],
            'actor' => [
                'principalRef' => $this->scalarReferenceRef($actor['principal'], AuditOpaqueIdPurpose::Principal),
                'executionRef' => $this->scalarReferenceRef($actor['execution'], AuditOpaqueIdPurpose::Execution),
            ],
            'resource' => [
                'type' => $resource['type'],
                'ref' => $this->scalarRef($resourceId, AuditOpaqueIdPurpose::Resource),
            ],
            'outcome' => [
                'status' => $outcome['status'],
                'code' => $outcome['code'],
            ],
            'policy' => [
                'decision' => $policy['decision'],
                'reasonCode' => $policy['reasonCode'],
                'auditDelivery' => $policy['auditDelivery'],
            ],
            'tenantRef' => $this->tenantReferenceRef($snapshot['tenant']),
            'time' => [
                'occurredAt' => $time['occurredAt'],
                'recordedAt' => $time['recordedAt'],
            ],
            'source' => [
                'owner' => $source['owner'],
                'runtime' => $source['runtime'],
            ],
            'correlation' => $this->correlation($correlation),
            'evidence' => $this->evidence($snapshot['evidence'], $validated),
        ];
    }

    private function scalarRef(string $value, AuditOpaqueIdPurpose $purpose): string
    {
        return $this->deriver->deriveScalar($purpose, $value)->value;
    }

    private function scalarReferenceRef(mixed $reference, AuditOpaqueIdPurpose $purpose): ?string
    {
        if ($reference === null)
            return null;
        if (!is_array($reference) || !is_string($reference['id'] ?? null))
            throw new AuditProjectionException();
        return $this->scalarRef($reference['id'], $purpose);
    }

    private function tenantReferenceRef(mixed $reference): ?string
    {
        if ($reference === null)
            return null;
        if (!is_array($reference) || !is_string($reference['id'] ?? null) || !is_string($reference['type'] ?? null))
            throw new AuditProjectionException();
        return $this->deriver->deriveTenant($reference)->value;
    }

    /** @param array<string,mixed> $correlation @return array<string,mixed> */
    private function correlation(array $correlation): array
    {
        return [
            'operationRef' => $this->correlationRef($correlation, 'operationId', AuditOpaqueIdPurpose::Operation),
            'correlationRef' => $this->correlationRef($correlation, 'correlationId', AuditOpaqueIdPurpose::Correlation),
            'causationRef' => $this->correlationRef($correlation, 'causationId', AuditOpaqueIdPurpose::Causation),
            'attempt' => $this->attempt($correlation),
            'traceRef' => $this->correlationRef($correlation, 'traceId', AuditOpaqueIdPurpose::Trace),
            'spanRef' => $this->correlationRef($correlation, 'spanId', AuditOpaqueIdPurpose::Span),
            'sampled' => array_key_exists('sampled', $correlation) ? $correlation['sampled'] : null,
        ];
    }

    /** @param array<string,mixed> $correlation */
    private function correlationRef(array $correlation, string $name, AuditOpaqueIdPurpose $purpose): ?string
    {
        if (!array_key_exists($name, $correlation) || $correlation[$name] === null)
            return null;
        if (!is_string($correlation[$name]))
            throw new AuditProjectionException();
        return $this->scalarRef($correlation[$name], $purpose);
    }

    /** @param array<string,mixed> $correlation @return array{number:int,kind:string}|null */
    private function attempt(array $correlation): ?array
    {
        if (!array_key_exists('attempt', $correlation) || $correlation['attempt'] === null)
            return null;
        if (!is_array($correlation['attempt']))
            throw new AuditProjectionException();
        return [
            'number' => $correlation['attempt']['number'],
            'kind' => $correlation['attempt']['kind'],
        ];
    }

    /** @param mixed $evidence @return array<string,mixed> */
    private function evidence(mixed $evidence, ValidatedAuditLogicalRecord $validated): array
    {
        if (!is_array($evidence) || !is_int($evidence['version'] ?? null))
            throw new AuditProjectionException();
        $result = ['version' => $evidence['version']];
        foreach ($validated->definition->evidenceFields as $field) {
            if (!in_array(
                $field->classification,
                [AuditEvidenceClassification::SafeCode, AuditEvidenceClassification::OpaqueId],
                strict: true,
            ))
                continue;
            if (!array_key_exists($field->name, $evidence) || $evidence[$field->name] === null)
                continue;
            if (!is_string($evidence[$field->name]))
                throw new AuditProjectionException();
            $result[$field->name] = $evidence[$field->name];
        }
        return $result;
    }
}
