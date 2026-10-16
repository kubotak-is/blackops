<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authority\History;

use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\AuditJcsObject;
use InvalidArgumentException;

/** Encodes the closed, flat JSON groups carried by history records. */
final class AuditHistoryGroupCodec
{
    public function encode(#[\SensitiveParameter] AuditHistoryNativeGroup $group): string
    {
        $scalars = $group->scalars();

        try {
            $bytes = new AuditJcsCodec()->canonicalJson($scalars);
        } catch (\Throwable) {
            throw new InvalidArgumentException('History native group cannot be encoded.');
        }
        if (strlen($bytes) > 16_384) {
            throw new InvalidArgumentException('History native group exceeds its byte bound.');
        }

        return $bytes;
    }

    public function decode(
        AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag $tag,
        #[\SensitiveParameter]
        string $bytes,
    ): AuditHistoryNativeGroup {
        $isNative = match ($tag->value) {
            1,
            3,
            5,
            16,
            17,
            18,
            19,
            21,
            23,
            24,
            25,
            26,
            28,
            31,
            33,
            35,
            48,
            49,
            51,
            52,
            53,
            55,
            64,
            65,
            67,
            68,
            72,
            73,
            75,
            76,
            79,
                => true,
            default => false,
        };
        if (!$isNative) {
            throw new InvalidArgumentException('History tag does not carry a native group.');
        }

        $length = strlen($bytes);
        if ($bytes === '' || $length > 16_384 || str_contains($bytes, "\r") || str_contains($bytes, "\n")) {
            throw new InvalidArgumentException('History native group bytes are invalid.');
        }

        $withoutStrings = preg_replace(pattern: '/"(?:\\\\.|[^"\\\\])*"/s', replacement: '', subject: $bytes);
        if (
            $bytes[0] !== '{'
            || $bytes[$length - 1] !== '}'
            || $withoutStrings === null
            || preg_match('/\A\{[^{}\[\]"]*\}\z/D', $withoutStrings) !== 1
        ) {
            throw new InvalidArgumentException('History native group bytes are invalid.');
        }

        try {
            $codec = new AuditJcsCodec();
            /** @var AuditJcsObject|array<array-key,mixed>|bool|int|string|null $decoded */
            $decoded = $codec->decode($bytes);
            /** @var AuditJcsObject|array<array-key,mixed>|bool|int|string|null $native */
            $native = $codec->toNative($decoded);
            if (!is_array($native) || array_is_list($native)) {
                throw new InvalidArgumentException();
            }
            $canonical = $codec->canonicalJson($native);
        } catch (\Throwable) {
            throw new InvalidArgumentException('History native group bytes are invalid.');
        }
        if ($bytes !== $canonical) {
            throw new InvalidArgumentException('History native group is not canonical.');
        }

        return AuditHistoryNativeGroup::fromDecoded($tag, $native);
    }
}
