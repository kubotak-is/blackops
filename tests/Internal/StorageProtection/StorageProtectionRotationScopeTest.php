<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\StorageProtection;

use BlackOps\Core\TenantRef;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\StorageProtection\StorageKeyProvider;
use BlackOps\StorageProtection\StoragePurpose;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class StorageProtectionRotationScopeTest extends TestCase
{
    public function testScopeHashIsDeterministicAndTenantScoped(): void
    {
        $first = new StorageProtectionRotationScope(
            StoragePurpose::JournalRecord,
            new TenantRef('org', 'a'),
            'old:v1',
            'new:v1',
            10,
            'rotate-a',
        );
        $same = new StorageProtectionRotationScope(
            StoragePurpose::JournalRecord,
            new TenantRef('org', 'a'),
            'old:v1',
            'new:v1',
            10,
            'rotate-a',
        );
        $other = new StorageProtectionRotationScope(
            StoragePurpose::JournalRecord,
            new TenantRef('org', 'b'),
            'old:v1',
            'new:v1',
            10,
            'rotate-a',
        );
        self::assertSame($first->scopeHash(), $same->scopeHash());
        self::assertNotSame($first->scopeHash(), $other->scopeHash());
    }

    public function testConfirmedRotationRequiresActorAndReasonAndPositiveBound(): void
    {
        foreach ([
            static fn(): StorageProtectionRotationScope => new StorageProtectionRotationScope(
                StoragePurpose::JournalRecord,
                null,
                'old:v1',
                'new:v1',
                0,
                'rotate',
            ),
            static fn(): StorageProtectionRotationScope => new StorageProtectionRotationScope(
                StoragePurpose::JournalRecord,
                null,
                'old:v1',
                'new:v1',
                1,
                'rotate',
                null,
                'reason',
                true,
            ),
            static fn(): StorageProtectionRotationScope => new StorageProtectionRotationScope(
                StoragePurpose::JournalRecord,
                null,
                'old:v1',
                'old:v1',
                1,
                'rotate',
            ),
        ] as $factory) {
            try {
                $factory();
                self::fail('Expected rotation scope validation failure.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testAllProtectedPurposesPreserveAadThroughOldToNewRoundTrip(): void
    {
        $tenant = new TenantRef('org', 'tenant-a');
        $old = new BopdEnvelopeCodec(new RotationPurposeProvider('old:v1'));
        $new = new BopdEnvelopeCodec(new RotationPurposeProvider('new:v1'));
        foreach (StoragePurpose::cases() as $purpose) {
            $identity = match ($purpose) {
                StoragePurpose::DeferredPayload => 'operation:payload',
                StoragePurpose::DeferredContext => 'operation:context',
                StoragePurpose::IdempotencyResponse, StoragePurpose::IdempotencyResult => '2:scope-hash',
                default => 'record',
            };
            $schema = $purpose === StoragePurpose::OutcomePayload ? 9 : 1;
            $context = new StorageProtectionContext(
                $purpose,
                $identity,
                'operation',
                'fixture.operation',
                $schema,
                $tenant,
            );
            $envelope = $old->encrypt('purpose:' . $purpose->value, $context);
            $rotated = $new->encrypt($old->decrypt($envelope, $context), $context);
            self::assertSame('new:v1', $new->keyId($rotated));
            self::assertSame('purpose:' . $purpose->value, $new->decrypt($rotated, $context));
        }
    }
}

final readonly class RotationPurposeProvider implements StorageKeyProvider
{
    public function __construct(
        private string $active,
    ) {}

    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey($this->active, str_repeat($this->active === 'old:v1' ? 'o' : 'n', 32));
    }

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey($keyId, str_repeat($keyId === 'old:v1' ? 'o' : 'n', 32));
    }
}
