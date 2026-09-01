<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Outbox;

use BlackOps\Internal\Audit\Outbox\AuditOutboxIdempotencyKeyHasher;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreException;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreFailure;
use PHPUnit\Framework\TestCase;

final class AuditOutboxIdempotencyKeyHasherTest extends TestCase
{
    public function testMatchesAcceptedB1SystemAndTenantVectors(): void
    {
        $hasher = new AuditOutboxIdempotencyKeyHasher();

        self::assertSame('6e22dd41bce06ff2825a8081b3bd34bbe8fe47acba2f76cf0af2ea62771ebdbc', $hasher->hash(
            'system:global',
            'idem-null-1',
        ));
        self::assertSame('1caac6c03c7c676ad2506059eb7b26fdbe959bc68a0aa6790db76aa019a6ff54', $hasher->hash(
            'tenant:59f58bfcb476fcc98fe5e95c6bf81a5f8125a721f27e53ed70ba7345ae64eae1',
            'idem-tenant-2',
        ));
    }

    public function testUnicodeLineAndParagraphSeparatorsRemainHashable(): void
    {
        $hasher = new AuditOutboxIdempotencyKeyHasher();

        self::assertSame('cb7424fc93e23422f493bace560b1a8b671f68fe020ce671a709d8a3faf83eb6', $hasher->hash(
            'system:global',
            "line\u{2028}paragraph\u{2029}",
        ));
    }

    public function testOneAndTwoHundredFiftySixByteKeysAreAccepted(): void
    {
        $hasher = new AuditOutboxIdempotencyKeyHasher();

        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $hasher->hash('system:global', 'k'));
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $hasher->hash('system:global', str_repeat(
            'k',
            256,
        )));
    }

    public function testNfcControlAndByteCapRulesAreFailClosed(): void
    {
        $hasher = new AuditOutboxIdempotencyKeyHasher();
        foreach ([
            "e\u{301}",
            "contains\u{200e}format",
            "contains\x01control",
            str_repeat('k', 257),
        ] as $key) {
            $this->assertIntegrityFailure(static fn() => $hasher->hash('system:global', $key));
        }
    }

    public function testPartitionGrammarIsClosedBeforeHashing(): void
    {
        $hasher = new AuditOutboxIdempotencyKeyHasher();
        foreach ([
            'SYSTEM:GLOBAL',
            'tenant:' . str_repeat('a', 63),
            'tenant:' . str_repeat('A', 64),
            'tenant:' . str_repeat('a', 65),
            'tenant:' . str_repeat('a', 64) . "\n",
        ] as $partition) {
            $this->assertIntegrityFailure(static fn() => $hasher->hash($partition, 'key'));
        }
    }

    public function testHasherIsFinalReadonlyAndHasNoConstructor(): void
    {
        $reflection = new \ReflectionClass(AuditOutboxIdempotencyKeyHasher::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertNull($reflection->getConstructor());
    }

    private function assertIntegrityFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('invalid idempotency input was accepted');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertSame('integrity_invalid', $exception->code());
            self::assertSame('Audit outbox persistence failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }
}
