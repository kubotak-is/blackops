<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\AuditLengthPrefix;

final class AuditAnchorCanonical
{
    /** @param array<string,mixed> $intent */
    public static function stateHash(#[\SensitiveParameter] array $intent): string
    {
        unset($intent['stateHash']);
        return self::digest('blackops.anchor.state.v1', self::jcs($intent));
    }

    public static function idempotencyKeyHash(
        #[\SensitiveParameter]
        string $intentId,
        #[\SensitiveParameter]
        string $rawKey,
    ): string {
        return self::digestParts('blackops.anchor.idempotency.v1', [$intentId, $rawKey]);
    }

    /** @param array<string,mixed> $body */
    public static function receiptPreimageHash(#[\SensitiveParameter] array $body): string
    {
        return self::typedDigest('blackops.audit.anchor.intent.receipt-preimage.v1', self::jcs($body));
    }

    /** @param array<string,mixed> $body */
    public static function receiptBytesHash(#[\SensitiveParameter] array $body): string
    {
        return self::typedDigest('blackops.audit.anchor.intent.receipt-bytes.v1', self::jcs($body));
    }

    /** @param array<string,mixed> $value */
    public static function jcs(#[\SensitiveParameter] array $value): string
    {
        try {
            $bytes = new AuditJcsCodec()->canonicalJson($value);
            if ($bytes === '') {
                throw new \RuntimeException();
            }
            return $bytes;
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
    }

    /** @param list<string> $parts */
    private static function digestParts(
        #[\SensitiveParameter]
        string $domain,
        #[\SensitiveParameter]
        array $parts,
    ): string {
        $preimage = AuditLengthPrefix::encode($domain);
        foreach ($parts as $part) {
            $preimage .= AuditLengthPrefix::encode($part);
        }
        return hash('sha256', $preimage);
    }

    private static function digest(#[\SensitiveParameter] string $domain, #[\SensitiveParameter] string $bytes): string
    {
        return self::digestParts($domain, [$bytes]);
    }

    private static function typedDigest(
        #[\SensitiveParameter]
        string $domain,
        #[\SensitiveParameter]
        string $bytes,
    ): string {
        $preimage = 's:' . strlen($domain) . ':' . $domain . 'b:' . strlen($bytes) . ':' . $bytes;
        return hash('sha256', $preimage);
    }
}
