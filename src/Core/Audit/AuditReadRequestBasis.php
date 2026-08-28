<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class AuditReadRequestBasis
{
    public function __construct(
        public AuditReadPurpose $purpose,
        public ?AuditTypedReference $actor,
        public AuditTypedReference $execution,
        public ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        public string $requestedAt,
        #[\SensitiveParameter]
        public string $nonce,
    ) {
        $references = [$actor, $execution, $tenant];
        /** @var list<string> $strings */
        $strings = self::textValues($references, $requestedAt, $nonce);
        self::assertTextStages($strings);
        self::assertBounds($references, $nonce);
        self::assertTime($requestedAt);
    }

    /** @param list<?AuditTypedReference> $references @return list<string> */
    private static function textValues(array $references, string $requestedAt, string $nonce): array
    {
        /** @var list<string> $strings */
        $strings = [$requestedAt, $nonce];
        foreach ($references as $reference) {
            if ($reference === null) {
                continue;
            }
            $strings[] = $reference->type;
            $strings[] = $reference->id;
        }
        return $strings;
    }

    /** @param list<string> $strings */
    private static function assertTextStages(array $strings): void
    {
        foreach ($strings as $value) {
            AuditValidationException::assertUtf8($value);
        }
        foreach ($strings as $value) {
            if (preg_match('/[\p{Cc}\p{Cf}]/u', $value) === 1) {
                throw new AuditValidationException(AuditValidationFailure::ControlInvalid);
            }
        }
        foreach ($strings as $value) {
            if (!class_exists('Normalizer')) {
                continue;
            }
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if ($normalized === false || $normalized !== $value) {
                throw new AuditValidationException(AuditValidationFailure::NfcInvalid);
            }
        }
    }

    /** @param list<?AuditTypedReference> $references */
    private static function assertBounds(array $references, string $nonce): void
    {
        foreach ($references as $reference) {
            if ($reference === null) {
                continue;
            }
            foreach ([$reference->type, $reference->id] as $value) {
                if (strlen($value) < 1 || strlen($value) > 256) {
                    throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
                }
            }
        }
        if (strlen($nonce) < 1 || strlen($nonce) > 128) {
            throw new AuditValidationException(AuditValidationFailure::ByteBoundInvalid);
        }
    }

    /** @return array{purpose:string,actor:array{type:string,id:string}|null,execution:array{type:string,id:string},tenant:array{type:string,id:string}|null,requestedAt:string,nonce:string} */
    public function toArray(): array
    {
        return [
            'purpose' => $this->purpose->value,
            'actor' => $this->actor?->toArray(),
            'execution' => $this->execution->toArray(),
            'tenant' => $this->tenant?->toArray(),
            'requestedAt' => $this->requestedAt,
            'nonce' => $this->nonce,
        ];
    }

    public function requestRef(): string
    {
        // This fixed six-member wire is the Core-owned JCS boundary. The
        // Internal codec delegates to this method, so there is one request
        // reference calculation for every consumer.
        $json = self::jcsString($this->toArray());
        $domain = 'blackops.audit.read.request-ref.v1';
        $preimage = pack('N', strlen($domain)) . $domain . pack('N', strlen($json)) . $json;
        return 'oid:v1:' . hash('sha256', $preimage);
    }

    /** @param array<string,mixed> $members */
    private static function jcsString(array $members): string
    {
        $parts = [];
        foreach (['actor', 'execution', 'nonce', 'purpose', 'requestedAt', 'tenant'] as $key) {
            $parts[] = self::quote($key) . ':' . self::jcsValue($members[$key]);
        }
        return '{' . implode(',', $parts) . '}';
    }

    private static function jcsValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_string($value)) {
            return self::quote($value);
        }
        if (is_array($value)) {
            /** @var array{type:string,id:string} $value */
            return (
                '{'
                . self::quote('id')
                . ':'
                . self::quote($value['id'])
                . ','
                . self::quote('type')
                . ':'
                . self::quote($value['type'])
                . '}'
            );
        }
        throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }

    private static function quote(string $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (!is_string($encoded)) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }
        return str_replace(['\\u2028', '\\u2029'], ["\u{2028}", "\u{2029}"], $encoded);
    }

    private static function assertTime(string $value): void
    {
        AuditValidationException::assertSafeString($value, 27, 27);
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $value) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
    }
}
