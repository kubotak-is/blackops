<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Checkpoint;

use BlackOps\Internal\Audit\Artifact\AuditCheckpointCodec;
use BlackOps\Internal\Audit\Checkpoint\AuditCheckpointPreparation;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointBinding;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use PHPUnit\Framework\TestCase;

final class AuditCheckpointPreparationTest extends TestCase
{
    private const string CHECKPOINT_ID = '01941f29-7c00-7000-8000-000000000101';

    private const string SEGMENT_ID = '01941f29-7c00-7000-8000-000000000102';

    private const string TOKEN = 'aft:v1:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const string FINGERPRINT = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testHydratesCanonicalUnsignedBytesAndDetachesReturnedValues(): void
    {
        $row = $this->row();
        $preparation = AuditCheckpointPreparation::fromRow($row);

        $unsigned = $preparation->unsigned();
        self::assertSame(
            AuditCheckpointCodec::signingInput($this->unsigned())['bytes'],
            AuditCheckpointCodec::signingInput($unsigned)['bytes'],
        );
        self::assertSame(
            [
                'partitionId' => 'system:global',
                'segmentId' => self::SEGMENT_ID,
                'closedStateVersion' => 2,
                'closeFencingToken' => self::TOKEN,
            ],
            $preparation->boundary(),
        );
        self::assertSame(self::FINGERPRINT, $preparation->signingKeyFingerprint());
        self::assertSame([], $preparation->__debugInfo());
        self::assertSame([], $preparation->__serialize());

        $unsigned['checkpointId'] = 'caller-mutated';
        $boundary = $preparation->boundary();
        $boundary['partitionId'] = 'caller-mutated';
        self::assertSame(self::CHECKPOINT_ID, $preparation->unsigned()['checkpointId']);
        self::assertSame('system:global', $preparation->boundary()['partitionId']);
    }

    public function testRejectsNonUuidV7IdentityAndMalformedStoredBytesAsFreshSafeFailures(): void
    {
        $invalidCheckpoint = $this->row();
        $invalidCheckpoint['checkpoint_id'] = '01941f29-7c00-6000-8000-000000000101';
        $this->assertIntegrityInvalid($invalidCheckpoint);

        $invalidSegment = $this->row();
        $invalidSegment['segment_id'] = '01941f29-7c00-6000-8000-000000000102';
        $this->assertIntegrityInvalid($invalidSegment);

        $duplicateKey = $this->row();
        $duplicateKey['unsigned_checkpoint'] = str_replace(
            '"kind":"audit_checkpoint"',
            '"kind":"audit_checkpoint","kind":"audit_checkpoint"',
            $duplicateKey['unsigned_checkpoint'],
            $count,
        );
        self::assertSame(1, $count);
        $this->assertIntegrityInvalid($duplicateKey);

        $deepBody = $this->row();
        $deepBody['unsigned_checkpoint'] = str_replace(
            '"kind":"audit_checkpoint"',
            '"kind":{"nested":{"tooDeep":true}}',
            $deepBody['unsigned_checkpoint'],
            $count,
        );
        self::assertSame(1, $count);
        $this->assertIntegrityInvalid($deepBody);

        $lineFeed = $this->row();
        $lineFeed['unsigned_checkpoint'] .= "\n";
        $this->assertIntegrityInvalid($lineFeed);

        $canonicalBytes = $this->row()['unsigned_checkpoint'];
        self::assertIsString($canonicalBytes);
        $nonCanonical = $this->row();
        $nonCanonical['unsigned_checkpoint'] = $canonicalBytes . ' ';
        $this->assertIntegrityInvalid($nonCanonical);

        $oversized = $this->row();
        $oversized['unsigned_checkpoint'] = $canonicalBytes . str_repeat(' ', 1_048_576 - strlen($canonicalBytes));
        $this->assertIntegrityInvalid($oversized);
    }

