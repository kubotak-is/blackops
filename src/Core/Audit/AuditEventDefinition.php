<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

use BlackOps\Core\Attribute\PublicApi;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:block-statement
 * @mago-expect lint:no-isset
 * @mago-expect lint:no-nested-ternary
 * @mago-expect lint:no-else-clause
 */
#[PublicApi]
final readonly class AuditEventDefinition
{
    /**
     * @param list<string> $actionTypes
     * @param list<AuditSemanticTuple> $semanticTuples
     * @param list<AuditEvidenceFieldDefinition> $evidenceFields
     * @mago-expect lint:halstead
     */
    public function __construct(
        public string $type,
        public int $version,
        public int $evidenceVersion,
        public AuditEventCategory $category,
        public array $actionTypes,
        public array $semanticTuples,
        public array $evidenceFields,
        public int $maxEvidenceBytes,
        public AuditDelivery $delivery,
    ) {
        if (!array_is_list($actionTypes) || !array_is_list($semanticTuples) || !array_is_list($evidenceFields)) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        foreach ($actionTypes as $actionType) {
            if (!is_string($actionType)) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
        }
        foreach ($semanticTuples as $tuple) {
            if (!$tuple instanceof AuditSemanticTuple) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
        }
        foreach ($evidenceFields as $field) {
            if (!$field instanceof AuditEvidenceFieldDefinition) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
        }
        $strings = [$type, ...$actionTypes];
        foreach ($semanticTuples as $tuple)
            $strings = [...$strings, ...array_values($tuple->toArray())];
        foreach ($evidenceFields as $field) {
            $strings[] = $field->name;
            $strings[] = $field->type->value;
            if ($field->classification !== null)
                $strings[] = $field->classification->value;
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
        foreach ($strings as $string)
            if (strlen($string) < 1 || strlen($string) > 128)
                throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
        if (preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $type) !== 1)
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        foreach ($actionTypes as $actionType)
            if (preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $actionType) !== 1)
                throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        $fieldNames = array_map(
            static fn(AuditEvidenceFieldDefinition $field): string => $field->name,
            $evidenceFields,
        );
        foreach ($semanticTuples as $tuple) {
            $values = array_values($tuple->toArray());
            if (
                preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $values[0]) !== 1
                || preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $values[2]) !== 1
                || preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $values[4]) !== 1
                || preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $values[6]) !== 1
            )
                throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
        foreach ($fieldNames as $fieldName)
            if (preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $fieldName) !== 1)
                throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        if (count(array_unique($actionTypes, SORT_STRING)) !== count($actionTypes)) {
            throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
        }
        if ($this->sortedStrings($actionTypes) !== $actionTypes) {
            throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
        }
        if (count(array_unique($fieldNames, SORT_STRING)) !== count($fieldNames)) {
            throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
        }
        $tupleKeys = array_map(static fn(AuditSemanticTuple $tuple): string => implode(
            "\x1f",
            array_values($tuple->toArray()),
        ), $semanticTuples);
        if (count(array_unique($tupleKeys, SORT_STRING)) !== count($tupleKeys)) {
            throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
        }
        $tupleActions = array_values(array_unique(
            array_map(static fn(AuditSemanticTuple $tuple): string => $tuple->actionType, $semanticTuples),
            SORT_STRING,
        ));
        if (
            $this->sortedStrings($tupleActions) !== $tupleActions
            || $this->sortedTuples($semanticTuples) !== $semanticTuples
        ) {
            throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
        }
        if ($this->sortedStrings($fieldNames) !== $fieldNames) {
            throw new AuditValidationException(AuditValidationFailure::OrderOrUniquenessInvalid);
        }
        if ($tupleActions !== $actionTypes) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
        if (
            count($actionTypes) < 1
            || count($actionTypes) > 32
            || count($semanticTuples) < 1
            || count($semanticTuples) > 32
            || count($evidenceFields) > 32
            || $version < 1
            || $version > AuditValidationException::MAX_SAFE_INTEGER
            || $evidenceVersion < 1
            || $evidenceVersion > AuditValidationException::MAX_SAFE_INTEGER
            || $maxEvidenceBytes < 1
            || $maxEvidenceBytes > 8192
        ) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
    }

    /** @return array{type:string,version:int,evidenceVersion:int,category:string,actionTypes:list<string>,semanticTuples:list<array<string,mixed>>,evidenceFields:list<array<string,mixed>>,maxEvidenceBytes:int,delivery:string} */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'version' => $this->version,
            'evidenceVersion' => $this->evidenceVersion,
            'category' => $this->category->value,
            'actionTypes' => $this->actionTypes,
            'semanticTuples' => array_values(array_map(
                static fn(AuditSemanticTuple $tuple): array => $tuple->toArray(),
                $this->semanticTuples,
            )),
            'evidenceFields' => array_values(array_map(
                static fn(AuditEvidenceFieldDefinition $field): array => $field->toArray(),
                $this->evidenceFields,
            )),
            'maxEvidenceBytes' => $this->maxEvidenceBytes,
            'delivery' => $this->delivery->value,
        ];
    }

    /** @param list<string> $values */
    private function sortedStrings(array $values): array
    {
        $sorted = $values;
        usort($sorted, self::utf16Compare(...));
        return $sorted;
    }

    /** @param list<AuditSemanticTuple> $tuples */
    private function sortedTuples(array $tuples): array
    {
        $sorted = $tuples;
        usort($sorted, static function (AuditSemanticTuple $left, AuditSemanticTuple $right): int {
            $l = array_values($left->toArray());
            $r = array_values($right->toArray());
            foreach ($l as $i => $value) {
                $comparison = is_string($value) ? self::utf16Compare($value, $r[$i]) : $value <=> $r[$i];
                if ($comparison !== 0)
                    return $comparison;
            }
            return 0;
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
            } else
                $units[] = $codepoint;
        }
        return $units;
    }
}
