<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Relay;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Delivery\AuditOutboxStateSnapshot;
use BlackOps\Internal\Audit\Outbox\AuditOutboxRecord;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelope;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedIngress;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Record\AuditLogicalRecordCandidate;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use BlackOps\Internal\Audit\Relay\AuditIngressCanonicalizer;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\StorageProtection\StorageKey;
use PHPUnit\Framework\TestCase;

final class AuditIngressCanonicalizerTest extends TestCase
{
    public function testCanonicalizesOneClaimedSystemIngressWithoutRetainingItsRawKey(): void
    {
        $context = $this->record('durableSystemIngress');
        $canonical = new AuditIngressCanonicalizer($context['protection'], $context['validator'])->canonicalize(
            $context['record'],
        );

        self::assertSame(
            $context['record']->protectedIngress()->toArray()['contentHash'],
            $this->contentHash($canonical),
        );
        self::assertSame('durable', $canonical->toArray()['policy']['auditDelivery']);
        $this->assertNoRawKey($context['rawKey'], [
            $canonical,
            $canonical->toArray(),
            $context['record'],
            $context['record']->protectedIngress(),
            $context['record']->stateSnapshot(),
        ]);
    }

    public function testCanonicalizesTenantIngressAndUsesOnlyTheOutboxProtectionRole(): void
    {
        $context = $this->record('durableTenantIngress');
        $readerKeys = new RelayFixtureKeySource();
        $canonical = new AuditIngressCanonicalizer(
            $this->protection($context['records'], $context['envelopes'], $readerKeys),
            $context['validator'],
        )->canonicalize($context['record']);

        self::assertSame(['type' => 'account', 'id' => 'tenant-a'], $canonical->toArray()['tenant']);
        self::assertSame(
            $context['record']->protectedIngress()->toArray()['partitionId'],
            $this->partitionId($canonical->toArray()['tenant']),
        );
        self::assertSame([AuditProtectionPurpose::AuditOutboxRecord], $context['keys']->activePurposes);
        self::assertSame([AuditProtectionPurpose::AuditOutboxRecord], $readerKeys->readPurposes);
        self::assertSame(1, $readerKeys->keyCalls);
        self::assertNotSame(
            $readerKeys->keyId(AuditProtectionPurpose::AuditRecord),
            $readerKeys->keyId(AuditProtectionPurpose::AuditOutboxRecord),
        );
        $this->assertNoRawKey($context['rawKey'], [$canonical, $canonical->toArray()]);
    }

    public function testOpensExactlyOneHistoricalOutboxKeyAndPreservesCiphertext(): void
    {
        $writerKeys = new RelayFixtureKeySource('audit-outbox-key-history', str_repeat('h', 32));
        $readerKeys = new RelayFixtureKeySource(
            'audit-outbox-key-history',
            str_repeat('h', 32),
            false,
            false,
            'audit-outbox-key-current',
            str_repeat('n', 32),
        );
        $context = $this->record('durableTenantIngress', $writerKeys);
        $protectedBody = $context['record']->protectedIngress()->protectedBody();
        $canonical = new AuditIngressCanonicalizer(
            $this->protection($context['records'], $context['envelopes'], $readerKeys),
            $context['validator'],
        )->canonicalize($context['record']);

        self::assertSame('durable', $canonical->toArray()['policy']['auditDelivery']);
        self::assertSame($protectedBody, $context['record']->protectedIngress()->protectedBody());
        self::assertSame(0, $readerKeys->activeKeyCalls);
        self::assertSame(1, $readerKeys->keyCalls);
        self::assertSame(['audit-outbox-key-history'], $readerKeys->readKeyIds);
        self::assertSame([AuditProtectionPurpose::AuditOutboxRecord], $readerKeys->readPurposes);
        self::assertSame([str_repeat('h', 32)], $readerKeys->readMaterials);
        self::assertSame(
            'audit-outbox-key-current',
            $readerKeys->activeKeyId(AuditProtectionPurpose::AuditOutboxRecord),
        );
        self::assertSame(
            str_repeat('n', 32),
            $readerKeys->activeKeyMaterial(AuditProtectionPurpose::AuditOutboxRecord),
        );
        self::assertNotSame(
            $readerKeys->activeKeyId(AuditProtectionPurpose::AuditOutboxRecord),
            $readerKeys->readKeyIds[0],
        );
        self::assertNotSame(
            $readerKeys->activeKeyMaterial(AuditProtectionPurpose::AuditOutboxRecord),
            $readerKeys->readMaterials[0],
        );
        self::assertNotSame(
            $readerKeys->keyId(AuditProtectionPurpose::AuditRecord),
            $readerKeys->keyId(AuditProtectionPurpose::AuditOutboxRecord),
        );
        $this->assertNoRawKey($context['rawKey'], [$canonical, $canonical->toArray()]);
    }

