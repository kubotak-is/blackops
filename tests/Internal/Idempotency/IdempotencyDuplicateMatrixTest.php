<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Idempotency;

use BlackOps\Core\ActorRef;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationResult;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Internal\Idempotency\IdempotencyClaimStatus;
use BlackOps\Internal\Idempotency\IdempotencyResultSnapshot;
use BlackOps\Internal\Idempotency\IdempotencyScopeHasher;
use BlackOps\Internal\Idempotency\InMemoryIdempotencyStore;
use BlackOps\Internal\Idempotency\OperationValueFingerprinter;
use BlackOps\Internal\Idempotency\TerminalRecord;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class IdempotencyDuplicateMatrixTest extends TestCase
{
    public function testClaimMatrixAndTerminalReplayPreserveOperationIdentity(): void
    {
        $store = new InMemoryIdempotencyStore();
        $scope = new IdempotencyScopeHasher()->hash(
            'fixture.operation',
            new ActorRef('u-1', 'user'),
            new IdempotencyKey('retry-key'),
        );
        $key = new IdempotencyKey('retry-key')->hash();
        $fingerprint = $this->fingerprint('same');
        $created = new DateTimeImmutable('2026-07-24T00:00:00Z');
        $operation = OperationId::fromString('019f8fbf-43b2-791c-9e4b-9d73d28e477e');
        $claim = $store->claim(
            $scope,
            $key,
            $fingerprint,
            $operation,
            new Inline(),
            $created,
            $created->modify('+1 day'),
        );

        self::assertSame(IdempotencyClaimStatus::Claimed, $claim->status());
        self::assertSame(
            IdempotencyClaimStatus::ExistingSameFingerprint,
            $store
                ->claim($scope, $key, $fingerprint, $operation, new Inline(), $created, $created->modify('+1 day'))
                ->status(),
        );
        self::assertSame(
            IdempotencyClaimStatus::ExistingConflict,
            $store
                ->claim(
                    $scope,
                    $key,
                    $this->fingerprint('different'),
                    $operation,
                    new Inline(),
                    $created,
                    $created->modify('+1 day'),
                )
                ->status(),
        );

        $terminal = new TerminalRecord(
            $scope,
            $key,
            $fingerprint,
            $operation,
            new Inline(),
            $created,
            $created->modify('+1 day'),
            null,
            new IdempotencyResultSnapshot(OperationResult::completed(new EmptyOutcome(), $operation)),
        );
        self::assertTrue($store->terminalize($operation, $terminal));
        $record = $store->find($scope);
        self::assertInstanceOf(TerminalRecord::class, $record);
        self::assertSame($operation->toString(), $record->result()?->result()->operationId()?->toString());
    }

    public function testProcessingRecordRemainsInProgressUntilExplicitTerminalization(): void
    {
        $store = new InMemoryIdempotencyStore();
        $scope = new IdempotencyScopeHasher()->hash(
            'fixture.operation',
            new ActorRef('u-1', 'user'),
            new IdempotencyKey('in-progress'),
        );
        $key = new IdempotencyKey('in-progress')->hash();
        $now = new DateTimeImmutable('2026-07-24T00:00:00Z');
        $store->claim(
            $scope,
            $key,
            $this->fingerprint('same'),
            OperationId::fromString('019f8fbf-43b2-791c-9e4b-9d73d28e477e'),
            new Inline(),
            $now,
            $now->modify('+1 day'),
        );

        $record = $store->find($scope);
        self::assertSame('processing', $record?->state()->value);
    }

    private function fingerprint(string $value): \BlackOps\Internal\Idempotency\OperationFingerprint
    {
        $input = new class($value) implements \BlackOps\Core\OperationValue {
            public function __construct(
                public string $value,
            ) {}
        };

        return new OperationValueFingerprinter()->fingerprint('fixture.operation', $input);
    }
}
