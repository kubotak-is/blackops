<?php

declare(strict_types=1);

namespace BlackOps\Internal\StorageProtection;

use RuntimeException;

/**
 * Holds the binary envelope parts without exposing them through object
 * inspection or PHP serialization.
 */
final class BopdBinaryEnvelope
{
    private \SensitiveParameterValue $keyId;

    private \SensitiveParameterValue $nonce;

    private \SensitiveParameterValue $ciphertext;

    private \SensitiveParameterValue $tag;

    public function __construct(
        #[\SensitiveParameter]
        string $keyId,
        #[\SensitiveParameter]
        string $nonce,
        #[\SensitiveParameter]
        string $ciphertext,
        #[\SensitiveParameter]
        string $tag,
    ) {
        if (
            strlen($keyId) < 1
            || strlen($keyId) > 128
            || preg_match('/^[A-Za-z0-9]+(?:[._:\/-][A-Za-z0-9]+)*$/D', $keyId) !== 1
            || strlen($nonce) !== 24
            || strlen($tag) !== 16
        ) {
            throw new RuntimeException('Malformed envelope.');
        }

        $this->keyId = new \SensitiveParameterValue($keyId);
        $this->nonce = new \SensitiveParameterValue($nonce);
        $this->ciphertext = new \SensitiveParameterValue($ciphertext);
        $this->tag = new \SensitiveParameterValue($tag);
    }

    public function keyId(): string
    {
        return (string) $this->keyId->getValue();
    }

    public function nonce(): string
    {
        return (string) $this->nonce->getValue();
    }

    public function ciphertext(): string
    {
        return (string) $this->ciphertext->getValue();
    }

    public function tag(): string
    {
        return (string) $this->tag->getValue();
    }

    /** @return array<string,never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<string,never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<string,mixed> $data */
    public function __unserialize(array $data): void
    {
        throw new RuntimeException('Envelope unserialization is unavailable.');
    }
}
