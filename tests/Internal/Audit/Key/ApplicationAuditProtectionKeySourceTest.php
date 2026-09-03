<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Key;

use BlackOps\Audit\AuditProtectionKeyProvider;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Key\ApplicationAuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\StorageProtection\StorageKey;
use PHPUnit\Framework\TestCase;

final class ApplicationAuditProtectionKeySourceTest extends TestCase
{
    public function testCanonicalPartitionsAndFourProviderRoutesAreExplicit(): void
    {
        $provider = $this->createMock(AuditProtectionKeyProvider::class);
        $opaque = new StorageKey('opaque-key', str_repeat('o', 32));
        $record = new StorageKey('record-key', str_repeat('r', 32));
        $outbox = new StorageKey('outbox-key', str_repeat('b', 32));
        $historicalRecord = new StorageKey('record-old', str_repeat('s', 32));
        $historicalOutbox = new StorageKey('outbox-old', str_repeat('t', 32));
        $tenant = new AuditTypedReference('tenant', 'tenant-1');
        $partition = new AuditPartitionIdDeriver(new AuditJcsCodec())->derive($tenant);

        $provider->expects(self::once())->method('activeRecordKey')->with($partition)->willReturn($record);
        $provider
            ->expects(self::once())
            ->method('recordKey')
            ->with('record-old', $partition)
            ->willReturn($historicalRecord);
        $provider->expects(self::once())->method('activeOutboxKey')->with($partition)->willReturn($outbox);
        $provider
            ->expects(self::once())
            ->method('outboxKey')
            ->with('outbox-old', $partition)
            ->willReturn($historicalOutbox);

        $source = new ApplicationAuditProtectionKeySource(
            $provider,
            new AuditPartitionIdDeriver(new AuditJcsCodec()),
            $opaque,
        );
        self::assertSame($record, $source->activeKey($tenant, AuditProtectionPurpose::AuditRecord));
        self::assertSame($historicalRecord, $source->key('record-old', $tenant, AuditProtectionPurpose::AuditRecord));
        self::assertSame($outbox, $source->activeKey($tenant, AuditProtectionPurpose::AuditOutboxRecord));
        self::assertSame($historicalOutbox, $source->key(
            'outbox-old',
            $tenant,
            AuditProtectionPurpose::AuditOutboxRecord,
        ));
    }

    public function testSystemPartitionUsesCanonicalGlobalValue(): void
    {
        $provider = $this->createMock(AuditProtectionKeyProvider::class);
        $key = new StorageKey('record-key', str_repeat('r', 32));
        $provider->expects(self::once())->method('activeRecordKey')->with('system:global')->willReturn($key);

        $source = new ApplicationAuditProtectionKeySource(
            $provider,
            new AuditPartitionIdDeriver(new AuditJcsCodec()),
            new StorageKey('opaque-key', str_repeat('o', 32)),
        );
        self::assertSame($key, $source->activeKey(null, AuditProtectionPurpose::AuditRecord));
    }

