<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit;

use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;
use JsonException;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:block-statement
 * @mago-expect lint:literal-named-argument
 * @mago-expect lint:no-isset
 * @mago-expect lint:no-else-clause
 * @mago-expect lint:halstead
 */
class AuditJcsCodec
{
    public const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    private bool $deferUtf8Validation = false;
    private bool $invalidUtf8Observed = false;
    private bool $unpairedSurrogateObserved = false;

    public function deferUtf8Validation(): void
    {
        $this->deferUtf8Validation = true;
    }

    public function invalidUtf8Observed(): bool
    {
        return $this->invalidUtf8Observed;
    }

    public function unpairedSurrogateObserved(): bool
    {
        return $this->unpairedSurrogateObserved;
    }

    public function canonicalJson(mixed $value): string
    {
        $this->assertEncodableShape($value);
        /** @var list<string> $strings */
        $strings = [];
        $this->collectStrings($value, $strings);
        foreach ($strings as $string)
            AuditValidationException::assertUtf8($string);
        foreach ($strings as $string)
            if (preg_match('/[\p{Cc}\p{Cf}]/u', $string) === 1)
                throw new AuditValidationException(AuditValidationFailure::ControlInvalid);
        foreach ($strings as $string) {
            if (!class_exists('Normalizer'))
                continue;
            $normalized = \Normalizer::normalize($string, \Normalizer::FORM_C);
            if ($normalized === false || $normalized !== $string)
                throw new AuditValidationException(AuditValidationFailure::NfcInvalid);
        }
        return $this->encode($value);
    }

    public function toNative(mixed $value): mixed
    {
        if (!$value instanceof AuditJcsObject) {
            if (!is_array($value)) {
                return $value;
            }
            foreach ($value as $key => $member) {
                $value[$key] = $this->toNative($member);
            }
            return $value;
        }
        if ($value->members === []) {
            return $value;
        }
        $result = [];
        foreach ($value->members as $pair) {
            if (
                !is_array($pair)
                || !array_key_exists('key', $pair)
                || !array_key_exists('value', $pair)
                || !is_string($pair['key'])
            ) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
            // PHP converts canonical decimal object keys such as "0" to
            // integer array keys. Keep that object represented explicitly so
            // a numeric-key object can never be mistaken for a JSON list.
            if (preg_match('/^(?:0|[1-9][0-9]*)$/', $pair['key']) === 1) {
                return $value;
            }
            $result[$pair['key']] = $this->toNative($pair['value']);
        }
        return $result;
    }

