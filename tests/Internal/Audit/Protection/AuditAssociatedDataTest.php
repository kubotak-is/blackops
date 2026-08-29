<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Protection;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedIngress;
use BlackOps\Internal\Audit\Protection\AuditStoredRecord;
use BlackOps\Internal\Audit\Record\AuditChainPosition;
use BlackOps\Internal\Audit\Record\AuditLogicalRecordCandidate;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\StorageProtection\StorageKey;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuditAssociatedDataTest extends TestCase
{
    public function testStoredRecordSystemAndTenantVectorsMatchTheCanonicalHashes(): void
    {
        $fixture = $this->fixture();
        $codec = $this->codec();
        $aad = new AuditAssociatedData();
        $vectors = [
            'systemGenesis' => [
                'audit-key-1',
                308,
                'c917e26a1a4d124db7701ceb76fc141581a54ce248e8df3783fc5e8500a4cade',
                '0000001c626c61636b6f70732e61756469742e7265636f72642e6161642e763100000011417564697453746f7265645265636f72640000000131000000013100000001310000000b61756469742d6b65792d310000002430313934316632392d376330302d373030302d383030302d3030303030303030303030310000000d73797374656d3a676c6f62616c00000001000000002430313934316632392d376330302d373030302d383030302d303030303030303030313031000000013100000001000000002041b8f7df71f09eb50e78a6f4259b5ee0147efb192ae40c9fe13f1a4526b3244100000020a4f7fd2ac8a282deb00bd0221edd2f7c14aa559de97db4b83e5a9609227459b00000001b323032362d30382d31395430303a30303a30302e3030303030305a00000006616363657373',
            ],
            'tenantNonGenesis' => [
                'audit-key-2',
                421,
                '0ab1465ae6e1e908228cafd36cf13fa55a805d4189bbd096d8df885a47452c48',
                '0000001c626c61636b6f70732e61756469742e7265636f72642e6161642e763100000011417564697453746f7265645265636f72640000000131000000013100000001310000000b61756469742d6b65792d320000002430313934316632392d376330302d373030302d383030302d3030303030303030303030330000004774656e616e743a353966353862666362343736666363393866653565393563366266383161356638313235613732316632376535336564373062613733343561653634656165310000001801000000076163636f756e740000000874656e616e742d610000002430313934316632392d376330302d373030302d383030302d3030303030303030303130320000000132000000210153f065f0fcd332fb2dc80e6bb2c23ed9d1ee127531550bb4e0a459d09544935e00000020034a0ef2dc757517a1045660072835fbacfbfc9d77781be9e1db3b168ecf41860000002090b6a61cdd0bc6eae188fe7f79f21656cea5a97e49d8b5e6f2656a7a1c99a5cd0000001b323032362d30382d31395430303a30303a30312e3030303030305a00000006616363657373',
            ],
        ];
        foreach ($vectors as $name => [$keyId, $length, $hash, $hex]) {
            $entry = $fixture['records'][$name];
            $complete = $codec->complete(
                $this->canonical($entry['logical']),
                new AuditChainPosition(...array_values($entry['chain'])),
            );
            $stored = $this->stored($entry);
            $value = $aad->recordForWrite($keyId, $complete);
            self::assertSame($length, strlen($value), $name . ' length');
            self::assertSame($hash, hash('sha256', $value), $name . ' hash');
            self::assertSame($hex, bin2hex($value), $name . ' full bytes');
            self::assertSame($value, $aad->recordForRead($keyId, $stored), $name . ' read/write');
        }
    }

    public function testOutboxVectorsDeriveClearIdempotencyHashFromTheRawKey(): void
    {
        $fixture = $this->fixture();
        $codec = $this->codec();
        $envelopes = new AuditIngressEnvelopeCodec(
            $codec,
            new AuditPartitionIdDeriver(new AuditJcsCodec()),
            new AuditJcsCodec(),
        );
        $aad = new AuditAssociatedData();
        foreach ([
            'durableSystemIngress' => [
                'audit-key-1',
                1,
                222,
                '1444d7e8430fdb22bed2b29cb10988684aa8b75c061d943ef43cf1d5ec976f7d',
            ],
            'durableTenantIngress' => [
                'audit-key-2',
                2,
                303,
                '6ad3000f8b1b7fa7d76a266c813f9bf83be8c30340c751e761abc396e9acd9e6',
            ],
        ] as $name => [$keyId, $ordinal, $length, $hash]) {
            $entry = $fixture['ingress'][$name];
            $envelope = $envelopes->create($this->canonical($entry['logical']), $ordinal, $entry['idempotencyKey']);
            $value = $aad->outboxForWrite($keyId, $envelope);
            self::assertSame($length, strlen($value), $name . ' length');
            self::assertSame($hash, hash('sha256', $value), $name . ' hash');
            $protected = AuditProtectedIngress::fromArray([
                'recordId' => $entry['logical']['recordId'],
                'partitionId' => $entry['logical']['tenant'] === null
                    ? 'system:global'
                    : 'tenant:59f58bfcb476fcc98fe5e95c6bf81a5f8125a721f27e53ed70ba7345ae64eae1',
                'tenantBinding' => $entry['logical']['tenant'],
                'ingressOrdinal' => $ordinal,
                'idempotencyKeyHash' => $entry['idempotencyKeyHash'],
                'contentHash' => $entry['contentHash'],
                'protectedBody' => str_repeat('x', 53),
            ]);
            self::assertSame($value, $aad->outboxForRead($keyId, $protected));
        }
    }

    public function testChangingTheRawIdempotencyKeyChangesTheDerivedOutboxAad(): void
    {
        $fixture = $this->fixture();
        $codec = $this->codec();
        $partitions = new AuditPartitionIdDeriver(new AuditJcsCodec());
        $ingress = new AuditIngressEnvelopeCodec($codec, $partitions, new AuditJcsCodec());
        $logical = $this->canonical($fixture['ingress']['durableSystemIngress']['logical']);
        $first = $ingress->create($logical, 1, 'idem-null-1');
        $second = $ingress->create($logical, 1, 'idem-null-2');
        $aad = new AuditAssociatedData();

        self::assertNotSame($aad->outboxForWrite('audit-key-1', $first), $aad->outboxForWrite('audit-key-1', $second));
        self::assertNotSame($ingress->idempotencyKeyHash($first), $ingress->idempotencyKeyHash($second));
    }

    public function testOneByteTenantMarkerMutationCannotOpenWithTheCanonicalAad(): void
    {
        $fixture = $this->fixture();
        $entry = $fixture['records']['systemGenesis'];
        $recordCodec = $this->codec();
        $complete = $recordCodec->complete(
            $this->canonical($entry['logical']),
            new AuditChainPosition(...array_values($entry['chain'])),
        );
        $key = new StorageKey('aad-key', str_repeat('a', 32));
        $binary = new BopdBinaryEnvelopeCodec();
        $canonical = new AuditAssociatedData()->recordForWrite('aad-key', $complete);
        self::assertSame(0, ord($canonical[140]));
        $sealed = $binary->seal('payload', $canonical, $key, str_repeat("\x07", 24), 1024, 2048);
        $parsed = $binary->parse($sealed, 2048);
        $mutated = substr_replace($canonical, "\x01", 140, 1);
        try {
            $binary->open($parsed, $mutated, $key, 1024);
            self::fail('mutated tenant marker accepted');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }
    }

    /** @param array<string,mixed> $entry */
    private function stored(array $entry): AuditStoredRecord
    {
        return AuditStoredRecord::fromArray([
            'schemaVersion' => 1,
            'kind' => 'audit_stored_record',
            'recordId' => $entry['logical']['recordId'],
            'partitionId' => $entry['chain']['partitionId'],
            'tenantBinding' => $entry['logical']['tenant'],
            'segmentId' => $entry['chain']['segmentId'],
            'sequence' => $entry['chain']['sequence'],
            'previousRecordHash' => $entry['chain']['previousRecordHash'],
            'contentHash' => $entry['contentHash'],
            'recordHash' => $entry['recordHash'],
            'recordedAt' => $entry['logical']['time']['recordedAt'],
            'eventCategory' => $entry['logical']['event']['category'],
            'protectedBody' => str_repeat('x', 53),
        ]);
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

    private function codec(): AuditRecordCodec
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog]));
        return new AuditRecordCodec($validator, new AuditJcsCodec());
    }

    /** @param array<string,mixed> $snapshot */
    private function canonical(array $snapshot): CanonicalAuditLogicalRecord
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog]));
        return new CanonicalAuditLogicalRecord($validator->validateLogical(new AuditLogicalRecordCandidate($snapshot)));
    }
}
