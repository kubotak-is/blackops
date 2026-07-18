<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationLoggingConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApplicationLoggingConfigurationTest extends TestCase
{
    public function testUsesSafeJsonlDefaultsWhenConfigurationIsMissing(): void
    {
        $configuration = ApplicationLoggingConfiguration::fromConfiguration([]);

        self::assertSame('php://stderr', $configuration->stream);
        self::assertSame('blackops', $configuration->channel);
        self::assertSame('info', $configuration->minimumLevel);
    }

    public function testUsesSafeJsonlDefaultsWhenBackendIsMissing(): void
    {
        $configuration = ApplicationLoggingConfiguration::fromConfiguration(['logging' => []]);

        self::assertSame('php://stderr', $configuration->stream);
        self::assertSame('blackops', $configuration->channel);
        self::assertSame('info', $configuration->minimumLevel);
    }

    /** @param array<array-key, mixed> $backend */
    #[DataProvider('validBackendProvider')]
    public function testAcceptsCanonicalBackendConfiguration(array $backend): void
    {
        $configuration = ApplicationLoggingConfiguration::fromConfiguration(['logging' => ['backend' => $backend]]);

        self::assertSame($backend['stream'], $configuration->stream);
        self::assertSame($backend['channel'], $configuration->channel);
        self::assertSame($backend['minimum_level'], $configuration->minimumLevel);
    }

    /** @return iterable<string, array{array{driver: string, stream: string, channel: string, minimum_level: string}}> */
    public static function validBackendProvider(): iterable
    {
        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $level) {
            yield $level => [[
                'driver' => 'jsonl',
                'stream' => $level === 'debug' ? '/var/log/blackops/application.jsonl' : 'php://stdout',
                'channel' => 'application',
                'minimum_level' => $level,
            ]];
        }
    }

    /** @param array<array-key, mixed> $logging */
    #[DataProvider('invalidLoggingProvider')]
    public function testRejectsInvalidConfigurationWithoutReflectingValues(array $logging, string $sensitive): void
    {
        try {
            ApplicationLoggingConfiguration::fromConfiguration(['logging' => $logging]);
            self::fail('Invalid logging configuration was accepted.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString($sensitive, $exception->getMessage());
        }
    }

    /** @return iterable<string, array{array<array-key, mixed>, string}> */
    public static function invalidLoggingProvider(): iterable
    {
        yield 'unknown root key' => [['credential_secret' => 'root-secret'], 'root-secret'];
        yield 'backend is not an array' => [['backend' => 'backend-secret'], 'backend-secret'];
        yield 'unknown backend key' => [['backend' => ['password' => 'credential-secret']], 'credential-secret'];
        yield 'disable switch' => [['backend' => ['enabled' => false]], 'not-present'];
        yield 'custom driver' => [['backend' => ['driver' => 'syslog-secret']], 'syslog-secret'];
        yield 'driver case mismatch' => [['backend' => ['driver' => 'JSONL']], 'JSONL'];
        yield 'driver is not a string' => [['backend' => ['driver' => 1]], 'not-present'];
        yield 'stream is not a string' => [['backend' => ['stream' => 1]], 'not-present'];
        yield 'relative path' => [['backend' => ['stream' => 'var/log/private-secret']], 'private-secret'];
        yield 'empty stream' => [['backend' => ['stream' => '']], 'not-present'];
        yield 'arbitrary PHP wrapper' => [['backend' => ['stream' => 'php://memory-secret']], 'memory-secret'];
        yield 'network wrapper' => [['backend' => ['stream' => 'tcp://credential-secret']], 'credential-secret'];
        yield 'absolute path with wrapper marker' => [
            ['backend' => ['stream' => '/tmp/http://credential-secret']],
            'credential-secret',
        ];
        yield 'path with NUL' => [['backend' => ['stream' => "/tmp/credential-secret\0.jsonl"]], 'credential-secret'];
        yield 'empty channel' => [['backend' => ['channel' => '']], 'not-present'];
        yield 'channel whitespace' => [['backend' => ['channel' => ' credential-secret ']], 'credential-secret'];
        yield 'channel control character' => [['backend' => ['channel' => "credential-secret\n"]], 'credential-secret'];
        yield 'channel invalid UTF-8' => [['backend' => ['channel' => "credential-secret\xFF"]], 'credential-secret'];
        yield 'channel is not a string' => [['backend' => ['channel' => 1]], 'not-present'];
        yield 'unknown level' => [['backend' => ['minimum_level' => 'verbose-secret']], 'verbose-secret'];
        yield 'uppercase level' => [['backend' => ['minimum_level' => 'INFO']], 'INFO'];
        yield 'numeric level' => [['backend' => ['minimum_level' => 200]], '200'];
    }
}
