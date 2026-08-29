<?php

declare(strict_types=1);

namespace BlackOps\Internal\StorageProtection;

use BlackOps\StorageProtection\StorageKey;
use RuntimeException;

/**
 * Parses and authenticates the shared BOPD v1 binary wire without knowing any
 * storage purpose, tenant, row, or provider semantics.
 *
 * @mago-expect lint:excessive-parameter-list
 * @mago-expect lint:cyclomatic-complexity
 */
final class BopdBinaryEnvelopeCodec
{
    private const string MAGIC = 'BOPD';

    private const int VERSION = 1;

    private const int ALGORITHM = 1;

    private const int TAG_LENGTH = 16;

    private const int NONCE_LENGTH = 24;

    private const int KEY_ID_MIN_LENGTH = 1;

    private const int KEY_ID_MAX_LENGTH = 128;

    private const int HEADER_LENGTH = 4 + 1 + 1 + 2;

    private const int LENGTH_AND_TAG_LENGTH = 4 + self::TAG_LENGTH;

    private const int MIN_ENVELOPE_BYTES = self::HEADER_LENGTH + self::NONCE_LENGTH + self::LENGTH_AND_TAG_LENGTH;

    public function keyIdFromHeader(string $prefix): string
    {
        if (strlen($prefix) < self::HEADER_LENGTH) {
            throw new RuntimeException('Malformed envelope.');
        }
        if (
            substr($prefix, offset: 0, length: 4) !== self::MAGIC
            || ord($prefix[4]) !== self::VERSION
            || ord($prefix[5]) !== self::ALGORITHM
        ) {
            throw new RuntimeException('Malformed envelope.');
        }

        $keyLength = $this->u16(substr($prefix, offset: 6, length: 2));
        if (strlen($prefix) < (self::HEADER_LENGTH + $keyLength)) {
            throw new RuntimeException('Malformed envelope.');
        }
        $keyId = substr($prefix, self::HEADER_LENGTH, $keyLength);
        $this->assertKeyId($keyId, $keyLength);

        return $keyId;
    }

    public function parse(string $envelope, int $maxEnvelopeBytes): BopdBinaryEnvelope
    {
        $length = strlen($envelope);
        if (
            $maxEnvelopeBytes < 0
            || $length > $maxEnvelopeBytes
            || $length < (self::MIN_ENVELOPE_BYTES + self::KEY_ID_MIN_LENGTH)
        ) {
            throw new RuntimeException('Malformed envelope.');
        }
        if (
            substr($envelope, offset: 0, length: 4) !== self::MAGIC
            || ord($envelope[4]) !== self::VERSION
            || ord($envelope[5]) !== self::ALGORITHM
        ) {
            throw new RuntimeException('Malformed envelope.');
        }

        $offset = 6;
        $keyLength = $this->u16(substr($envelope, $offset, length: 2));
        $offset += 2;
        $keyId = substr($envelope, $offset, $keyLength);
        $this->assertKeyId($keyId, $keyLength);
        $offset += $keyLength;

        $minimumAfterKey = self::NONCE_LENGTH + self::LENGTH_AND_TAG_LENGTH;
        if ($offset > ($length - $minimumAfterKey)) {
            throw new RuntimeException('Malformed envelope.');
        }
        $nonce = substr($envelope, $offset, self::NONCE_LENGTH);
        $offset += self::NONCE_LENGTH;
        $ciphertextLength = $this->u32(substr($envelope, $offset, length: 4));
        $offset += 4;
        $remaining = $length - $offset - self::TAG_LENGTH;
        if ($ciphertextLength !== $remaining) {
            throw new RuntimeException('Malformed envelope.');
        }
        $ciphertext = substr($envelope, $offset, $ciphertextLength);
        $offset += $ciphertextLength;
        $tag = substr($envelope, $offset, self::TAG_LENGTH);
        $offset += self::TAG_LENGTH;
        if ($offset !== $length || strlen($nonce) !== self::NONCE_LENGTH || strlen($tag) !== self::TAG_LENGTH) {
            throw new RuntimeException('Malformed envelope.');
        }

        return new BopdBinaryEnvelope($keyId, $nonce, $ciphertext, $tag);
    }

