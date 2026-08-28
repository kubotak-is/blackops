<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:block-statement
 * @mago-expect lint:no-isset
 * @mago-expect lint:no-nested-ternary
 * @mago-expect lint:no-else-clause
 */
final readonly class AuditEventCatalog
{
    /** @param list<AuditEventDefinition> $events */
    /** @mago-expect lint:halstead */
    public function __construct(
        public string $catalogId,
        public int $catalogVersion,
        public AuditSourceOwner $owner,
        /** @var list<AuditEventDefinition> */
        public array $events,
        public string $catalogHash,
    ) {
        /** @var list<AuditEventDefinition> $events */
        if (!array_is_list($events))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        $selectors = [];
        foreach ($events as $event) {
            if (!$event instanceof AuditEventDefinition) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
        }
        $strings = [$catalogId, $catalogHash];
        foreach ($events as $event) {
            $strings[] = $event->type;
            $strings[] = $event->category->value;
            $strings[] = $event->delivery->value;
            $strings = [...$strings, ...$event->actionTypes];
            foreach ($event->semanticTuples as $tuple)
                $strings = [...$strings, ...array_values($tuple->toArray())];
            foreach ($event->evidenceFields as $field) {
                $strings[] = $field->name;
                $strings[] = $field->type->value;
                if ($field->classification !== null)
                    $strings[] = $field->classification->value;
            }
        }
        foreach ($strings as $string)
            AuditValidationException::assertUtf8($string);
        foreach ($strings as $string)
            if (preg_match('/[\p{Cc}\p{Cf}]/u', $string) === 1)
                throw new AuditValidationException(AuditValidationFailure::ControlInvalid);
        foreach ($strings as $string) {
            if (!class_exists('Normalizer')) {
                continue;
            }
            $normalized = \Normalizer::normalize($string, \Normalizer::FORM_C);
            if ($normalized === false || $normalized !== $string) {
                throw new AuditValidationException(AuditValidationFailure::NfcInvalid);
            }
        }
        if (strlen($catalogId) < 1 || strlen($catalogId) > 128 || strlen($catalogHash) !== 64)
            throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
        foreach ($events as $event) {
            if (strlen($event->type) < 1 || strlen($event->type) > 128)
                throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
            foreach ($event->actionTypes as $actionType)
                if (strlen($actionType) < 1 || strlen($actionType) > 128)
                    throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
            foreach ($event->semanticTuples as $tuple)
                foreach (array_values($tuple->toArray()) as $string)
                    if (strlen($string) < 1 || strlen($string) > 128)
                        throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
            foreach ($event->evidenceFields as $field)
                if (strlen($field->name) < 1 || strlen($field->name) > 128)
                    throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
        }
        if (preg_match('/^[a-f0-9]{64}$/', $catalogHash) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
        AuditValidationException::assertGrammarString($catalogId, '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', 128);
        foreach ($events as $event) {
            $selector = $event->type . "\x1f" . $event->version . "\x1f" . $event->evidenceVersion;
            if (isset($selectors[$selector])) {
                throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
            }
            $selectors[$selector] = true;
        }
        if ($this->sortedEvents($events) !== $events) {
            throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
        }
        if (
            $catalogVersion < 1
            || $catalogVersion > AuditValidationException::MAX_SAFE_INTEGER
            || count($events) < 1
            || count($events) > 256
        )
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        $this->assertApplicationNamespaces($owner, $events);
    }

    /** @return array{schemaVersion:int,kind:string,catalogId:string,catalogVersion:int,owner:string,events:list<array<string,mixed>>,catalogHash:string} */
    public function toArray(): array
    {
        /** @var list<array<string,mixed>> $events */
        $events = array_values(array_map(
            static fn(AuditEventDefinition $event): array => $event->toArray(),
            $this->events,
        ));
        return [
            'schemaVersion' => 1,
            'kind' => 'audit_event_catalog',
            'catalogId' => $this->catalogId,
            'catalogVersion' => $this->catalogVersion,
            'owner' => $this->owner->value,
            'events' => $events,
            'catalogHash' => $this->catalogHash,
        ];
    }

    /** @return array{schemaVersion:int,kind:string,catalogId:string,catalogVersion:int,owner:string,events:list<array<string,mixed>>} */
    public function unsignedArray(): array
    {
        $array = $this->toArray();
        unset($array['catalogHash']);
        return $array;
    }

    /** @param list<AuditEventDefinition> $events */
    private function assertApplicationNamespaces(AuditSourceOwner $owner, array $events): void
    {
        if ($owner !== AuditSourceOwner::Application)
            return;
        foreach ($events as $event)
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
                if (str_starts_with($event->type, $prefix))
                    throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
    }

    /** @param list<AuditEventDefinition> $events */
    private function sortedEvents(array $events): array
    {
        $sorted = $events;
        usort($sorted, static function (AuditEventDefinition $left, AuditEventDefinition $right): int {
            $type = self::utf16Compare($left->type, $right->type);
            if ($type !== 0)
                return $type;
            $version = $left->version <=> $right->version;
            return $version !== 0 ? $version : $left->evidenceVersion <=> $right->evidenceVersion;
        });
        return $sorted;
    }

    private static function utf16Compare(string $left, string $right): int
    {
        $leftUnits = self::utf16Units($left);
        $rightUnits = self::utf16Units($right);
        foreach ($leftUnits as $index => $unit) {
            if (!isset($rightUnits[$index]))
                return 1;
            if ($unit !== $rightUnits[$index])
                return $unit <=> $rightUnits[$index];
        }
        return count($leftUnits) <=> count($rightUnits);
    }

    /** @return list<int> */
    private static function utf16Units(string $value): array
    {
        AuditValidationException::assertUtf8($value);
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
            } else {
                $units[] = $codepoint;
            }
        }
        return $units;
    }
}
