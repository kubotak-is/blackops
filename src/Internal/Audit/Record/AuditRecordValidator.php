<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Record;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditAttemptKind;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditEvidenceClassification;
use BlackOps\Core\Audit\AuditEvidenceFieldDefinition;
use BlackOps\Core\Audit\AuditEvidenceType;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditRecordTime;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\AuditEventCatalogCodec;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;

/**
 * Validates producer drafts and trusted logical-record snapshots against one
 * immutable Catalog lookup. The logical boundary deliberately accepts only a
 * read-only interface and captures it before any validation continues.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:block-statement
 * @mago-expect lint:no-isset
 * @mago-expect lint:no-else-clause
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:no-boolean-flag-parameter
 */
final class AuditRecordValidator
{
    private const int MAX_CORRELATION_MEMBERS = 7;

    private const int MAX_EVIDENCE_MEMBERS = 33;

    public function __construct(
        private readonly ImmutableAuditEventCatalogLookup $lookup,
    ) {}

    public function validateDraft(AuditRecordDraft $draft): void
    {
        $value = $draft->toArray();
        $this->validate($value, false);
    }

    public function validateLogical(AuditLogicalRecord $logical): ValidatedAuditLogicalRecord
    {
        // Capture exactly once. Closed shape and descriptor-dependent evidence
        // are checked before the detached snapshot is rebuilt.
        $captured = $logical->toArray();
        $this->validateShape($captured, true);
        $resolution = $this->resolveDefinitionCandidate($captured);
        /** @var array<string,mixed> $evidence */
        $evidence = $captured['evidence'];
        $this->validateEvidenceDefinitionShape($evidence, $resolution['definition']);
        $snapshot = $this->snapshot($captured, $resolution['definition']);
        /** @var array<string,mixed> $snapshot */
        $definition = $this->validate($snapshot, true, $resolution);
        return new ValidatedAuditLogicalRecord($snapshot, $definition);
    }

    /**
     * @param array<string,mixed> $value
     * @param array{catalog:AuditEventCatalog|null,definition:AuditEventDefinition|null}|null $resolution
     */
    private function validate(array $value, bool $logical, ?array $resolution = null): AuditEventDefinition
    {
        $this->validateShape($value, $logical);
        if ($resolution === null)
            $resolution = $this->resolveDefinitionCandidate($value);
        $definition = $resolution['definition'];
        /** @var array<string,mixed> $evidence */
        $evidence = $value['evidence'];
        $this->validateEvidenceDefinitionShape($evidence, $definition);
        $this->validateUtf8($value);
        $this->validateControls($value);
        $this->validateNfc($value);
        $this->validateBounds($value, $logical, $definition);
        $this->validateGrammar($value, $logical, $definition);
        return $this->validateSemantics($value, $logical, $resolution['catalog'], $definition);
    }

    /** @param array<string,mixed> $value */
    private function validateShape(array $value, bool $logical): void
    {
        $this->sameKeys(
            $value,
            $logical
                ? [
                    'schemaVersion',
                    'kind',
                    'recordId',
                    'event',
                    'action',
                    'actor',
                    'resource',
                    'outcome',
                    'policy',
                    'tenant',
                    'time',
                    'source',
                    'correlation',
                    'evidence',
                ]
                : [
                    'schemaVersion',
                    'kind',
                    'event',
                    'action',
                    'actor',
                    'resource',
                    'outcome',
                    'policy',
                    'tenant',
                    'occurredAt',
                    'source',
                    'correlation',
                    'evidence',
                ],
        );
        if (!is_int($value['schemaVersion']) || !is_string($value['kind']))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        if ($logical && !is_string($value['recordId']))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        if (!$logical && !is_string($value['occurredAt']))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);