    public function seal(
        #[\SensitiveParameter]
        string $plaintext,
        #[\SensitiveParameter]
        string $aad,
        StorageKey $key,
        #[\SensitiveParameter]
        string $nonce,
        int $maxPlaintextBytes,
        int $maxEnvelopeBytes,
    ): string {
        $keyId = $key->id();
        $keyIdLength = strlen($keyId);
        $plaintextLength = strlen($plaintext);
        $this->assertKeyId($keyId, $keyIdLength);
        if (
            $maxPlaintextBytes < 0
            || $maxEnvelopeBytes < 0
            || $plaintextLength > $maxPlaintextBytes
            || strlen($nonce) !== self::NONCE_LENGTH
        ) {
            throw new RuntimeException('Envelope plaintext is too large.');
        }
        $envelopeLength = $this->checkedAdd(
            $this->checkedAdd(self::MIN_ENVELOPE_BYTES, $keyIdLength),
            $plaintextLength,
        );
        if ($envelopeLength > $maxEnvelopeBytes) {
            throw new RuntimeException('Envelope is too large.');
        }

        try {
            $combined = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $key->material());
        } catch (\Throwable) {
            throw new RuntimeException('Envelope sealing failed.');
        }
        if (strlen($combined) !== $this->checkedAdd($plaintextLength, self::TAG_LENGTH)) {
            throw new RuntimeException('Envelope sealing failed.');
        }

        return (
            self::MAGIC
            . chr(self::VERSION)
            . chr(self::ALGORITHM)
            . pack('n', $keyIdLength)
            . $keyId
            . $nonce
            . pack('N', $plaintextLength)
            . substr($combined, offset: 0, length: -self::TAG_LENGTH)
            . substr($combined, -self::TAG_LENGTH)
        );
    }

    public function open(
        #[\SensitiveParameter]
        BopdBinaryEnvelope $envelope,
        #[\SensitiveParameter]
        string $aad,
        StorageKey $key,
        int $maxPlaintextBytes,
    ): string {
        $ciphertext = $envelope->ciphertext();
        if ($maxPlaintextBytes < 0 || strlen($ciphertext) > $maxPlaintextBytes) {
            throw new RuntimeException('Envelope plaintext is too large.');
        }

        try {
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ciphertext . $envelope->tag(),
                $aad,
                $envelope->nonce(),
                $key->material(),
            );
        } catch (\Throwable) {
            throw new RuntimeException('Envelope opening failed.');
        }
        if ($plaintext === false || strlen($plaintext) > $maxPlaintextBytes) {
            throw new RuntimeException('Envelope opening failed.');
        }

        return $plaintext;
    }

    private function assertKeyId(string $keyId, int $expectedLength): void
    {
        if (
            $expectedLength < self::KEY_ID_MIN_LENGTH
            || $expectedLength > self::KEY_ID_MAX_LENGTH
            || strlen($keyId) !== $expectedLength
            || preg_match('/^[A-Za-z0-9]+(?:[._:\/-][A-Za-z0-9]+)*$/D', $keyId) !== 1
        ) {
            throw new RuntimeException('Malformed envelope.');
        }
    }

    private function checkedAdd(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || $left > (PHP_INT_MAX - $right)) {
            throw new RuntimeException('Envelope length overflow.');
        }

        return $left + $right;
    }

    private function u16(string $bytes): int
    {
        if (strlen($bytes) !== 2) {
            throw new RuntimeException('Malformed envelope.');
        }
        $values = unpack('nvalue', $bytes);
        if ($values === false) {
            throw new RuntimeException('Malformed envelope.');
        }

        return $values['value'];
    }

    private function u32(string $bytes): int
    {
        if (strlen($bytes) !== 4) {
            throw new RuntimeException('Malformed envelope.');
        }
        $values = unpack('Nvalue', $bytes);
        if ($values === false) {
            throw new RuntimeException('Malformed envelope.');
        }

        return $values['value'];
    }
}
