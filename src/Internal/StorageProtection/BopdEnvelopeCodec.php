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
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final readonly class BopdEnvelopeCodec
{
    private const MAGIC = 'BOPD';
    private const VERSION = 1;
    private const ALGORITHM = 1;
    private const int TAG_LENGTH = 16;
    private const int NONCE_LENGTH = 24;
    private const int MAX_LENGTH = 0xFFFF_FFFF;

    public function __construct(
        private StorageKeyProvider $keys,
        private CanonicalAssociatedData $aad = new CanonicalAssociatedData(),
        private NonceSource $nonces = new RandomNonceSource(),
        private ?TelemetryMetrics $metrics = null,
    ) {}

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
            $combined = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $plaintext,
                $this->aad->encode($context, $key->id()),
                $nonce,
                $key->material(),
            );
            $ciphertext = substr(string: $combined, offset: 0, length: -self::TAG_LENGTH);
            $tag = substr(string: $combined, offset: -self::TAG_LENGTH);
            if (strlen($tag) !== self::TAG_LENGTH) {
                throw new \RuntimeException('Invalid ciphertext.');
            }
            if (strlen($ciphertext) > self::MAX_LENGTH) {
                throw new \RuntimeException('Ciphertext is too large.');
            }

            return (
                self::MAGIC
                . chr(self::VERSION)
                . chr(self::ALGORITHM)
                . pack('n', strlen($key->id()))
                . $key->id()
                . $nonce
                . pack('N', strlen($ciphertext))
                . $ciphertext
                . $tag
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
            $parsed = $this->parse($envelope);
            $key = $this->keys->key($parsed['keyId'], $context->tenant, $context->purpose);
            $this->assertKey($key);
            if ($key->id() !== $parsed['keyId']) {
                throw new \RuntimeException('Storage provider returned the wrong key.');
            }
            $combined = $parsed['ciphertext'] . $parsed['tag'];
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $combined,
                $this->aad->encode($context, $parsed['keyId']),
                $parsed['nonce'],
                $key->material(),
            );
            if ($plaintext === false) {
                throw new \RuntimeException('Authentication failed.');
            }

            return $plaintext;
        } catch (Throwable) {
            $this->metrics?->protectionFailure($context->purpose->value, 'decryption_failed');
            throw StorageProtectionException::failure();
        }
    }

    /** @return array{keyId: string, digest: string} */
    public function header(string $envelope): array
    {
        try {
            $parsed = $this->parse($envelope);
            return [
                'keyId' => $parsed['keyId'],
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
            if (
                strlen($prefix) < 8
                || substr($prefix, offset: 0, length: 4) !== self::MAGIC
                || ord($prefix[4]) !== self::VERSION
                || ord($prefix[5]) !== self::ALGORITHM
            ) {
                throw new \RuntimeException('Malformed envelope.');
            }
            $keyLength = $this->u16(substr($prefix, offset: 6, length: 2));
            if ($keyLength < 1 || $keyLength > 128) {
                throw new \RuntimeException('Malformed envelope.');
            }
            $keyId = substr($prefix, offset: 8, length: $keyLength);
            if (
                strlen($keyId) !== $keyLength
                || preg_match('/^[A-Za-z0-9]+(?:[._:\/-][A-Za-z0-9]+)*$/D', $keyId) !== 1
            ) {
                throw new \RuntimeException('Malformed envelope.');
            }
            return $keyId;
        } catch (Throwable) {
            $this->metrics?->protectionFailure('unknown', 'header_parse_failed');
            throw StorageProtectionException::failure();
        }
    }

    /**
     * @return array{keyId: string, nonce: string, ciphertext: string, tag: string}
     * @mago-expect lint:halstead
     */
    private function parse(string $envelope): array
    {
        $length = strlen($envelope);
        $offset = 0;
        if ($length < (4 + 1 + 1 + 2 + self::NONCE_LENGTH + 4 + self::TAG_LENGTH)) {
            throw new \RuntimeException('Malformed envelope.');
        }
        if (substr(string: $envelope, offset: 0, length: 4) !== self::MAGIC) {
            throw new \RuntimeException('Malformed envelope.');
        }
        $offset = 4;
        if (ord($envelope[$offset++]) !== self::VERSION || ord($envelope[$offset++]) !== self::ALGORITHM) {
            throw new \RuntimeException('Unsupported envelope.');
        }
        $keyLength = $this->u16(substr(string: $envelope, offset: $offset, length: 2));
        $offset += 2;
        if ($keyLength < 1 || $keyLength > 128 || ($offset + $keyLength) > $length) {
            throw new \RuntimeException('Malformed envelope.');
        }
        $keyId = substr(string: $envelope, offset: $offset, length: $keyLength);
        $offset += $keyLength;
        if (!preg_match('/^[A-Za-z0-9]+(?:[._:\/-][A-Za-z0-9]+)*$/D', $keyId)) {
            throw new \RuntimeException('Malformed envelope.');
        }
        $nonce = substr(string: $envelope, offset: $offset, length: self::NONCE_LENGTH);
        $offset += self::NONCE_LENGTH;
        if (strlen($nonce) !== self::NONCE_LENGTH || ($offset + 4) > $length) {
            throw new \RuntimeException('Malformed envelope.');
        }
        $ciphertextLength = $this->u32(substr(string: $envelope, offset: $offset, length: 4));
        $offset += 4;
        if ($ciphertextLength > ($length - $offset - self::TAG_LENGTH)) {
            throw new \RuntimeException('Malformed envelope.');
        }
        $ciphertext = substr(string: $envelope, offset: $offset, length: $ciphertextLength);
        $offset += $ciphertextLength;
        $tag = substr(string: $envelope, offset: $offset, length: self::TAG_LENGTH);
        $offset += self::TAG_LENGTH;
        if ($offset !== $length || strlen($tag) !== self::TAG_LENGTH) {
            throw new \RuntimeException('Malformed envelope.');
        }

        return ['keyId' => $keyId, 'nonce' => $nonce, 'ciphertext' => $ciphertext, 'tag' => $tag];
    }

    private function assertKey(mixed $key): void
    {
        if (!$key instanceof StorageKey) {
            throw new \RuntimeException('Invalid storage key.');
        }
    }

    private function u16(string $bytes): int
    {
        $values = unpack('nvalue', $bytes);
        if ($values === false) {
            throw new \RuntimeException('Malformed envelope.');
        }

        return $values['value'];
    }

    private function u32(string $bytes): int
    {
        $values = unpack('Nvalue', $bytes);
        if ($values === false) {
            throw new \RuntimeException('Malformed envelope.');
        }

        return $values['value'];
    }
}