    public function testUnserializeAlwaysFailsWithoutRetainingCallerData(): void
    {
        try {
            new \ReflectionClass(AuditCheckpointPreparation::class)
                ->newInstanceWithoutConstructor()
                ->__unserialize(['canary' => 'preparation-unserialize-canary']);
            self::fail('unserialize accepted caller data');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertSame('Audit store operation failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('preparation-unserialize-canary', $exception->getMessage());
        }
    }

    public function testCanonicalPreparationBytesSurviveMapOrderAndRejectIdentityBoundaryMutations(): void
    {
        $unsigned = $this->unsigned();
        $reordered = array_reverse($unsigned, preserve_keys: true);
        $row = $this->row();
        $row['unsigned_checkpoint'] = AuditCheckpointCodec::signingInput($reordered)['bytes'];
        self::assertSame($row['unsigned_checkpoint'], AuditCheckpointCodec::signingInput($unsigned)['bytes']);
        $prepared = AuditCheckpointPreparation::fromRow($row)->unsigned();
        self::assertSame(
            AuditCheckpointCodec::signingInput($unsigned)['bytes'],
            AuditCheckpointCodec::signingInput($prepared)['bytes'],
        );

        foreach ([
            'checkpoint_id' => '01941f29-7c00-6000-8000-000000000101',
            'partition_id' => 'tenant:' . str_repeat('d', 64),
            'segment_id' => '01941f29-7c00-6000-8000-000000000102',
            'close_fencing_token' => 'aft:v1:' . str_repeat('e', 63),
            'signing_key_fingerprint' => str_repeat('f', 63),
        ] as $field => $value) {
            $mutated = $this->row();
            $mutated[$field] = $value;
            $this->assertIntegrityInvalid($mutated);
        }

        foreach ([1, 9_007_199_254_740_991, '01'] as $version) {
            $mutated = $this->row();
            $mutated['closed_state_version'] = $version;
            $this->assertIntegrityInvalid($mutated);
        }

        $bodyIdentity = $this->unsigned();
        $bodyIdentity['segmentId'] = '01941f29-7c00-7000-8000-000000000103';
        $mutated = $this->row();
        $mutated['unsigned_checkpoint'] = AuditCheckpointCodec::signingInput($bodyIdentity)['bytes'];
        $this->assertIntegrityInvalid($mutated);
    }

    public function testPreparationStateVersionBindingSeparatesClosedAndSealedReplay(): void
    {
        foreach ([
            ['closed', 2,                     2,                     true],
            ['closed', 2,                     3,                     false],
            ['sealed', 2,                     2,                     false],
            ['sealed', 2,                     3,                     true],
            ['sealed', 9_007_199_254_740_990, 9_007_199_254_740_991, true],
            ['sealed', 9_007_199_254_740_991, 9_007_199_254_740_992, false],
        ] as [$state, $captured, $current, $valid]) {
            try {
                PostgreSqlAuditCheckpointBinding::stateVersion($state, $captured, $current);
                self::assertTrue($valid, $state . ':' . $captured . ':' . $current);
            } catch (AuditStoreException $exception) {
                self::assertFalse($valid, $state . ':' . $captured . ':' . $current);
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }

        $proof = [
            'partitionId' => 'system:global',
            'segmentId' => self::SEGMENT_ID,
            'state' => 'closed',
            'stateVersion' => 2,
            'firstSequence' => 1,
            'lastSequence' => 1,
            'recordCount' => 1,
            'firstRecordHash' => str_repeat('c', 64),
            'chainHeadHash' => str_repeat('c', 64),
            'predecessorRecordHash' => null,
            'previousSegmentId' => null,
            'successorSegmentId' => '01941f29-7c00-7000-8000-000000000103',
            'closeFencingToken' => self::TOKEN,
            'closedAt' => '2030-01-02T03:03:00.000000Z',
        ];
        $boundary = [
            'partitionId' => 'system:global',
            'segmentId' => self::SEGMENT_ID,
            'closedStateVersion' => 2,
            'closeFencingToken' => self::TOKEN,
        ];
        $unsigned = $this->unsigned();
        PostgreSqlAuditCheckpointBinding::preparation($boundary, $proof, $unsigned, null);
        $unsigned['sealedAt'] = '2030-01-02T03:04:00.000000Z';
        $this->assertBindingFailure(static function () use ($boundary, $proof, $unsigned): void {
            PostgreSqlAuditCheckpointBinding::preparation($boundary, $proof, $unsigned, null);
        });
    }

    public function testPreparationRepresentationsAndFreshFailuresExcludeCallerCanaries(): void
    {
        $canary = 'checkpoint-preparation-sensitive-canary';
        $unsigned = $this->unsigned();
        $unsigned['signingKeyRef'] = $canary;
        $row = $this->row();
        $row['unsigned_checkpoint'] = AuditCheckpointCodec::signingInput($unsigned)['bytes'];
        $preparation = AuditCheckpointPreparation::fromRow($row);
        self::assertSame($canary, $preparation->unsigned()['signingKeyRef']);
        foreach ([
            serialize($preparation),
            (string) json_encode($preparation, JSON_THROW_ON_ERROR),
            var_export($preparation, return: true),
        ] as $representation) {
            self::assertStringNotContainsString($canary, $representation);
            self::assertStringNotContainsString(self::CHECKPOINT_ID, $representation);
        }

        $exceptions = [];
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                new \ReflectionClass(AuditCheckpointPreparation::class)
                    ->newInstanceWithoutConstructor()
                    ->__unserialize(['canary' => $canary]);
                self::fail('caller data was accepted');
            } catch (AuditStoreException $exception) {
                $exceptions[] = $exception;
                self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
                self::assertSame('Audit store operation failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString($canary, $exception->getTraceAsString());
            }
        }
        self::assertNotSame($exceptions[0], $exceptions[1]);
    }

    /** @return array<string,mixed> */
    private function row(): array
    {
        $unsigned = $this->unsigned();
        return [
            'checkpoint_id' => self::CHECKPOINT_ID,
            'partition_id' => 'system:global',
            'segment_id' => self::SEGMENT_ID,
            'closed_state_version' => '2',
            'close_fencing_token' => self::TOKEN,
            'unsigned_checkpoint' => AuditCheckpointCodec::signingInput($unsigned)['bytes'],
            'signing_key_fingerprint' => self::FINGERPRINT,
        ];
    }

    /** @return array<string,mixed> */
    private function unsigned(): array
    {
        return [
            'schemaVersion' => 1,
            'kind' => 'audit_checkpoint',
            'checkpointId' => self::CHECKPOINT_ID,
            'partitionId' => 'system:global',
            'segmentId' => self::SEGMENT_ID,
            'firstSequence' => 1,
            'lastSequence' => 1,
            'recordCount' => 1,
            'firstRecordHash' => str_repeat('c', 64),
            'chainHeadHash' => str_repeat('c', 64),
            'previousCheckpointHash' => null,
            'sealedAt' => '2030-01-02T03:03:00.000000Z',
            'signedAt' => '2030-01-02T03:04:00.000000Z',
            'hashSuite' => 'audit-sha256-jcs-v1',
            'signatureAlgorithm' => 'ed25519-v1',
            'signingKeyRef' => 'checkpoint-key',
        ];
    }

    /** @param array<string,mixed> $row */
    private function assertIntegrityInvalid(array $row): void
    {
        try {
            AuditCheckpointPreparation::fromRow($row);
            self::fail('malformed preparation row was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertSame('Audit store operation failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    /** @param callable():void $operation */
    private function assertBindingFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('invalid checkpoint binding was accepted');
        } catch (AuditStoreException $exception) {
            self::assertSame(AuditStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }
}
