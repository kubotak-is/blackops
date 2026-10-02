<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Artifact;

use BlackOps\Internal\Audit\Artifact\AuditArtifactException;
use BlackOps\Internal\Audit\Artifact\AuditArtifactFailure;
use BlackOps\Internal\Audit\Artifact\AuditCheckpointCodec;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Record\AuditChainPosition;
use BlackOps\Internal\Audit\Record\AuditLogicalRecordCandidate;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use PHPUnit\Framework\TestCase;

final class AuditCheckpointCodecTest extends TestCase
{
    public function testLiteralCheckpointRowsRoundTripAndHashExactBytes(): void
    {
        foreach ($this->fixture()['checkpoints'] as $name => $row) {
            $input = AuditCheckpointCodec::signingInput($row['unsigned']);
            self::assertSame($row['unsignedBytes'], $input['bytes'], $name . ' unsigned bytes');
            self::assertSame($row['checkpointHash'], $input['hash'], $name . ' checkpoint hash');
            self::assertSame($row['signaturePreimageHex'], bin2hex($input['preimage']), $name . ' preimage');
            $rawSignature = sodium_base642bin($row['signature'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            self::assertSame(SODIUM_CRYPTO_SIGN_BYTES, strlen($rawSignature), $name . ' signature length');
            self::assertSame(
                $row['completeBytes'],
                AuditCheckpointCodec::complete($row['unsigned'], $rawSignature),
                $name . ' complete bytes',
            );
            $decoded = $row['complete'];
            $actual = AuditCheckpointCodec::decode($row['completeBytes']);
            ksort($decoded);
            ksort($actual);
            self::assertSame($decoded, $actual, $name . ' decoded complete');
            self::assertSame(
                $row['completeBytesHash'],
                AuditCheckpointCodec::bytesHash($row['completeBytes']),
                $name . ' complete bytes hash',
            );
            self::assertStringNotContainsString("\n", $row['unsignedBytes'], $name . ' unsigned LF');
            self::assertStringNotContainsString("\n", $row['completeBytes'], $name . ' complete LF');
        }
    }

    public function testFixtureLineageProvidesRealSystemAndTenantEndpoints(): void
    {
        $fixture = $this->fixture();
        $canonicalFixture = $this->recordFixture();
        $codec = $this->recordCodec();
        $rows = $fixture['recordLineage']['rows'];
        self::assertCount(51, $rows);
        $sourcePath = __DIR__ . '/../../../Fixtures/Audit/audit-canonical-record-v1.json';
        $sourceHash = hash_file('sha256', $sourcePath);
        self::assertIsString($sourceHash);
        self::assertSame($fixture['recordLineage']['source']['sha256'], $sourceHash);

        foreach ($rows as $row) {
            /** @var array<string,mixed> $source */
            $source = $canonicalFixture['records'][$row['sourceRecord']];
            /** @var array<string,mixed> $logical */
            $logical = $source['logical'];
            if ($row['scope'] === 'tenant' && $row['chain']['sequence'] >= 3) {
                /** @var int $sequence */
                $sequence = $row['chain']['sequence'];
                $logical['recordId'] = sprintf('01941f29-7c00-7000-8000-%012x', 0x100000 + $sequence);
                /** @var array<string,mixed> $time */
                $time = $logical['time'];
                $time['occurredAt'] = sprintf('2026-08-19T00:00:%02d.000000Z', $sequence - 1);
                $time['recordedAt'] = $time['occurredAt'];
                $logical['time'] = $time;
            }
            self::assertSame($logical, $row['logical'], $row['rowId'] . ' logical source');
            $logicalRecord = $this->canonicalRecord($logical);
            $position = new AuditChainPosition(
                $row['chain']['partitionId'],
                $row['chain']['segmentId'],
                $row['chain']['sequence'],
                $row['chain']['previousRecordHash'],
            );
            self::assertSame(
                $row['logicalBytes'],
                $codec->logicalBytes($logicalRecord),
                $row['rowId'] . ' logical bytes',
            );
            self::assertSame($row['contentHash'], $codec->contentHash($logicalRecord), $row['rowId'] . ' content hash');
            $complete = $codec->complete($logicalRecord, $position);
            self::assertSame(
                $row['chain']['partitionId'],
                $complete->integrity()->partitionId,
                $row['rowId'] . ' partition',
            );
            self::assertSame($row['recordHash'], $complete->integrity()->recordHash, $row['rowId'] . ' record hash');
            self::assertSame($row['completeBytes'], $complete->bytes(), $row['rowId'] . ' complete bytes');
            self::assertSame(
                $row['completeBytes'],
                $codec->decode($row['completeBytes'])->bytes(),
                $row['rowId'] . ' decode',
            );
        }

        self::assertSame('systemGenesis', $rows[0]['sourceRecord']);
        self::assertSame('system:global', $rows[0]['chain']['partitionId']);
        self::assertSame($fixture['recordLineage']['partitions']['system'], $rows[0]['chain']['partitionId']);

        $tenant = [];
        foreach ($rows as $row) {
            if ($row['scope'] === 'tenant') {
                $tenant[$row['chain']['sequence']] = $row;
            }
        }
        self::assertCount(50, $tenant);
        ksort($tenant);
        self::assertSame($fixture['recordLineage']['partitions']['tenant'], $tenant[1]['chain']['partitionId']);
        self::assertSame($fixture['recordLineage']['partitions']['tenantInput'], $tenant[1]['logical']['tenant']);
        $previous = null;
        foreach ($tenant as $sequence => $row) {
            self::assertSame($sequence, $row['chain']['sequence']);
            self::assertSame($previous, $row['chain']['previousRecordHash']);
            $previous = $row['recordHash'];
        }
        self::assertSame($fixture['recordEndpoints']['systemGenesis'], $rows[0]['recordHash']);
        self::assertSame($fixture['recordEndpoints']['tenantSequence1'], $tenant[1]['recordHash']);
        self::assertSame($fixture['recordEndpoints']['tenantSequence42'], $tenant[42]['recordHash']);
        self::assertSame($fixture['recordEndpoints']['tenantSequence43'], $tenant[43]['recordHash']);
        self::assertSame($fixture['recordEndpoints']['tenantSequence50'], $tenant[50]['recordHash']);
        self::assertSame($fixture['recordLineage']['endpointRows']['systemGenesis'], $rows[0]['rowId']);
        self::assertSame($fixture['recordLineage']['endpointRows']['tenantSequence1'], $tenant[1]['rowId']);
        self::assertSame($fixture['recordLineage']['endpointRows']['tenantSequence42'], $tenant[42]['rowId']);
        self::assertSame($fixture['recordLineage']['endpointRows']['tenantSequence43'], $tenant[43]['rowId']);
        self::assertSame($fixture['recordLineage']['endpointRows']['tenantSequence50'], $tenant[50]['rowId']);
        self::assertSame($tenant[42]['recordHash'], $tenant[43]['chain']['previousRecordHash']);
        self::assertNotSame($tenant[42]['chain']['segmentId'], $tenant[43]['chain']['segmentId']);
        self::assertSame($fixture['recordLineage']['segments']['first'], $tenant[1]['chain']['segmentId']);
        self::assertSame($fixture['recordLineage']['segments']['first'], $tenant[42]['chain']['segmentId']);
        self::assertSame($fixture['recordLineage']['segments']['second'], $tenant[43]['chain']['segmentId']);
        self::assertSame(43, $fixture['recordLineage']['segments']['transitionAtSequence']);
        self::assertSame(
            $tenant[1]['recordHash'],
            $fixture['checkpoints']['tenantRange1to42']['unsigned']['firstRecordHash'],
        );
        self::assertSame(
            $tenant[42]['recordHash'],
            $fixture['checkpoints']['tenantRange1to42']['unsigned']['chainHeadHash'],
        );
        self::assertSame(
            $tenant[43]['recordHash'],
            $fixture['checkpoints']['tenantRange43to50']['unsigned']['firstRecordHash'],
        );
        self::assertSame(
            $tenant[50]['recordHash'],
            $fixture['checkpoints']['tenantRange43to50']['unsigned']['chainHeadHash'],
        );
        self::assertSame(
            $fixture['checkpoints']['tenantRange1to42']['checkpointHash'],
            $fixture['checkpoints']['tenantRange43to50']['unsigned']['previousCheckpointHash'],
        );
    }

    public function testClosedShapeFixedGrammarAndRelationsRejectInvalidUnsignedRows(): void
    {
        $fixture = $this->fixture();
        $range = $fixture['checkpoints']['tenantRange1to42']['unsigned'];
        foreach ([
            'schemaVersion' => '1',
            'kind' => 'other',
            'checkpointId' => 42,
            'partitionId' => 'not-a-partition',
            'segmentId' => '01941f29-7c00-6000-8000-000000000101',
            'firstSequence' => '1',
            'lastSequence' => '42',
            'recordCount' => '42',
            'firstRecordHash' => 'not-a-digest',
            'chainHeadHash' => 'not-a-digest',
            'previousCheckpointHash' => 'not-a-digest',
            'sealedAt' => 'not-a-timestamp',
            'signedAt' => 'not-a-timestamp',
            'hashSuite' => 'other-suite',
            'signatureAlgorithm' => 'other-signature',
            'signingKeyRef' => 42,
        ] as $field => $invalid) {
            $mutated = $range;
            $mutated[$field] = $invalid;
            $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::signingInput($mutated));
        }
        $completeRange = $fixture['checkpoints']['tenantRange1to42']['complete'];
        foreach ([
            'schemaVersion' => '1',
            'kind' => 'other',
            'checkpointId' => 42,
            'partitionId' => 'not-a-partition',
            'segmentId' => 'not-a-segment',
            'firstSequence' => '1',
            'lastSequence' => '42',
            'recordCount' => '42',
            'firstRecordHash' => 'not-a-digest',
            'chainHeadHash' => 'not-a-digest',
            'previousCheckpointHash' => 'not-a-digest',
            'sealedAt' => 'not-a-timestamp',
            'signedAt' => 'not-a-timestamp',
            'hashSuite' => 'other-suite',
            'signatureAlgorithm' => 'other-signature',
            'signingKeyRef' => 42,
        ] as $field => $invalid) {
            $mutated = $completeRange;
            $mutated[$field] = $invalid;
            $mutated = $this->withCheckpointHash($mutated);
            $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::decode(new AuditJcsCodec()->canonicalJson(
                $mutated,
            )));
        }
        $missing = $range;
        unset($missing['checkpointId']);
        $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::signingInput($missing));
        $extra = [...$range, 'extra' => true];
        $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::signingInput($extra));
        $oneRecord = $fixture['checkpoints']['systemOne']['unsigned'];
        $oneRecord['firstRecordHash'] = str_repeat('0', 64);
        $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::signingInput($oneRecord));
        $oneRecord = $fixture['checkpoints']['systemOne']['unsigned'];
        $oneRecord['previousCheckpointHash'] = str_repeat('0', 64);
        $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::signingInput($oneRecord));

        $invalidComplete = $fixture['checkpoints']['systemOne']['complete'];
        $invalidComplete['recordCount'] = 2;
        $invalidBytes = new AuditJcsCodec()->canonicalJson($invalidComplete);
        $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::decode($invalidBytes));

        $upper = $fixture['checkpoints']['systemOne']['unsigned'];
        $upper['firstSequence'] = 9_007_199_254_740_991;
        $upper['lastSequence'] = 9_007_199_254_740_991;
        $upper['previousCheckpointHash'] = str_repeat('0', 64);
        $upperInput = AuditCheckpointCodec::signingInput($upper);
        self::assertStringContainsString('"firstSequence":9007199254740991', $upperInput['bytes']);

        foreach (array_keys($fixture['checkpoints']['systemOne']['complete']) as $field) {
            $complete = $fixture['checkpoints']['systemOne']['complete'];
            unset($complete[$field]);
            if ($field !== 'checkpointHash') {
                $complete = $this->withCheckpointHash($complete);
            }
            $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::decode(new AuditJcsCodec()->canonicalJson(
                $complete,
            )));
        }
    }

    public function testCompleteRelationsUseIndependentlyRepairedHashes(): void
    {
        $fixture = $this->fixture();
        $complete = $fixture['checkpoints']['tenantRange1to42']['complete'];
        $complete['lastSequence'] = 41;
        $complete['recordCount'] = 41;
        $unsigned = $complete;
        unset($unsigned['checkpointHash'], $unsigned['signature']);
        $complete['checkpointHash'] = $this->artifactHash(
            'blackops.audit.checkpoint.hash.v1',
            new AuditJcsCodec()->canonicalJson($unsigned),
        );
        $validBytes = new AuditJcsCodec()->canonicalJson($complete);
        self::assertSame(41, AuditCheckpointCodec::decode($validBytes)['recordCount']);

        $invalid = $complete;
        $invalid['recordCount'] = 42;
        $invalidUnsigned = $invalid;
        unset($invalidUnsigned['checkpointHash'], $invalidUnsigned['signature']);
        $invalid['checkpointHash'] = $this->artifactHash(
            'blackops.audit.checkpoint.hash.v1',
            new AuditJcsCodec()->canonicalJson($invalidUnsigned),
        );
        $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::decode(new AuditJcsCodec()->canonicalJson(
            $invalid,
        )));

        $wrongStoredHash = $fixture['checkpoints']['systemOne']['complete'];
        $wrongStoredHash['checkpointHash'] = str_repeat('0', 64);
        $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::decode(new AuditJcsCodec()->canonicalJson(
            $wrongStoredHash,
        )));
    }

    public function testRawPreflightRunsBeforeRecursiveJcsAndPreservesQuotedData(): void
    {
        $fixture = $this->fixture();
        $wide =
            '{' . implode(',', array_map(static fn(int $index): string => '"m' . $index . '":1', range(0, 18))) . '}';
        $overCap = '{' . str_repeat(' ', 1_048_574) . '}';
        foreach ([
            '{"a":{"b":{"c":1}}}',
            '{"a":[' . str_repeat('[', 16) . '1' . str_repeat(']', 16) . ']}',
            $wide,
            $overCap,
            "\n" . $fixture['checkpoints']['systemOne']['completeBytes'],
            "\xEF\xBB\xBF" . $fixture['checkpoints']['systemOne']['completeBytes'],
        ] as $bytes) {
            $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::decode($bytes));
        }

        $unsigned = $fixture['checkpoints']['systemOne']['unsigned'];
        $unsigned['checkpointId'] = 'comma,brace{}bracket[]slash\\quote"';
        $input = AuditCheckpointCodec::signingInput($unsigned);
        self::assertStringContainsString('comma,brace{}bracket[]', $input['bytes']);
        $complete = AuditCheckpointCodec::complete($unsigned, str_repeat("\0", 64));
        $decoded = array_diff_key(AuditCheckpointCodec::decode($complete), [
            'checkpointHash' => true,
            'signature' => true,
        ]);
        ksort($decoded);
        ksort($unsigned);
        self::assertSame($unsigned, $decoded);
    }

    public function testTextDuplicateAndSignatureEncodingBoundariesReject(): void
    {
        $fixture = $this->fixture();
        $unsigned = $fixture['checkpoints']['systemOne']['unsigned'];
        $unsigned['checkpointId'] = str_repeat('a', 256);
        $input = AuditCheckpointCodec::signingInput($unsigned);
        self::assertStringContainsString($unsigned['checkpointId'], $input['bytes']);
        $unsigned = $fixture['checkpoints']['systemOne']['unsigned'];
        foreach ([
            str_repeat('a', 257),
            "invalid\xC3\x28",
            'control' . chr(1),
            "format\u{200B}",
            "e\u{301}",
        ] as $value) {
            $unsigned['checkpointId'] = $value;
            $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::signingInput($unsigned));
        }
        $this->assertArtifactFailure(
            static fn(): mixed => AuditCheckpointCodec::decode('{"schemaVersion":1,"schemaVersion":1}'),
        );
        $this->assertArtifactFailure(
            static fn(): mixed => AuditCheckpointCodec::decode('{"schemaVersion":1,"\\u0073chemaVersion":1}'),
        );

        $row = $fixture['checkpoints']['systemOne'];
        $padded = str_replace(
            '"signature":"' . $row['signature'] . '"',
            '"signature":"' . $row['signature'] . '="',
            $row['completeBytes'],
        );
        $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::decode($padded));
        $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::decode($row['completeBytes'] . "\n"));
        $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::decode($row['completeBytes'] . "\r"));
        $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::decode(str_replace(
            '"recordCount":1',
            '"recordCount":1.0',
            $row['completeBytes'],
        )));
        $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::decode(str_replace(
            '"recordCount":1',
            '"recordCount":9007199254740992',
            $row['completeBytes'],
        )));
        $firstComma = strpos($row['completeBytes'], ',');
        $secondComma = strpos($row['completeBytes'], ',', $firstComma === false ? 0 : $firstComma + 1);
        self::assertIsInt($firstComma);
        self::assertIsInt($secondComma);
        $firstMember = substr($row['completeBytes'], 1, $firstComma - 1);
        $secondMember = substr($row['completeBytes'], $firstComma + 1, $secondComma - $firstComma - 1);
        $reordered = '{' . $secondMember . ',' . $firstMember . substr($row['completeBytes'], $secondComma);
        $this->assertArtifactFailure(static fn(): mixed => AuditCheckpointCodec::decode($reordered));
    }

    public function testFailureExceptionIsFixedAndFresh(): void
    {
        $canary = 'checkpoint-wire-secret-canary-1a2b';
        $previousIgnoreArgs = ini_get('zend.exception_ignore_args');
        self::assertIsString($previousIgnoreArgs);
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            try {
                AuditCheckpointCodec::decode('{"kind":"' . $canary . '"}');
                self::fail('invalid checkpoint was accepted');
            } catch (AuditArtifactException $exception) {
                self::assertSame(AuditArtifactFailure::ArtifactInvalid, $exception->failure());
                self::assertSame('Audit artifact operation failed.', $exception->getMessage());
                self::assertSame(0, $exception->getCode());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString($canary, $exception->getMessage());
                $this->assertSensitiveFrame($exception, AuditCheckpointCodec::class, 'decode', 1, [$canary]);
            }
        } finally {
            self::assertNotFalse(ini_set('zend.exception_ignore_args', $previousIgnoreArgs));
        }
    }

    /** @return array<string,mixed> */
    private function fixture(): array
    {
        $decoded = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../Fixtures/Audit/audit-signed-artifacts-v1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($decoded);
        return $decoded;
    }

    /** @return array<string,mixed> */
    private function recordFixture(): array
    {
        $decoded = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../Fixtures/Audit/audit-canonical-record-v1.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($decoded);
        return $decoded;
    }

    private function recordCodec(): AuditRecordCodec
    {
        $lookup = new ImmutableAuditEventCatalogLookup([FrameworkAuditEventCatalog::catalog()]);
        return new AuditRecordCodec(new AuditRecordValidator($lookup), new AuditJcsCodec());
    }

    /** @param array<string,mixed> $logical */
    private function canonicalRecord(array $logical): CanonicalAuditLogicalRecord
    {
        $lookup = new ImmutableAuditEventCatalogLookup([FrameworkAuditEventCatalog::catalog()]);
        $validated = new AuditRecordValidator($lookup)->validateLogical(new AuditLogicalRecordCandidate($logical));
        return new CanonicalAuditLogicalRecord($validated);
    }

    private function artifactHash(string $domain, string $bytes): string
    {
        return hash('sha256', 's:' . strlen($domain) . ':' . $domain . 'b:' . strlen($bytes) . ':' . $bytes);
    }

    /** @param array<string,mixed> $complete */
    private function withCheckpointHash(array $complete): array
    {
        $unsigned = $complete;
        unset($unsigned['checkpointHash'], $unsigned['signature']);
        $complete['checkpointHash'] = $this->artifactHash(
            'blackops.audit.checkpoint.hash.v1',
            new AuditJcsCodec()->canonicalJson($unsigned),
        );
        return $complete;
    }

    private function assertArtifactFailure(\Closure $operation): void
    {
        try {
            $operation();
            self::fail('invalid artifact was accepted');
        } catch (AuditArtifactException $exception) {
            self::assertSame(AuditArtifactFailure::ArtifactInvalid, $exception->failure());
        }
    }

    /**
     * @param list<string> $canaries
     */
    private function assertSensitiveFrame(
        \Throwable $failure,
        string $class,
        string $function,
        int $argumentCount,
        array $canaries,
    ): void {
        $ownedTrace = [];
        $targetSeen = false;
        foreach ($failure->getTrace() as $frame) {
            $frameClass = $frame['class'] ?? null;
            $frameFunction = $frame['function'] ?? null;
            if (!is_string($frameClass) || !is_string($frameFunction)) {
                continue;
            }
            if ($frameClass === AuditArtifactException::class || $frameClass === AuditArtifactFailure::class) {
                continue;
            }
            if (!str_starts_with($frameClass, 'BlackOps\\Internal\\Audit\\Artifact\\')) {
                continue;
            }
            $ownedTrace[] = $frame;
            $arguments = $frame['args'] ?? [];
            self::assertIsArray($arguments, $frameClass . '::' . $frameFunction . ' arguments');
            foreach ($arguments as $position => $argument) {
                self::assertInstanceOf(
                    \SensitiveParameterValue::class,
                    $argument,
                    $frameClass . '::' . $frameFunction . ' argument ' . $position,
                );
            }
            if ($frameClass === $class && $frameFunction === $function) {
                self::assertFalse($targetSeen, $class . '::' . $function . ' frame repeated');
                $targetSeen = true;
                self::assertCount($argumentCount, $arguments, $class . '::' . $function . ' argument count');
            }
        }
        self::assertTrue($targetSeen, $class . '::' . $function . ' frame missing');

        $surfaces = [
            'trace-string' => $failure->getTraceAsString(),
            'print-r' => print_r($ownedTrace, true),
            'var-export' => var_export($ownedTrace, true),
        ];
        ob_start();
        debug_zval_dump($ownedTrace);
        $surfaces['debug'] = (string) ob_get_clean();
        $serialized = null;
        try {
            $serialized = serialize($ownedTrace);
        } catch (\Exception $exception) {
            self::assertSame("Serialization of 'SensitiveParameterValue' is not allowed", $exception->getMessage());
            foreach ($canaries as $canary) {
                self::assertStringNotContainsString($canary, $exception->getMessage());
            }
        }
        if ($serialized !== null) {
            $surfaces['serialize'] = $serialized;
        }
        foreach ($surfaces as $surface => $output) {
            self::assertIsString($output, $surface);
            foreach ($canaries as $canary) {
                self::assertStringNotContainsString($canary, $output, $surface);
            }
        }
    }
}