    public function testSystemAndTenantPurposesCoverActiveAndHistoricalProviderRoutes(): void
    {
        $provider = new class implements AuditProtectionKeyProvider {
            /** @var list<array{route:string,partition:string,keyId:?string}> */
            public array $calls = [];

            public function activeRecordKey(string $partitionId): StorageKey
            {
                $this->calls[] = ['route' => 'active-record', 'partition' => $partitionId, 'keyId' => null];

                return new StorageKey('record-active', str_repeat('r', 32));
            }

            public function recordKey(string $keyId, string $partitionId): StorageKey
            {
                $this->calls[] = ['route' => 'record', 'partition' => $partitionId, 'keyId' => $keyId];

                return new StorageKey($keyId, str_repeat('r', 32));
            }

            public function activeOutboxKey(string $partitionId): StorageKey
            {
                $this->calls[] = ['route' => 'active-outbox', 'partition' => $partitionId, 'keyId' => null];

                return new StorageKey('outbox-active', str_repeat('b', 32));
            }

            public function outboxKey(string $keyId, string $partitionId): StorageKey
            {
                $this->calls[] = ['route' => 'outbox', 'partition' => $partitionId, 'keyId' => $keyId];

                return new StorageKey($keyId, str_repeat('b', 32));
            }
        };
        $partitions = new AuditPartitionIdDeriver(new AuditJcsCodec());
        $source = new ApplicationAuditProtectionKeySource(
            $provider,
            $partitions,
            new StorageKey('opaque-key', str_repeat('o', 32)),
        );
        $tenant = new AuditTypedReference('tenant', 'tenant-matrix');
        $cases = [
            ['label' => 'system', 'tenant' => null, 'partition' => 'system:global'],
            ['label' => 'tenant', 'tenant' => $tenant, 'partition' => $partitions->derive($tenant)],
        ];

        foreach ($cases as $case) {
            $label = $case['label'];
            $tenantValue = $case['tenant'];
            $partition = $case['partition'];
            $recordHistory = 'record-history-' . $label;
            $outboxHistory = 'outbox-history-' . $label;
            self::assertSame(
                'record-active',
                $source->activeKey($tenantValue, AuditProtectionPurpose::AuditRecord)->id(),
            );
            self::assertSame(
                $recordHistory,
                $source->key($recordHistory, $tenantValue, AuditProtectionPurpose::AuditRecord)->id(),
            );
            self::assertSame(
                'outbox-active',
                $source->activeKey($tenantValue, AuditProtectionPurpose::AuditOutboxRecord)->id(),
            );
            self::assertSame(
                $outboxHistory,
                $source->key($outboxHistory, $tenantValue, AuditProtectionPurpose::AuditOutboxRecord)->id(),
            );
            self::assertSame(
                [
                    ['route' => 'active-record', 'partition' => $partition, 'keyId' => null],
                    ['route' => 'record', 'partition' => $partition, 'keyId' => $recordHistory],
                    ['route' => 'active-outbox', 'partition' => $partition, 'keyId' => null],
                    ['route' => 'outbox', 'partition' => $partition, 'keyId' => $outboxHistory],
                ],
                array_slice($provider->calls, -4),
            );
        }
    }

    public function testHistoricalMissingAndThrowingProviderRoutesContractUnavailableWithoutCrypto(): void
    {
        $opaque = new StorageKey('opaque-key', str_repeat('o', 32));
        $tenant = new AuditTypedReference('tenant', 'tenant-history');
        foreach ([
            [AuditProtectionPurpose::AuditRecord,       'recordKey'],
            [AuditProtectionPurpose::AuditOutboxRecord, 'outboxKey'],
        ] as [$purpose, $method]) {
            foreach (['missing', 'throwing'] as $mode) {
                $provider = $this->createMock(AuditProtectionKeyProvider::class);
                $expectation = $provider->expects(self::once())->method($method);
                $expectation->with('history-missing', self::anything());
                $expectation->willThrowException(
                    $mode === 'missing'
                        ? new \TypeError('historical key is missing')
                        : new \RuntimeException('historical-provider-canary'),
                );
                $source = new ApplicationAuditProtectionKeySource(
                    $provider,
                    new AuditPartitionIdDeriver(new AuditJcsCodec()),
                    $opaque,
                );
                $cryptoCalls = 0;
                try {
                    $key = $source->key('history-missing', $tenant, $purpose);
                    $cryptoCalls++;
                    self::fail('historical provider failure was accepted');
                } catch (AuditProtectionException $exception) {
                    self::assertSame('Audit protected data is unavailable.', $exception->getMessage());
                    self::assertNull($exception->getPrevious());
                    self::assertStringNotContainsString('historical-provider-canary', $exception->getTraceAsString());
                }
                self::assertSame(0, $cryptoCalls);
            }
        }
    }

    public function testOpaqueReferenceAndMaterialReuseAreRejectedForBothPurposesBeforeCrypto(): void
    {
        $opaque = new StorageKey('opaque-key', str_repeat('o', 32));
        foreach ([
            ['label' => 'reference', 'key' => $opaque],
            ['label' => 'material', 'key' => new StorageKey('safe-key', $opaque->material())],
        ] as $case) {
            foreach ([
                AuditProtectionPurpose::AuditRecord,
                AuditProtectionPurpose::AuditOutboxRecord,
            ] as $purpose) {
                $provider = $this->createMock(AuditProtectionKeyProvider::class);
                $method = $purpose === AuditProtectionPurpose::AuditRecord ? 'activeRecordKey' : 'activeOutboxKey';
                $provider->expects(self::once())->method($method)->with('system:global')->willReturn($case['key']);
                $source = new ApplicationAuditProtectionKeySource(
                    $provider,
                    new AuditPartitionIdDeriver(new AuditJcsCodec()),
                    $opaque,
                );
                $cryptoCalls = 0;
                try {
                    $key = $source->activeKey(null, $purpose);
                    $cryptoCalls++;
                    self::fail('opaque key reuse was accepted for ' . $case['label']);
                } catch (AuditProtectionException $exception) {
                    self::assertSame('Audit protected data is unavailable.', $exception->getMessage());
                    self::assertNull($exception->getPrevious());
                }
                self::assertSame(0, $cryptoCalls);
            }
        }
    }