        $this->eventShape($value['event']);
        $this->actionShape($value['action']);
        $this->actorShape($value['actor']);
        $this->referenceOrNull($value['tenant']);
        $this->resourceShape($value['resource']);
        $this->outcomeShape($value['outcome']);
        $this->policyShape($value['policy']);
        $this->sourceShape($value['source']);
        $this->correlationShape($value['correlation']);
        $this->evidenceShape($value['evidence']);
        if ($logical)
            $this->timeShape($value['time']);
    }

    /**
     * @param array<string,mixed> $value
     * @return array{catalog:AuditEventCatalog|null,definition:AuditEventDefinition|null}
     */
    private function resolveDefinitionCandidate(array $value): array
    {
        /** @var array<string,mixed> $event */
        $event = $value['event'];
        /** @var array<string,mixed> $evidence */
        $evidence = $value['evidence'];
        $catalog = $this->lookup->resolve((string) $event['catalogHash']);
        if ($catalog === null)
            return ['catalog' => null, 'definition' => null];
        return [
            'catalog' => $catalog,
            'definition' => $this->definition(
                $catalog,
                (string) $event['type'],
                (int) $event['version'],
                (int) $evidence['version'],
            ),
        ];
    }

    /** @param array<string,mixed> $evidence */
    private function validateEvidenceDefinitionShape(array $evidence, ?AuditEventDefinition $definition): void
    {
        if ($definition === null)
            return;
        /** @var array<string,AuditEvidenceFieldDefinition> $declared */
        $declared = [];
        foreach ($definition->evidenceFields as $field)
            $declared[$field->name] = $field;
        foreach ($evidence as $name => $actual) {
            if ($name === 'version')
                continue;
            if (!is_string($name) || !array_key_exists($name, $declared))
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            if ($actual === null)
                continue;
            /** @var AuditEvidenceFieldDefinition $field */
            $field = $declared[$name];
            $valid = match ($field->type) {
                AuditEvidenceType::String => is_string($actual),
                AuditEvidenceType::Integer => is_int($actual)
                    && $actual >= -AuditJcsCodec::MAX_SAFE_INTEGER
                    && $actual <= AuditJcsCodec::MAX_SAFE_INTEGER,
                AuditEvidenceType::Boolean => is_bool($actual),
            };
            if (!$valid)
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
    }

    private function eventShape(mixed $value): void
    {
        if (!is_array($value))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        $this->sameKeys($value, ['type', 'version', 'category', 'catalogHash']);
        /** @var array<string,mixed> $value */
        if (
            !is_string($value['type'])
            || !is_int($value['version'])
            || !is_string($value['category'])
            || !is_string($value['catalogHash'])
        )
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }

    private function actionShape(mixed $value): void
    {
        if (!is_array($value))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        $this->sameKeys($value, ['id', 'type', 'phase']);
        /** @var array<string,mixed> $value */
        if (!is_string($value['id']) || !is_string($value['type']) || !is_string($value['phase']))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }

    private function actorShape(mixed $value): void
    {
        if (!is_array($value))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        $this->sameKeys($value, ['principal', 'execution']);
        /** @var array<string,mixed> $value */
        $this->referenceOrNull($value['principal']);
        $this->reference($value['execution']);
    }

    private function referenceOrNull(mixed $value): void
    {
        if ($value !== null)
            $this->reference($value);
    }

    private function reference(mixed $value): void
    {
        if (!is_array($value))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        $this->sameKeys($value, ['type', 'id']);
        /** @var array<string,mixed> $value */
        if (!is_string($value['type']) || !is_string($value['id']))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }

    private function resourceShape(mixed $value): void
    {
        if (!is_array($value))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        $this->sameKeys($value, ['type', 'id']);
        /** @var array<string,mixed> $value */
        if (!is_string($value['type']) || !is_string($value['id']))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }

    private function outcomeShape(mixed $value): void
    {
        if (!is_array($value))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        $this->sameKeys($value, ['status', 'code']);
        /** @var array<string,mixed> $value */
        if (!is_string($value['status']) || !is_string($value['code']))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }

    private function policyShape(mixed $value): void
    {
        if (!is_array($value))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        $this->sameKeys($value, ['id', 'version', 'decision', 'reasonCode', 'auditDelivery']);
        /** @var array<string,mixed> $value */
        if (
            !is_string($value['id'])
            || !is_int($value['version'])
            || !is_string($value['decision'])
            || !is_string($value['reasonCode'])
            || !is_string($value['auditDelivery'])
        )
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }

    private function sourceShape(mixed $value): void
    {
        if (!is_array($value))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        $this->sameKeys($value, ['owner', 'component', 'runtime', 'buildId']);
        /** @var array<string,mixed> $value */
        if (
            !is_string($value['owner'])
            || !is_string($value['component'])
            || !is_string($value['runtime'])
            || !is_string($value['buildId'])
        )
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }

    private function correlationShape(mixed $value): void
    {
        if (!is_array($value) || count($value) > self::MAX_CORRELATION_MEMBERS)
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        if ($value !== [] && array_is_list($value))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        /** @var array<string,mixed> $value */
        $allowed = ['operationId', 'correlationId', 'causationId', 'attempt', 'traceId', 'spanId', 'sampled'];
        foreach ($value as $name => $member) {
            if (!is_string($name) || !in_array($name, $allowed, strict: true))
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            if (in_array($name, ['operationId', 'correlationId', 'causationId', 'traceId', 'spanId'], strict: true)) {
                if ($member !== null && !is_string($member))
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                continue;
            }
            if ($name === 'sampled') {
                if ($member !== null && !is_bool($member))
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                continue;
            }
            if ($member === null)
                continue;
            if (!is_array($member))
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            $this->sameKeys($member, ['number', 'kind']);
            /** @var array<string,mixed> $member */
            if (!is_int($member['number']) || !is_string($member['kind']))
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
    }

    private function evidenceShape(mixed $value): void
    {
        if (!is_array($value) || count($value) > self::MAX_EVIDENCE_MEMBERS)
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        if (array_is_list($value) || !array_key_exists('version', $value))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        /** @var array<string,mixed> $value */
        if (
            !is_int($value['version'])
            || $value['version'] < -AuditJcsCodec::MAX_SAFE_INTEGER
            || $value['version'] > AuditJcsCodec::MAX_SAFE_INTEGER
        )
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        foreach ($value as $name => $member) {
            if (!is_string($name))
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            if (!is_string($member) && !is_int($member) && !is_bool($member) && $member !== null)
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
    }

    private function timeShape(mixed $value): void
    {
        if (!is_array($value))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        $this->sameKeys($value, ['occurredAt', 'recordedAt']);
        /** @var array<string,mixed> $value */
        if (!is_string($value['occurredAt']) || !is_string($value['recordedAt']))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }

    /** @param array<string,mixed> $value */
    private function validateUtf8(array $value): void
    {
        foreach ($this->strings($value) as $string)
            AuditValidationException::assertUtf8((string) $string);
    }

    /** @param array<string,mixed> $value */
    private function validateControls(array $value): void
    {
        foreach ($this->strings($value) as $string)
            if (preg_match('/[\p{Cc}\p{Cf}]/u', (string) $string) === 1)
                throw new AuditValidationException(AuditValidationFailure::ControlInvalid);
    }

    /** @param array<string,mixed> $value */
    private function validateNfc(array $value): void
    {
        foreach ($this->strings($value) as $string) {
            if (!class_exists('Normalizer'))
                continue;
            $normalized = \Normalizer::normalize((string) $string, \Normalizer::FORM_C);
            if ($normalized === false || $normalized !== $string)
                throw new AuditValidationException(AuditValidationFailure::NfcInvalid);
        }
    }

    /** @param array<string,mixed> $value */
    private function validateBounds(array $value, bool $logical, ?AuditEventDefinition $definition): void
    {
        foreach ($this->strings($value) as $string)
            if (strlen((string) $string) < 1 || strlen((string) $string) > 256)
                throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);

        /** @var array<string,mixed> $event */
        $event = $value['event'];
        $this->bound((string) $event['type'], 1, 128);
        $this->bound((string) $event['catalogHash'], 64, 64);
        /** @var array<string,mixed> $action */
        $action = $value['action'];
        $this->bound((string) $action['type'], 1, 128);
        /** @var array<string,mixed> $resource */
        $resource = $value['resource'];
        $this->bound((string) $resource['type'], 1, 128);
        /** @var array<string,mixed> $outcome */
        $outcome = $value['outcome'];
        $this->bound((string) $outcome['code'], 1, 128);
        /** @var array<string,mixed> $policy */
        $policy = $value['policy'];
        $this->bound((string) $policy['reasonCode'], 1, 128);
        /** @var array<string,mixed> $correlation */
        $correlation = $value['correlation'];
        if (isset($correlation['traceId']) && is_string($correlation['traceId']))
            $this->bound($correlation['traceId'], 32, 32);
        if (isset($correlation['spanId']) && is_string($correlation['spanId']))
            $this->bound($correlation['spanId'], 16, 16);
        if ($logical) {
            /** @var string $recordId */
            $recordId = $value['recordId'];
            $this->bound($recordId, 1, 256);
        }
        /** @var array<string,mixed> $evidence */
        $evidence = $value['evidence'];
        $this->validateEvidenceBounds($evidence, $definition);
    }

    private function bound(string $value, int $minimum, int $maximum): void
    {
        $length = strlen($value);
        if ($length < $minimum || $length > $maximum)
            throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
    }

    /** @param array<string,mixed> $evidence */
    private function validateEvidenceBounds(array $evidence, ?AuditEventDefinition $definition): void
    {
        if ($definition === null)
            return;
        /** @var array<string,AuditEvidenceFieldDefinition> $declared */
        $declared = [];
        foreach ($definition->evidenceFields as $field)
            $declared[$field->name] = $field;
        foreach ($evidence as $name => $actual) {
            if ($name === 'version' || $actual === null)
                continue;
            /** @var AuditEvidenceFieldDefinition $field */
            $field = $declared[$name];
            if (is_string($actual) && $field->maxUtf8Bytes !== null)
                $this->bound($actual, 1, $field->maxUtf8Bytes);
        }
    }

    /** @param array<string,mixed> $value */
    private function validateGrammar(array $value, bool $logical, ?AuditEventDefinition $definition): void
    {
        if ($value['schemaVersion'] !== 1 || $value['kind'] !== 'audit')
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        /** @var array<string,mixed> $event */
        $event = $value['event'];
        if (
            preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', (string) $event['type']) !== 1
            || preg_match('/^[a-f0-9]{64}$/', (string) $event['catalogHash']) !== 1
        )
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        /** @var array<string,mixed> $action */
        $action = $value['action'];
        if (preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', (string) $action['type']) !== 1)
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        /** @var array<string,mixed> $resource */
        $resource = $value['resource'];
        if (preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', (string) $resource['type']) !== 1)
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        /** @var array<string,mixed> $outcome */
        $outcome = $value['outcome'];
        /** @var array<string,mixed> $policy */
        $policy = $value['policy'];
        if (
            preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', (string) $outcome['code']) !== 1
            || preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', (string) $policy['reasonCode']) !== 1
        )
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        /** @var array<string,mixed> $correlation */
        $correlation = $value['correlation'];
        if (
            isset($correlation['traceId'])
            && $correlation['traceId'] !== null
            && preg_match('/^[a-f0-9]{32}$/', (string) $correlation['traceId']) !== 1
            || isset($correlation['spanId'])
            && $correlation['spanId'] !== null
            && preg_match('/^[a-f0-9]{16}$/', (string) $correlation['spanId']) !== 1
        )
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        if (!$logical)
            AuditRecordTime::assertTimestamp((string) $value['occurredAt']);
        else {
            /** @var array<string,mixed> $time */
            $time = $value['time'];
            AuditRecordTime::assertTimestamp((string) $time['occurredAt']);
            AuditRecordTime::assertTimestamp((string) $time['recordedAt']);
        }
        /** @var array<string,mixed> $evidence */
        $evidence = $value['evidence'];
        $this->validateEvidenceGrammar($evidence, $definition);
    }

    /** @param array<string,mixed> $evidence */
    private function validateEvidenceGrammar(array $evidence, ?AuditEventDefinition $definition): void
    {
        if ($definition === null)
            return;
        /** @var array<string,AuditEvidenceFieldDefinition> $declared */
        $declared = [];
        foreach ($definition->evidenceFields as $field)
            $declared[$field->name] = $field;
        foreach ($evidence as $name => $actual) {
            if ($name === 'version' || $actual === null)
                continue;
            /** @var AuditEvidenceFieldDefinition $field */
            $field = $declared[$name];
            if (
                $field->classification === AuditEvidenceClassification::SafeCode
                && preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', (string) $actual) !== 1
            )
                throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
            if (
                $field->classification === AuditEvidenceClassification::OpaqueId
                && preg_match('/^oid:v1:[a-f0-9]{64}$/', (string) $actual) !== 1
            )
                throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
    }

    /** @param array<string,mixed> $value */
    private function validateSemantics(
        array $value,
        bool $logical,
        ?AuditEventCatalog $catalog,
        ?AuditEventDefinition $definition,
    ): AuditEventDefinition {
        /** @var array<string,mixed> $event */
        $event = $value['event'];
        /** @var array<string,mixed> $action */
        $action = $value['action'];
        /** @var array<string,mixed> $resource */
        $resource = $value['resource'];
        /** @var array<string,mixed> $outcome */
        $outcome = $value['outcome'];
        /** @var array<string,mixed> $policy */
        $policy = $value['policy'];
        /** @var array<string,mixed> $source */
        $source = $value['source'];
        /** @var array<string,mixed> $correlation */
        $correlation = $value['correlation'];
        $this->positiveInteger((int) $event['version']);
        $this->positiveInteger((int) $policy['version']);
        $this->enumValues($value);
        if ($logical) {
            /** @var string $recordId */
            $recordId = $value['recordId'];
            AuditValidationException::assertSafeString($recordId);
            /** @var array<string,mixed> $time */
            $time = $value['time'];
            $this->assertTimeWindow((string) $time['occurredAt'], (string) $time['recordedAt']);
        }

        if ($catalog === null || $definition === null)
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        /** @var array<string,mixed> $evidence */
        $evidence = $value['evidence'];
        if ($event['category'] !== $definition->category->value)
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        if (!in_array((string) $action['type'], $definition->actionTypes, strict: true))
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        $tuple = [
            'actionType' => $action['type'],
            'phase' => $action['phase'],
            'resourceType' => (string) $resource['type'],
            'outcomeStatus' => $outcome['status'],
            'outcomeCode' => $outcome['code'],
            'policyDecision' => $policy['decision'],
            'policyReasonCode' => $policy['reasonCode'],
        ];
        $matched = false;
        foreach ($definition->semanticTuples as $candidate) {
            if ($candidate->toArray() !== $tuple)
                continue;
            $matched = true;
            break;
        }
        if (
            !$matched
            || $source['owner'] !== $catalog->owner->value
            || $policy['auditDelivery'] !== $definition->delivery->value
        )
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);

        $codec = new AuditEventCatalogCodec();
        $codec->validateEvidence($definition, $evidence);
        return $definition;
    }

    private function positiveInteger(int $value): void
    {
        if ($value < 1 || $value > AuditJcsCodec::MAX_SAFE_INTEGER)
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
    }

    /** @param array<string,mixed> $value */
    private function enumValues(array $value): void
    {
        /** @var array<string,mixed> $event */
        $event = $value['event'];
        /** @var array<string,mixed> $action */
        $action = $value['action'];
        /** @var array<string,mixed> $outcome */
        $outcome = $value['outcome'];
        /** @var array<string,mixed> $policy */
        $policy = $value['policy'];
        /** @var array<string,mixed> $source */
        $source = $value['source'];
        /** @var array<string,mixed> $correlation */
        $correlation = $value['correlation'];
        if (
            AuditEventCategory::tryFrom((string) $event['category']) === null
            || AuditActionPhase::tryFrom((string) $action['phase']) === null
            || AuditOutcomeStatus::tryFrom((string) $outcome['status']) === null
            || AuditPolicyDecision::tryFrom((string) $policy['decision']) === null
            || AuditDelivery::tryFrom((string) $policy['auditDelivery']) === null
            || AuditSourceOwner::tryFrom((string) $source['owner']) === null
            || \BlackOps\Core\Audit\AuditSourceRuntime::tryFrom((string) $source['runtime']) === null
        )
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        if (array_key_exists('attempt', $correlation) && $correlation['attempt'] !== null) {
            /** @var array<string,mixed> $attempt */
            $attempt = $correlation['attempt'];
            $this->positiveInteger((int) $attempt['number']);
            if (AuditAttemptKind::tryFrom((string) $attempt['kind']) === null)
                throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
        if (
            (int) $event['version'] > AuditJcsCodec::MAX_SAFE_INTEGER
            || (int) $policy['version'] > AuditJcsCodec::MAX_SAFE_INTEGER
        )
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
    }

    private function assertTimeWindow(string $occurredAt, string $recordedAt): void
    {
        $occurred = $this->timestamp($occurredAt);
        $recorded = $this->timestamp($recordedAt);
        if (
            $occurred->format('Y-m-d\\TH:i:s.u\\Z') !== $occurredAt
            || $recorded->format('Y-m-d\\TH:i:s.u\\Z') !== $recordedAt
        )
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        if ($occurred > $recorded->modify('+300 seconds'))
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
    }

    private function timestamp(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s.u\\Z', $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || is_array($errors) && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        return $date;
    }

    private function definition(
        AuditEventCatalog $catalog,
        string $type,
        int $version,
        int $evidenceVersion,
    ): ?AuditEventDefinition {
        foreach ($catalog->events as $definition)
            if (
                $definition->type === $type
                && $definition->version === $version
                && $definition->evidenceVersion === $evidenceVersion
            )
                return $definition;
        return null;
    }

    /**
     * @param array<string,mixed> $value
     * @return array<string,mixed>
     */
    private function snapshot(array $value, ?AuditEventDefinition $definition): array
    {
        /** @var array<string,mixed> $event */
        $event = $value['event'];
        /** @var array<string,mixed> $action */
        $action = $value['action'];
        /** @var array<string,mixed> $actor */
        $actor = $value['actor'];
        /** @var array<string,mixed> $resource */
        $resource = $value['resource'];
        /** @var array<string,mixed> $outcome */
        $outcome = $value['outcome'];
        /** @var array<string,mixed> $policy */
        $policy = $value['policy'];
        /** @var array<string,mixed> $tenant */
        $tenant = $value['tenant'];
        /** @var array<string,mixed> $time */
        $time = $value['time'];
        /** @var array<string,mixed> $source */
        $source = $value['source'];
        /** @var array<string,mixed> $correlation */
        $correlation = $value['correlation'];
        /** @var array<string,mixed> $evidence */
        $evidence = $value['evidence'];

        return [
            'schemaVersion' => $value['schemaVersion'],
            'kind' => $value['kind'],
            'recordId' => $value['recordId'],
            'event' => [
                'type' => $event['type'],
                'version' => $event['version'],
                'category' => $event['category'],
                'catalogHash' => $event['catalogHash'],
            ],
            'action' => [
                'id' => $action['id'],
                'type' => $action['type'],
                'phase' => $action['phase'],
            ],
            'actor' => [
                'principal' => $this->referenceSnapshot($actor['principal']),
                'execution' => $this->referenceSnapshot($actor['execution']),
            ],
            'resource' => [
                'type' => $resource['type'],
                'id' => $resource['id'],
            ],
            'outcome' => [
                'status' => $outcome['status'],
                'code' => $outcome['code'],
            ],
            'policy' => [
                'id' => $policy['id'],
                'version' => $policy['version'],
                'decision' => $policy['decision'],
                'reasonCode' => $policy['reasonCode'],
                'auditDelivery' => $policy['auditDelivery'],
            ],
            'tenant' => $this->referenceSnapshot($tenant),
            'time' => [
                'occurredAt' => $time['occurredAt'],
                'recordedAt' => $time['recordedAt'],
            ],
            'source' => [
                'owner' => $source['owner'],
                'component' => $source['component'],
                'runtime' => $source['runtime'],
                'buildId' => $source['buildId'],
            ],
            'correlation' => $this->correlationSnapshot($correlation),
            'evidence' => $this->evidenceSnapshot($evidence, $definition),
        ];
    }

    /** @return array{type:string,id:string}|null */
    private function referenceSnapshot(mixed $value): ?array
    {
        if ($value === null)
            return null;
        /** @var array{type:string,id:string} $value */
        return ['type' => $value['type'], 'id' => $value['id']];
    }

    /** @param array<string,mixed> $value @return array<string,mixed> */
    private function correlationSnapshot(array $value): array
    {
        $snapshot = [];
        foreach (['operationId', 'correlationId', 'causationId', 'attempt', 'traceId', 'spanId', 'sampled'] as $name) {
            if (!array_key_exists($name, $value))
                continue;
            if ($name !== 'attempt') {
                $snapshot[$name] = $value[$name];
                continue;
            }
            if ($value['attempt'] === null) {
                $snapshot[$name] = null;
                continue;
            }
            /** @var array<string,mixed> $attempt */
            $attempt = $value['attempt'];
            $snapshot[$name] = ['number' => $attempt['number'], 'kind' => $attempt['kind']];
        }
        return $snapshot;
    }

    /**
     * @param array<string,mixed> $value
     * @return array<string,mixed>
     */
    private function evidenceSnapshot(array $value, ?AuditEventDefinition $definition): array
    {
        $snapshot = ['version' => $value['version']];
        if ($definition === null)
            return $snapshot;
        foreach ($definition->evidenceFields as $field) {
            if (!array_key_exists($field->name, $value))
                continue;
            $snapshot[$field->name] = $value[$field->name];
        }
        return $snapshot;
    }

    /**
     * @param array $value
     * @return list<string>
     */
    private function strings(array $value): array
    {
        $strings = [];
        foreach ($value as $key => $member) {
            if (is_string($key))
                $strings[] = $key;
            if (is_string($member))
                $strings[] = $member;
            elseif (is_array($member))
                $strings = [...$strings, ...$this->strings($member)];
        }
        return $strings;
    }

    /** @param array $value @param list<string> $keys */
    private function sameKeys(array $value, array $keys): void
    {
        if (count($value) !== count($keys))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        if (array_keys($value) !== $keys)
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }
}