    public function testCanonicalizerCaptureAndValidationPathIsSingleAndOrdered(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../../src/Internal/Audit/Relay/AuditIngressCanonicalizer.php');
        self::assertIsString($source);
        self::assertSame([], $this->canonicalizerContractViolations($source));

        $bindingGuard =
            "        if (!\$this->hasBindings(\$bindings, \$envelopeValue)) {\n"
            . "            throw AuditProtectionException::unavailable();\n"
            . "        }\n";
        $mutant = str_replace($bindingGuard, '', $source);
        self::assertNotSame($source, $mutant, 'binding-guard deletion mutant was not applied');
        self::assertNotEmpty($this->canonicalizerContractViolations($mutant));
    }

    public function testEveryOuterBindingSubstitutionFailsClosedIndividually(): void
    {
        foreach ([
            'recordId' => '01941f29-7c00-7000-8000-000000000006',
            'partitionId' => 'tenant:' . str_repeat('a', 64),
            'tenantBinding' => ['type' => 'account', 'id' => 'tenant-a'],
            'ingressOrdinal' => 2,
            'contentHash' => str_repeat('b', 64),
        ] as $field => $replacement) {
            $context = $this->record('durableSystemIngress');
            $value = $context['record']->protectedIngress()->toArray();
            $value[$field] = $replacement;
            $tampered = $this->recordWithIngressValue($context, $value);
            $exception = $this->protectionFailure(
                $tampered,
                $context['validator'],
                $context['protection'],
                'outer ' . $field,
            );
            $this->assertNoRawKey($context['rawKey'], [$exception]);
        }
    }

    public function testCodecRejectsNonDurablePolicyLogicalShapeCatalogAndEvidenceFailures(): void
    {
        $mutations = [
            'codec non-durable policy' => static function (array &$logical): void {
                $policy = $logical['policy'] ?? null;
                if (!is_array($policy)) {
                    throw new \LogicException('fixture policy unavailable');
                }
                $policy['auditDelivery'] = 'required';
                $logical['policy'] = $policy;
            },
            'codec logical shape' => static function (array &$logical): void {
                unset($logical['event']);
            },
            'codec Catalog' => static function (array &$logical): void {
                $event = $logical['event'] ?? null;
                if (!is_array($event)) {
                    throw new \LogicException('fixture event unavailable');
                }
                $event['catalogHash'] = str_repeat('f', 64);
                $logical['event'] = $event;
            },
            'codec evidence' => static function (array &$logical): void {
                $evidence = $logical['evidence'] ?? null;
                if (!is_array($evidence)) {
                    throw new \LogicException('fixture evidence unavailable');
                }
                $evidence['version'] = 2;
                $logical['evidence'] = $evidence;
            },
        ];
        foreach ($mutations as $name => $mutation) {
            $context = $this->tamperedPlaintextRecord('durableSystemIngress', $mutation);
            $exception = $this->protectionFailure(
                $context['record'],
                $context['validator'],
                $context['protection'],
                $name,
            );
            $this->assertNoRawKey($context['rawKey'], [$exception]);
        }
    }

    public function testMalformedBopdUnavailableKeyAndKeyIdMismatchFailClosed(): void
    {
        $context = $this->record('durableSystemIngress');
        $value = $context['record']->protectedIngress()->toArray();
        $body = $value['protectedBody'];
        self::assertIsString($body);
        $value['protectedBody'] = 'X' . substr($body, 1);
        $malformed = $this->recordWithIngressValue($context, $value);
        $exception = $this->protectionFailure(
            $malformed,
            $context['validator'],
            $context['protection'],
            'malformed BOPD',
        );
        $this->assertNoRawKey($context['rawKey'], [$exception]);
        self::assertSame(0, $context['keys']->keyCalls);

        $unavailableKeys = new RelayFixtureKeySource('audit-outbox-key', null, true);
        $exception = $this->protectionFailure(
            $context['record'],
            $context['validator'],
            $this->protection($context['records'], $context['envelopes'], $unavailableKeys),
            'unavailable key',
        );
        $this->assertNoRawKey($context['rawKey'], [$exception]);
        self::assertSame(1, $unavailableKeys->keyCalls);
        self::assertSame([AuditProtectionPurpose::AuditOutboxRecord], $unavailableKeys->readPurposes);

        $mismatchedKeys = new RelayFixtureKeySource('audit-outbox-key', null, false, true);
        $exception = $this->protectionFailure(
            $context['record'],
            $context['validator'],
            $this->protection($context['records'], $context['envelopes'], $mismatchedKeys),
            'key-ID mismatch',
        );
        $this->assertNoRawKey($context['rawKey'], [$exception]);
        self::assertSame(1, $mismatchedKeys->keyCalls);
        self::assertSame([AuditProtectionPurpose::AuditOutboxRecord], $mismatchedKeys->readPurposes);
    }

