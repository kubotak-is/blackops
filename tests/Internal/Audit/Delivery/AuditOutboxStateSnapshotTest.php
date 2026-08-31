<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Delivery;

use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Delivery\AuditOutboxException;
use BlackOps\Internal\Audit\Delivery\AuditOutboxFailure;
use BlackOps\Internal\Audit\Delivery\AuditOutboxState;
use BlackOps\Internal\Audit\Delivery\AuditOutboxStateSnapshot;
use PHPUnit\Framework\TestCase;

final class AuditOutboxStateSnapshotTest extends TestCase
{
    public function testPendingGenesisHasTheExactDetachedSixFieldShape(): void
    {
        $configuration = new AuditOutboxConfiguration();
        $availableAt = '2026-09-01T03:00:00.123456Z';
        $snapshot = AuditOutboxStateSnapshot::pending($availableAt, $configuration);

        self::assertSame(
            [
                'state' => 'pending',
                'attempt' => 0,
                'availableAt' => $availableAt,
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'version' => 1,
            ],
            $snapshot->toArray(),
        );
        self::assertSame(AuditOutboxState::Pending, $snapshot->state());
        self::assertSame(0, $snapshot->attempt());
        self::assertSame($availableAt, $snapshot->availableAt());
        self::assertNull($snapshot->leaseFencingToken());
        self::assertNull($snapshot->leaseExpiresAt());
        self::assertSame(1, $snapshot->version());
    }

    public function testEveryStateMatrixBoundaryIsAccepted(): void
    {
        $configuration = new AuditOutboxConfiguration();
        $token = $this->token('1');
        $expiresAt = '2026-09-01T03:05:00.123456Z';
        $states = [
            [
                'state' => 'pending',
                'attempt' => 0,
                'availableAt' => '2026-09-01T03:00:00.123456Z',
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'version' => 1,
            ],
            [
                'state' => 'pending',
                'attempt' => 4,
                'availableAt' => '2026-09-01T03:00:00.123456Z',
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'version' => 9_007_199_254_740_991,
            ],
            [
                'state' => 'leased',
                'attempt' => 1,
                'availableAt' => null,
                'leaseFencingToken' => $token,
                'leaseExpiresAt' => $expiresAt,
                'version' => 1,
            ],
            [
                'state' => 'leased',
                'attempt' => 5,
                'availableAt' => null,
                'leaseFencingToken' => $token,
                'leaseExpiresAt' => $expiresAt,
                'version' => 2,
            ],
            [
                'state' => 'relayed',
                'attempt' => 1,
                'availableAt' => null,
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'version' => 3,
            ],
            [
                'state' => 'relayed',
                'attempt' => 5,
                'availableAt' => null,
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'version' => 4,
            ],
            [
                'state' => 'poisoned',
                'attempt' => 1,
                'availableAt' => null,
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'version' => 5,
            ],
            [
                'state' => 'poisoned',
                'attempt' => 5,
                'availableAt' => null,
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'version' => 6,
            ],
        ];

        foreach ($states as $data) {
            self::assertSame($data, AuditOutboxStateSnapshot::fromArray($data, $configuration)->toArray());
        }
    }

    public function testCallerArrayMutationDoesNotAlterTheCapturedSnapshot(): void
    {
        $configuration = new AuditOutboxConfiguration();
        $data = [
            'state' => 'leased',
            'attempt' => 2,
            'availableAt' => null,
            'leaseFencingToken' => $this->token('2'),
            'leaseExpiresAt' => '2026-09-01T03:05:00.123456Z',
            'version' => 7,
        ];
        $snapshot = AuditOutboxStateSnapshot::fromArray($data, $configuration);
        $expected = $data;
        $data['leaseFencingToken'] = $this->token('3');
        $data['leaseExpiresAt'] = '2026-09-01T03:06:00.123456Z';
        $data['attempt'] = 3;

        self::assertSame($expected, $snapshot->toArray());
        $copy = $snapshot->toArray();
        $copy['leaseFencingToken'] = $this->token('4');
        self::assertSame($expected, $snapshot->toArray());
    }

    public function testFixedWidthGuardRejectsMalformedTopLevelShapes(): void
    {
        $configuration = new AuditOutboxConfiguration();
        $valid = [
            'state' => 'pending',
            'attempt' => 0,
            'availableAt' => '2026-09-01T03:00:00.123456Z',
            'leaseFencingToken' => null,
            'leaseExpiresAt' => null,
            'version' => 1,
        ];
        $invalid = [
            array_slice($valid, 0, 5, true),
            [...$valid, 'unknown' => 'wide'],
            array_values($valid),
            [
                'attempt' => 0,
                'state' => 'pending',
                'availableAt' => $valid['availableAt'],
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'version' => 1,
            ],
        ];

        foreach ($invalid as $data) {
            $this->assertFailure(static fn() => AuditOutboxStateSnapshot::fromArray($data, $configuration));
        }

        $deep = $valid;
        $deep['availableAt'] = ['unknown' => ['nested' => 'value']];
        $this->assertFailure(static fn() => AuditOutboxStateSnapshot::fromArray($deep, $configuration));
    }

