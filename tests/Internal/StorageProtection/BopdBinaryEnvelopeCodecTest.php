<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\StorageProtection;

use BlackOps\Internal\StorageProtection\BopdBinaryEnvelope;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\StorageProtection\StorageKey;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BopdBinaryEnvelopeCodecTest extends TestCase
{
    public const KEY = '01234567890123456789012345678901';

    public function testPrimitiveSealsParsesAndOpensTheBinaryWire(): void
    {
        $codec = new BopdBinaryEnvelopeCodec();
        $key = new StorageKey('binary:v1', str_repeat('k', 32));
        $nonce = str_repeat("\x01", 24);
        $envelope = $codec->seal('secret', 'aad', $key, $nonce, 1024, 2048);
        $parsed = $codec->parse($envelope, 2048);

        self::assertSame('binary:v1', $parsed->keyId());
        self::assertSame($nonce, $parsed->nonce());
        self::assertSame('secret', $codec->open($parsed, 'aad', $key, 1024));
        self::assertInstanceOf(BopdBinaryEnvelope::class, $parsed);
    }

    public function testPrimitiveKnownAnswerRemainsByteCompatibleWithTheLegacyWrapper(): void
    {
        $codec = new BopdBinaryEnvelopeCodec();
        $key = new StorageKey('primary:v1', self::KEY);
        $aad = hex2bin(
            '00000013626c61636b6f70732e73746f726167652e7631000000013100000001310000000a7072696d6172793a76310000000e6a6f75726e616c5f7265636f7264000000067265636f7264000000096f7065726174696f6e0000000e6f7065726174696f6e2e7479706500000001310000000131000000036f72670000000674656e616e74',
        );
        self::assertIsString($aad);
        self::assertSame(
            '424f50440101000a7072696d6172793a763131323334353637383930313233343536373839303132333400000005b9668e91503c10618bd88b54df04aea3ebd9d0bb72',
            bin2hex($codec->seal('hello', $aad, $key, '123456789012345678901234', 0xFFFF_FFFF, 0x1_0000_00B3)),
        );
    }

    public function testPartialHeaderKeyIdBoundariesAreCallableWithoutAFullEnvelope(): void
    {
        $codec = new BopdBinaryEnvelopeCodec();
        foreach ([str_repeat('a', 1), str_repeat('b', 128)] as $keyId) {
            $prefix = 'BOPD' . "\x01\x01" . pack('n', strlen($keyId)) . $keyId;
            self::assertSame($keyId, $codec->keyIdFromHeader($prefix));
        }

        foreach ([
            '',
            'BOPD',
            'BOPD' . "\x01\x01\x00\x02" . 'x',
            'BOPD' . "\x01\x01\x00\x01" . '!',
            'BOPD' . "\x02\x01\x00\x01" . 'x',
        ] as $prefix) {
            $this->assertRuntimeFailure(static fn(): string => $codec->keyIdFromHeader($prefix));
        }
    }

    public function testParseRejectsTruncationTrailingBytesAndWrongDeclaredLength(): void
    {
        $codec = new BopdBinaryEnvelopeCodec();
        $key = new StorageKey('binary:v1', str_repeat('k', 32));
        $envelope = $codec->seal('payload', 'aad', $key, str_repeat("\x02", 24), 1024, 2048);

        $variants = [
            substr($envelope, 0, -1),
            $envelope . "\x00",
            substr_replace($envelope, pack('N', 9), 8 + strlen($key->id()) + 24, 4),
            substr_replace($envelope, pack('n', 0), 6, 2),
        ];
        foreach ($variants as $variant)
            $this->assertRuntimeFailure(static fn(): BopdBinaryEnvelope => $codec->parse($variant, 2048));
    }

    public function testInjectedCapsAreInclusiveAndCapPlusOneFailsBeforeEncryption(): void
    {
        $codec = new BopdBinaryEnvelopeCodec();
        $key = new StorageKey('binary:v1', str_repeat('k', 32));
        $nonce = str_repeat("\x03", 24);
        $plaintext = 'boundary';
        $envelopeLength = strlen($codec->seal($plaintext, 'aad', $key, $nonce, 1024, 2048));

        $codec->seal($plaintext, 'aad', $key, $nonce, strlen($plaintext), $envelopeLength);
        $this->assertRuntimeFailure(static fn(): string => $codec->seal(
            $plaintext,
            'aad',
            $key,
            $nonce,
            strlen($plaintext) - 1,
            $envelopeLength,
        ));
        $this->assertRuntimeFailure(static fn(): string => $codec->seal(
            $plaintext,
            'aad',
            $key,
            $nonce,
            strlen($plaintext),
            $envelopeLength - 1,
        ));
        $this->assertRuntimeFailure(static fn(): string => $codec->seal(
            $plaintext . 'x',
            'aad',
            $key,
            $nonce,
            strlen($plaintext),
            2048,
        ));
        $this->assertRuntimeFailure(static fn(): string => $codec->seal(
            $plaintext . 'x',
            'aad',
            $key,
            $nonce,
            strlen($plaintext) + 1,
            $envelopeLength,
        ));
        $parsed = $codec->parse($codec->seal($plaintext, 'aad', $key, $nonce, 1024, 2048), 2048);
        self::assertSame($plaintext, $codec->open($parsed, 'aad', $key, strlen($plaintext)));
        $this->assertRuntimeFailure(static fn(): string => $codec->open($parsed, 'aad', $key, strlen($plaintext) - 1));
    }

    public function testEnvelopeCapIncludesTheFixedOverheadAndKeyIdBytes(): void
    {
        $codec = new BopdBinaryEnvelopeCodec();
        $plaintext = 'あ';
        $nonce = str_repeat("\x06", 24);
        foreach ([str_repeat('a', 1), str_repeat('b', 128)] as $keyId) {
            $key = new StorageKey($keyId, str_repeat('k', 32));
            $envelope = $codec->seal(
                $plaintext,
                'aad',
                $key,
                $nonce,
                strlen($plaintext),
                strlen($plaintext) + 52 + strlen($keyId),
            );
            self::assertSame(strlen($plaintext) + 52 + strlen($keyId), strlen($envelope));
            $this->assertRuntimeFailure(static fn(): string => $codec->seal(
                $plaintext,
                'aad',
                $key,
                $nonce,
                strlen($plaintext),
                strlen($plaintext) + 52 + strlen($keyId) - 1,
            ));
        }
    }

    public function testBinaryPartsAreNotExposedByInspectionOrSerialization(): void
    {
        $codec = new BopdBinaryEnvelopeCodec();
        $key = new StorageKey('binary-secret-marker', str_repeat('m', 32));
        $envelope = $codec->seal(
            'plaintext-secret-marker',
            'aad-secret-marker',
            $key,
            str_repeat("\x04", 24),
            1024,
            2048,
        );
        $parsed = $codec->parse($envelope, 2048);
        $debug = var_export($parsed, true) . print_r($parsed, true) . json_encode($parsed, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('binary-secret-marker', $debug);
        self::assertStringNotContainsString('plaintext-secret-marker', $debug);
        self::assertSame([], $parsed->__serialize());
    }

    public function testSecretPartsUseSensitiveStorageAndBoundaryShapesAreRejected(): void
    {
        $reflection = new \ReflectionClass(BopdBinaryEnvelope::class);
        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isPrivate());
            self::assertSame('SensitiveParameterValue', $property->getType()?->getName());
        }
        $codec = new BopdBinaryEnvelopeCodec();
        $key = new StorageKey('binary:v1', str_repeat('k', 32));
        foreach ([
            ['bad!',      str_repeat("\x01", 24), '', str_repeat("\x02", 16)],
            ['binary:v1', str_repeat("\x01", 23), '', str_repeat("\x02", 16)],
            ['binary:v1', str_repeat("\x01", 24), '', str_repeat("\x02", 15)],
        ] as [$keyId, $nonce, $ciphertext, $tag]) {
            $this->assertRuntimeFailure(
                static fn(): BopdBinaryEnvelope => new BopdBinaryEnvelope($keyId, $nonce, $ciphertext, $tag),
            );
        }
        $envelope = $codec->seal('payload', 'aad', $key, str_repeat("\x05", 24), 1024, 2048);
        $this->assertRuntimeFailure(static fn(): BopdBinaryEnvelope => $codec->parse($envelope, -1));
        $this->assertRuntimeFailure(static fn(): string => $codec->seal(
            'payload',
            'aad',
            $key,
            str_repeat("\x05", 24),
            -1,
            2048,
        ));
    }

    private function assertRuntimeFailure(callable $callback): void
    {
        try {
            $callback();
            self::fail('Expected a binary envelope failure.');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }
    }
}