    public function testCiphertextAndAadSwapsFailClosed(): void
    {
        $system = $this->record('durableSystemIngress');
        $tenant = $this->record('durableTenantIngress');

        $ciphertextSwap = $system['record']->protectedIngress()->toArray();
        $ciphertextSwap['protectedBody'] = $tenant['record']->protectedIngress()->protectedBody();
        $exception = $this->protectionFailure(
            $this->recordWithIngressValue($system, $ciphertextSwap),
            $system['validator'],
            $system['protection'],
            'ciphertext swap',
        );
        $this->assertNoRawKey($system['rawKey'], [$exception]);

        $aadSwap = $system['record']->protectedIngress()->toArray();
        $aadSwap['contentHash'] = str_repeat('c', 64);
        $exception = $this->protectionFailure(
            $this->recordWithIngressValue($system, $aadSwap),
            $system['validator'],
            $system['protection'],
            'AAD swap',
        );
        $this->assertNoRawKey($system['rawKey'], [$exception]);
    }

    private function protectionFailure(
        AuditOutboxRecord $record,
        AuditRecordValidator $validator,
        AuditProtectedBodyCodec $protection,
        string $case,
    ): AuditProtectionException {
        try {
            new AuditIngressCanonicalizer($protection, $validator)->canonicalize($record);
            self::fail($case . ' was accepted');
        } catch (AuditProtectionException $exception) {
            self::assertSame('protection_failed', $exception->failure()->value);
            self::assertNull($exception->getPrevious());
            return $exception;
        }
    }

    /** @param array<string,mixed> $context @param array<string,mixed> $value */
    private function recordWithIngressValue(array $context, array $value): AuditOutboxRecord
    {
        return AuditOutboxRecord::fromParts(
            AuditProtectedIngress::fromArray($value),
            $context['record']->stateSnapshot(),
        );
    }

    /** @return array<string,mixed> */
    private function tamperedPlaintextRecord(string $fixtureName, callable $mutation): array
    {
        $context = $this->record($fixtureName);
        $value = $context['envelope']->toArray();
        $logical = $value['logicalRecord'] ?? null;
        self::assertIsArray($logical);
        $mutation($logical);
        $value['logicalRecord'] = $logical;
        $unsafeEnvelope = $this->unsafeEnvelope($value);
        $protected = $context['protection']->protectOutbox($unsafeEnvelope);
        $context['record'] = AuditOutboxRecord::fromParts($protected, $context['record']->stateSnapshot());
        return $context;
    }

    /** @param array<string,mixed> $value */
    private function unsafeEnvelope(array $value): AuditIngressEnvelope
    {
        $envelope = new \ReflectionClass(AuditIngressEnvelope::class)->newInstanceWithoutConstructor();
        $snapshot = new \ReflectionProperty(AuditIngressEnvelope::class, 'snapshot');
        $snapshot->setValue($envelope, new \SensitiveParameterValue($value));
        return $envelope;
    }

    /** @param array<int,mixed> $values */
    private function assertNoRawKey(string $rawKey, array $values): void
    {
        foreach ($values as $value) {
            $surfaces = [
                print_r($value, true),
                var_export($value, true),
                json_encode($value, JSON_THROW_ON_ERROR),
            ];
            try {
                $surfaces[] = serialize($value);
            } catch (\Throwable $failure) {
                $surfaces[] = $failure->getMessage();
            }
            if ($value instanceof \Throwable) {
                $surfaces[] = json_encode($value->getTrace(), JSON_THROW_ON_ERROR);
                $surfaces[] = $value->getTraceAsString();
            }
            foreach ($surfaces as $surface) {
                self::assertStringNotContainsString($rawKey, $surface);
            }
        }
        self::assertStringNotContainsString($rawKey, json_encode(
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            JSON_THROW_ON_ERROR,
        ));
    }

    /** @param array<string,mixed> $tenant */
    private function partitionId(array $tenant): string
    {
        $jcs = new AuditJcsCodec();
        return new AuditPartitionIdDeriver($jcs)->derive(new AuditTypedReference($tenant['type'], $tenant['id']));
    }

