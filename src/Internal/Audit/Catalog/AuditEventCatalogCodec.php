<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Catalog;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditEvidenceClassification;
use BlackOps\Core\Audit\AuditEvidenceFieldDefinition;
use BlackOps\Core\Audit\AuditEvidenceType;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditSemanticTuple;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;
use BlackOps\Internal\Audit\AuditJcsCodec;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:block-statement
 * @mago-expect lint:literal-named-argument
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:no-isset
 * @mago-expect lint:no-nested-ternary
 * @mago-expect lint:no-else-clause
 * @mago-expect lint:prefer-early-continue
 */
final class AuditEventCatalogCodec
{
    public const int RAW_MAX_BYTES = 1_048_658;
    public const int COMPLETE_MAX_BYTES = 1_048_657;
    public const int UNSIGNED_MAX_BYTES = 1_048_576;

    public function __construct(
        private readonly AuditJcsCodec $jcs = new AuditJcsCodec(),
    ) {}

    public function encode(AuditEventCatalog $catalog): string
    {
        $this->assertApplicationNamespaces($catalog->owner, $catalog->events);
        $unsigned = $this->jcs->canonicalJson($catalog->unsignedArray());
        if (strlen($unsigned) > self::UNSIGNED_MAX_BYTES)
            throw new AuditValidationException(AuditValidationFailure::JcsBoundInvalid);
        $complete = $this->jcs->canonicalJson($catalog->toArray());
        if (strlen($complete) > self::COMPLETE_MAX_BYTES || (strlen($complete) + 1) > self::RAW_MAX_BYTES)
            throw new AuditValidationException(AuditValidationFailure::JcsBoundInvalid);
        $hash = $this->hash($unsigned);
        if ($catalog->catalogHash !== $hash)
            throw new AuditValidationException(AuditValidationFailure::HashMismatch);
        return $complete . "\n";
    }

    /** @param list<AuditEventDefinition> $events */
    public function catalog(
        string $catalogId,
        int $catalogVersion,
        AuditSourceOwner $owner,
        array $events,
    ): AuditEventCatalog {
        $candidate = new AuditEventCatalog(
            catalogId: $catalogId,
            catalogVersion: $catalogVersion,
            owner: $owner,
            events: $events,
            catalogHash: str_repeat('0', 64),
        );
        // Complete all typed shape, text, grammar, ordering, and semantic
        // checks before measuring the canonical bodies. A reserved
        // application namespace is a semantic defect and must not be hidden
        // by a later JCS size failure.
        $this->assertApplicationNamespaces($owner, $events);
        $unsigned = $this->jcs->canonicalJson($candidate->unsignedArray());
        if (strlen($unsigned) > self::UNSIGNED_MAX_BYTES)
            throw new AuditValidationException(AuditValidationFailure::JcsBoundInvalid);
        $catalog = new AuditEventCatalog($catalogId, $catalogVersion, $owner, $events, $this->hash($unsigned));
        if (strlen($this->jcs->canonicalJson($catalog->toArray())) > self::COMPLETE_MAX_BYTES)
            throw new AuditValidationException(AuditValidationFailure::JcsBoundInvalid);
        return $catalog;
    }

