<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core;

use BlackOps\Core\TenantRef;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TenantRefTest extends TestCase
{
    public function testTrimsAndExposesOpaqueIdentity(): void
    {
        $tenant = new TenantRef(' account ', ' tenant-1 ');
        self::assertSame('account', $tenant->type());
        self::assertSame('tenant-1', $tenant->id());
    }

    #[DataProvider('emptyValues')]
    public function testRejectsEmptyOrWhitespace(string $type, string $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TenantRef($type, $id);
    }

    /** @return iterable<string, array{string, string}> */
    public static function emptyValues(): iterable
    {
        yield 'empty type' => ['', 'id'];
        yield 'whitespace type' => ['  ', 'id'];
        yield 'empty id' => ['type', ''];
        yield 'whitespace id' => ['type', "\t\n"];
    }
}
