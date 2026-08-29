<?php

declare(strict_types=1);

namespace BlackOps\Internal\StorageProtection;

use BlackOps\Internal\Telemetry\TelemetryMetrics;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\StorageProtection\StorageKeyProvider;
use BlackOps\StorageProtection\StorageProtectionException;
use Throwable;

/**
 * The parser deliberately keeps all envelope checks in one fail-closed path
 * so malformed bytes can never be reinterpreted as plaintext.
 *
 */
final readonly class BopdEnvelopeCodec
{
    private const MAGIC = 'BOPD';
    private const VERSION = 1;
    private const ALGORITHM = 1;
    private const int TAG_LENGTH = 16;
    private const int NONCE_LENGTH = 24;
    private const int MAX_LENGTH = 0xFFFF_FFFF;
    private const int MAX_ENVELOPE_LENGTH = 0x1_0000_00B3;

    private BopdBinaryEnvelopeCodec $binary;

    public function __construct(
        private StorageKeyProvider $keys,
        private CanonicalAssociatedData $aad = new CanonicalAssociatedData(),
        private NonceSource $nonces = new RandomNonceSource(),
        private ?TelemetryMetrics $metrics = null,
    ) {
        $this->binary = new BopdBinaryEnvelopeCodec();
    }

    public function withMetrics(?TelemetryMetrics $metrics): self
    {
        return new self($this->keys, $this->aad, $this->nonces, $metrics);
    }

    public function encrypt(
        #[\SensitiveParameter]
        string $plaintext,
        #[\SensitiveParameter]
        StorageProtectionContext $context,
    ): string {
        try {
            $key = $this->keys->activeKey($context->tenant, $context->purpose);
            $this->assertKey($key);
            $nonce = $this->nonces->generate();
            if (strlen($nonce) !== self::NONCE_LENGTH) {
                throw new \RuntimeException('Invalid nonce.');
            }
            return $this->binary->seal(
                $plaintext,
                $this->aad->encode($context, $key->id()),
                $key,
                $nonce,
                self::MAX_LENGTH,
                self::MAX_ENVELOPE_LENGTH,
            );
        } catch (Throwable) {
            $this->metrics?->protectionFailure($context->purpose->value, 'encryption_failed');
            throw StorageProtectionException::failure();
        }
    }

    public function decrypt(
        #[\SensitiveParameter]
        string $envelope,
        #[\SensitiveParameter]
        StorageProtectionContext $context,
    ): string {
        try {
            $parsed = $this->binary->parse($envelope, self::MAX_ENVELOPE_LENGTH);
            $key = $this->keys->key($parsed->keyId(), $context->tenant, $context->purpose);
            $this->assertKey($key);
            if (!hash_equals($parsed->keyId(), $key->id())) {
                throw new \RuntimeException('Storage provider returned the wrong key.');
            }
            return $this->binary->open($parsed, $this->aad->encode($context, $parsed->keyId()), $key, self::MAX_LENGTH);
        } catch (Throwable) {
            $this->metrics?->protectionFailure($context->purpose->value, 'decryption_failed');
            throw StorageProtectionException::failure();
        }
    }

    /** @return array{keyId: string, digest: string} */
    public function header(string $envelope): array
    {
        try {
            $parsed = $this->binary->parse($envelope, self::MAX_ENVELOPE_LENGTH);
            return [
                'keyId' => $parsed->keyId(),
                'digest' => hash('sha256', $envelope),
            ];
        } catch (Throwable) {
            $this->metrics?->protectionFailure('unknown', 'header_parse_failed');
            throw StorageProtectionException::failure();
        }
    }

    public function keyId(string $envelope): string
    {
        return $this->header($envelope)['keyId'];
    }

    public function keyIdFromHeader(string $prefix): string
    {
        try {
            return $this->binary->keyIdFromHeader($prefix);
        } catch (Throwable) {
            $this->metrics?->protectionFailure('unknown', 'header_parse_failed');
            throw StorageProtectionException::failure();
        }
    }

    private function assertKey(mixed $key): void
    {
        if (!$key instanceof StorageKey) {
            throw new \RuntimeException('Invalid storage key.');
        }
    }
}
