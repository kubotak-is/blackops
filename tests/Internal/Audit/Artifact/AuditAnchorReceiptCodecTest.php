<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Artifact;

use BlackOps\Internal\Audit\Artifact\AuditAnchorReceiptCodec;
use BlackOps\Internal\Audit\Artifact\AuditArtifactException;
use BlackOps\Internal\Audit\Artifact\AuditArtifactFailure;
use BlackOps\Internal\Audit\AuditJcsCodec;
use PHPUnit\Framework\TestCase;

final class AuditAnchorReceiptCodecTest extends TestCase
{
    public function testLiteralReceiptRowsRoundTripAndBindToTheirCheckpoints(): void
    {
        $fixture = $this->fixture();
        foreach ($fixture['receipts'] as $name => $row) {
            $input = AuditAnchorReceiptCodec::signingInput($row['unsigned']);
            self::assertSame($row['unsignedBytes'], $input['bytes'], $name . ' unsigned bytes');
            self::assertSame($row['anchorReceiptHash'], $input['hash'], $name . ' receipt hash');
            self::assertSame($row['signaturePreimageHex'], bin2hex($input['preimage']), $name . ' preimage');
            $rawSignature = sodium_base642bin($row['signature'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            self::assertSame(SODIUM_CRYPTO_SIGN_BYTES, strlen($rawSignature), $name . ' signature length');
            self::assertSame(
                $row['completeBytes'],
                AuditAnchorReceiptCodec::complete($row['unsigned'], $rawSignature),
                $name . ' complete bytes',
            );
            $decoded = $row['complete'];
            $actual = AuditAnchorReceiptCodec::decode($row['completeBytes']);
            ksort($decoded);
            ksort($actual);
            self::assertSame($decoded, $actual, $name . ' decoded complete');
            AuditAnchorReceiptCodec::assertCheckpointBinding(
                $row['unsigned'],
                $fixture['checkpoints'][$row['checkpoint']]['completeBytes'],
            );
        }
    }

    public function testReceiptRowsFormAChronologicalSameObjectChain(): void
    {
        $fixture = $this->fixture();
        $first = $fixture['receipts']['receiptV1'];
        $second = $fixture['receipts']['receiptV2'];
        self::assertSame(1, $first['unsigned']['anchorObjectVersion']);
        self::assertNull($first['unsigned']['previousAnchorReceiptHash']);
        self::assertSame(2, $second['unsigned']['anchorObjectVersion']);
        self::assertSame($first['anchorReceiptHash'], $second['unsigned']['previousAnchorReceiptHash']);
        foreach (['partitionId', 'anchorStoreRef', 'anchorObjectRef'] as $field) {
            self::assertSame($first['unsigned'][$field], $second['unsigned'][$field], $field . ' chain');
        }
        self::assertLessThan($second['unsigned']['anchoredAt'], $first['unsigned']['signedAt']);
        self::assertSame(
            $second['unsigned']['checkpointBytesHash'],
            $fixture['checkpoints'][$second['checkpoint']]['completeBytesHash'],
        );
    }

    public function testBindingVersionAndChronologyMismatchesAreArtifactFailures(): void
    {
        $fixture = $this->fixture();
        $row = $fixture['receipts']['receiptV2'];
        $checkpointBytes = $fixture['checkpoints'][$row['checkpoint']]['completeBytes'];
        foreach ([
            static function (array $value): array {
                $value['checkpointId'] = 'wrong-checkpoint';
                return $value;
            },
            static function (array $value): array {
                $value['partitionId'] = 'system:global';
                return $value;
            },
            static function (array $value): array {
                $value['segmentId'] = '01941f29-7c00-7000-8000-000000000104';
                return $value;
            },
            static function (array $value): array {
                $value['checkpointHash'] = str_repeat('0', 64);
                return $value;
            },
            static function (array $value): array {
                $value['checkpointBytesHash'] = str_repeat('0', 64);
                return $value;
            },
            static function (array $value): array {
                $value['previousAnchorReceiptHash'] = null;
                return $value;
            },
            static function (array $value): array {
                $value['anchoredAt'] = '2030-01-02T03:04:05.000000Z';
                $value['signedAt'] = '2030-01-02T03:04:04.000000Z';
                return $value;
            },
        ] as $mutation) {
            $this->assertArtifactFailure(static function () use ($mutation, $row, $checkpointBytes): void {
                AuditAnchorReceiptCodec::assertCheckpointBinding($mutation($row['unsigned']), $checkpointBytes);
            });
        }
        $this->assertArtifactFailure(static function () use ($row, $checkpointBytes): void {
            AuditAnchorReceiptCodec::assertCheckpointBinding($row['unsigned'], $checkpointBytes . "\n");
        });

        $upper = $fixture['receipts']['receiptV1']['unsigned'];
        $upper['anchorObjectVersion'] = 9_007_199_254_740_991;
        $upper['previousAnchorReceiptHash'] = str_repeat('0', 64);
        $upperInput = AuditAnchorReceiptCodec::signingInput($upper);
        self::assertStringContainsString('"anchorObjectVersion":9007199254740991', $upperInput['bytes']);
    }

    public function testCompleteDecodeRerunsReceiptRelationsAndRawPreflight(): void
    {
        $fixture = $this->fixture();
        $validComplete = $fixture['receipts']['receiptV2']['complete'];
        $validComplete['anchorObjectVersion'] = 3;
        $unsigned = $validComplete;
        unset($unsigned['anchorReceiptHash'], $unsigned['signature']);
        $validComplete['anchorReceiptHash'] = $this->artifactHash(
            'blackops.audit.anchor.hash.v1',
            new AuditJcsCodec()->canonicalJson($unsigned),
        );
        $validBytes = new AuditJcsCodec()->canonicalJson($validComplete);
        self::assertSame(3, AuditAnchorReceiptCodec::decode($validBytes)['anchorObjectVersion']);

        $invalidComplete = $validComplete;
        $invalidComplete['previousAnchorReceiptHash'] = null;
        $invalidUnsigned = $invalidComplete;
        unset($invalidUnsigned['anchorReceiptHash'], $invalidUnsigned['signature']);
        $invalidComplete['anchorReceiptHash'] = $this->artifactHash(
            'blackops.audit.anchor.hash.v1',
            new AuditJcsCodec()->canonicalJson($invalidUnsigned),
        );
        $this->assertArtifactFailure(static fn(): mixed => AuditAnchorReceiptCodec::decode(new AuditJcsCodec()->canonicalJson(
            $invalidComplete,
        )));

        $wrongStoredHash = $fixture['receipts']['receiptV1']['complete'];
        $wrongStoredHash['anchorReceiptHash'] = str_repeat('0', 64);
        $this->assertArtifactFailure(static fn(): mixed => AuditAnchorReceiptCodec::decode(new AuditJcsCodec()->canonicalJson(
            $wrongStoredHash,
        )));

        $wide =
            '{' . implode(',', array_map(static fn(int $index): string => '"m' . $index . '":1', range(0, 18))) . '}';
        foreach ([
            '{"a":[1]}',
            $wide,
            '{' . str_repeat(' ', 1_048_574) . '}',
        ] as $bytes) {
            $this->assertArtifactFailure(static fn(): mixed => AuditAnchorReceiptCodec::decode($bytes));
        }
        $canary = 'anchor-wire-frame-canary';
        $previousIgnoreArgs = ini_get('zend.exception_ignore_args');
        self::assertIsString($previousIgnoreArgs);
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            try {
                AuditAnchorReceiptCodec::decode('{"kind":"' . $canary . '"}');
                self::fail('malformed canary receipt was accepted');
            } catch (AuditArtifactException $exception) {
                self::assertSame(AuditArtifactFailure::ArtifactInvalid, $exception->failure());
                self::assertStringNotContainsString($canary, $exception->getMessage());
                self::assertNull($exception->getPrevious());
                $this->assertSensitiveFrame($exception, AuditAnchorReceiptCodec::class, 'decode', 1, [$canary]);
            }
        } finally {
            self::assertNotFalse(ini_set('zend.exception_ignore_args', $previousIgnoreArgs));
        }
    }

    public function testTextAndSignatureEncodingBoundariesRejectInvalidValues(): void
    {
        $fixture = $this->fixture();
        $unsigned = $fixture['receipts']['receiptV1']['unsigned'];
        $unsigned['anchorObjectRef'] = 'comma,brace{}bracket[]slash\\quote"';
        $complete = AuditAnchorReceiptCodec::complete($unsigned, str_repeat("\0", 64));
        $decoded = array_diff_key(AuditAnchorReceiptCodec::decode($complete), [
            'anchorReceiptHash' => true,
            'signature' => true,
        ]);
        ksort($decoded);
        ksort($unsigned);
        self::assertSame($unsigned, $decoded);
        $unsigned = $fixture['receipts']['receiptV1']['unsigned'];
        $unsigned['anchorObjectRef'] = str_repeat('a', 256);
        $input = AuditAnchorReceiptCodec::signingInput($unsigned);
        self::assertStringContainsString($unsigned['anchorObjectRef'], $input['bytes']);
        $unsigned = $fixture['receipts']['receiptV1']['unsigned'];
        foreach ([
            str_repeat('a', 257),
            'control' . chr(1),
            "format\u{200B}",
            "invalid\xC3\x28",
            "e\u{301}",
        ] as $value) {
            $unsigned['anchorObjectRef'] = $value;
            $this->assertArtifactFailure(static fn(): mixed => AuditAnchorReceiptCodec::signingInput($unsigned));
        }
        $this->assertArtifactFailure(
            static fn(): mixed => AuditAnchorReceiptCodec::decode('{"schemaVersion":1,"schemaVersion":1}'),
        );
        $this->assertArtifactFailure(
            static fn(): mixed => AuditAnchorReceiptCodec::decode('{"schemaVersion":1,"\\u0073chemaVersion":1}'),
        );
        $row = $fixture['receipts']['receiptV1'];
        $padded = str_replace(
            '"signature":"' . $row['signature'] . '"',
            '"signature":"' . $row['signature'] . '="',
            $row['completeBytes'],
        );
        $this->assertArtifactFailure(static fn(): mixed => AuditAnchorReceiptCodec::decode($padded));
        $this->assertArtifactFailure(static fn(): mixed => AuditAnchorReceiptCodec::decode(
            $row['completeBytes'] . "\n",
        ));
        $this->assertArtifactFailure(static fn(): mixed => AuditAnchorReceiptCodec::decode(
            $row['completeBytes'] . "\r",
        ));
        $this->assertArtifactFailure(static fn(): mixed => AuditAnchorReceiptCodec::decode(str_replace(
            '"anchorObjectVersion":1',
            '"anchorObjectVersion":1.0',
            $row['completeBytes'],
        )));
        $this->assertArtifactFailure(static fn(): mixed => AuditAnchorReceiptCodec::decode(str_replace(
            '"anchorObjectVersion":1',
            '"anchorObjectVersion":9007199254740992',
            $row['completeBytes'],
        )));
        $firstComma = strpos($row['completeBytes'], ',');
        $secondComma = strpos($row['completeBytes'], ',', $firstComma === false ? 0 : $firstComma + 1);
        self::assertIsInt($firstComma);
        self::assertIsInt($secondComma);
        $firstMember = substr($row['completeBytes'], 1, $firstComma - 1);
        $secondMember = substr($row['completeBytes'], $firstComma + 1, $secondComma - $firstComma - 1);
        $reordered = '{' . $secondMember . ',' . $firstMember . substr($row['completeBytes'], $secondComma);
        $this->assertArtifactFailure(static fn(): mixed => AuditAnchorReceiptCodec::decode($reordered));
    }

    public function testClosedShapeRejectsEveryUnsignedFieldAndCompleteOmission(): void
    {
        $fixture = $this->fixture();
        $unsigned = $fixture['receipts']['receiptV2']['unsigned'];
        foreach ([
            'schemaVersion' => '1',
            'kind' => 'other',
            'anchorReceiptId' => 42,
            'partitionId' => 'not-a-partition',
            'segmentId' => 'not-a-segment',
            'checkpointId' => 42,
            'checkpointHash' => 'not-a-digest',
            'checkpointBytesHash' => 'not-a-digest',
            'anchorStoreRef' => 42,
            'anchorObjectRef' => 42,
            'anchorObjectVersion' => '2',
            'previousAnchorReceiptHash' => 'not-a-digest',
            'anchoredAt' => 'not-a-timestamp',
            'signedAt' => 'not-a-timestamp',
            'signatureAlgorithm' => 'other-signature',
            'signingKeyRef' => 42,
        ] as $field => $invalid) {
            $mutated = $unsigned;
            $mutated[$field] = $invalid;
            $this->assertArtifactFailure(static fn(): mixed => AuditAnchorReceiptCodec::signingInput($mutated));
        }
        $completeReceipt = $fixture['receipts']['receiptV2']['complete'];
        foreach ([
            'schemaVersion' => '1',
            'kind' => 'other',
            'anchorReceiptId' => 42,
            'partitionId' => 'not-a-partition',
            'segmentId' => 'not-a-segment',
            'checkpointId' => 42,
            'checkpointHash' => 'not-a-digest',
            'checkpointBytesHash' => 'not-a-digest',
            'anchorStoreRef' => 42,
            'anchorObjectRef' => 42,
            'anchorObjectVersion' => '2',
            'previousAnchorReceiptHash' => 'not-a-digest',
            'anchoredAt' => 'not-a-timestamp',
            'signedAt' => 'not-a-timestamp',
            'signatureAlgorithm' => 'other-signature',
            'signingKeyRef' => 42,
        ] as $field => $invalid) {
            $mutated = $completeReceipt;
            $mutated[$field] = $invalid;
            $mutated = $this->withAnchorReceiptHash($mutated);
            $this->assertArtifactFailure(static fn(): mixed => AuditAnchorReceiptCodec::decode(new AuditJcsCodec()->canonicalJson(
                $mutated,
            )));
        }
        $missing = $unsigned;
        unset($missing['checkpointId']);
        $this->assertArtifactFailure(static fn(): mixed => AuditAnchorReceiptCodec::signingInput($missing));
        $extra = [...$unsigned, 'extra' => true];
        $this->assertArtifactFailure(static fn(): mixed => AuditAnchorReceiptCodec::signingInput($extra));

        foreach (array_keys($fixture['receipts']['receiptV1']['complete']) as $field) {
            $complete = $fixture['receipts']['receiptV1']['complete'];
            unset($complete[$field]);
            if ($field !== 'anchorReceiptHash') {
                $complete = $this->withAnchorReceiptHash($complete);
            }
            $this->assertArtifactFailure(static fn(): mixed => AuditAnchorReceiptCodec::decode(new AuditJcsCodec()->canonicalJson(
                $complete,
            )));
        }
    }

    private function artifactHash(string $domain, string $bytes): string
    {
        return hash('sha256', 's:' . strlen($domain) . ':' . $domain . 'b:' . strlen($bytes) . ':' . $bytes);
    }

    /** @param array<string,mixed> $complete */
    private function withAnchorReceiptHash(array $complete): array
    {
        $unsigned = $complete;
        unset($unsigned['anchorReceiptHash'], $unsigned['signature']);
        $complete['anchorReceiptHash'] = $this->artifactHash(
            'blackops.audit.anchor.hash.v1',
            new AuditJcsCodec()->canonicalJson($unsigned),
        );
        return $complete;
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
