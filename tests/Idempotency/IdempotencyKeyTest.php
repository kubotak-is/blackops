<?php

declare(strict_types=1);

namespace BlackOps\Tests\Idempotency;

use BlackOps\Idempotency\IdempotencyKey;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdempotencyKeyTest extends TestCase
{
    public function testValidKeyProducesOpaqueVersionedHash(): void
    {
        $key = new IdempotencyKey('request-123');
        $hash = $key->hash();

        self::assertSame(1, $hash->version());
        self::assertSame('sha256', $hash->algorithm());
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $hash->digest());
        self::assertStringNotContainsString('request-123', $hash->digest());
        self::assertFalse(method_exists($key, 'value'));
        self::assertFalse(method_exists($key, '__toString'));
    }

    #[DataProvider('invalidKeys')]
    public function testInvalidShapeDoesNotExposeRawValue(string $value): void
    {
        try {
            new IdempotencyKey($value);
            self::fail('Expected invalid key exception.');
        } catch (InvalidArgumentException $exception) {
            if ($value === '') {
                self::assertSame('Idempotency key has an invalid shape.', $exception->getMessage());
                return;
            }

            self::assertStringNotContainsString($value, $exception->getMessage());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['request key'];
        yield 'newline' => ["request\nkey"];
        yield 'control' => ["request\x01key"];
        yield 'unicode' => ['リクエスト'];
        yield 'too long' => [str_repeat('a', times: 256)];
    }
}
