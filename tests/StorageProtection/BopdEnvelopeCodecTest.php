<?php

declare(strict_types=1);

namespace BlackOps\Tests\StorageProtection;

use BlackOps\Core\TenantRef;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\CanonicalAssociatedData;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\Internal\Telemetry\TelemetryMetrics;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\StorageProtection\StorageKeyProvider;
use BlackOps\StorageProtection\StorageProtectionException;
use BlackOps\StorageProtection\StoragePurpose;
use BlackOps\Tests\Internal\Telemetry\RecordingMeterProvider;
use PHPUnit\Framework\TestCase;

final class BopdEnvelopeCodecTest extends TestCase
{
    public const KEY = '01234567890123456789012345678901';
    public const NONCE = '123456789012345678901234';

    public function testKnownAnswerAndRoundTrip(): void
    {
        $codec = new BopdEnvelopeCodec(new FixtureKeyProvider(), new CanonicalAssociatedData(), new FixedNonce());
        $envelope = $codec->encrypt('hello', $this->context());

        self::assertSame(
            '424f50440101000a7072696d6172793a763131323334353637383930313233343536373839303132333400000005b9668e91503c10618bd88b54df04aea3ebd9d0bb72',
            bin2hex($envelope),
        );
        self::assertSame(
            '00000013626c61636b6f70732e73746f726167652e7631000000013100000001310000000a7072696d6172793a76310000000e6a6f75726e616c5f7265636f7264000000067265636f7264000000096f7065726174696f6e0000000e6f7065726174696f6e2e7479706500000001310000000131000000036f72670000000674656e616e74',
            bin2hex(new CanonicalAssociatedData()->encode($this->context(), 'primary:v1')),
        );
        self::assertSame('hello', $codec->decrypt($envelope, $this->context()));
    }

    public function testPayloadShapesAndNonceUniqueness(): void
    {
        $provider = new FixtureKeyProvider();
        $codec = new BopdEnvelopeCodec($provider);
        $payloads = ['', "\x00\xFFbinary", str_repeat('x', 100_000)];
        $envelopes = [];
        foreach ($payloads as $payload) {
            $envelopes[] = $codec->encrypt($payload, $this->context());
            self::assertSame($payload, $codec->decrypt($envelopes[array_key_last($envelopes)], $this->context()));
        }
        self::assertCount(3, array_unique($envelopes));
        $first = $codec->encrypt('same', $this->context());
        $second = $codec->encrypt('same', $this->context());
        self::assertNotSame($first, $second);
        self::assertNotSame($this->nonce($first), $this->nonce($second));
    }

    public function testHeaderScanReturnsKeyIdAndDigestWithoutDecrypting(): void
    {
        $codec = new BopdEnvelopeCodec(new FixtureKeyProvider(), new CanonicalAssociatedData(), new FixedNonce());
        $envelope = $codec->encrypt('payload-marker', $this->context());

        self::assertSame('primary:v1', $codec->keyId($envelope));
        self::assertSame(hash('sha256', $envelope), $codec->header($envelope)['digest']);
        self::assertStringNotContainsString('payload-marker', json_encode(
            $codec->header($envelope),
            JSON_THROW_ON_ERROR,
        ));
    }

