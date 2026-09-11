<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Protection;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelope;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedIngress;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Protection\AuditStoredRecord;
use BlackOps\Internal\Audit\Record\AuditChainPosition;
use BlackOps\Internal\Audit\Record\AuditLogicalRecordCandidate;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use BlackOps\Internal\Audit\Record\CompleteAuditRecord;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\StorageProtection\StorageKey;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuditProtectedBodyCodecTest extends TestCase
{
    public function testRecordAndOutboxBodiesProtectAndUnprotect(): void
    {
        $fixture = $this->fixture();
        [$codec, $keys] = $this->protectedCodec();
        $recordEntry = $fixture['records']['systemGenesis'];
        $complete = $this->complete($recordEntry);
        $stored = $codec->protectRecord($complete);
        self::assertSame(
            [
                'schemaVersion',
                'kind',
                'recordId',
                'partitionId',
                'tenantBinding',
                'segmentId',
                'sequence',
                'previousRecordHash',
                'contentHash',
                'recordHash',
                'recordedAt',
                'eventCategory',
                'protectedBody',
            ],
            array_keys($stored->toArray()),
        );
        self::assertSame($complete->bytes(), $codec->unprotectRecord($stored, null, 'system:global')->bytes());

        $ingressEntry = $fixture['ingress']['durableTenantIngress'];
        $ingress = $this->ingress($ingressEntry);
        $protected = $codec->protectOutbox($ingress);
        self::assertSame(
            [
                'recordId',
                'partitionId',
                'tenantBinding',
                'ingressOrdinal',
                'idempotencyKeyHash',
                'contentHash',
                'protectedBody',
            ],
            array_keys($protected->toArray()),
        );
        self::assertSame(
            $ingress->toArray()['contentHash'],
            $codec->unprotectOutbox($protected)->toArray()['contentHash'],
        );
        self::assertSame(2, $keys->readCalls);
        self::assertSame(2, $keys->activeCalls);
    }

    public function testBindingMismatchFailsBeforeRecordKeyLookup(): void
    {
        $fixture = $this->fixture();
        [$codec, $keys] = $this->protectedCodec();
        $entry = $fixture['records']['tenantGenesis'];
        $stored = $codec->protectRecord($this->complete($entry));
        $before = $keys->readCalls;
        $this->assertUnavailable(static fn(): CompleteAuditRecord => $codec->unprotectRecord(
            $stored,
            new AuditTypedReference('account', 'other-tenant'),
            $entry['chain']['partitionId'],
        ));
        self::assertSame($before, $keys->readCalls);
        $this->assertUnavailable(static fn(): CompleteAuditRecord => $codec->unprotectRecord(
            $stored,
            new AuditTypedReference('account', 'tenant-a'),
            'system:global',
        ));
        self::assertSame($before, $keys->readCalls);
    }

    public function testDetachedHydrationRoundTripsAndBodyCapsAreInclusive(): void
    {
        $fixture = $this->fixture();
        [$codec] = $this->protectedCodec();
        $stored = $codec->protectRecord($this->complete($fixture['records']['systemGenesis']));
        $hydratedStored = AuditStoredRecord::fromArray($stored->toArray());
        self::assertSame($stored->toArray(), $hydratedStored->toArray());

        $ingress = $this->ingress($fixture['ingress']['durableSystemIngress']);
        $protected = $codec->protectOutbox($ingress);
        $hydratedIngress = AuditProtectedIngress::fromArray($protected->toArray());
        self::assertSame($protected->toArray(), $hydratedIngress->toArray());

        $storedAtCap = $stored->toArray();
        $storedAtCap['protectedBody'] = str_repeat('s', 1_048_756);
        AuditStoredRecord::fromArray($storedAtCap);
        $storedOverCap = $storedAtCap;
        $storedOverCap['protectedBody'] .= 'x';
        $this->assertUnavailable(static fn(): AuditStoredRecord => AuditStoredRecord::fromArray($storedOverCap));

        $ingressAtCap = $protected->toArray();
        $ingressAtCap['protectedBody'] = str_repeat('o', 1_048_576);
        AuditProtectedIngress::fromArray($ingressAtCap);
        $ingressOverCap = $ingressAtCap;
        $ingressOverCap['protectedBody'] .= 'x';
        $this->assertUnavailable(
            static fn(): AuditProtectedIngress => AuditProtectedIngress::fromArray($ingressOverCap),
        );
    }

    public function testHydratedBodiesUnprotectWithASecondCodecAndEquivalentKeys(): void
    {
        $fixture = $this->fixture();
        [$writer, $writerKeys] = $this->protectedCodec();
        [$reader, $readerKeys] = $this->protectedCodec();

        $stored = $writer->protectRecord($this->complete($fixture['records']['systemGenesis']));
        $hydratedStored = AuditStoredRecord::fromArray($stored->toArray());
        self::assertSame(
            $stored->toArray()['recordHash'],
            $reader->unprotectRecord($hydratedStored, null, 'system:global')->integrity()->recordHash,
        );

        $protected = $writer->protectOutbox($this->ingress($fixture['ingress']['durableTenantIngress']));
        $hydratedIngress = AuditProtectedIngress::fromArray($protected->toArray());
        self::assertSame(
            $protected->toArray()['contentHash'],
            $reader->unprotectOutbox($hydratedIngress)->toArray()['contentHash'],
        );
        self::assertSame(2, $writerKeys->activeCalls);
        self::assertSame(2, $readerKeys->readCalls);
        self::assertSame(0, $readerKeys->activeCalls);
    }

    public function testMalformedHeadersFailBeforeAnyKeySourceRead(): void
    {
        $fixture = $this->fixture();
        [$codec, $keys] = $this->protectedCodec();
        $stored = $codec->protectRecord($this->complete($fixture['records']['systemGenesis']));
        $storedValue = $stored->toArray();
        $storedValue['protectedBody'] = substr_replace($storedValue['protectedBody'], 'X', 0, 1);
        $malformedStored = AuditStoredRecord::fromArray($storedValue);
        $beforeRecordReads = $keys->readCalls;
        $this->assertUnavailable(
            static fn(): CompleteAuditRecord => $codec->unprotectRecord($malformedStored, null, 'system:global'),
        );
        self::assertSame($beforeRecordReads, $keys->readCalls);

        $protected = $codec->protectOutbox($this->ingress($fixture['ingress']['durableSystemIngress']));
        $protectedValue = $protected->toArray();
        $protectedValue['protectedBody'] = substr_replace($protectedValue['protectedBody'], 'X', 0, 1);
        $malformedIngress = AuditProtectedIngress::fromArray($protectedValue);
        $beforeOutboxReads = $keys->readCalls;
        $this->assertUnavailable(static fn(): AuditIngressEnvelope => $codec->unprotectOutbox($malformedIngress));
        self::assertSame($beforeOutboxReads, $keys->readCalls);
    }

    // Keep the oversized body allocation isolated from the full-suite process.
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testOversizedRecordBodyFailsBeforeActiveKeyLookup(): void
    {
        $fixture = $this->fixture();
        [$codec, $keys] = $this->protectedCodec();
        $record = $this->complete($fixture['records']['systemGenesis']);
        $oversized = new CompleteAuditRecord(
            $record->logicalSnapshot(),
            $record->definition(),
            $record->integrity(),
            str_repeat('p', AuditRecordCodec::MAX_BYTES + 1),
        );
        $this->assertUnavailable(static fn(): AuditStoredRecord => $codec->protectRecord($oversized));
        self::assertSame(0, $keys->activeCalls);

        $ingress = $this->ingress($fixture['ingress']['durableSystemIngress']);
        $oversizedLogical = $ingress->toArray()['logicalRecord'];
        $oversizedLogical['source']['buildId'] = str_repeat('x', AuditRecordCodec::MAX_BYTES);
        $oversizedIngress = new AuditIngressEnvelope(
            1,
            'audit_ingress_envelope',
            $oversizedLogical,
            $ingress->toArray()['contentHash'],
            $ingress->toArray()['recordedAt'],
            $ingress->toArray()['ingressOrdinal'],
            $ingress->idempotencyKey(),
            $ingress->toArray()['recordId'],
            $ingress->toArray()['partitionId'],
            null,
        );
        $this->assertUnavailable(static fn(): AuditProtectedIngress => $codec->protectOutbox($oversizedIngress));
        self::assertSame(0, $keys->activeCalls);
    }

    // Keep the plaintext ceiling allocation isolated from the full-suite process.
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testOutboxBopdPlaintextCeilingIsInclusiveAndPreKey(): void
    {
        $fixture = $this->fixture();
        $base = $this->ingress($fixture['ingress']['durableSystemIngress']);
        $ceiling = AuditRecordCodec::MAX_BYTES - 52 - 128;
        $atCeiling = $this->envelopeAtEncodedLength($base, $ceiling);
        self::assertSame($ceiling, strlen($this->ingressCodec()->encode($atCeiling)));

        [$codec, $keys] = $this->protectedCodec();
        $protected = $codec->protectOutbox($atCeiling);
        self::assertSame($ceiling + 52 + strlen('audit-outbox-key'), strlen($protected->protectedBody()));
        self::assertSame(1, $keys->activeCalls);

        $overCeiling = $this->envelopeAtEncodedLength($base, $ceiling + 1);
        self::assertSame($ceiling + 1, strlen($this->ingressCodec()->encode($overCeiling)));
        [$overCodec, $overKeys] = $this->protectedCodec();
        $this->assertUnavailable(static fn(): AuditProtectedIngress => $overCodec->protectOutbox($overCeiling));
        self::assertSame(0, $overKeys->activeCalls);
    }

    public function testStoredAndOutboxHydrationRejectEveryBoundaryShape(): void
    {
        $fixture = $this->fixture();
        [$codec] = $this->protectedCodec();
        $stored = $codec->protectRecord($this->complete($fixture['records']['systemGenesis']))->toArray();
        $ingress = $codec->protectOutbox($this->ingress($fixture['ingress']['durableSystemIngress']))->toArray();

        $storedVariants = [
            'missing' => array_diff_key($stored, ['recordId' => true]),
            'extra' => [...$stored, 'extra' => 'x'],
            'list' => array_values($stored),
            'wrong scalar' => [...$stored, 'schemaVersion' => '1'],
            'wrong enum' => [...$stored, 'kind' => 'other'],
        ];
        foreach ($storedVariants as $variant)
            $this->assertUnavailable(static fn(): AuditStoredRecord => AuditStoredRecord::fromArray($variant));

        $ingressVariants = [
            'missing' => array_diff_key($ingress, ['recordId' => true]),
            'extra' => [...$ingress, 'extra' => 'x'],
            'list' => array_values($ingress),
            'wrong scalar' => [...$ingress, 'ingressOrdinal' => '1'],
            'wrong enum' => [...$ingress, 'protectedBody' => 42],
        ];
        foreach ($ingressVariants as $variant)
            $this->assertUnavailable(static fn(): AuditProtectedIngress => AuditProtectedIngress::fromArray($variant));
    }

    public function testTamperedBodiesAndWrongKeysCollapseToOneSafeFailure(): void
    {
        $fixture = $this->fixture();
        [$codec] = $this->protectedCodec();
        $entry = $fixture['records']['systemGenesis'];
        $stored = $codec->protectRecord($this->complete($entry));
        $value = $stored->toArray();
        $body = $value['protectedBody'];
        $value['protectedBody'] = substr_replace($body, chr(ord($body[0]) ^ 1), 0, 1);
        $tampered = AuditStoredRecord::fromArray($value);
        $this->assertUnavailable(
            static fn(): CompleteAuditRecord => $codec->unprotectRecord($tampered, null, 'system:global'),
        );

        $invalid = AuditProtectedIngress::fromArray([
            'recordId' => $entry['logical']['recordId'],
            'partitionId' => 'system:global',
            'tenantBinding' => null,
            'ingressOrdinal' => 1,
            'idempotencyKeyHash' => str_repeat('0', 64),
            'contentHash' => str_repeat('0', 64),
            'protectedBody' => str_repeat('x', 53),
        ]);
        $this->assertUnavailable(
            static fn(): \BlackOps\Internal\Audit\Protection\AuditIngressEnvelope => $codec->unprotectOutbox($invalid),
        );
    }

    public function testProtectedRecordAndOutboxRejectWrongKeyPurposeNonceTagAndCiphertext(): void
    {
        $fixture = $this->fixture();
        [$codec] = $this->protectedCodec();
        $record = $this->complete($fixture['records']['systemGenesis']);
        $stored = $codec->protectRecord($record);
        foreach (['nonce', 'tag', 'ciphertext'] as $part) {
            $value = $stored->toArray();
            $value['protectedBody'] = $this->mutateBinaryPart($value['protectedBody'], $part);
            $this->assertUnavailable(fn(): CompleteAuditRecord => $codec->unprotectRecord(
                AuditStoredRecord::fromArray($value),
                null,
                'system:global',
            ));
        }
        $wrongKeyId = $stored->toArray();
        $wrongKeyId['protectedBody'] = $this->replaceBinaryKeyId($wrongKeyId['protectedBody'], 'wrong-record-key');
        $this->assertUnavailable(fn(): CompleteAuditRecord => $codec->unprotectRecord(
            AuditStoredRecord::fromArray($wrongKeyId),
            null,
            'system:global',
        ));

        $purposeCodec = $this->protectedCodecWithKeys(new PurposeMismatchAuditKeySource());
        $purposeStored = $purposeCodec->protectRecord($record);
        $this->assertUnavailable(
            fn(): CompleteAuditRecord => $purposeCodec->unprotectRecord($purposeStored, null, 'system:global'),
        );

        $ingress = $this->ingress($fixture['ingress']['durableSystemIngress']);
        $protected = $codec->protectOutbox($ingress);
        $purposeProtected = $purposeCodec->protectOutbox($ingress);
        $this->assertUnavailable(
            fn(): \BlackOps\Internal\Audit\Protection\AuditIngressEnvelope => $purposeCodec->unprotectOutbox(
                $purposeProtected,
            ),
        );
        foreach (['nonce', 'tag', 'ciphertext'] as $part) {
            $value = $protected->toArray();
            $value['protectedBody'] = $this->mutateBinaryPart($value['protectedBody'], $part);
            $this->assertUnavailable(fn(): \BlackOps\Internal\Audit\Protection\AuditIngressEnvelope => $codec->unprotectOutbox(AuditProtectedIngress::fromArray(
                $value,
            )));
        }
        $wrongOutboxKeyId = $protected->toArray();
        $wrongOutboxKeyId['protectedBody'] = $this->replaceBinaryKeyId(
            $wrongOutboxKeyId['protectedBody'],
            'wrong-outbox-key',
        );
        $this->assertUnavailable(fn(): \BlackOps\Internal\Audit\Protection\AuditIngressEnvelope => $codec->unprotectOutbox(AuditProtectedIngress::fromArray(
            $wrongOutboxKeyId,
        )));
    }

    public function testEveryStoredAndOutboxAadClearMemberIsBoundToTheProtectedBody(): void
    {
        $fixture = $this->fixture();
        [$codec] = $this->protectedCodec();
        $stored = $codec->protectRecord($this->complete($fixture['records']['tenantNonGenesis']));
        $storedValue = $stored->toArray();
        $storedMutations = [
            'recordId' => '01941f29-7c00-7000-8000-000000000099',
            'partitionId' => 'system:global',
            'tenantBinding' => null,
            'segmentId' => '01941f29-7c00-7000-8000-000000000199',
            'sequence' => 3,
            'previousRecordHash' => str_repeat('0', 64),
            'contentHash' => str_repeat('1', 64),
            'recordHash' => str_repeat('2', 64),
            'recordedAt' => '2026-08-19T00:00:09.000000Z',
            'eventCategory' => 'security',
        ];
        foreach ($storedMutations as $field => $replacement) {
            $value = $storedValue;
            $value[$field] = $replacement;
            $tenant = $value['tenantBinding'];
            $expectedTenant = $tenant === null ? null : new AuditTypedReference($tenant['type'], $tenant['id']);
            $this->assertUnavailable(fn(): CompleteAuditRecord => $codec->unprotectRecord(
                AuditStoredRecord::fromArray($value),
                $expectedTenant,
                $value['partitionId'],
            ));
        }
        foreach ([
            [...$storedValue, 'schemaVersion' => 2],
            [...$storedValue, 'kind' => 'other'],
        ] as $value) {
            $this->assertUnavailable(static fn(): AuditStoredRecord => AuditStoredRecord::fromArray($value));
        }

        $protected = $codec->protectOutbox($this->ingress($fixture['ingress']['durableTenantIngress']));
        $protectedValue = $protected->toArray();
        $protectedMutations = [
            'recordId' => '01941f29-7c00-7000-8000-000000000099',
            'partitionId' => 'system:global',
            'tenantBinding' => null,
            'ingressOrdinal' => 3,
            'idempotencyKeyHash' => str_repeat('1', 64),
            'contentHash' => str_repeat('2', 64),
        ];
        foreach ($protectedMutations as $field => $replacement) {
            $value = $protectedValue;
            $value[$field] = $replacement;
            $this->assertUnavailable(fn(): \BlackOps\Internal\Audit\Protection\AuditIngressEnvelope => $codec->unprotectOutbox(AuditProtectedIngress::fromArray(
                $value,
            )));
        }
    }

    public function testPostDecryptNoncanonicalAndHashFailuresStayInsideProtectionBoundary(): void
    {
        $fixture = $this->fixture();
        [$codec] = $this->protectedCodec();
        $binary = new BopdBinaryEnvelopeCodec();
        $aad = new AuditAssociatedData();
        $recordKey = new StorageKey('audit-record-key', str_repeat('r', 32));
        $record = $this->complete($fixture['records']['systemGenesis']);
        $stored = $codec->protectRecord($record);
        $noncanonical =
            json_encode(
                array_reverse($record->toArray(), true),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ) . "\n";
        $noncanonicalRow = $stored->toArray();
        $noncanonicalRow['protectedBody'] = $binary->seal(
            $noncanonical,
            $aad->recordForRead($recordKey->id(), $stored),
            $recordKey,
            str_repeat("\x09", 24),
            AuditRecordCodec::MAX_BYTES,
            AuditRecordCodec::MAX_BYTES + 180,
        );
        $this->assertUnavailable(fn(): CompleteAuditRecord => $codec->unprotectRecord(
            AuditStoredRecord::fromArray($noncanonicalRow),
            null,
            'system:global',
        ));

        $hashMismatch = $record->toArray();
        $hashMismatch['integrity']['contentHash'] = str_repeat('0', 64);
        $hashMismatchBytes = new AuditJcsCodec()->canonicalJson($hashMismatch) . "\n";
        $hashMismatchRow = $stored->toArray();
        $hashMismatchRow['protectedBody'] = $binary->seal(
            $hashMismatchBytes,
            $aad->recordForRead($recordKey->id(), $stored),
            $recordKey,
            str_repeat("\x0a", 24),
            AuditRecordCodec::MAX_BYTES,
            AuditRecordCodec::MAX_BYTES + 180,
        );
        $this->assertUnavailable(fn(): CompleteAuditRecord => $codec->unprotectRecord(
            AuditStoredRecord::fromArray($hashMismatchRow),
            null,
            'system:global',
        ));

        $outboxKey = new StorageKey('audit-outbox-key', str_repeat('o', 32));
        $ingress = $this->ingress($fixture['ingress']['durableSystemIngress']);
        $protected = $codec->protectOutbox($ingress);
        $noncanonicalIngress = json_encode(
            array_reverse($ingress->toArray(), true),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $noncanonicalIngressRow = $protected->toArray();
        $noncanonicalIngressRow['protectedBody'] = $binary->seal(
            $noncanonicalIngress,
            $aad->outboxForRead($outboxKey->id(), $protected),
            $outboxKey,
            str_repeat("\x0b", 24),
            AuditRecordCodec::MAX_BYTES,
            AuditRecordCodec::MAX_BYTES,
        );
        $this->assertUnavailable(fn(): \BlackOps\Internal\Audit\Protection\AuditIngressEnvelope => $codec->unprotectOutbox(AuditProtectedIngress::fromArray(
            $noncanonicalIngressRow,
        )));

        $hashMismatchIngress = $ingress->toArray();
        $hashMismatchIngress['contentHash'] = str_repeat('0', 64);
        $hashMismatchIngressBytes = new AuditJcsCodec()->canonicalJson($hashMismatchIngress);
        $hashMismatchIngressRow = $protected->toArray();
        $hashMismatchIngressRow['protectedBody'] = $binary->seal(
            $hashMismatchIngressBytes,
            $aad->outboxForRead($outboxKey->id(), $protected),
            $outboxKey,
            str_repeat("\x0c", 24),
            AuditRecordCodec::MAX_BYTES,
            AuditRecordCodec::MAX_BYTES,
        );
        $this->assertUnavailable(fn(): \BlackOps\Internal\Audit\Protection\AuditIngressEnvelope => $codec->unprotectOutbox(AuditProtectedIngress::fromArray(
            $hashMismatchIngressRow,
        )));
    }

    public function testProtectedSnapshotsDoNotExposeRawCanaries(): void
    {
        $fixture = $this->fixture();
        [$codec] = $this->protectedCodec();
        $stored = $codec->protectRecord($this->complete($fixture['records']['systemGenesis']));
        $inspection = var_export($stored, true) . print_r($stored, true) . json_encode($stored, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('system:global', $inspection);
        self::assertStringNotContainsString('protectedBody', $inspection);
        self::assertSame([], $stored->__serialize());
        self::assertSame('protection_failed', AuditProtectionException::unavailable()->failure()->value);
        self::assertNull(AuditProtectionException::unavailable()->getPrevious());
    }

    public function testProtectionExceptionSurfacesRemainValueFreeAcrossEveryInspection(): void
    {
        $fixture = $this->fixture();
        [$codec] = $this->protectedCodec();
        $stored = $codec->protectRecord($this->complete($fixture['records']['systemGenesis']));
        $protected = $codec->protectOutbox($this->ingress($fixture['ingress']['durableSystemIngress']));
        $surfaces = [
            var_export($stored, true),
            print_r($stored, true),
            json_encode($stored, JSON_THROW_ON_ERROR),
            serialize($stored),
            var_export($protected, true),
            print_r($protected, true),
            json_encode($protected, JSON_THROW_ON_ERROR),
            serialize($protected),
        ];
        foreach ($surfaces as $surface) {
            self::assertStringNotContainsString('01941f29-7c00-7000-8000-000000000001', $surface);
            self::assertStringNotContainsString('idem-null-1', $surface);
            self::assertStringNotContainsString('audit-record-key', $surface);
            self::assertStringNotContainsString('audit-outbox-key', $surface);
        }

        try {
            $this->protectedCodecWithKeys(new LeakingAuditKeySource())->protectRecord($this->complete(
                $fixture['records']['systemGenesis'],
            ));
            self::fail('provider failure unexpectedly succeeded');
        } catch (AuditProtectionException $exception) {
            $surfaces = [
                $exception->getMessage(),
                $exception->getTraceAsString(),
                var_export($exception, true),
                print_r($exception, true),
                json_encode($exception, JSON_PARTIAL_OUTPUT_ON_ERROR),
                $this->safeSerialize($exception),
            ];
            foreach ($surfaces as $surface) {
                self::assertIsString($surface);
                foreach ([
                    'provider-canary',
                    'material-canary',
                    'tenant-canary',
                    'record-canary',
                    'plaintext-canary',
                    'nonce-canary',
                    'tag-canary',
                    'ciphertext-canary',
                    'content-hash-canary',
                    'record-hash-canary',
                ] as $canary) {
                    self::assertStringNotContainsString($canary, $surface);
                }
            }
            self::assertNull($exception->getPrevious());
        }
    }

    public function testActualProtectedValuesDoNotEscapeExceptionSurfaces(): void
    {
        $fixture = $this->fixture();
        [$writer] = $this->protectedCodec();
        $record = $this->complete($fixture['records']['systemGenesis']);
        $stored = $writer->protectRecord($record);
        $ingress = $this->ingress($fixture['ingress']['durableSystemIngress']);
        $protected = $writer->protectOutbox($ingress);
        $binary = new BopdBinaryEnvelopeCodec();
        $recordEnvelope = $binary->parse($stored->protectedBody(), AuditRecordCodec::MAX_BYTES + 180);
        $outboxEnvelope = $binary->parse($protected->protectedBody(), AuditRecordCodec::MAX_BYTES);
        $recordValue = $stored->toArray();
        $outboxValue = $protected->toArray();
        $canaries = [
            $recordEnvelope->keyId(),
            str_repeat('r', 32),
            $record->bytes(),
            bin2hex($recordEnvelope->nonce()),
            bin2hex($recordEnvelope->tag()),
            bin2hex($recordEnvelope->ciphertext()),
            $recordValue['recordId'],
            $recordValue['partitionId'],
            $recordValue['contentHash'],
            $recordValue['recordHash'],
            $outboxEnvelope->keyId(),
            str_repeat('o', 32),
            bin2hex($outboxEnvelope->nonce()),
            bin2hex($outboxEnvelope->tag()),
            bin2hex($outboxEnvelope->ciphertext()),
            $outboxValue['recordId'],
            $outboxValue['partitionId'],
            $outboxValue['idempotencyKeyHash'],
            $outboxValue['contentHash'],
            $ingress->idempotencyKey(),
        ];
        $message = implode('|', $canaries);
        $leakingCodec = $this->protectedCodecWithKeys(new LeakingAuditKeySource($message));
        foreach ([
            static fn(): AuditStoredRecord => $leakingCodec->protectRecord($record),
            static fn(): AuditProtectedIngress => $leakingCodec->protectOutbox($ingress),
        ] as $operation) {
            try {
                $operation();
                self::fail('leaking provider unexpectedly succeeded');
            } catch (AuditProtectionException $exception) {
                $surfaces = [
                    $exception->getMessage(),
                    (string) $exception,
                    $exception->getTraceAsString(),
                    var_export($exception, true),
                    print_r($exception, true),
                    json_encode($exception, JSON_PARTIAL_OUTPUT_ON_ERROR),
                    $this->safeSerialize($exception),
                ];
                foreach ($surfaces as $surface) {
                    self::assertIsString($surface);
                    foreach ($canaries as $canary)
                        self::assertStringNotContainsString($canary, $surface);
                }
                self::assertSame('Audit protected data is unavailable.', $exception->getMessage());
                self::assertSame('protection_failed', $exception->failure()->value);
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testPurposeEnumKeepsRecordAndOutboxDomainsDistinct(): void
    {
        self::assertSame(
            ['audit_record', 'audit_outbox_record'],
            array_map(
                static fn(AuditProtectionPurpose $purpose): string => $purpose->value,
                AuditProtectionPurpose::cases(),
            ),
        );
    }

    /** @return array{AuditProtectedBodyCodec,FixtureAuditKeySource} */
    private function protectedCodec(): array
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog]));
        $records = new AuditRecordCodec($validator, new AuditJcsCodec());
        $ingress = new AuditIngressEnvelopeCodec(
            $records,
            new AuditPartitionIdDeriver(new AuditJcsCodec()),
            new AuditJcsCodec(),
        );
        $keys = new FixtureAuditKeySource();
        $codec = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            $keys,
            new FixtureAuditNonceSource(),
            new AuditAssociatedData(),
            $records,
            $ingress,
        );
        return [$codec, $keys];
    }

    private function protectedCodecWithKeys(AuditProtectionKeySource $keys): AuditProtectedBodyCodec
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog]));
        $records = new AuditRecordCodec($validator, new AuditJcsCodec());
        $ingress = new AuditIngressEnvelopeCodec(
            $records,
            new AuditPartitionIdDeriver(new AuditJcsCodec()),
            new AuditJcsCodec(),
        );
        return new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            $keys,
            new FixtureAuditNonceSource(),
            new AuditAssociatedData(),
            $records,
            $ingress,
        );
    }

    /** @param array<string,mixed> $entry */
    private function complete(array $entry): CompleteAuditRecord
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog]));
        $records = new AuditRecordCodec($validator, new AuditJcsCodec());
        return $records->complete(
            new CanonicalAuditLogicalRecord($validator->validateLogical(
                new AuditLogicalRecordCandidate($entry['logical']),
            )),
            new AuditChainPosition(...array_values($entry['chain'])),
        );
    }

    /** @param array<string,mixed> $entry */
    private function ingress(array $entry): \BlackOps\Internal\Audit\Protection\AuditIngressEnvelope
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog]));
        $records = new AuditRecordCodec($validator, new AuditJcsCodec());
        $codec = new AuditIngressEnvelopeCodec(
            $records,
            new AuditPartitionIdDeriver(new AuditJcsCodec()),
            new AuditJcsCodec(),
        );
        return $codec->create(
            new CanonicalAuditLogicalRecord($validator->validateLogical(
                new AuditLogicalRecordCandidate($entry['logical']),
            )),
            $entry['ingressOrdinal'],
            $entry['idempotencyKey'],
        );
    }

    private function ingressCodec(): AuditIngressEnvelopeCodec
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

    /** @param callable():mixed $callback */
    private function assertUnavailable(callable $callback): void
    {
        try {
            $callback();
            self::fail('protected operation unexpectedly succeeded');
        } catch (AuditProtectionException $exception) {
            self::assertSame('Audit protected data is unavailable.', $exception->getMessage());
            self::assertSame('protection_failed', $exception->failure()->value);
            self::assertNull($exception->getPrevious());
        }
    }

    private function mutateBinaryPart(string $body, string $part): string
    {
        $keyLength = unpack('nvalue', substr($body, 6, 2))['value'];
        $nonceOffset = 8 + $keyLength;
        $ciphertextOffset = $nonceOffset + 24 + 4;
        return match ($part) {
            'nonce' => substr_replace($body, chr(ord($body[$nonceOffset]) ^ 1), $nonceOffset, 1),
            'ciphertext' => substr_replace($body, chr(ord($body[$ciphertextOffset]) ^ 1), $ciphertextOffset, 1),
            'tag' => substr_replace($body, chr(ord($body[strlen($body) - 1]) ^ 1), -1, 1),
            default => throw new \LogicException('unknown binary part'),
        };
    }

    private function replaceBinaryKeyId(string $body, string $keyId): string
    {
        $keyLength = unpack('nvalue', substr($body, 6, 2))['value'];
        self::assertSame(strlen($keyId), $keyLength);
        return substr_replace($body, $keyId, 8, $keyLength);
    }

    private function safeSerialize(object $value): string
    {
        try {
            return serialize($value);
        } catch (\Throwable $exception) {
            return $exception->getMessage();
        }
    }
}

