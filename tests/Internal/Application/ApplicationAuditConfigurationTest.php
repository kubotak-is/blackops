<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationAuditConfiguration;
use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use PHPUnit\Framework\TestCase;

final class ApplicationAuditConfigurationTest extends TestCase
{
    public function testMissingAuditAndOutboxUseOneAcceptedConfiguration(): void
    {
        $missing = ApplicationAuditConfiguration::fromConfiguration([]);
        $missingAudit = ApplicationAuditConfiguration::fromConfiguration(['database' => []]);
        $missingOutbox = ApplicationAuditConfiguration::fromConfiguration(['audit' => []]);
        foreach ([$missing, $missingAudit, $missingOutbox] as $configuration) {
            self::assertInstanceOf(AuditOutboxConfiguration::class, $configuration->outbox());
            self::assertSame(300, $configuration->outbox()->leaseDurationSeconds);
            self::assertSame(5, $configuration->outbox()->maxAttempts);
            self::assertSame(1, $configuration->outbox()->backoffBaseSeconds);
            self::assertSame(3600, $configuration->outbox()->backoffCapSeconds);
        }
    }

    public function testClosedNativeIntegerConfigurationIsPassedToC1(): void
    {
        $configuration = ApplicationAuditConfiguration::fromConfiguration([
            'audit' => [
                'outbox' => [
                    'lease_duration_seconds' => 42,
                    'max_attempts' => 7,
                    'backoff_base_seconds' => 3,
                    'backoff_cap_seconds' => 900,
                ],
            ],
        ]);
        self::assertSame(42, $configuration->outbox()->leaseDurationSeconds);
        self::assertSame(7, $configuration->outbox()->maxAttempts);
        self::assertSame(3, $configuration->outbox()->backoffBaseSeconds);
        self::assertSame(900, $configuration->outbox()->backoffCapSeconds);
    }

    public function testEmptyOutboxObjectUsesAcceptedDefaults(): void
    {
        $configuration = ApplicationAuditConfiguration::fromConfiguration(['audit' => ['outbox' => []]]);

        self::assertSame(300, $configuration->outbox()->leaseDurationSeconds);
        self::assertSame(5, $configuration->outbox()->maxAttempts);
        self::assertSame(1, $configuration->outbox()->backoffBaseSeconds);
        self::assertSame(3600, $configuration->outbox()->backoffCapSeconds);
    }

    public function testEveryC1MemberAcceptsItsClosedBoundsAndRejectsOutsideThem(): void
    {
        $bounds = [
            'lease_duration_seconds' => [1, 86_400],
            'max_attempts' => [1, 32],
            'backoff_base_seconds' => [1, 3_600],
            'backoff_cap_seconds' => [1, 86_400],
        ];
        foreach ($bounds as $member => [$lower, $upper]) {
            foreach ([$lower, $upper] as $value) {
                $configuration = ApplicationAuditConfiguration::fromConfiguration([
                    'audit' => ['outbox' => [$member => $value]],
                ]);
                self::assertSame($value, $configuration->outbox()->{$this->property($member)});
            }
            try {
                ApplicationAuditConfiguration::fromConfiguration([
                    'audit' => ['outbox' => [$member => $lower - 1]],
                ]);
                self::fail($member . ' lower bound was not enforced');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $exception->failure());
            }
            try {
                ApplicationAuditConfiguration::fromConfiguration([
                    'audit' => ['outbox' => [$member => $upper + 1]],
                ]);
                self::fail($member . ' upper bound was not enforced');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $exception->failure());
            }
        }
    }

    public function testEveryC1MemberRejectsNonNativeScalarAndArrayValues(): void
    {
        foreach ([
            'lease_duration_seconds',
            'max_attempts',
            'backoff_base_seconds',
            'backoff_cap_seconds',
        ] as $member) {
            foreach (['1', 1.0, true, null, []] as $value) {
                try {
                    ApplicationAuditConfiguration::fromConfiguration([
                        'audit' => ['outbox' => [$member => $value]],
                    ]);
                    self::fail($member . ' accepted a non-native value');
                } catch (AuditDeliveryException $exception) {
                    self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $exception->failure());
                    self::assertNull($exception->getPrevious());
                }
            }
        }
    }

    public function testUnknownParentsMembersNullAndNonNativeValuesAreConfigurationFailures(): void
    {
        $invalid = [
            ['audit' => null],
            ['audit' => ['outbox' => null]],
            ['audit' => ['future' => []]],
            ['audit' => ['outbox' => ['unknown' => 1]]],
            ['audit' => ['outbox' => ['max_attempts' => '7']]],
            ['audit' => ['outbox' => ['max_attempts' => 1.0]]],
            ['audit' => ['outbox' => ['max_attempts' => null]]],
            ['audit' => ['outbox' => ['max_attempts' => true]]],
            ['audit' => ['outbox' => ['max_attempts' => 0]]],
            ['audit' => ['outbox' => ['lease_duration_seconds' => 86401]]],
        ];
        foreach ($invalid as $value) {
            try {
                ApplicationAuditConfiguration::fromConfiguration($value);
                self::fail('invalid audit configuration was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $exception->failure());
                self::assertSame('configuration_invalid', $exception->code());
                self::assertNull($exception->getPrevious());
                self::assertSame('Audit delivery failed.', $exception->getMessage());
            }
        }
    }

    private function property(string $member): string
    {
        return match ($member) {
            'lease_duration_seconds' => 'leaseDurationSeconds',
            'max_attempts' => 'maxAttempts',
            'backoff_base_seconds' => 'backoffBaseSeconds',
            'backoff_cap_seconds' => 'backoffCapSeconds',
        };
    }
}