    private function assertEncodableShape(mixed $value): void
    {
        if ($value instanceof AuditJcsObject) {
            if (!array_is_list($value->members))
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            /** @var list<string> $keys */
            $keys = [];
            foreach ($value->members as $pair) {
                if (
                    !is_array($pair)
                    || !array_key_exists('key', $pair)
                    || !array_key_exists('value', $pair)
                    || count($pair) !== 2
                    || !is_string($pair['key'])
                )
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                if (in_array($pair['key'], $keys, true))
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                $keys[] = $pair['key'];
                $this->assertEncodableShape($pair['value']);
            }
            return;
        }
        if (is_array($value)) {
            if (!array_is_list($value)) {
                foreach (array_keys($value) as $key)
                    if (!is_string($key))
                        throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
            foreach ($value as $member)
                $this->assertEncodableShape($member);
            return;
        }
        if (is_int($value)) {
            if ($value < -self::MAX_SAFE_INTEGER || $value > self::MAX_SAFE_INTEGER)
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            return;
        }
        if (is_string($value) || is_bool($value) || $value === null)
            return;
        throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }

    /** @param list<string> $strings */
    private function collectStrings(mixed $value, array &$strings): void
    {
        if ($value instanceof AuditJcsObject) {
            foreach ($value->members as $pair) {
                if (
                    !is_array($pair)
                    || !array_key_exists('key', $pair)
                    || !array_key_exists('value', $pair)
                    || !is_string($pair['key'])
                )
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                /** @var array{key:string,value:mixed} $pair */
                $strings[] = $pair['key'];
                $this->collectStrings($pair['value'], $strings);
            }
            return;
        }
        if (is_array($value)) {
            foreach ($value as $key => $member) {
                if (is_string($key))
                    $strings[] = $key;
                $this->collectStrings($member, $strings);
            }
            return;
        }
        if (is_string($value))
            $strings[] = $value;
    }

    /** @return mixed */
    public function decode(string $bytes): mixed
    {
        try {
            return $this->decodeValue($bytes);
        } finally {
            $this->deferUtf8Validation = false;
        }
    }

    /** @return mixed */
    private function decodeValue(string $bytes): mixed
    {
        $this->invalidUtf8Observed = false;
        $this->unpairedSurrogateObserved = false;
        $index = 0;
        try {
            $this->scanSyntaxValue($bytes, $index, strlen($bytes));
            $this->skipWhitespace($bytes, $index, strlen($bytes));
            if ($index !== strlen($bytes)) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
        } catch (AuditValidationException) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        $this->invalidUtf8Observed = preg_match('//u', $bytes) !== 1;
        $this->unpairedSurrogateObserved = $this->hasUnpairedSurrogate($bytes);
        if ($this->invalidUtf8Observed && !$this->deferUtf8Validation) {
            throw new AuditValidationException(AuditValidationFailure::Utf8Invalid);
        }
        $decodeBytes = $this->unpairedSurrogateObserved ? $this->replaceUnpairedSurrogates($bytes) : $bytes;
        $index = 0;
        try {
            $value = $this->parseDecodedValue($decodeBytes, $index, strlen($decodeBytes));
            $this->skipWhitespace($decodeBytes, $index, strlen($decodeBytes));
            if ($index !== strlen($decodeBytes)) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
            if (!$this->invalidUtf8Observed && $this->hasDuplicateDecodedKeys($value)) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
            if ($this->unpairedSurrogateObserved && !$this->deferUtf8Validation) {
                throw new AuditValidationException(AuditValidationFailure::Utf8Invalid);
            }
            return $value;
        } catch (JsonException) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
    }

    private function parseDecodedValue(string $bytes, int &$index, int $length): mixed
    {
        $this->skipWhitespace($bytes, $index, $length);
        $character = $bytes[$index] ?? '';
        if ($character === '{') {
            ++$index;
            $pairs = [];
            $keyValidity = [];
            $this->skipWhitespace($bytes, $index, $length);
            if (($bytes[$index] ?? '') === '}') {
                ++$index;
                return new AuditJcsObject([], []);
            }
            while (true) {
                $keyValid = true;
                $key = $this->decodeStringToken($bytes, $index, $length, $keyValid);
                $this->skipWhitespace($bytes, $index, $length);
                if (($bytes[$index] ?? '') !== ':') {
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                }
                ++$index;
                $pairs[] = ['key' => $key, 'value' => $this->parseDecodedValue($bytes, $index, $length)];
                $keyValidity[] = $keyValid === true;
                $this->skipWhitespace($bytes, $index, $length);
                $next = $bytes[$index] ?? '';
                if ($next === '}') {
                    ++$index;
                    return new AuditJcsObject($pairs, $keyValidity);
                }
                if ($next !== ',') {
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                }
                ++$index;
                $this->skipWhitespace($bytes, $index, $length);
            }
        }
        if ($character === '[') {
            ++$index;
            $items = [];
            $this->skipWhitespace($bytes, $index, $length);
            if (($bytes[$index] ?? '') === ']') {
                ++$index;
                return $items;
            }
            while (true) {
                $items[] = $this->parseDecodedValue($bytes, $index, $length);
                $this->skipWhitespace($bytes, $index, $length);
                $next = $bytes[$index] ?? '';
                if ($next === ']') {
                    ++$index;
                    return $items;
                }
                if ($next !== ',') {
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                }
                ++$index;
                $this->skipWhitespace($bytes, $index, $length);
            }
        }
        if ($character === '"') {
            return $this->decodeStringToken($bytes, $index, $length);
        }
        $remaining = substr($bytes, $index);
        if (preg_match('/^(true|false|null|-?(?:0|[1-9][0-9]*))(?=$|[\x20\t\r\n,\]\}])/A', $remaining, $match) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        $index += strlen($match[0]);
        return match ($match[0]) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => (int) $match[0],
        };
    }

    private function decodeStringToken(string $bytes, int &$index, int $length, ?bool &$validText = null): string
    {
        $raw = $this->scanRawString($bytes, $index, $length);
        $validText = preg_match('//u', $raw) === 1 && !$this->tokenHasUnpairedSurrogate($raw);
        $flags = JSON_THROW_ON_ERROR | ($this->deferUtf8Validation ? JSON_INVALID_UTF8_SUBSTITUTE : 0);
        try {
            $decoded = json_decode($raw, associative: true, depth: 512, flags: $flags);
        } catch (JsonException) {
            $decoded = $this->replaceUnpairedSurrogates($raw);
            $decoded = json_decode($decoded, associative: true, depth: 512, flags: $flags);
        }
        if (!is_string($decoded)) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        return $decoded;
    }

    private function replaceUnpairedSurrogates(string $bytes): string
    {
        $result = '';
        $length = strlen($bytes);
        $index = 0;
        $inString = false;
        while ($index < $length) {
            $character = $bytes[$index];
            if (!$inString && $character === '"') {
                $inString = !$inString;
                $result .= $character;
                ++$index;
                continue;
            }
            if ($inString && $character === '"') {
                $inString = false;
                $result .= $character;
                ++$index;
                continue;
            }
            if (!$inString || $character !== '\\') {
                $result .= $character;
                ++$index;
                continue;
            }
            $escaped = $bytes[$index + 1] ?? '';
            if ($escaped !== 'u' || ($index + 5) >= $length) {
                $result .= substr($bytes, $index, 2);
                $index += 2;
                continue;
            }
            $hex = substr($bytes, $index + 2, 4);
            if (preg_match('/^[0-9a-fA-F]{4}$/', $hex) !== 1) {
                $result .= substr($bytes, $index, 2);
                $index += 2;
                continue;
            }
            $code = hexdec($hex);
            $nextHex = substr($bytes, $index + 8, 4);
            $hasPair =
                $code >= 0xd800
                && $code <= 0xdbff
                && ($bytes[$index + 6] ?? '') === '\\'
                && ($bytes[$index + 7] ?? '') === 'u'
                && preg_match('/^[0-9a-fA-F]{4}$/', $nextHex) === 1
                && hexdec($nextHex) >= 0xdc00
                && hexdec($nextHex) <= 0xdfff;
            if ($code >= 0xd800 && $code <= 0xdbff && !$hasPair || $code >= 0xdc00 && $code <= 0xdfff) {
                $result .= '\\uFFFD';
                $index += 6;
                continue;
            }
            $result .= substr($bytes, $index, $hasPair ? 12 : 6);
            $index += $hasPair ? 12 : 6;
        }
        return $result;
    }

    private function hasUnpairedSurrogate(string $bytes): bool
    {
        $length = strlen($bytes);
        $index = 0;
        while ($index < $length) {
            if ($bytes[$index] !== '"') {
                ++$index;
                continue;
            }
            $start = $index;
            $token = $this->scanRawString($bytes, $index, $length);
            if ($this->tokenHasUnpairedSurrogate($token)) {
                return true;
            }
            if ($index <= $start) {
                ++$index;
            }
        }
        return false;
    }

    private function tokenHasUnpairedSurrogate(#[\SensitiveParameter] string $token): bool
    {
        $escapes = [];
        $length = strlen($token);
        $index = 1;
        while ($index < ($length - 1)) {
            if ($token[$index] !== '\\') {
                ++$index;
                continue;
            }
            if (($token[$index + 1] ?? '') === 'u' && ($index + 5) < $length) {
                $hex = substr($token, $index + 2, 4);
                if (preg_match('/^[0-9a-fA-F]{4}$/', $hex) === 1) {
                    $escapes[] = [$index, hexdec($hex)];
                    $index += 6;
                    continue;
                }
            }
            $index += 2;
        }
        foreach ($escapes as $position => [$offset, $code]) {
            $next = $escapes[$position + 1] ?? null;
            $previous = $escapes[$position - 1] ?? null;
            $nextIsLow = $next !== null && $next[0] === ($offset + 6) && $next[1] >= 0xdc00 && $next[1] <= 0xdfff;
            $previousIsHigh =
                $previous !== null
                && $offset === ($previous[0] + 6)
                && $previous[1] >= 0xd800
                && $previous[1] <= 0xdbff;
            if (
                $code >= 0xd800 && $code <= 0xdbff && !$nextIsLow
                || $code >= 0xdc00 && $code <= 0xdfff && !$previousIsHigh
            ) {
                return true;
            }
        }
        return false;
    }

    private function encode(mixed $value): string
    {
        if ($value instanceof AuditJcsObject) {
            return $this->encodeObject($value->members);
        }
        if (is_array($value)) {
            if (!array_is_list($value)) {
                /** @var array<string,mixed> $value */
                return $this->encodeObject($value);
            }
            $parts = [];
            foreach ($value as $item) {
                $parts[] = $this->encode($item);
            }
            return '[' . implode(',', $parts) . ']';
        }
        if (is_string($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (!is_string($encoded)) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
            return str_replace(['\\u2028', '\\u2029'], ["\u{2028}", "\u{2029}"], $encoded);
        }
        if (is_int($value)) {
            if ($value < -self::MAX_SAFE_INTEGER || $value > self::MAX_SAFE_INTEGER) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }

    /** @param array<array-key,mixed> $members */
    private function encodeObject(array $members): string
    {
        if (array_is_list($members) && $this->isObjectPairs($members)) {
            /** @var list<array{key:string,value:mixed}> $pairs */
            $pairs = $members;
            usort($pairs, fn(array $a, array $b): int => $this->utf16Compare($a['key'], $b['key']));
            $parts = [];
            foreach ($pairs as $pair) {
                $parts[] = $this->encode($pair['key']) . ':' . $this->encode($pair['value']);
            }
            return '{' . implode(',', $parts) . '}';
        }
        /** @var list<string> $keys */
        $keys = array_keys($members);
        foreach ($keys as $key) {
            if (!is_string($key)) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
        }
        usort($keys, $this->utf16Compare(...));
        $parts = [];
        foreach ($keys as $key) {
            $parts[] = $this->encode($key) . ':' . $this->encode($members[$key]);
        }
        return '{' . implode(',', $parts) . '}';
    }

    /** @param array<array-key,mixed> $members */
    private function isObjectPairs(array $members): bool
    {
        $keys = [];
        foreach ($members as $pair) {
            if (!is_array($pair)) {
                return false;
            }
            /** @var array<array-key,mixed> $pair */
            if (!array_key_exists('key', $pair) || !array_key_exists('value', $pair) || !is_string($pair['key'])) {
                return false;
            }
            if (in_array($pair['key'], $keys, true)) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
            $keys[] = $pair['key'];
        }
        return $members !== [];
    }

    private function utf16Compare(string $a, string $b): int
    {
        $aUnits = $this->utf16Units($a);
        $bUnits = $this->utf16Units($b);
        $length = min(count($aUnits), count($bUnits));
        for ($i = 0; $i < $length; ++$i) {
            if ($aUnits[$i] !== $bUnits[$i]) {
                return $aUnits[$i] <=> $bUnits[$i];
            }
        }
        return count($aUnits) <=> count($bUnits);
    }

    /** @return list<int> */
    private function utf16Units(string $value): array
    {
        AuditValidationException::assertUtf8($value);
        $units = [];
        preg_match_all('/./us', $value, $matches);
        foreach ($matches[0] as $character) {
            $codepoint = $this->codepoint($character);
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

    private function codepoint(string $character): int
    {
        $bytes = unpack('C*', $character);
        $first = $bytes[1];
        if ($first < 0x80)
            return $first;
        if ($first < 0xe0)
            return (($first & 0x1f) << 6) | ($bytes[2] & 0x3f);
        if ($first < 0xf0)
            return (($first & 0x0f) << 12) | (($bytes[2] & 0x3f) << 6) | ($bytes[3] & 0x3f);
        return (($first & 0x07) << 18) | (($bytes[2] & 0x3f) << 12) | (($bytes[3] & 0x3f) << 6) | ($bytes[4] & 0x3f);
    }

    private function hasDuplicateObjectKey(string $bytes): bool
    {
        $index = 0;
        $length = strlen($bytes);
        try {
            $this->scanValue($bytes, $index, $length);
            $this->skipWhitespace($bytes, $index, $length);
            return $index !== $length;
        } catch (AuditValidationException) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
    }

    private function hasDuplicateDecodedKeys(mixed $value): bool
    {
        if ($value instanceof AuditJcsObject) {
            $keys = [];
            foreach ($value->members as $index => $pair) {
                if (
                    !is_array($pair)
                    || !array_key_exists('key', $pair)
                    || !array_key_exists('value', $pair)
                    || !is_string($pair['key'])
                ) {
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                }
                /** @var array{key:string,value:mixed} $pair */
                $keyValid = $value->keyValidity[$index] ?? true;
                if ($keyValid && in_array($pair['key'], $keys, true)) {
                    return true;
                }
                if ($keyValid) {
                    $keys[] = $pair['key'];
                }
                if ($this->hasDuplicateDecodedKeys($pair['value'])) {
                    return true;
                }
            }
            return false;
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->hasDuplicateDecodedKeys($item)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function scanValue(string $bytes, int &$index, int $length): void
    {
        $this->skipWhitespace($bytes, $index, $length);
        $char = $bytes[$index] ?? '';
        if ($char === '{') {
            ++$index;
            $keys = [];
            $this->skipWhitespace($bytes, $index, $length);
            if (($bytes[$index] ?? '') === '}') {
                ++$index;
                return;
            }
            while (true) {
                $key = $this->scanString($bytes, $index, $length);
                if (isset($keys[$key]))
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                $keys[$key] = true;
                $this->skipWhitespace($bytes, $index, $length);
                if (($bytes[$index] ?? '') !== ':')
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                ++$index;
                $this->scanValue($bytes, $index, $length);
                $this->skipWhitespace($bytes, $index, $length);
                $next = $bytes[$index] ?? '';
                if ($next === '}') {
                    ++$index;
                    return;
                }
                if ($next !== ',')
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                ++$index;
                $this->skipWhitespace($bytes, $index, $length);
            }
        }
        if ($char === '[') {
            ++$index;
            $this->skipWhitespace($bytes, $index, $length);
            if (($bytes[$index] ?? '') === ']') {
                ++$index;
                return;
            }
            while (true) {
                $this->scanValue($bytes, $index, $length);
                $this->skipWhitespace($bytes, $index, $length);
                $next = $bytes[$index] ?? '';
                if ($next === ']') {
                    ++$index;
                    return;
                }
                if ($next !== ',')
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                ++$index;
                $this->skipWhitespace($bytes, $index, $length);
            }
        }
        if ($char === '"') {
            $this->scanString($bytes, $index, $length);
            return;
        }
        if (
            preg_match('/(?:true|false|null|-?(?:0|[1-9][0-9]*))(?=$|[\x20\t\r\n,\]\}])/A', $bytes, $match, 0, $index)
            === 1
        ) {
            if ($match[0] !== 'true' && $match[0] !== 'false' && $match[0] !== 'null') {
                $this->assertSafeIntegerLiteral($match[0]);
            }
            $index += strlen($match[0]);
            return;
        }
        throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }

    private function scanSyntaxValue(string $bytes, int &$index, int $length): void
    {
        $this->skipWhitespace($bytes, $index, $length);
        $char = $bytes[$index] ?? '';
        if ($char === '{') {
            ++$index;
            $keys = [];
            $this->skipWhitespace($bytes, $index, $length);
            if (($bytes[$index] ?? '') === '}') {
                ++$index;
                return;
            }
            while (true) {
                $rawKey = $this->scanRawString($bytes, $index, $length);
                if (isset($keys[$rawKey])) {
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                }
                $keys[$rawKey] = true;
                $this->skipWhitespace($bytes, $index, $length);
                if (($bytes[$index] ?? '') !== ':') {
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                }
                ++$index;
                $this->scanSyntaxValue($bytes, $index, $length);
                $this->skipWhitespace($bytes, $index, $length);
                $next = $bytes[$index] ?? '';
                if ($next === '}') {
                    ++$index;
                    return;
                }
                if ($next !== ',') {
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                }
                ++$index;
                $this->skipWhitespace($bytes, $index, $length);
            }
        }
        if ($char === '[') {
            ++$index;
            $this->skipWhitespace($bytes, $index, $length);
            if (($bytes[$index] ?? '') === ']') {
                ++$index;
                return;
            }
            while (true) {
                $this->scanSyntaxValue($bytes, $index, $length);
                $this->skipWhitespace($bytes, $index, $length);
                $next = $bytes[$index] ?? '';
                if ($next === ']') {
                    ++$index;
                    return;
                }
                if ($next !== ',') {
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                }
                ++$index;
                $this->skipWhitespace($bytes, $index, $length);
            }
        }
        if ($char === '"') {
            $this->scanRawString($bytes, $index, $length);
            return;
        }
        if (
            preg_match('/(?:true|false|null|-?(?:0|[1-9][0-9]*))(?=$|[\x20\t\r\n,\]\}])/A', $bytes, $match, 0, $index)
            === 1
        ) {
            if ($match[0] !== 'true' && $match[0] !== 'false' && $match[0] !== 'null') {
                $this->assertSafeIntegerLiteral($match[0]);
            }
            $index += strlen($match[0]);
            return;
        }
        throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }

    private function scanRawString(string $bytes, int &$index, int $length): string
    {
        $start = $index;
        if (($bytes[$index] ?? '') !== '"') {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        ++$index;
        while ($index < $length) {
            $ord = ord($bytes[$index]);
            if ($ord < 0x20) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
            if ($bytes[$index] === '\\') {
                ++$index;
                $escaped = $bytes[$index] ?? '';
                if ($escaped === 'u') {
                    if (
                        ($index + 4) >= $length
                        || preg_match('/^[0-9a-fA-F]{4}$/', substr($bytes, $index + 1, 4)) !== 1
                    ) {
                        throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                    }
                    $index += 5;
                    continue;
                }
                if (!str_contains('"\\/bfnrt', $escaped)) {
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                }
                ++$index;
                continue;
            }
            if ($bytes[$index] === '"') {
                ++$index;
                return substr($bytes, $start, $index - $start);
            }
            ++$index;
        }
        throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }

    private function scanString(string $bytes, int &$index, int $length): string
    {
        $start = $index;
        if (($bytes[$index] ?? '') !== '"')
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        ++$index;
        while ($index < $length) {
            $ord = ord($bytes[$index]);
            if ($ord < 0x20)
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            if ($bytes[$index] === '\\') {
                $index += 2;
                continue;
            }
            if ($bytes[$index] === '"') {
                ++$index;
                $raw = substr($bytes, $start, $index - $start);
                try {
                    $decoded = json_decode(
                        json: $raw,
                        associative: true,
                        depth: 512,
                        flags: JSON_THROW_ON_ERROR | ($this->deferUtf8Validation ? JSON_INVALID_UTF8_SUBSTITUTE : 0),
                    );
                } catch (JsonException) {
                    return $this->replaceUnpairedSurrogates($raw);
                }
                if (!is_string($decoded))
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                return $decoded;
            }
            ++$index;
        }
        throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }

    private function skipWhitespace(string $bytes, int &$index, int $length): void
    {
        while ($index < $length && str_contains(" \t\r\n", $bytes[$index]))
            ++$index;
    }

    private function assertSafeIntegerLiteral(string $value): void
    {
        if ($value === '-0') {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        $negative = str_starts_with($value, '-');
        $digits = ltrim($value, '-');
        $limit = $negative ? '9007199254740991' : '9007199254740991';
        if (strlen($digits) > strlen($limit) || strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
    }
}
