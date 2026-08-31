<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Delivery;

use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Delivery\AuditOutboxException;
use BlackOps\Internal\Audit\Delivery\AuditOutboxFailure;
use PHPUnit\Framework\TestCase;

final class AuditOutboxConfigurationTest extends TestCase
{
    public function testDefaultsAndClosedBounds(): void
    {
        $defaults = new AuditOutboxConfiguration();
        self::assertSame(300, $defaults->leaseDurationSeconds);
        self::assertSame(5, $defaults->maxAttempts);
        self::assertSame(1, $defaults->backoffBaseSeconds);
        self::assertSame(3600, $defaults->backoffCapSeconds);

        $minimum = new AuditOutboxConfiguration(1, 1, 1, 1);
        self::assertSame(1, $minimum->leaseDurationSeconds);
        self::assertSame(1, $minimum->maxAttempts);
        self::assertSame(1, $minimum->backoffBaseSeconds);
        self::assertSame(1, $minimum->backoffCapSeconds);

        $maximum = new AuditOutboxConfiguration(86400, 32, 3600, 86400);
        self::assertSame(86400, $maximum->leaseDurationSeconds);
        self::assertSame(32, $maximum->maxAttempts);
        self::assertSame(3600, $maximum->backoffBaseSeconds);
        self::assertSame(86400, $maximum->backoffCapSeconds);
    }

    public function testEachOutsideBoundIsConfigurationFailure(): void
    {
        foreach ([
            [0, 5, 1, 3600],
            [86401, 5, 1, 3600],
            [300, 0, 1, 3600],
            [300, 33, 1, 3600],
            [300, 5, 0, 3600],
            [300, 5, 3601, 3600],
            [300, 5, 1, 0],
            [300, 5, 1, 86401],
        ] as $arguments) {
            try {
                new AuditOutboxConfiguration(...$arguments);
                self::fail('out-of-range configuration must fail');
            } catch (AuditOutboxException $exception) {
                self::assertSame(AuditOutboxFailure::ConfigurationInvalid, $exception->failure());
                self::assertSame('configuration_invalid', $exception->code());
                self::assertSame('Audit outbox state transition failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testBackoffBaseMayExceedCap(): void
    {
        $configuration = new AuditOutboxConfiguration(17, 32, 3600, 1);

        self::assertSame(17, $configuration->leaseDurationSeconds);
        self::assertSame(32, $configuration->maxAttempts);
        self::assertSame(3600, $configuration->backoffBaseSeconds);
        self::assertSame(1, $configuration->backoffCapSeconds);
    }

    public function testConfigurationIsReadonly(): void
    {
        $configuration = new AuditOutboxConfiguration();
        $reflection = new \ReflectionClass($configuration);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        foreach (['leaseDurationSeconds', 'maxAttempts', 'backoffBaseSeconds', 'backoffCapSeconds'] as $property) {
            self::assertTrue($reflection->getProperty($property)->isPublic());
            self::assertTrue($reflection->getProperty($property)->isReadOnly());
        }
    }

    public function testFailureSurfacesAreFixedAndValueFree(): void
    {
        try {
            new AuditOutboxConfiguration(0);
            self::fail('invalid configuration must fail');
        } catch (AuditOutboxException $exception) {
            self::assertSame(AuditOutboxFailure::ConfigurationInvalid, $exception->failure());
            self::assertSame('configuration_invalid', $exception->code());
            self::assertSame('Audit outbox state transition failed.', $exception->getMessage());
            self::assertSame(0, $exception->getCode());
            self::assertNull($exception->getPrevious());
        }
    }
}
