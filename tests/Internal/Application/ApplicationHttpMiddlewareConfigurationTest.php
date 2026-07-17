<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationHttpMiddlewareConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApplicationHttpMiddlewareConfigurationTest extends TestCase
{
    public function testMissingConfigurationProducesEmptyList(): void
    {
        self::assertSame([], ApplicationHttpMiddlewareConfiguration::fromConfiguration([])->http);
    }

    public function testPreservesTrimmedConfiguredOrder(): void
    {
        $configuration = ApplicationHttpMiddlewareConfiguration::fromConfiguration([
            'middleware' => ['http' => [' App\\Outer ', 'App\\Inner']],
        ]);

        self::assertSame(['App\\Outer', 'App\\Inner'], $configuration->http);
    }

    #[DataProvider('invalidConfigurations')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApplicationHttpMiddlewareConfiguration::fromConfiguration($configuration);
    }

    /** @return iterable<string, array{configuration: array<string, array<array-key, mixed>>}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'not a list' => ['configuration' => ['middleware' => ['http' => ['outer' => 'App\\Outer']]]];
        yield 'non string' => ['configuration' => ['middleware' => ['http' => [42]]]];
        yield 'empty' => ['configuration' => ['middleware' => ['http' => ['  ']]]];
        yield 'duplicates after trim' => [
            'configuration' => ['middleware' => ['http' => ['App\\Outer', ' App\\Outer ']]],
        ];
    }
}
