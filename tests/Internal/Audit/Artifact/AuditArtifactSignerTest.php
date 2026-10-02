<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Artifact;

use BlackOps\Internal\Audit\Artifact\AuditArtifactException;
use BlackOps\Internal\Audit\Artifact\AuditArtifactFailure;
use BlackOps\Internal\Audit\Artifact\AuditArtifactSigner;
use BlackOps\Internal\Audit\AuditJcsCodec;
use PHPUnit\Framework\TestCase;

final class AuditArtifactSignerTest extends TestCase
{
    public function testDeterministicCheckpointAndReceiptSigningMatchesAllLiterals(): void
    {
        $fixture = $this->fixture();
        $checkpointSeed = hex2bin($fixture['seeds']['checkpoint']);
        $anchorSeed = hex2bin($fixture['seeds']['anchor']);
        self::assertIsString($checkpointSeed);
        self::assertIsString($anchorSeed);
        $checkpointBytes = null;
        foreach ($fixture['checkpoints'] as $name => $row) {
            $bytes = AuditArtifactSigner::checkpoint(
                $row['unsigned'],
                $checkpointSeed,
                $fixture['trust']['snapshot'],
                $fixture['times']['verifiedAt'],
            );
            self::assertSame($row['completeBytes'], $bytes, $name . ' signed bytes');
            self::assertSame(
                $bytes,
                AuditArtifactSigner::checkpoint(
                    $row['unsigned'],
                    $checkpointSeed,
                    $fixture['trust']['snapshot'],
                    $fixture['times']['verifiedAt'],
                ),
                $name . ' deterministic bytes',
            );
            if ($name === 'systemOne') {
                $checkpointBytes = $bytes;
            }
        }
        self::assertIsString($checkpointBytes);
        foreach ($fixture['receipts'] as $name => $row) {
            $checkpointBytes = $fixture['checkpoints'][$row['checkpoint']]['completeBytes'];
            self::assertSame(
                $row['completeBytes'],
                AuditArtifactSigner::anchorReceipt(
                    $row['unsigned'],
                    $checkpointBytes,
                    $anchorSeed,
                    $fixture['trust']['snapshot'],
                    $fixture['times']['verifiedAt'],
                ),
                $name . ' signed bytes',
            );
        }
        self::assertStringNotContainsString($checkpointSeed, $checkpointBytes);
        $keyPair = sodium_crypto_sign_seed_keypair($checkpointSeed);
        $secretKey = sodium_crypto_sign_secretkey($keyPair);
        try {
            self::assertStringNotContainsString($secretKey, $checkpointBytes);
        } finally {
            sodium_memzero($secretKey);
            sodium_memzero($keyPair);
        }
    }

    public function testWrongSeedLengthAndMaterialAreSigningFailures(): void
    {
        $fixture = $this->fixture();
        $checkpoint = $fixture['checkpoints']['systemOne'];
        $anchorSeed = hex2bin($fixture['seeds']['anchor']);
        self::assertIsString($anchorSeed);
        foreach ([str_repeat("\x11", 31), str_repeat("\x11", 33), $anchorSeed] as $seed) {
            $this->assertSigningFailure(static function () use ($checkpoint, $seed, $fixture): void {
                AuditArtifactSigner::checkpoint(
                    $checkpoint['unsigned'],
                    $seed,
                    $fixture['trust']['snapshot'],
                    $fixture['times']['verifiedAt'],
                );
            });
        }
    }

    public function testInvalidTrustAndCheckpointBindingPrecedeInvalidSeed(): void
    {
        $fixture = $this->fixture();
        $checkpoint = $fixture['checkpoints']['systemOne'];
        $trust = $fixture['trust']['snapshot'];
        $trust['hash'] = str_repeat('0', 64);
        $this->assertTrustFailure(static function () use ($checkpoint, $trust, $fixture): void {
            AuditArtifactSigner::checkpoint($checkpoint['unsigned'], '', $trust, $fixture['times']['verifiedAt']);
        });

        $receipt = $fixture['receipts']['receiptV1'];
        $otherCheckpoint = $fixture['checkpoints']['tenantOne']['completeBytes'];
        $this->assertArtifactFailure(static function () use ($receipt, $otherCheckpoint, $fixture): void {
            AuditArtifactSigner::anchorReceipt(
                $receipt['unsigned'],
                $otherCheckpoint,
                '',
                $fixture['trust']['snapshot'],
                $fixture['times']['verifiedAt'],
            );
        });
    }