    /** @return array{record:AuditOutboxRecord,validator:AuditRecordValidator,rawKey:string,envelope:AuditIngressEnvelope,records:AuditRecordCodec,envelopes:AuditIngressEnvelopeCodec,protection:AuditProtectedBodyCodec,keys:RelayFixtureKeySource} */
    private function record(string $fixtureName, ?RelayFixtureKeySource $keys = null): array
    {
        $fixture = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../Fixtures/Audit/audit-canonical-record-v1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($fixture);
        $entry = $fixture['ingress'][$fixtureName] ?? null;
        self::assertIsArray($entry);
        $logicalValue = $entry['logical'] ?? null;
        self::assertIsArray($logicalValue);
        $ingressOrdinal = $entry['ingressOrdinal'] ?? null;
        $idempotencyKey = $entry['idempotencyKey'] ?? null;
        self::assertIsInt($ingressOrdinal);
        self::assertIsString($idempotencyKey);

        $jcs = new AuditJcsCodec();
        $lookup = new ImmutableAuditEventCatalogLookup([FrameworkAuditEventCatalog::catalog()]);
        $validator = new AuditRecordValidator($lookup);
        $records = new AuditRecordCodec($validator, $jcs);
        $partitions = new AuditPartitionIdDeriver($jcs);
        $envelopes = new AuditIngressEnvelopeCodec($records, $partitions, $jcs);
        $logical = new CanonicalAuditLogicalRecord($validator->validateLogical(
            new AuditLogicalRecordCandidate($logicalValue),
        ));
        $envelope = $envelopes->create($logical, $ingressOrdinal, $idempotencyKey);
        $keys ??= new RelayFixtureKeySource();
        $protection = $this->protection($records, $envelopes, $keys);
        $protected = $protection->protectOutbox($envelope);
        $recordedAt = $logicalValue['time']['recordedAt'] ?? null;
        self::assertIsString($recordedAt);
        $snapshot = AuditOutboxStateSnapshot::pending($recordedAt, new AuditOutboxConfiguration());

        return [
            'record' => AuditOutboxRecord::fromParts($protected, $snapshot),
            'validator' => $validator,
            'rawKey' => $idempotencyKey,
            'envelope' => $envelope,
            'records' => $records,
            'envelopes' => $envelopes,
            'protection' => $protection,
            'keys' => $keys,
        ];
    }

    private function protection(
        ?AuditRecordCodec $records = null,
        ?AuditIngressEnvelopeCodec $envelopes = null,
        ?RelayFixtureKeySource $keys = null,
    ): AuditProtectedBodyCodec {
        if ($records === null || $envelopes === null) {
            $jcs = new AuditJcsCodec();
            $lookup = new ImmutableAuditEventCatalogLookup([FrameworkAuditEventCatalog::catalog()]);
            $validator = new AuditRecordValidator($lookup);
            $records = new AuditRecordCodec($validator, $jcs);
            $partitions = new AuditPartitionIdDeriver($jcs);
            $envelopes = new AuditIngressEnvelopeCodec($records, $partitions, $jcs);
        }

        return new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            $keys ?? new RelayFixtureKeySource(),
            new RelayFixtureNonceSource(),
            new AuditAssociatedData(),
            $records,
            $envelopes,
        );
    }

    private function contentHash(CanonicalAuditLogicalRecord $logical): string
    {
        return new AuditRecordCodec(
            new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([FrameworkAuditEventCatalog::catalog()])),
            new AuditJcsCodec(),
        )->contentHash($logical);
    }

    /** @return list<string> */
    private function canonicalizerContractViolations(string $source): array
    {
        $body = $this->methodBody($source);
        $violations = [];
        $calls = [
            '$record->protectedIngress()',
            '$ingress->toArray()',
            '$this->protection->unprotectOutbox($ingress)',
            '$envelope->toArray()',
            '$this->hasBindings($bindings, $envelopeValue)',
            'new AuditLogicalRecordCandidate($logical)',
            '$this->validator->validateLogical($candidate)',
            'new CanonicalAuditLogicalRecord($validated)',
        ];
        $positions = [];
        foreach ($calls as $call) {
            if (substr_count($body, $call) !== 1) {
                $violations[] = $call . ' call count changed';
                continue;
            }
            $positions[] = strpos($body, $call);
        }
        if (count($positions) === count($calls)) {
            $ordered = $positions;
            sort($ordered);
            if ($ordered !== $positions) {
                $violations[] = 'capture and validation order changed';
            }
        }

        $normalized = preg_replace('/\s+/', '', $body);
        if (!is_string($normalized)) {
            $violations[] = 'canonicalizer body could not be normalized';
        } else {
            foreach ([
                'if(!$this->hasBindings($bindings,$envelopeValue)){throwAuditProtectionException::unavailable();}',
                '$logical=$envelopeValue[\'logicalRecord\']??null;if(!is_array($logical)){throwAuditProtectionException::unavailable();}',
            ] as $guard) {
                if (!str_contains($normalized, $guard)) {
                    $violations[] = 'required fail-closed guard missing';
                }
            }
        }
        if (str_contains($body, 'contentHash(')) {
            $violations[] = 'content hash recomputation introduced';
        }
        if (str_contains($body, 'idempotencyKey')) {
            $violations[] = 'raw idempotency-key access introduced';
        }

        return $violations;
    }

    private function methodBody(string $source): string
    {
        $start = strpos($source, 'public function canonicalize(');
        if ($start === false) {
            return '';
        }
        $open = strpos($source, '{', $start);
        if ($open === false) {
            return '';
        }
        $depth = 0;
        $length = strlen($source);
        for ($index = $open; $index < $length; ++$index) {
            if ($source[$index] === '{') {
                ++$depth;
                continue;
            }
            if ($source[$index] !== '}') {
                continue;
            }
            --$depth;
            if ($depth === 0) {
                return substr($source, $open + 1, $index - $open - 1);
            }
        }
        return '';
    }
}

