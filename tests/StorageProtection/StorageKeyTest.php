<?php

declare(strict_types=1);

namespace BlackOps\Tests\StorageProtection;

use BlackOps\StorageProtection\StorageKey;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class StorageKeyTest extends TestCase
{
    public function testValidKeyExposesOnlyIdentifierOnDebugSurfaces(): void
    {
        $key = new StorageKey('primary:v1', str_repeat('k', 32));

        self::assertSame('primary:v1', $key->id());
        self::assertSame(str_repeat('k', 32), $key->material());
        self::assertSame(['id' => 'primary:v1'], $key->__debugInfo());
        self::assertStringNotContainsString(str_repeat('k', 32), var_export($key, true));
        self::assertStringNotContainsString(str_repeat('k', 32), print_r($key, true));
        self::assertStringNotContainsString(str_repeat('k', 32), (string) json_encode($key));
        foreach ([1, 128] as $length) {
            self::assertSame($length, strlen(new StorageKey(str_repeat('a', $length), str_repeat('k', 32))->id()));
        }
    }

    public function testSerializationIsRejectedBySensitiveMaterialWrapper(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Serialization of 'SensitiveParameterValue' is not allowed");
        serialize(new StorageKey('primary:v1', str_repeat('k', 32)));
    }

    public function testIdentifierAndMaterialAreStrictlyValidated(): void
    {
        foreach (['', 'bad id', 'é', str_repeat('a', 129)] as $id) {
            try {
                new StorageKey($id, str_repeat('k', 32));
                self::fail('Expected invalid identifier.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testMaterialMustBeExactly32Bytes(): void
    {
        foreach ([31, 33] as $length) {
            try {
                new StorageKey('primary', str_repeat('k', $length));
                self::fail('Expected invalid material.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }

        self::assertSame(32, strlen(new StorageKey('binary', random_bytes(32))->material()));
    }
}
