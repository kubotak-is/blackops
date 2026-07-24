<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationOutboxRelayConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ApplicationOutboxRelayConfigurationTest extends TestCase
{
    public function testDefaultsAndRequiredId(): void
    {
        $configuration = ApplicationOutboxRelayConfiguration::fromConfiguration([
            'execution' => ['outbox_relay' => ['id' => 'relay-test']],
        ]);
        self::assertSame(50, $configuration->batchSize);
        self::assertSame(60, $configuration->leaseSeconds);
        self::assertSame(10, $configuration->heartbeatSeconds);
        self::assertSame(20, $configuration->graceSeconds);
        self::assertSame(8, $configuration->maxAttempts);
        self::assertSame(1, $configuration->initialBackoffSeconds);
        self::assertSame(300, $configuration->maxBackoffSeconds);
        self::assertSame(1000, $configuration->pollIntervalMilliseconds);
    }

    public function testRejectsInvalidRelationshipsAndValues(): void
    {
        $cases = [
            ['id' => ''],
            ['id' => 'relay', 'heartbeat_seconds' => 60],
            ['id' => 'relay', 'initial_backoff_seconds' => 301],
            ['id' => 'relay', 'batch_size' => 0],
            ['id' => 'relay', 'max_attempts' => '8'],
        ];
        foreach ($cases as $relay) {
            try {
                ApplicationOutboxRelayConfiguration::fromConfiguration(['execution' => ['outbox_relay' => $relay]]);
                self::fail('Invalid relay configuration was accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