final class RelayFixtureKeySource implements AuditProtectionKeySource
{
    private string $outboxMaterial;

    private string $activeOutboxKeyId;

    private string $activeOutboxMaterial;

    /** @var list<AuditProtectionPurpose> */
    public array $activePurposes = [];

    /** @var list<AuditProtectionPurpose> */
    public array $readPurposes = [];

    /** @var list<string> */
    public array $readKeyIds = [];

    /** @var list<string> */
    public array $readMaterials = [];

    public int $activeKeyCalls = 0;

    public int $keyCalls = 0;

    public function __construct(
        private string $outboxKeyId = 'audit-outbox-key',
        ?string $outboxMaterial = null,
        private bool $unavailable = false,
        private bool $wrongKeyId = false,
        ?string $activeOutboxKeyId = null,
        ?string $activeOutboxMaterial = null,
    ) {
        $this->outboxMaterial = $outboxMaterial ?? str_repeat('o', 32);
        $this->activeOutboxKeyId = $activeOutboxKeyId ?? $this->outboxKeyId;
        $this->activeOutboxMaterial = $activeOutboxMaterial ?? $this->outboxMaterial;
    }

    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        ++$this->activeKeyCalls;
        $this->activePurposes[] = $purpose;
        return $this->activeKeyFor($purpose);
    }

    public function key(
        #[\SensitiveParameter]
        string $keyId,
        ?AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): StorageKey {
        ++$this->keyCalls;
        $this->readKeyIds[] = $keyId;
        $this->readPurposes[] = $purpose;
        if ($this->unavailable) {
            throw new \RuntimeException('relay fixture key unavailable');
        }
        if ($this->wrongKeyId) {
            return new StorageKey('wrong-audit-key', str_repeat('x', 32));
        }
        $key = $this->keyFor($purpose);
        if ($key->id() !== $keyId) {
            throw new \RuntimeException('unexpected relay fixture key');
        }
        $this->readMaterials[] = $key->material();

        return $key;
    }

    public function keyId(AuditProtectionPurpose $purpose): string
    {
        return $this->keyFor($purpose)->id();
    }

    public function activeKeyId(AuditProtectionPurpose $purpose): string
    {
        return $this->activeKeyFor($purpose)->id();
    }

    public function activeKeyMaterial(AuditProtectionPurpose $purpose): string
    {
        return $this->activeKeyFor($purpose)->material();
    }

    private function activeKeyFor(AuditProtectionPurpose $purpose): StorageKey
    {
        return match ($purpose) {
            AuditProtectionPurpose::AuditRecord => new StorageKey('audit-record-key', str_repeat('r', 32)),
            AuditProtectionPurpose::AuditOutboxRecord => new StorageKey(
                $this->activeOutboxKeyId,
                $this->activeOutboxMaterial,
            ),
        };
    }

    private function keyFor(AuditProtectionPurpose $purpose): StorageKey
    {
        return match ($purpose) {
            AuditProtectionPurpose::AuditRecord => new StorageKey('audit-record-key', str_repeat('r', 32)),
            AuditProtectionPurpose::AuditOutboxRecord => new StorageKey($this->outboxKeyId, $this->outboxMaterial),
        };
    }
}

final class RelayFixtureNonceSource implements NonceSource
{
    public function generate(): string
    {
        return str_repeat("\x01", 24);
    }
}