    public function decode(string $bytes): AuditEventCatalog
    {
        if (strlen($bytes) > self::RAW_MAX_BYTES)
            throw new AuditValidationException(AuditValidationFailure::JcsBoundInvalid);
        $this->jcs->deferUtf8Validation();
        $decoded = $this->jcs->decode($bytes);
        $value = $this->jcs->toNative($decoded);
        if (!is_array($value))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        /** @var array<string,mixed> $value */
        $this->validateShape($value);
        if ($this->jcs->invalidUtf8Observed() || $this->jcs->unpairedSurrogateObserved())
            throw new AuditValidationException(AuditValidationFailure::Utf8Invalid);
        $this->validateUtf8($value);
        $this->validateControls($value);
        $this->validateNfc($value);
        $this->validateBounds($value);
        $this->validateGrammar($value);
        if ($this->jcs->canonicalJson($value) . "\n" !== $bytes)
            throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
        $this->validateOrderAndUniqueness($value);
        /** @var string $catalogId */
        $catalogId = $value['catalogId'];
        /** @var int $catalogVersion */
        $catalogVersion = $value['catalogVersion'];
        /** @var string $ownerValue */
        $ownerValue = $value['owner'];
        /** @var list<array<string,mixed>> $rawCatalogEvents */
        $rawCatalogEvents = $value['events'];
        /** @var string $catalogHash */
        $catalogHash = $value['catalogHash'];
        $owner = AuditSourceOwner::tryFrom($ownerValue);
        if ($owner === null)
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        /** @var list<mixed> $rawEvents */
        $rawEvents = $rawCatalogEvents;
        // Run the semantic pass over the complete decoded tree before
        // hydrating any value objects. This keeps precedence independent of
        // the order in which constructors happen to be reached.
        $this->validateSemantics($value);
        /** @var list<AuditEventDefinition> $events */
        $events = [];
        foreach ($rawEvents as $rawEvent)
            $events[] = $this->event($rawEvent);
        $catalog = new AuditEventCatalog($catalogId, $catalogVersion, $owner, $events, $catalogHash);
        $this->assertApplicationNamespaces($owner, $events);
        $unsigned = $this->jcs->canonicalJson($catalog->unsignedArray());
        if (
            strlen($unsigned) > self::UNSIGNED_MAX_BYTES
            || strlen($this->jcs->canonicalJson($catalog->toArray())) > self::COMPLETE_MAX_BYTES
        )
            throw new AuditValidationException(AuditValidationFailure::JcsBoundInvalid);
        if (!hash_equals($this->hash($unsigned), $catalogHash))
            throw new AuditValidationException(AuditValidationFailure::HashMismatch);
        return $catalog;
    }

    /**
     * @param array<array-key,mixed> $value
     * @return list<array{type:string,version:int,evidenceVersion:int,category:string,actionTypes:list<string>,semanticTuples:list<array{actionType:string,phase:string,resourceType:string,outcomeStatus:string,outcomeCode:string,policyDecision:string,policyReasonCode:string}>,evidenceFields:list<array{name:string,type:string,required:bool,nullable:bool,maxUtf8Bytes:int|null,classification:string|null}>,maxEvidenceBytes:int,delivery:string}>
     */
    private function rawEvents(array $value): array
    {
        /** @var list<array{type:string,version:int,evidenceVersion:int,category:string,actionTypes:list<string>,semanticTuples:list<array{actionType:string,phase:string,resourceType:string,outcomeStatus:string,outcomeCode:string,policyDecision:string,policyReasonCode:string}>,evidenceFields:list<array{name:string,type:string,required:bool,nullable:bool,maxUtf8Bytes:int|null,classification:string|null}>,maxEvidenceBytes:int,delivery:string}> $events */
        $events = $value['events'];
        return array_values($events);
    }