    public function testHistoricalOpaqueReferenceAndMaterialReuseAreRejectedWithMatchingIdsBeforeCrypto(): void
    {
        $opaque = new StorageKey('opaque-key', str_repeat('o', 32));
        $partitions = new AuditPartitionIdDeriver(new AuditJcsCodec());
        $tenant = new AuditTypedReference('tenant', 'tenant-history');
        $partition = $partitions->derive($tenant);
        foreach ([
            ['label' => 'reference', 'keyId' => $opaque->id(), 'key' => $opaque],
            [
                'label' => 'material',
                'keyId' => 'safe-key',
                'key' => new StorageKey('safe-key', $opaque->material()),
            ],
        ] as $case) {
            foreach ([
                AuditProtectionPurpose::AuditRecord,
                AuditProtectionPurpose::AuditOutboxRecord,
            ] as $purpose) {
                $provider = $this->createMock(AuditProtectionKeyProvider::class);
                $method = $purpose === AuditProtectionPurpose::AuditRecord ? 'recordKey' : 'outboxKey';
                $provider
                    ->expects(self::once())
                    ->method($method)
                    ->with($case['keyId'], $partition)
                    ->willReturn($case['key']);
                $source = new ApplicationAuditProtectionKeySource($provider, $partitions, $opaque);
                $cryptoCalls = 0;
                try {
                    $source->key($case['keyId'], $tenant, $purpose);
                    $cryptoCalls++;
                    self::fail('historical opaque ' . $case['label'] . ' reuse was accepted');
                } catch (AuditProtectionException $exception) {
                    self::assertSame('Audit protected data is unavailable.', $exception->getMessage());
                    self::assertNull($exception->getPrevious());
                }
                self::assertSame(0, $cryptoCalls);
            }
        }
    }

