<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\SessionSettings;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SessionSettingsTest extends TestCase
{
    public function testDefaultAndConfiguredTtlArePositiveFiniteSeconds(): void
    {
        self::assertSame(28_800, SessionSettings::fromEnvironment([])->ttlSeconds);
        self::assertSame(600, SessionSettings::fromEnvironment(['SESSION_TTL_SECONDS' => '600'])->ttlSeconds);
    }

    #[DataProvider('invalidTtlProvider')]
    public function testInvalidTtlFailsClosed(string $ttl): void
    {
        $this->expectException(InvalidArgumentException::class);
        SessionSettings::fromEnvironment(['SESSION_TTL_SECONDS' => $ttl]);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidTtlProvider(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'float' => ['1.5'];
        yield 'non numeric' => ['forever'];
        yield 'overflow' => [str_repeat('9', 100)];
    }
}