final class FixtureAuditKeySource implements AuditProtectionKeySource
{
    public int $activeCalls = 0;

    public int $readCalls = 0;

    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        ++$this->activeCalls;
        return $this->keyFor($purpose);
    }

    public function key(string $keyId, ?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        ++$this->readCalls;
        $key = $this->keyFor($purpose);
        if ($key->id() !== $keyId)
            throw new RuntimeException('unknown fixture key');
        return $key;
    }

    private function keyFor(AuditProtectionPurpose $purpose): StorageKey
    {
        return match ($purpose) {
            AuditProtectionPurpose::AuditRecord => new StorageKey('audit-record-key', str_repeat('r', 32)),
            AuditProtectionPurpose::AuditOutboxRecord => new StorageKey('audit-outbox-key', str_repeat('o', 32)),
        };
    }
}

final class FixtureAuditNonceSource implements NonceSource
{
    private int $counter = 0;

    public function generate(): string
    {
        ++$this->counter;
        return str_repeat(chr($this->counter), 24);
    }
}

final class PurposeMismatchAuditKeySource implements AuditProtectionKeySource
{
    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        return match ($purpose) {
            AuditProtectionPurpose::AuditRecord => new StorageKey('audit-record-key', str_repeat('r', 32)),
            AuditProtectionPurpose::AuditOutboxRecord => new StorageKey('audit-outbox-key', str_repeat('o', 32)),
        };
    }

    public function key(string $keyId, ?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        return new StorageKey(
            $keyId,
            $purpose === AuditProtectionPurpose::AuditRecord ? str_repeat('o', 32) : str_repeat('r', 32),
        );
    }
}

final class LeakingAuditKeySource implements AuditProtectionKeySource
{
    public function __construct(
        private string $message = 'provider-canary material-canary tenant-canary record-canary plaintext-canary nonce-canary tag-canary ciphertext-canary content-hash-canary record-hash-canary',
    ) {}

    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        throw new RuntimeException($this->message);
    }

    public function key(string $keyId, ?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        throw new RuntimeException($this->message);
    }
}
