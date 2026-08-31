<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Migration;

use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PostgreSqlMigrationSchemaTest extends TestCase
{
    public function testAcceptsTheInclusive63ByteAsciiSchemaBoundary(): void
    {
        $name = 'a' . str_repeat('b', 62);
        $schema = new PostgreSqlMigrationSchema($name);

        self::assertSame($name, $schema->name());
        self::assertSame('"' . $name . '"', $schema->quoted());
    }

    #[DataProvider('invalidSchemaNames')]
    public function testRejectsUnsafeOrOverlongSchemaNames(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PostgreSqlMigrationSchema($name);
    }

    /** @return \Generator<string, array{string}> */
    public static function invalidSchemaNames(): \Generator
    {
        yield 'empty' => [''];
        yield '64 bytes' => ['a' . str_repeat('b', 63)];
        yield 'same 63 byte prefix alias' => ['a' . str_repeat('b', 62) . 'x'];
        yield 'uppercase' => ['BlackOps'];
        yield 'non ascii' => ['schéma'];
        yield 'quoted alias' => ['"blackops"'];
        yield 'trailing line feed' => ["blackops\n"];
        yield 'leading digit' => ['1blackops'];
        yield 'punctuation' => ['blackops-audit'];
    }
}
