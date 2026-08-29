<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Protection;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelope;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Record\AuditLogicalRecordCandidate;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use PHPUnit\Framework\TestCase;

final class AuditIngressEnvelopeCodecTest extends TestCase
{
    public function testDurableFixturesRoundTripWithExactEnvelopeMemberOrder(): void
    {
        $fixture = $this->fixture();
        $codec = $this->codec();
        foreach ([
            'durableSystemIngress' => [
                'idem-null-1',
                1,
                '6e22dd41bce06ff2825a8081b3bd34bbe8fe47acba2f76cf0af2ea62771ebdbc',
            ],
            'durableTenantIngress' => [
                'idem-tenant-2',
                2,
                '1caac6c03c7c676ad2506059eb7b26fdbe959bc68a0aa6790db76aa019a6ff54',
            ],
        ] as $name => [$key, $ordinal, $hash]) {
            $entry = $fixture['ingress'][$name];
            $envelope = $codec->create($this->canonical($entry['logical']), $ordinal, $key);
            self::assertSame(
                $entry['logicalBytes'],
                $this->records()->logicalBytes($this->canonical($entry['logical'])),
                $name . ' logical bytes',
            );
            self::assertSame($entry['contentHash'], $envelope->toArray()['contentHash']);
            self::assertSame($hash, $codec->idempotencyKeyHash($envelope));
            self::assertSame($key, $envelope->idempotencyKey());
            self::assertSame(
                [
                    'schemaVersion',
                    'kind',
                    'logicalRecord',
                    'contentHash',
                    'recordedAt',
                    'ingressOrdinal',
                    'idempotencyKey',
                    'recordId',
                    'partitionId',
                    'tenantBinding',
                ],
                array_keys($envelope->toArray()),
            );
            $bytes = $codec->encode($envelope);
            self::assertSame($bytes, $codec->encode($codec->decode($bytes)));
        }
    }

    public function testRequiredDeliveryCannotEnterTheDurableIngressPath(): void
    {
        $fixture = $this->fixture();
        $entry = $fixture['records']['systemGenesis'];
        try {
            $this->codec()->create($this->canonical($entry['logical']), 1, 'not-durable');
            self::fail('required record accepted as durable ingress');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }
    }