    /** @param array<string,mixed> $value */
    private function validateShape(array $value): void
    {
        if (!$this->sameKeys($value, [
            'schemaVersion',
            'kind',
            'catalogId',
            'catalogVersion',
            'owner',
            'events',
            'catalogHash',
        ]))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        if (
            !is_int($value['schemaVersion'])
            || !is_string($value['kind'])
            || !is_string($value['catalogId'])
            || !is_int($value['catalogVersion'])
            || !is_string($value['owner'])
            || !is_array($value['events'])
            || !is_string($value['catalogHash'])
            || !array_is_list($value['events'])
        )
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        foreach ($value['events'] as $event) {
            if (!is_array($event) || array_is_list($event))
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            /** @var array<string,mixed> $event */
            if (
                !$this->sameKeys($event, [
                    'type',
                    'version',
                    'evidenceVersion',
                    'category',
                    'actionTypes',
                    'semanticTuples',
                    'evidenceFields',
                    'maxEvidenceBytes',
                    'delivery',
                ])
                || !is_string($event['type'])
                || !is_int($event['version'])
                || !is_int($event['evidenceVersion'])
                || !is_string($event['category'])
                || !is_array($event['actionTypes'])
                || !array_is_list($event['actionTypes'])
                || !is_array($event['semanticTuples'])
                || !array_is_list($event['semanticTuples'])
                || !is_array($event['evidenceFields'])
                || !array_is_list($event['evidenceFields'])
                || !is_int($event['maxEvidenceBytes'])
                || !is_string($event['delivery'])
            )
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            foreach ($event['actionTypes'] as $actionType)
                if (!is_string($actionType))
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            foreach ($event['semanticTuples'] as $tuple) {
                if (!is_array($tuple))
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                /** @var array<string,mixed> $tuple */
                if (!$this->sameKeys($tuple, [
                    'actionType',
                    'phase',
                    'resourceType',
                    'outcomeStatus',
                    'outcomeCode',
                    'policyDecision',
                    'policyReasonCode',
                ]))
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                foreach ($tuple as $member)
                    if (!is_string($member))
                        throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
            foreach ($event['evidenceFields'] as $field) {
                if (!is_array($field))
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                /** @var array<string,mixed> $field */
                if (
                    !$this->sameKeys($field, ['name', 'type', 'required', 'nullable', 'maxUtf8Bytes', 'classification'])
                    || !is_string($field['name'])
                    || !is_string($field['type'])
                    || !is_bool($field['required'])
                    || !is_bool($field['nullable'])
                    || $field['maxUtf8Bytes'] !== null && !is_int($field['maxUtf8Bytes'])
                    || $field['classification'] !== null && !is_string($field['classification'])
                )
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
        }
    }

    /** @param array<string,mixed> $value */
    private function validateUtf8(array $value): void
    {
        foreach ($this->strings($value) as $string) {
            if (!is_string($string))
                continue;
            AuditValidationException::assertUtf8($string);
        }
    }

    /** @param array<string,mixed> $value */
    private function validateControls(array $value): void
    {
        foreach ($this->strings($value) as $string)
            if (is_string($string) && preg_match('/[\p{Cc}\p{Cf}]/u', $string) === 1)
                throw new AuditValidationException(AuditValidationFailure::ControlInvalid);
    }

    /** @param array<string,mixed> $value */
    private function validateNfc(array $value): void
    {
        foreach ($this->strings($value) as $string) {
            if (!is_string($string))
                continue;
            $normalized = class_exists('Normalizer') ? \Normalizer::normalize($string, \Normalizer::FORM_C) : $string;
            if ($normalized === false || $normalized !== $string)
                throw new AuditValidationException(AuditValidationFailure::NfcInvalid);
        }
    }

    /** @param array<string,mixed> $value */
    private function validateBounds(array $value): void
    {
        /** @var string $catalogId */
        $catalogId = $value['catalogId'];
        /** @var string $kind */
        $kind = $value['kind'];
        /** @var string $owner */
        $owner = $value['owner'];
        /** @var string $catalogHash */
        $catalogHash = $value['catalogHash'];
        $this->bound($catalogId, 1, 128);
        $this->bound($kind, 1, 64);
        $this->bound($owner, 1, 32);
        $this->bound($catalogHash, 64, 64);
        foreach ($this->rawEvents($value) as $event) {
            $this->bound($event['type'], 1, 128);
            $this->bound($event['category'], 1, 32);
            $this->bound($event['delivery'], 1, 32);
            foreach ($event['actionTypes'] as $actionType)
                $this->bound($actionType, 1, 128);
            foreach ($event['semanticTuples'] as $tuple) {
                $this->bound($tuple['actionType'], 1, 128);
                $this->bound($tuple['phase'], 1, 32);
                $this->bound($tuple['resourceType'], 1, 128);
                $this->bound($tuple['outcomeStatus'], 1, 32);
                $this->bound($tuple['outcomeCode'], 1, 128);
                $this->bound($tuple['policyDecision'], 1, 32);
                $this->bound($tuple['policyReasonCode'], 1, 128);
            }
            foreach ($event['evidenceFields'] as $field) {
                $this->bound($field['name'], 1, 128);
                $this->bound($field['type'], 1, 32);
                if ($field['classification'] !== null)
                    $this->bound($field['classification'], 1, 32);
            }
        }
    }

    private function bound(string $value, int $min, int $max): void
    {
        $length = strlen($value);
        if ($length < $min || $length > $max)
            throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
    }

    /** @param array<string,mixed> $value */
    private function validateGrammar(array $value): void
    {
        /** @var int $schemaVersion */
        $schemaVersion = $value['schemaVersion'];
        /** @var string $kind */
        $kind = $value['kind'];
        /** @var string $catalogHash */
        $catalogHash = $value['catalogHash'];
        /** @var string $catalogId */
        $catalogId = $value['catalogId'];
        if (
            $schemaVersion !== 1
            || $kind !== 'audit_event_catalog'
            || !preg_match('/^[a-f0-9]{64}$/', $catalogHash)
            || preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $catalogId) !== 1
        )
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        $identifier = '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/';
        $safe = '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/';
        foreach ($this->rawEvents($value) as $event) {
            foreach ([$event['type'], ...$event['actionTypes']] as $name)
                if (preg_match($identifier, $name) !== 1)
                    throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
            foreach ($event['semanticTuples'] as $tuple) {
                if (
                    preg_match($identifier, $tuple['actionType']) !== 1
                    || preg_match($identifier, $tuple['resourceType']) !== 1
                    || preg_match($safe, $tuple['outcomeCode']) !== 1
                    || preg_match($safe, $tuple['policyReasonCode']) !== 1
                )
                    throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
            }
            foreach ($event['evidenceFields'] as $field)
                if (preg_match($safe, $field['name']) !== 1)
                    throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
    }

    /** @param array<string,mixed> $value */
    private function validateOrderAndUniqueness(array $value): void
    {
        $events = $this->rawEvents($value);
        $selectors = [];
        foreach ($events as $event) {
            $selector = $event['type'] . "\x1f" . $event['version'] . "\x1f" . $event['evidenceVersion'];
            if (isset($selectors[$selector]))
                throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
            $selectors[$selector] = true;
            if (
                count(array_unique($event['actionTypes'], SORT_STRING)) !== count($event['actionTypes'])
                || $this->sortedStrings($event['actionTypes']) !== $event['actionTypes']
                || $this->sortedTuples($event['semanticTuples']) !== $event['semanticTuples']
                || count(array_unique(array_map(static fn(array $tuple): string => implode(
                    "\x1f",
                    $tuple,
                ), $event['semanticTuples']), SORT_STRING)) !== count($event['semanticTuples'])
            )
                throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
            $names = array_column($event['evidenceFields'], 'name');
            if (
                count(array_unique($names, SORT_STRING)) !== count($names)
                || $this->sortedByteStrings($names) !== $names
            )
                throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
        }
        if ($this->sortedEvents($events) !== $events)
            throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
    }

    /** @param array<string,mixed> $root */
    /** @mago-expect lint:halstead */
    private function validateSemantics(array $root): void
    {
        $events = $this->rawEvents($root);
        /** @var int $catalogVersion */
        $catalogVersion = $root['catalogVersion'];
        if (
            $catalogVersion < 1
            || $catalogVersion > AuditJcsCodec::MAX_SAFE_INTEGER
            || count($events) < 1
            || count($events) > 256
        )
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        foreach ($events as $raw) {
            if (
                $raw['version'] < 1
                || $raw['version'] > AuditJcsCodec::MAX_SAFE_INTEGER
                || $raw['evidenceVersion'] < 1
                || $raw['evidenceVersion'] > AuditJcsCodec::MAX_SAFE_INTEGER
                || count($raw['actionTypes']) < 1
                || count($raw['actionTypes']) > 32
                || count($raw['semanticTuples']) < 1
                || count($raw['semanticTuples']) > 32
                || count($raw['evidenceFields']) > 32
                || $raw['maxEvidenceBytes'] < 1
                || $raw['maxEvidenceBytes'] > 8192
            )
                throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
            if (
                AuditEventCategory::tryFrom($raw['category']) === null
                || AuditDelivery::tryFrom($raw['delivery']) === null
            )
                throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
            foreach ($raw['semanticTuples'] as $tuple)
                if (
                    AuditActionPhase::tryFrom($tuple['phase']) === null
                    || AuditOutcomeStatus::tryFrom($tuple['outcomeStatus']) === null
                    || AuditPolicyDecision::tryFrom($tuple['policyDecision']) === null
                )
                    throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
            foreach ($raw['evidenceFields'] as $field) {
                $type = AuditEvidenceType::tryFrom($field['type']);
                $classification = $field['classification'] === null
                    ? null
                    : AuditEvidenceClassification::tryFrom($field['classification']);
                if (
                    $type === null
                    || $field['classification'] !== null && $classification === null
                    || $type !== AuditEvidenceType::String
                    && ($field['maxUtf8Bytes'] !== null || $classification !== null)
                    || $type === AuditEvidenceType::String
                    && ($field['maxUtf8Bytes'] === null || $classification === null)
                    || $classification === AuditEvidenceClassification::OpaqueId && $field['maxUtf8Bytes'] !== 71
                    || $field['name'] === 'version'
                    || $field['maxUtf8Bytes'] !== null && ($field['maxUtf8Bytes'] < 1 || $field['maxUtf8Bytes'] > 256)
                )
                    throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
            }
            $actions = array_values(array_unique($raw['actionTypes'], SORT_STRING));
            $tupleActions = array_values(array_unique(
                array_map(static fn(array $tuple): string => $tuple['actionType'], $raw['semanticTuples']),
                SORT_STRING,
            ));
            if ($actions !== $tupleActions)
                throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
            if ($root['owner'] === 'application')
                $this->assertApplicationNamespacesRaw($raw['type']);
        }
    }

    private function assertApplicationNamespacesRaw(string $type): void
    {
        foreach ([
            'security.',
            'access.',
            'retention.',
            'storage.',
            'observer.',
            'outbox.',
            'audit.',
            'configuration.',
            'control.',
            'replay.',
            'rotation.',
        ] as $prefix)
            if (str_starts_with($type, $prefix))
                throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
    }

    /** @param array<array-key,mixed> $value @return list<string> */
    private function strings(array $value): array
    {
        /** @var list<string> $strings */
        $strings = [];
        foreach ($value as $member) {
            if (is_string($member))
                $strings[] = $member;
            elseif (is_array($member))
                $strings = [...$strings, ...$this->strings($member)];
        }
        return $strings;
    }

    /** @param list<string> $values */
    private function sortedStrings(array $values): array
    {
        $sorted = $values;
        usort($sorted, $this->utf16Compare(...));
        return $sorted;
    }

    /** @param list<string> $values */
    private function sortedByteStrings(array $values): array
    {
        $sorted = $values;
        usort($sorted, static fn(string $a, string $b): int => strcmp($a, $b));
        return $sorted;
    }

    /** @param list<array{type:string,version:int,evidenceVersion:int,category:string,actionTypes:list<string>,semanticTuples:list<array{actionType:string,phase:string,resourceType:string,outcomeStatus:string,outcomeCode:string,policyDecision:string,policyReasonCode:string}>,evidenceFields:list<array{name:string,type:string,required:bool,nullable:bool,maxUtf8Bytes:int|null,classification:string|null}>,maxEvidenceBytes:int,delivery:string}> $events */
    private function sortedEvents(array $events): array
    {
        $sorted = $events;
        usort($sorted, function (array $a, array $b): int {
            $type = $this->utf16Compare($a['type'], $b['type']);
            if ($type !== 0)
                return $type;
            $version = $a['version'] <=> $b['version'];
            return $version !== 0 ? $version : $a['evidenceVersion'] <=> $b['evidenceVersion'];
        });
        return $sorted;
    }

    /** @param list<array{actionType:string,phase:string,resourceType:string,outcomeStatus:string,outcomeCode:string,policyDecision:string,policyReasonCode:string}> $tuples */
    private function sortedTuples(array $tuples): array
    {
        $sorted = $tuples;
        usort($sorted, function (array $a, array $b): int {
            foreach ([
                'actionType',
                'phase',
                'resourceType',
                'outcomeStatus',
                'outcomeCode',
                'policyDecision',
                'policyReasonCode',
            ] as $key) {
                $comparison = $this->utf16Compare($a[$key], $b[$key]);
                if ($comparison !== 0)
                    return $comparison;
            }
            return 0;
        });
        return $sorted;
    }

    private function utf16Compare(string $a, string $b): int
    {
        $aUnits = $this->utf16Units($a);
        $bUnits = $this->utf16Units($b);
        foreach ($aUnits as $index => $unit) {
            if (!isset($bUnits[$index]))
                return 1;
            if ($unit !== $bUnits[$index])
                return $unit <=> $bUnits[$index];
        }
        return count($aUnits) <=> count($bUnits);
    }

    /** @return list<int> */
    private function utf16Units(string $value): array
    {
        preg_match_all('/./us', $value, $matches);
        $units = [];
        foreach ($matches[0] as $character) {
            $bytes = unpack('C*', $character);
            $first = $bytes[1];
            $codepoint = $first < 0x80
                ? $first
                : (
                    $first < 0xe0
                        ? (($first & 0x1f) << 6) | ($bytes[2] & 0x3f)
                        : (
                            $first < 0xf0
                                ? (($first & 0x0f) << 12) | (($bytes[2] & 0x3f) << 6) | ($bytes[3] & 0x3f)
                                : (($first & 0x07) << 18)
                                | (($bytes[2] & 0x3f) << 12)
                                | (($bytes[3] & 0x3f) << 6)
                                | ($bytes[4] & 0x3f)
                        )
                );
            if ($codepoint > 0xffff) {
                $codepoint -= 0x1_0000;
                $units[] = 0xd800 | ($codepoint >> 10);
                $units[] = 0xdc00 | ($codepoint & 0x3ff);
            } else
                $units[] = $codepoint;
        }
        return $units;
    }

    public function hash(string $unsignedCanonicalBytes): string
    {
        $domain = 'blackops.audit.catalog.v1';
        return hash(
            'sha256',
            's:'
            . strlen($domain)
            . ':'
            . $domain
            . 'b:'
            . strlen($unsignedCanonicalBytes)
            . ':'
            . $unsignedCanonicalBytes,
        );
    }

    public function validateEvidence(AuditEventDefinition $definition, mixed $evidence): void
    {
        $this->validateEvidenceShape($definition, $evidence);
        /** @var array<string,mixed> $evidence */
        /** @var array<string,AuditEvidenceFieldDefinition> $declared */
        $declared = [];
        foreach ($definition->evidenceFields as $field)
            $declared[$field->name] = $field;

        // Evidence follows the same safe ordering as a decoded catalog. The
        // descriptor/version relationship is intentionally delayed until all
        // user-controlled strings have passed their structural checks.
        $this->validateEvidenceText($evidence);
        $this->validateEvidenceBounds($declared, $evidence);
        $this->validateEvidenceGrammar($declared, $evidence);

        foreach ($evidence as $name => $actual) {
            if ($name === 'version')
                continue;
            /** @var AuditEvidenceFieldDefinition $field */
            $field = $declared[$name];
            if ($actual === null) {
                if (!$field->nullable)
                    throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
                continue;
            }
        }
        if ($evidence['version'] !== $definition->evidenceVersion || $evidence['version'] < 1)
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        foreach ($definition->evidenceFields as $field)
            if ($field->required && !array_key_exists($field->name, $evidence))
                throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        if (strlen($this->jcs->canonicalJson($evidence)) > $definition->maxEvidenceBytes)
            throw new AuditValidationException(AuditValidationFailure::JcsBoundInvalid);
    }

    private function validateEvidenceShape(AuditEventDefinition $definition, mixed $evidence): void
    {
        if (
            !is_array($evidence)
            || array_is_list($evidence)
            || !array_key_exists('version', $evidence)
            || !is_int($evidence['version'])
            || $evidence['version'] < -AuditJcsCodec::MAX_SAFE_INTEGER
            || $evidence['version'] > AuditJcsCodec::MAX_SAFE_INTEGER
        )
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        /** @var array<string,mixed> $evidence */
        $declared = [];
        foreach ($definition->evidenceFields as $field)
            $declared[$field->name] = $field;
        foreach ($evidence as $name => $actual) {
            if ($name !== 'version' && (!is_string($name) || !array_key_exists($name, $declared)))
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            if ($name === 'version' || $actual === null)
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

    /** @param array<string,mixed> $evidence */
    private function validateEvidenceText(array $evidence): void
    {
        $strings = [];
        foreach ($evidence as $name => $actual) {
            if (is_string($name))
                $strings[] = $name;
            if (is_string($actual))
                $strings[] = $actual;
        }
        foreach ($strings as $string)
            AuditValidationException::assertUtf8($string);
        foreach ($strings as $string)
            if (preg_match('/[\p{Cc}\p{Cf}]/u', $string) === 1)
                throw new AuditValidationException(AuditValidationFailure::ControlInvalid);
        foreach ($strings as $string) {
            if (class_exists('Normalizer')) {
                $normalized = \Normalizer::normalize($string, \Normalizer::FORM_C);
                if ($normalized === false || $normalized !== $string)
                    throw new AuditValidationException(AuditValidationFailure::NfcInvalid);
            }
        }
    }

    /** @param array<string,AuditEvidenceFieldDefinition> $declared @param array<string,mixed> $evidence */
    private function validateEvidenceBounds(array $declared, array $evidence): void
    {
        foreach ($evidence as $name => $actual) {
            if ($name === 'version' || $actual === null)
                continue;
            /** @var AuditEvidenceFieldDefinition $field */
            $field = $declared[$name];
            if (is_string($actual) && $field->maxUtf8Bytes !== null) {
                $length = strlen($actual);
                if ($length < 1 || $length > $field->maxUtf8Bytes)
                    throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
            }
        }
    }

    /** @param array<string,AuditEvidenceFieldDefinition> $declared @param array<string,mixed> $evidence */
    private function validateEvidenceGrammar(array $declared, array $evidence): void
    {
        foreach ($evidence as $name => $actual) {
            if ($name === 'version' || $actual === null)
                continue;
            /** @var AuditEvidenceFieldDefinition $field */
            $field = $declared[$name];
            if ($field->classification === AuditEvidenceClassification::SafeCode) {
                /** @var string $actual */
                if (preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $actual) !== 1)
                    throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
            }
            if ($field->classification === AuditEvidenceClassification::OpaqueId) {
                /** @var string $actual */
                if (preg_match('/^oid:v1:[a-f0-9]{64}$/', $actual) !== 1)
                    throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
            }
        }
    }

    public function assertTrustedOwner(AuditEventCatalog $catalog, AuditSourceOwner $trustedOwner): void
    {
        if ($catalog->owner !== $trustedOwner)
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        $this->assertApplicationNamespaces($trustedOwner, $catalog->events);
    }

    /** @param list<AuditEventDefinition> $events */
    private function assertApplicationNamespaces(AuditSourceOwner $owner, array $events): void
    {
        if ($owner !== AuditSourceOwner::Application)
            return;
        $reserved = [
            'security.',
            'access.',
            'retention.',
            'storage.',
            'observer.',
            'outbox.',
            'audit.',
            'configuration.',
            'control.',
            'replay.',
            'rotation.',
        ];
        foreach ($events as $event) {
            foreach ($reserved as $prefix) {
                if (str_starts_with($event->type, $prefix))
                    throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
            }
        }
    }

    /** @mago-expect lint:halstead */
    private function event(mixed $value): AuditEventDefinition
    {
        if (!is_array($value)) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        /** @var array<string,mixed> $value */
        if (!$this->sameKeys($value, [
            'type',
            'version',
            'evidenceVersion',
            'category',
            'actionTypes',
            'semanticTuples',
            'evidenceFields',
            'maxEvidenceBytes',
            'delivery',
        ]))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        $category = is_string($value['category'] ?? null) ? AuditEventCategory::tryFrom($value['category']) : null;
        $delivery = is_string($value['delivery'] ?? null) ? AuditDelivery::tryFrom($value['delivery']) : null;
        if (
            $category === null
            || $delivery === null
            || !is_int($value['version'] ?? null)
            || !is_int($value['evidenceVersion'] ?? null)
            || !is_int($value['maxEvidenceBytes'] ?? null)
            || !is_array($value['actionTypes'] ?? null)
            || !is_array($value['semanticTuples'] ?? null)
            || !is_array($value['evidenceFields'] ?? null)
        )
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        if (
            !array_is_list($value['semanticTuples'])
            || !array_is_list($value['actionTypes'])
            || !array_is_list($value['evidenceFields'])
        ) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        /** @var list<mixed> $rawTuples */
        $rawTuples = $value['semanticTuples'];
        /** @var list<mixed> $rawFields */
        $rawFields = $value['evidenceFields'];
        /** @var list<string> $actionTypes */
        $actionTypes = $value['actionTypes'];
        /** @var list<AuditSemanticTuple> $tuples */
        $tuples = [];
        foreach ($rawTuples as $tuple) {
            if (!is_array($tuple)) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
            /** @var array<string,mixed> $tuple */
            if (
                !is_array($tuple)
                || !$this->sameKeys($tuple, [
                    'actionType',
                    'phase',
                    'resourceType',
                    'outcomeStatus',
                    'outcomeCode',
                    'policyDecision',
                    'policyReasonCode',
                ])
            )
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            $phase = is_string($tuple['phase'] ?? null) ? AuditActionPhase::tryFrom($tuple['phase']) : null;
            $outcome = is_string($tuple['outcomeStatus'] ?? null)
                ? AuditOutcomeStatus::tryFrom($tuple['outcomeStatus'])
                : null;
            $policy = is_string($tuple['policyDecision'] ?? null)
                ? AuditPolicyDecision::tryFrom($tuple['policyDecision'])
                : null;
            $actionType = $tuple['actionType'] ?? null;
            $resourceType = $tuple['resourceType'] ?? null;
            $outcomeCode = $tuple['outcomeCode'] ?? null;
            $policyReasonCode = $tuple['policyReasonCode'] ?? null;
            if (
                $phase === null
                || $outcome === null
                || $policy === null
                || !is_string($actionType)
                || !is_string($resourceType)
                || !is_string($outcomeCode)
                || !is_string($policyReasonCode)
            )
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            $tuples[] = new AuditSemanticTuple(
                $actionType,
                $phase,
                $resourceType,
                $outcome,
                $outcomeCode,
                $policy,
                $policyReasonCode,
            );
        }
        /** @var list<AuditEvidenceFieldDefinition> $fields */
        $fields = [];
        foreach ($rawFields as $field) {
            if (!is_array($field)) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
            /** @var array<string,mixed> $field */
            if (
                !is_array($field)
                || !$this->sameKeys($field, ['name', 'type', 'required', 'nullable', 'maxUtf8Bytes', 'classification'])
                || !is_string($field['type'] ?? null)
                || !is_bool($field['required'] ?? null)
                || !is_bool($field['nullable'] ?? null)
            )
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            $type = AuditEvidenceType::tryFrom($field['type']);
            $fieldName = $field['name'] ?? null;
            $maxUtf8Bytes = $field['maxUtf8Bytes'] ?? null;
            $classificationValue = $field['classification'] ?? null;
            $classification = is_string($classificationValue)
                ? AuditEvidenceClassification::tryFrom($classificationValue)
                : null;
            if (
                $type === null
                || !is_string($fieldName)
                || $maxUtf8Bytes !== null && !is_int($maxUtf8Bytes)
                || $classificationValue !== null && $classification === null
            )
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            $required = $field['required'];
            $nullable = $field['nullable'];
            $fields[] = new AuditEvidenceFieldDefinition(
                $fieldName,
                $type,
                $required,
                $nullable,
                $maxUtf8Bytes,
                $classification,
            );
        }
        $eventType = $value['type'] ?? null;
        $eventVersion = $value['version'] ?? null;
        $evidenceVersion = $value['evidenceVersion'] ?? null;
        $maxEvidenceBytes = $value['maxEvidenceBytes'] ?? null;
        if (
            !is_string($eventType)
            || !is_int($eventVersion)
            || !is_int($evidenceVersion)
            || !is_int($maxEvidenceBytes)
        ) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        return new AuditEventDefinition(
            $eventType,
            $eventVersion,
            $evidenceVersion,
            $category,
            $actionTypes,
            $tuples,
            $fields,
            $maxEvidenceBytes,
            $delivery,
        );
    }

    /** @param array<string,mixed> $value @param list<string> $keys */
    private function sameKeys(array $value, array $keys): bool
    {
        return array_diff(array_keys($value), $keys) === [] && array_diff($keys, array_keys($value)) === [];
    }
}