    public function testHeaderScanRejectsMalformedAndTruncatedBytesSafely(): void
    {
        $codec = new BopdEnvelopeCodec(new FixtureKeyProvider());
        foreach (['', 'BOPD', 'BOPD' . "\x01\x01\x00\x04", "BOPD\x01\x01\x00\x02id"] as $bytes) {
            try {
                $codec->header($bytes);
                self::fail('Expected malformed header failure.');
            } catch (StorageProtectionException $exception) {
                self::assertSame('Protected storage data is unavailable.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testCanonicalCodecDistinguishesNullAndPresentTenantValues(): void
    {
        $codec = new CanonicalAssociatedData();
        $withoutTenant = new StorageProtectionContext(StoragePurpose::JournalRecord, 'record', 'operation', 'type', 1);

        self::assertNotSame(
            $codec->encode($withoutTenant, 'primary:v1'),
            $codec->encode($this->context(), 'primary:v1'),
        );
        self::assertStringEndsWith('0000000130ffffffffffffffff', bin2hex($codec->encode($withoutTenant, 'primary:v1')));
    }

    public function testContextAndEnvelopeTamperingFailsClosed(): void
    {
        $codec = new BopdEnvelopeCodec(new FixtureKeyProvider(), new CanonicalAssociatedData(), new FixedNonce());
        $envelope = $codec->encrypt('hello', $this->context());
        $variants = [
            substr_replace($envelope, 'X', 0, 1),
            substr_replace($envelope, "\x02", 4, 1),
            substr_replace($envelope, "\x02", 5, 1),
            substr_replace($envelope, "\x09", 7, 1),
            substr_replace($envelope, "\x0B", 7, 1),
            substr_replace($envelope, pack('n', 0), 6, 2),
            substr_replace($envelope, pack('n', 129), 6, 2),
            substr_replace($envelope, '!', 8, 1),
            substr_replace($envelope, 'X', 18, 1),
            substr_replace($envelope, "\x04", 45, 1),
            substr_replace($envelope, "\x06", 45, 1),
            substr_replace($envelope, 'X', 46, 1),
            substr_replace($envelope, 'X', -1, 1),
            $envelope . 'trailing',
            substr($envelope, 0, 3),
            substr($envelope, 0, 4 + 1),
            substr($envelope, 0, 8),
            substr($envelope, 0, 18),
            substr($envelope, 0, 42),
            substr($envelope, 0, 46),
            substr($envelope, 0, -1),
        ];
        foreach ($variants as $variant) {
            try {
                $codec->decrypt($variant, $this->context());
                self::fail('Expected protection failure.');
            } catch (StorageProtectionException $exception) {
                self::assertSame('Protected storage data is unavailable.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
        foreach ([
            $this->context('other-record'),
            new StorageProtectionContext(StoragePurpose::OutcomePayload, 'record', 'operation', 'type', 1),
            new StorageProtectionContext(StoragePurpose::JournalRecord, 'record', 'other-operation', 'type', 1),
            new StorageProtectionContext(StoragePurpose::JournalRecord, 'record', 'operation', 'other-type', 1),
            new StorageProtectionContext(
                StoragePurpose::JournalRecord,
                'record',
                'operation',
                'type',
                2,
                new TenantRef('org', 'tenant'),
            ),
            new StorageProtectionContext(StoragePurpose::JournalRecord, 'record', 'operation', 'type', 1),
            new StorageProtectionContext(
                StoragePurpose::JournalRecord,
                'record',
                'operation',
                'type',
                1,
                new TenantRef('org', 'other'),
            ),
        ] as $context) {
            try {
                $codec->decrypt($envelope, $context);
                self::fail('Expected context protection failure.');
            } catch (StorageProtectionException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testProviderFailureDoesNotExposeDetails(): void
    {
        $provider = new ThrowingProvider();
        $codec = new BopdEnvelopeCodec($provider);
        try {
            $codec->encrypt('secret', $this->context());
            self::fail('Expected protection failure.');
        } catch (StorageProtectionException $exception) {
            self::assertSame('Protected storage data is unavailable.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            $trace = json_encode($exception->getTrace(), JSON_PARTIAL_OUTPUT_ON_ERROR);
            self::assertIsString($trace);
            foreach (['provider-marker', 'secret', 'tenant'] as $marker) {
                self::assertStringNotContainsString($marker, $trace);
            }
        }
    }

    public function testProviderFailureRecordsOnlySafeProtectionMetricAttributes(): void
    {
        $provider = new RecordingMeterProvider();
        $codec = new BopdEnvelopeCodec(new ThrowingProvider(), metrics: new TelemetryMetrics($provider));

        try {
            $codec->encrypt('secret', $this->context());
            self::fail('Expected protection failure.');
        } catch (StorageProtectionException) {
            self::assertSame(
                'journal_record',
                $provider->instruments[9]->records[0]['attributes']['blackops.storage.purpose'],
            );
            self::assertSame(
                'encryption_failed',
                $provider->instruments[9]->records[0]['attributes']['blackops.failure.code'],
            );
        }
    }

    public function testUnknownKeyWrongKeyAndDecryptProviderFailureAreSafe(): void
    {
        $writer = new BopdEnvelopeCodec(new FixtureKeyProvider(), new CanonicalAssociatedData(), new FixedNonce());
        $envelope = $writer->encrypt('secret', $this->context());
        foreach ([
            new UnknownKeyProvider(),
            new MismatchedKeyProvider(),
            new WrongMaterialProvider(),
            new ThrowingProvider(),
        ] as $provider) {
            try {
                new BopdEnvelopeCodec($provider)->decrypt($envelope, $this->context());
                self::fail('Expected provider failure.');
            } catch (StorageProtectionException $exception) {
                self::assertSame('Protected storage data is unavailable.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testDecryptProviderFailureDoesNotExposeEnvelopeOrTenant(): void
    {
        $envelope = new BopdEnvelopeCodec(
            new FixtureKeyProvider(),
            new CanonicalAssociatedData(),
            new FixedNonce(),
        )->encrypt('secret', $this->context());
        try {
            new BopdEnvelopeCodec(new ThrowingProvider())->decrypt($envelope, $this->context());
            self::fail('Expected provider failure.');
        } catch (StorageProtectionException $exception) {
            $trace = json_encode($exception->getTrace(), JSON_PARTIAL_OUTPUT_ON_ERROR);
            self::assertIsString($trace);
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString(bin2hex($envelope), $trace);
            self::assertStringNotContainsString(base64_encode($envelope), $trace);
            self::assertStringNotContainsString('tenant', $trace);
            self::assertStringNotContainsString('provider-marker', $trace);
        }
    }

    private function context(string $record = 'record'): StorageProtectionContext
    {
        return new StorageProtectionContext(
            StoragePurpose::JournalRecord,
            $record,
            'operation',
            'operation.type',
            1,
            new TenantRef('org', 'tenant'),
        );
    }

    private function nonce(string $envelope): string
    {
        $keyLength = unpack('nvalue', substr($envelope, 6, 2))['value'];

        return substr($envelope, 8 + $keyLength, 24);
    }
}

final readonly class FixedNonce implements NonceSource
{
    public function generate(): string
    {
        return BopdEnvelopeCodecTest::NONCE;
    }
}

final readonly class FixtureKeyProvider implements StorageKeyProvider
{
    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey('primary:v1', BopdEnvelopeCodecTest::KEY);
    }

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey($keyId, BopdEnvelopeCodecTest::KEY);
    }
}

final readonly class ThrowingProvider implements StorageKeyProvider
{
    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        throw new \RuntimeException('provider-marker');
    }

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        throw new \RuntimeException('provider-marker');
    }
}

final readonly class UnknownKeyProvider implements StorageKeyProvider
{
    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey('primary:v1', BopdEnvelopeCodecTest::KEY);
    }

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        throw new \RuntimeException('unknown-key-provider-detail');
    }
}

final readonly class MismatchedKeyProvider implements StorageKeyProvider
{
    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey('primary:v1', BopdEnvelopeCodecTest::KEY);
    }

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey('different:v1', BopdEnvelopeCodecTest::KEY);
    }
}

final readonly class WrongMaterialProvider implements StorageKeyProvider
{
    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey('primary:v1', BopdEnvelopeCodecTest::KEY);
    }

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey($keyId, str_repeat('z', 32));
    }
}