    public function testIngressOrdinalBoundsAndMaximumSafeStringBoundaryAreClosed(): void
    {
        $fixture = $this->fixture();
        $logical = $this->canonical($fixture['ingress']['durableSystemIngress']['logical']);
        $codec = $this->codec();
        $codec->create($logical, AuditValidationException::MAX_SAFE_INTEGER, str_repeat('x', 256));
        foreach ([0, AuditValidationException::MAX_SAFE_INTEGER + 1] as $ordinal) {
            try {
                $codec->create($logical, $ordinal, 'ordinal-boundary');
                self::fail('invalid ingress ordinal accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('semantic_invalid', $exception->code());
            }
        }
    }

    public function testIngressWireUsesInclusiveUtf8ByteCap(): void
    {
        $fixture = $this->fixture();
        $ingressCodec = $this->codec();
        $base = $ingressCodec->create(
            $this->canonical($fixture['ingress']['durableSystemIngress']['logical']),
            1,
            'boundary-key',
        );
        $atLimit = $this->envelopeAtEncodedLength($base, AuditIngressEnvelopeCodec::MAX_BYTES);
        self::assertSame(AuditIngressEnvelopeCodec::MAX_BYTES, strlen($ingressCodec->encode($atLimit)));
        $overLimit = $this->envelopeAtEncodedLength($base, AuditIngressEnvelopeCodec::MAX_BYTES + 1);
        try {
            $ingressCodec->encode($overLimit);
            self::fail('over-cap ingress wire accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('jcs_bound_invalid', $exception->code());
        }
    }

    public function testEveryIngressBindingIsCheckedBeforeContentHashEquality(): void
    {
        $fixture = $this->fixture();
        $codec = $this->codec();
        $entry = $fixture['ingress']['durableSystemIngress'];
        $envelope = $codec->create($this->canonical($entry['logical']), 1, $entry['idempotencyKey']);
        $base = $envelope->toArray();
        foreach ([
            'recordId' => '01941f29-7c00-7000-8000-000000000099',
            'recordedAt' => '2026-08-19T00:00:09.000000Z',
            'partitionId' => 'tenant:' . str_repeat('f', 64),
            'tenantBinding' => ['type' => 'account', 'id' => 'tenant-a'],
        ] as $field => $replacement) {
            $value = $base;
            $value[$field] = $replacement;
            $this->assertDecodeFailure($codec, new AuditJcsCodec()->canonicalJson($value), 'semantic_invalid');
        }
        $nestedRecord = $base;
        $nestedRecord['logicalRecord']['recordId'] = '01941f29-7c00-7000-8000-000000000099';
        $this->assertDecodeFailure($codec, new AuditJcsCodec()->canonicalJson($nestedRecord), 'semantic_invalid');

        $wrongHash = $base;
        $wrongHash['contentHash'] = str_repeat('0', 64);
        $this->assertDecodeFailure($codec, new AuditJcsCodec()->canonicalJson($wrongHash), 'hash_mismatch');
    }

    public function testDecodeRejectsLogicalRecordedAtInjectionAndContentSubstitution(): void
    {
        $fixture = $this->fixture();
        $codec = $this->codec();
        $entry = $fixture['ingress']['durableSystemIngress'];
        $envelope = $codec->create($this->canonical($entry['logical']), 1, $entry['idempotencyKey']);
        $value = $envelope->toArray();
        $value['logicalRecord']['recordedAt'] = $value['recordedAt'];
        $this->assertDecodeFailure($codec, new AuditJcsCodec()->canonicalJson($value), 'shape_invalid');

        $value = $envelope->toArray();
        $value['contentHash'] = str_repeat('0', 64);
        $this->assertDecodeFailure($codec, new AuditJcsCodec()->canonicalJson($value), 'hash_mismatch');

        $required = $fixture['records']['systemGenesis']['logical'];
        $requiredEnvelope = [
            'schemaVersion' => 1,
            'kind' => 'audit_ingress_envelope',
            'logicalRecord' => $required,
            'contentHash' => str_repeat('0', 64),
            'recordedAt' => $required['time']['recordedAt'],
            'ingressOrdinal' => 1,
            'idempotencyKey' => 'not-durable',
            'recordId' => $required['recordId'],
            'partitionId' => 'system:global',
            'tenantBinding' => null,
        ];
        $this->assertDecodeFailure($codec, new AuditJcsCodec()->canonicalJson($requiredEnvelope), 'semantic_invalid');
    }

    public function testIdempotencyKeyBoundsAndEnvelopeInspectionDoNotLeakSecrets(): void
    {
        $fixture = $this->fixture();
        $logical = $this->canonical($fixture['ingress']['durableSystemIngress']['logical']);
        foreach (['', str_repeat('x', 257)] as $key) {
            try {
                $this->codec()->create($logical, 1, $key);
                self::fail('invalid idempotency key accepted');
            } catch (AuditValidationException) {
                self::addToAssertionCount(1);
            }
        }
        $envelope = $this->codec()->create($logical, 1, 'raw-idempotency-canary');
        $inspection =
            var_export($envelope, true) . print_r($envelope, true) . json_encode($envelope, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('raw-idempotency-canary', $inspection);
        self::assertSame([], $envelope->__serialize());
    }

    /** @return array<string,mixed> */
    private function fixture(): array
    {
        $value = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../Fixtures/Audit/audit-canonical-record-v1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($value);
        return $value;
    }

    private function codec(): AuditIngressEnvelopeCodec
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog]));
        $records = new AuditRecordCodec($validator, new AuditJcsCodec());
        return new AuditIngressEnvelopeCodec(
            $records,
            new AuditPartitionIdDeriver(new AuditJcsCodec()),
            new AuditJcsCodec(),
        );
    }

    private function records(): AuditRecordCodec
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog]));
        return new AuditRecordCodec($validator, new AuditJcsCodec());
    }

    private function envelopeAtEncodedLength(AuditIngressEnvelope $base, int $target): AuditIngressEnvelope
    {
        $value = $base->toArray();
        $value['logicalRecord']['source']['buildId'] = '';
        $jcs = new AuditJcsCodec();
        $prefixLength = strlen($jcs->canonicalJson($value));
        $available = $target - $prefixLength;
        $multibyteLength = intdiv($available, 3);
        $asciiLength = $available - ($multibyteLength * 3);
        $value['logicalRecord']['source']['buildId'] =
            str_repeat('あ', $multibyteLength) . str_repeat('x', $asciiLength);
        $tenant = $value['tenantBinding'];
        $tenantReference = $tenant === null ? null : new AuditTypedReference($tenant['type'], $tenant['id']);
        return new AuditIngressEnvelope(
            $value['schemaVersion'],
            $value['kind'],
            $value['logicalRecord'],
            $value['contentHash'],
            $value['recordedAt'],
            $value['ingressOrdinal'],
            $value['idempotencyKey'],
            $value['recordId'],
            $value['partitionId'],
            $tenantReference,
        );
    }

    /** @param array<string,mixed> $snapshot */
    private function canonical(array $snapshot): CanonicalAuditLogicalRecord
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog]));
        return new CanonicalAuditLogicalRecord($validator->validateLogical(new AuditLogicalRecordCandidate($snapshot)));
    }

    private function assertDecodeFailure(AuditIngressEnvelopeCodec $codec, string $bytes, string $expected): void
    {
        try {
            $codec->decode($bytes);
            self::fail('invalid ingress envelope accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame($expected, $exception->code());
        }
    }
}