    public function testScalarAndStateGrammarIsStrict(): void
    {
        $configuration = new AuditOutboxConfiguration();
        $valid = [
            'state' => 'pending',
            'attempt' => 0,
            'availableAt' => '2026-09-01T03:00:00.123456Z',
            'leaseFencingToken' => null,
            'leaseExpiresAt' => null,
            'version' => 1,
        ];
        foreach ([
            'state' => AuditOutboxState::Pending,
            'attempt' => 0.0,
            'availableAt' => 123,
            'leaseFencingToken' => 123,
            'leaseExpiresAt' => 123,
            'version' => '1',
        ] as $field => $value) {
            $data = $valid;
            $data[$field] = $value;
            $this->assertFailure(static fn() => AuditOutboxStateSnapshot::fromArray($data, $configuration));
        }

        foreach (['unknown', 'PENDING', 'leased'] as $state) {
            $data = $valid;
            $data['state'] = $state;
            $this->assertFailure(static fn() => AuditOutboxStateSnapshot::fromArray($data, $configuration));
        }
    }

    public function testNullabilityAttemptAndVersionBoundariesAreClosed(): void
    {
        $configuration = new AuditOutboxConfiguration();
        $pending = [
            'state' => 'pending',
            'attempt' => 0,
            'availableAt' => '2026-09-01T03:00:00.123456Z',
            'leaseFencingToken' => null,
            'leaseExpiresAt' => null,
            'version' => 1,
        ];
        $leased = [
            'state' => 'leased',
            'attempt' => 1,
            'availableAt' => null,
            'leaseFencingToken' => $this->token('5'),
            'leaseExpiresAt' => '2026-09-01T03:05:00.123456Z',
            'version' => 1,
        ];
        $terminal = [
            'state' => 'relayed',
            'attempt' => 1,
            'availableAt' => null,
            'leaseFencingToken' => null,
            'leaseExpiresAt' => null,
            'version' => 1,
        ];

        foreach ([
            ['source' => $pending, 'field' => 'availableAt', 'value' => null],
            ['source' => $pending, 'field' => 'leaseFencingToken', 'value' => $this->token('6')],
            ['source' => $pending, 'field' => 'leaseExpiresAt', 'value' => '2026-09-01T03:05:00.123456Z'],
            ['source' => $pending, 'field' => 'attempt', 'value' => -1],
            ['source' => $pending, 'field' => 'attempt', 'value' => 5],
            ['source' => $leased, 'field' => 'attempt', 'value' => 0],
            ['source' => $leased, 'field' => 'attempt', 'value' => 6],
            ['source' => $leased, 'field' => 'availableAt', 'value' => '2026-09-01T03:00:00.123456Z'],
            ['source' => $leased, 'field' => 'leaseFencingToken', 'value' => null],
            ['source' => $leased, 'field' => 'leaseExpiresAt', 'value' => null],
            ['source' => $terminal, 'field' => 'attempt', 'value' => 0],
            ['source' => $terminal, 'field' => 'attempt', 'value' => 6],
            ['source' => $terminal, 'field' => 'availableAt', 'value' => '2026-09-01T03:00:00.123456Z'],
            ['source' => $terminal, 'field' => 'leaseFencingToken', 'value' => $this->token('7')],
            ['source' => $terminal, 'field' => 'leaseExpiresAt', 'value' => '2026-09-01T03:05:00.123456Z'],
        ] as $case) {
            $data = $case['source'];
            $data[$case['field']] = $case['value'];
            $this->assertFailure(static fn() => AuditOutboxStateSnapshot::fromArray($data, $configuration));
        }

        foreach ([0, 9_007_199_254_740_992] as $version) {
            $data = $pending;
            $data['version'] = $version;
            $this->assertFailure(static fn() => AuditOutboxStateSnapshot::fromArray($data, $configuration));
        }
    }

