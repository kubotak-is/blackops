<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Idempotency;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Internal\Idempotency\IdempotencyClaimStatus;
use BlackOps\Internal\Idempotency\IdempotencyScopeHash;
use BlackOps\Internal\Idempotency\IdempotencyScopeHasher;
use BlackOps\Internal\Idempotency\InMemoryIdempotencyStore;
use BlackOps\Internal\Idempotency\OperationFingerprint;
use BlackOps\Internal\Idempotency\TerminalRecord;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class IdempotencyStoreTest extends TestCase
{
    public function testClaimIsAtomicAndDistinguishesSameAndConflict(): void
    {
        $store = new InMemoryIdempotencyStore();
        $scope = $this->scope();
        $key = new IdempotencyKey('request-123')->hash();
        $fingerprint = new OperationFingerprint(1, str_repeat('a', times: 64));
        $operation = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687701');
        $created = new DateTimeImmutable('2026-07-23T00:00:00Z');
        $expires = new DateTimeImmutable('2026-07-24T00:00:00Z');

        $claimed = $store->claim($scope, $key, $fingerprint, $operation, new Inline(), $created, $expires);
        $same = $store->claim($scope, $key, $fingerprint, $operation, new Inline(), $created, $expires);
        $conflict = $store->claim(
            $scope,
            $key,
            new OperationFingerprint(1, str_repeat('b', times: 64)),
            $operation,
            new Inline(),
            $created,
            $expires,
        );

        self::assertSame(IdempotencyClaimStatus::Claimed, $claimed->status());
        self::assertSame(IdempotencyClaimStatus::ExistingSameFingerprint, $same->status());
        self::assertSame(IdempotencyClaimStatus::ExistingConflict, $conflict->status());
    }

    public function testTerminalizeRequiresClaimedOperationAndExpectedProcessingState(): void
    {
        $store = new InMemoryIdempotencyStore();
        $scope = $this->scope();
        $key = new IdempotencyKey('request-123')->hash();
        $fingerprint = new OperationFingerprint(1, str_repeat('a', times: 64));
        $operation = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687701');
        $created = new DateTimeImmutable('2026-07-23T00:00:00Z');
        $expires = new DateTimeImmutable('2026-07-24T00:00:00Z');
        $store->claim($scope, $key, $fingerprint, $operation, new Inline(), $created, $expires);
        $terminal = new TerminalRecord($scope, $key, $fingerprint, $operation, new Inline(), $created, $expires);

        self::assertTrue($store->terminalize($operation, $terminal));
        self::assertFalse($store->terminalize($operation, $terminal));
        self::assertSame('terminal', $store->find($scope)?->state()->value);
    }

    public function testExpiredRecordStillPreventsReuseWithSameFingerprint(): void
    {
        $store = new InMemoryIdempotencyStore();
        $scope = $this->scope();
        $key = new IdempotencyKey('request-123')->hash();
        $fingerprint = new OperationFingerprint(1, str_repeat('a', times: 64));
        $operation = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687701');
        $created = new DateTimeImmutable('2026-07-23T00:00:00Z');
        $expires = new DateTimeImmutable('2026-07-24T00:00:00Z');
        $store->claim($scope, $key, $fingerprint, $operation, new Inline(), $created, $expires);

        self::assertSame(
            IdempotencyClaimStatus::ExistingSameFingerprint,
            $store
                ->claim(
                    $scope,
                    $key,
                    $fingerprint,
                    $operation,
                    new Inline(),
                    new DateTimeImmutable('2026-07-25T00:00:00Z'),
                    new DateTimeImmutable('2026-07-26T00:00:00Z'),
                )
                ->status(),
        );
    }

    private function scope(): IdempotencyScopeHash
    {
        return new IdempotencyScopeHasher()->hash(
            'reports.create',
            new ActorRef('user-1', 'user'),
            new IdempotencyKey('request-123'),
        );
    }
}