    public function testProviderErrorsWrongReturnsAndOpaqueReuseAreFixedUnavailableFailures(): void
    {
        $opaque = new StorageKey('opaque-key', str_repeat('o', 32));
        $cases = [
            function (AuditProtectionKeyProvider $provider): void {
                $provider
                    ->expects($this->once())
                    ->method('activeRecordKey')
                    ->willThrowException(new \RuntimeException('provider-canary'));
            },
            function (AuditProtectionKeyProvider $provider) use ($opaque): void {
                $provider->expects($this->once())->method('activeRecordKey')->willReturn($opaque);
            },
            function (AuditProtectionKeyProvider $provider) use ($opaque): void {
                $provider
                    ->expects($this->once())
                    ->method('activeRecordKey')
                    ->willReturn(new StorageKey($opaque->id(), str_repeat('r', 32)));
            },
            function (AuditProtectionKeyProvider $provider) use ($opaque): void {
                $provider
                    ->expects($this->once())
                    ->method('activeRecordKey')
                    ->willReturn(new StorageKey('record-key', $opaque->material()));
            },
        ];
        foreach ($cases as $configure) {
            $provider = $this->createMock(AuditProtectionKeyProvider::class);
            $configure($provider);
            $source = new ApplicationAuditProtectionKeySource(
                $provider,
                new AuditPartitionIdDeriver(new AuditJcsCodec()),
                $opaque,
            );
            try {
                $source->activeKey(null, AuditProtectionPurpose::AuditRecord);
                self::fail('invalid protection key was accepted');
            } catch (AuditProtectionException $exception) {
                self::assertSame('Audit protected data is unavailable.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString('provider-canary', $exception->getTraceAsString());
            }
        }
    }

    public function testHistoricalReturnedKeyIdMustMatchRequestedIdForRecordAndOutbox(): void
    {
        $provider = new class implements AuditProtectionKeyProvider {
            public array $recordCalls = [];

            public array $outboxCalls = [];

            public function activeRecordKey(string $partitionId): StorageKey
            {
                return new StorageKey('record-active', str_repeat('a', 32));
            }

            public function recordKey(string $keyId, string $partitionId): StorageKey
            {
                $this->recordCalls[] = [$keyId, $partitionId];

                return new StorageKey('record-returned', str_repeat('r', 32));
            }

            public function activeOutboxKey(string $partitionId): StorageKey
            {
                return new StorageKey('outbox-active', str_repeat('a', 32));
            }

            public function outboxKey(string $keyId, string $partitionId): StorageKey
            {
                $this->outboxCalls[] = [$keyId, $partitionId];

                return new StorageKey('outbox-returned', str_repeat('b', 32));
            }
        };
        $source = new ApplicationAuditProtectionKeySource(
            $provider,
            new AuditPartitionIdDeriver(new AuditJcsCodec()),
            new StorageKey('opaque-key', str_repeat('o', 32)),
        );

        foreach ([
            ['record-requested', AuditProtectionPurpose::AuditRecord],
            ['outbox-requested', AuditProtectionPurpose::AuditOutboxRecord],
        ] as [$keyId, $purpose]) {
            try {
                $source->key($keyId, null, $purpose);
                self::fail('a provider key with a different returned id was accepted');
            } catch (AuditProtectionException $exception) {
                self::assertSame('Audit protected data is unavailable.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
                $trace = var_export($exception->getTrace(), true);
                self::assertStringNotContainsString($keyId, $trace);
                self::assertStringNotContainsString(
                    $purpose === AuditProtectionPurpose::AuditRecord ? 'record-returned' : 'outbox-returned',
                    $trace,
                );
            }
        }
        self::assertSame([['record-requested', 'system:global']], $provider->recordCalls);
        self::assertSame([['outbox-requested', 'system:global']], $provider->outboxCalls);
    }

    public function testSensitiveProviderKeyAndTenantCanariesDoNotCrossRepresentations(): void
    {
        $providerCanary = 'provider-canary-9e2c';
        $keyCanary = 'key-canary-9e2c';
        $tenantCanary = 'tenant-canary-9e2c';
        $provider = new class($providerCanary) implements AuditProtectionKeyProvider {
            public function __construct(
                private string $canary,
            ) {}

            public function activeRecordKey(string $partitionId): StorageKey
            {
                return new StorageKey('record-key', str_repeat('r', 32));
            }

            public function recordKey(string $keyId, string $partitionId): StorageKey
            {
                return new StorageKey('record-key', str_repeat('r', 32));
            }

            public function activeOutboxKey(string $partitionId): StorageKey
            {
                return new StorageKey('outbox-key', str_repeat('b', 32));
            }

            public function outboxKey(string $keyId, string $partitionId): StorageKey
            {
                return new StorageKey('outbox-key', str_repeat('b', 32));
            }
        };
        $source = new ApplicationAuditProtectionKeySource(
            $provider,
            new AuditPartitionIdDeriver(new AuditJcsCodec()),
            new StorageKey($keyCanary, str_repeat('o', 32)),
        );
        $tenant = new AuditTypedReference('tenant', $tenantCanary);
        $representations = [
            var_export($source, true),
            print_r($source, true),
            json_encode($source, JSON_THROW_ON_ERROR),
        ];
        try {
            $representations[] = serialize($source);
        } catch (\Throwable $failure) {
            $representations[] = $failure->getMessage();
        }
        foreach ([$providerCanary, $keyCanary, $tenantCanary] as $canary) {
            foreach ($representations as $representation) {
                self::assertIsString($representation);
                self::assertStringNotContainsString($canary, $representation);
            }
        }

        $throwingProvider = new class implements AuditProtectionKeyProvider {
            public function activeRecordKey(string $partitionId): StorageKey
            {
                throw new \RuntimeException('provider-canary-9e2c');
            }

            public function recordKey(string $keyId, string $partitionId): StorageKey
            {
                throw new \RuntimeException('provider-canary-9e2c');
            }

            public function activeOutboxKey(string $partitionId): StorageKey
            {
                throw new \RuntimeException('provider-canary-9e2c');
            }

            public function outboxKey(string $keyId, string $partitionId): StorageKey
            {
                throw new \RuntimeException('provider-canary-9e2c');
            }
        };
        $throwing = new ApplicationAuditProtectionKeySource(
            $throwingProvider,
            new AuditPartitionIdDeriver(new AuditJcsCodec()),
            new StorageKey($keyCanary, str_repeat('o', 32)),
        );
        try {
            $throwing->activeKey($tenant, AuditProtectionPurpose::AuditRecord);
            self::fail('throwing provider was accepted');
        } catch (AuditProtectionException $exception) {
            $trace = var_export($exception->getTrace(), true) . $exception->getTraceAsString();
            self::assertSame('Audit protected data is unavailable.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            foreach ([$providerCanary, $keyCanary, $tenantCanary] as $canary) {
                self::assertStringNotContainsString($canary, $exception->getMessage());
                self::assertStringNotContainsString($canary, $trace);
            }
        }
    }
}
