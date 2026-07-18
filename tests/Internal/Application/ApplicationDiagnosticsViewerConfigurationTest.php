<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationDiagnosticsViewerConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApplicationDiagnosticsViewerConfigurationTest extends TestCase
{
    public function testDefaultsAreDisabledLoopbackOnly(): void
    {
        $configuration = ApplicationDiagnosticsViewerConfiguration::fromConfiguration([]);

        self::assertFalse($configuration->enabled);
        self::assertSame('127.0.0.1', $configuration->bind);
        self::assertSame(8082, $configuration->port);
        self::assertSame('127.0.0.1:8082', $configuration->authority());
    }

    public function testAcceptsBothLoopbackFamiliesAndPortBoundaries(): void
    {
        foreach ([['127.0.0.1', 1], ['::1', 65_535]] as [$bind, $port]) {
            $configuration = ApplicationDiagnosticsViewerConfiguration::fromConfiguration([
                'diagnostics' => ['viewer' => ['enabled' => true, 'bind' => $bind, 'port' => $port]],
            ]);
            self::assertTrue($configuration->enabled);
            self::assertSame($bind, $configuration->bind);
            self::assertSame($port, $configuration->port);
        }
    }

    /** @param mixed $viewer */
    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsInvalidConfiguration(mixed $viewer): void
    {
        $this->expectException(InvalidArgumentException::class);
        ApplicationDiagnosticsViewerConfiguration::fromConfiguration(['diagnostics' => ['viewer' => $viewer]]);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'viewer scalar' => ['enabled'];
        yield 'string boolean' => [['enabled' => 'true']];
        yield 'numeric string port' => [['port' => '8082']];
        yield 'zero port' => [['port' => 0]];
        yield 'large port' => [['port' => 65_536]];
        yield 'wildcard' => [['bind' => '0.0.0.0']];
        yield 'ipv6 wildcard' => [['bind' => '::']];
        yield 'lan' => [['bind' => '192.168.1.2']];
        yield 'hostname' => [['bind' => 'localhost']];
        yield 'trimmed' => [['bind' => ' 127.0.0.1']];
        yield 'unix socket' => [['bind' => '/tmp/viewer.sock']];
    }
}