    public function testSigningFailuresAreFreshAndHaveNoCause(): void
    {
        $fixture = $this->fixture();
        $row = $fixture['checkpoints']['systemOne'];
        $exceptions = [];
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                AuditArtifactSigner::checkpoint(
                    $row['unsigned'],
                    str_repeat("\x11", 31),
                    $fixture['trust']['snapshot'],
                    $fixture['times']['verifiedAt'],
                );
                self::fail('invalid seed was accepted');
            } catch (AuditArtifactException $exception) {
                $exceptions[] = $exception;
                self::assertSame(AuditArtifactFailure::SigningUnavailable, $exception->failure());
                self::assertSame('Audit artifact operation failed.', $exception->getMessage());
                self::assertSame(0, $exception->getCode());
                self::assertNull($exception->getPrevious());
            }
        }
        self::assertCount(2, $exceptions);
        self::assertNotSame($exceptions[0], $exceptions[1]);
    }

    public function testOwnedFailureFramesRedactCanariesAndWrongDerivedPublicMaterial(): void
    {
        $fixture = $this->fixture();
        $unsigned = $fixture['checkpoints']['systemOne']['unsigned'];
        $unsigned['checkpointId'] = 'artifact-frame-canary';
        $trust = $fixture['trust']['snapshot'];
        $trust['ref'] = 'trust-frame-canary';
        $trust = $this->withHash($trust);
        $timeCanary = 'time-frame-canary';
        $previousIgnoreArgs = ini_get('zend.exception_ignore_args');
        self::assertIsString($previousIgnoreArgs);
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            try {
                AuditArtifactSigner::checkpoint($unsigned, 'seed-frame-canary', $trust, $timeCanary);
                self::fail('invalid trusted time was accepted');
            } catch (AuditArtifactException $exception) {
                self::assertSame(AuditArtifactFailure::TrustInvalid, $exception->failure());
                self::assertSame('Audit artifact operation failed.', $exception->getMessage());
                self::assertSame(0, $exception->getCode());
                self::assertNull($exception->getPrevious());
                $this->assertSensitiveFrame(
                    $exception,
                    AuditArtifactSigner::class,
                    'checkpoint',
                    4,
                    ['artifact-frame-canary', 'trust-frame-canary', $timeCanary, 'seed-frame-canary'],
                );
            }
        } finally {
            self::assertNotFalse(ini_set('zend.exception_ignore_args', $previousIgnoreArgs));
        }

        $seed = hex2bin($fixture['seeds']['checkpoint']);
        self::assertIsString($seed);
        $trust = $fixture['trust']['snapshot'];
        $wrongSeed = str_repeat("\x42", SODIUM_CRYPTO_SIGN_SEEDBYTES);
        $wrongKeyPair = sodium_crypto_sign_seed_keypair($wrongSeed);
        try {
            $wrongPublicKey = sodium_crypto_sign_publickey($wrongKeyPair);
            $trust['entries'][1]['publicKey'] = sodium_bin2base64(
                $wrongPublicKey,
                SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
            );
            $trust['entries'][1]['publicKeyFingerprint'] = hash('sha256', $wrongPublicKey);
            sodium_memzero($wrongPublicKey);
        } finally {
            sodium_memzero($wrongSeed);
            sodium_memzero($wrongKeyPair);
        }
        $trust = $this->withHash($trust);
        $previousIgnoreArgs = ini_get('zend.exception_ignore_args');
        self::assertIsString($previousIgnoreArgs);
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            try {
                AuditArtifactSigner::checkpoint(
                    $fixture['checkpoints']['systemOne']['unsigned'],
                    $seed,
                    $trust,
                    $fixture['times']['verifiedAt'],
                );
                self::fail('wrong derived public material was accepted');
            } catch (AuditArtifactException $exception) {
                self::assertSame(AuditArtifactFailure::SigningUnavailable, $exception->failure());
                self::assertSame('Audit artifact operation failed.', $exception->getMessage());
                self::assertSame(0, $exception->getCode());
                self::assertNull($exception->getPrevious());
                $this->assertSensitiveFrame($exception, AuditArtifactSigner::class, 'checkpoint', 4, [$seed]);
                $this->assertSensitiveFrame($exception, AuditArtifactSigner::class, 'sign', 3, [$seed]);
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

    /** @param array<string,mixed> $snapshot */
    private function withHash(array $snapshot): array
    {
        $body = $snapshot;
        unset($body['hash']);
        $bodyBytes = new AuditJcsCodec()->canonicalJson($body);
        $domain = 'blackops.audit.trust-store-snapshot.v1';
        $snapshot['hash'] = hash(
            'sha256',
            's:' . strlen($domain) . ':' . $domain . 'b:' . strlen($bodyBytes) . ':' . $bodyBytes,
        );
        return $snapshot;
    }

    private function assertSigningFailure(\Closure $operation): void
    {
        try {
            $operation();
            self::fail('invalid signing input was accepted');
        } catch (AuditArtifactException $exception) {
            self::assertSame(AuditArtifactFailure::SigningUnavailable, $exception->failure());
        }
    }

    private function assertTrustFailure(\Closure $operation): void
    {
        try {
            $operation();
            self::fail('invalid trust input was accepted');
        } catch (AuditArtifactException $exception) {
            self::assertSame(AuditArtifactFailure::TrustInvalid, $exception->failure());
        }
    }

    private function assertArtifactFailure(\Closure $operation): void
    {
        try {
            $operation();
            self::fail('invalid artifact binding was accepted');
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