    public function testTimestampAndLeaseTokenGrammarIsCanonical(): void
    {
        $configuration = new AuditOutboxConfiguration();
        $pending = [
            'state' => 'pending',
            'attempt' => 0,
            'availableAt' => '2026-09-01T03:00:00.123456Z',
            'leaseFencingToken' => null,
            'leaseExpiresAt' => null,
            'version' => 1,
        ];
        foreach ([
            '2026-02-30T03:00:00.123456Z',
            '2026-09-01T03:00:00Z',
            '2026-09-01T03:00:00.123456+00:00',
            '2026-09-01T03:00:00.1234567Z',
            '10000-01-01T00:00:00.000000Z',
        ] as $timestamp) {
            $data = $pending;
            $data['availableAt'] = $timestamp;
            $this->assertFailure(static fn() => AuditOutboxStateSnapshot::fromArray($data, $configuration));
        }

        $leased = [
            'state' => 'leased',
            'attempt' => 1,
            'availableAt' => null,
            'leaseFencingToken' => $this->token('8'),
            'leaseExpiresAt' => '2026-09-01T03:05:00.123456Z',
            'version' => 1,
        ];
        foreach ([
            '018f0a00-0000-8000-8000-000000000001',
            '018f0a00-0000-7000-c000-000000000001',
            '018F0A00-0000-7000-8000-000000000001',
            'not-a-uuid',
        ] as $token) {
            $data = $leased;
            $data['leaseFencingToken'] = $token;
            $this->assertFailure(static fn() => AuditOutboxStateSnapshot::fromArray($data, $configuration));
        }
    }

    public function testInspectionAndSerializationSurfacesAreValueFree(): void
    {
        $configuration = new AuditOutboxConfiguration();
        $token = $this->token('9');
        $timestamp = '2026-09-01T03:05:00.123456Z';
        $snapshot = AuditOutboxStateSnapshot::fromArray([
            'state' => 'leased',
            'attempt' => 1,
            'availableAt' => null,
            'leaseFencingToken' => $token,
            'leaseExpiresAt' => $timestamp,
            'version' => 1,
        ], $configuration);

        ob_start();
        var_dump($snapshot);
        $dump = ob_get_clean();
        self::assertIsString($dump);
        $surfaces = [
            $dump,
            print_r($snapshot, true),
            var_export($snapshot, true),
            json_encode($snapshot, JSON_THROW_ON_ERROR),
            serialize($snapshot),
        ];
        foreach ($surfaces as $surface) {
            self::assertStringNotContainsString($token, $surface);
            self::assertStringNotContainsString($timestamp, $surface);
        }
        self::assertSame([], $snapshot->__debugInfo());
        self::assertSame([], $snapshot->__serialize());
        self::assertFalse($snapshot instanceof \JsonSerializable);
        self::assertFalse($snapshot instanceof \Stringable);
        self::assertFalse(method_exists($snapshot, '__set_state'));

        try {
            unserialize(serialize($snapshot));
            self::fail('unserialization must fail closed');
        } catch (AuditOutboxException $exception) {
            self::assertSame(AuditOutboxFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        try {
            $snapshot->__unserialize([]);
            self::fail('unserialization must fail closed');
        } catch (AuditOutboxException $exception) {
            self::assertSame(AuditOutboxFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testPendingRejectsInvalidTimestamp(): void
    {
        try {
            AuditOutboxStateSnapshot::pending('not-a-timestamp', new AuditOutboxConfiguration());
            self::fail('invalid pending timestamp must fail');
        } catch (AuditOutboxException $exception) {
            self::assertSame(AuditOutboxFailure::IntegrityInvalid, $exception->failure());
            self::assertSame('Audit outbox state transition failed.', $exception->getMessage());
        }
    }

    public function testFailureSurfacesDoNotCarryInputCanaries(): void
    {
        $timestamp = '2026-09-01T03:05:00.123456Z';
        $canary = 'snapshot-canary-7f0c';
        $data = [
            'state' => 'pending',
            'attempt' => 0,
            'availableAt' => $canary,
            'leaseFencingToken' => null,
            'leaseExpiresAt' => null,
            'version' => 1,
        ];

        try {
            AuditOutboxStateSnapshot::fromArray($data, new AuditOutboxConfiguration());
            self::fail('invalid timestamp must fail');
        } catch (AuditOutboxException $exception) {
            $surfaces = [
                $exception->getMessage(),
                (string) $exception->getPrevious(),
                $exception->getTraceAsString(),
                var_export($exception->getTrace(), true),
                print_r($exception, true),
                var_export($exception, true),
                json_encode($exception),
            ];
            try {
                $surfaces[] = serialize($exception);
            } catch (\Throwable $serializationFailure) {
                $surfaces[] = $serializationFailure->getMessage();
            }
            foreach ($surfaces as $surface) {
                self::assertIsString($surface);
                self::assertStringNotContainsString($canary, $surface);
                self::assertStringNotContainsString($timestamp, $surface);
            }
            self::assertNull($exception->getPrevious());
        }
    }

    private function assertFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('malformed snapshot must fail');
        } catch (AuditOutboxException $exception) {
            self::assertSame(AuditOutboxFailure::IntegrityInvalid, $exception->failure());
            self::assertSame('integrity_invalid', $exception->code());
            self::assertSame('Audit outbox state transition failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    private function token(string $last): string
    {
        return '018f0a00-0000-7000-8000-' . str_pad($last, 12, '0', STR_PAD_LEFT);
    }
}
