<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Artifact;

use BlackOps\Internal\Audit\Artifact\AuditAnchorReceiptCodec;
use BlackOps\Internal\Audit\Artifact\AuditArtifactException;
use BlackOps\Internal\Audit\Artifact\AuditArtifactFailure;
use BlackOps\Internal\Audit\Artifact\AuditArtifactVerifier;
use BlackOps\Internal\Audit\Artifact\AuditCheckpointCodec;
use PHPUnit\Framework\TestCase;

final class AuditArtifactVerifierTest extends TestCase
{
    public function testCheckpointAndReceiptLiteralsVerifyAgainstTheirSuppliedTrust(): void
    {
        $fixture = $this->fixture();
        foreach ($fixture['checkpoints'] as $row) {
            AuditArtifactVerifier::checkpoint(
                $row['completeBytes'],
                $fixture['trust']['snapshot'],
                $fixture['times']['verifiedAt'],
            );
        }
        foreach ($fixture['receipts'] as $row) {
            AuditArtifactVerifier::anchorReceipt(
                $row['completeBytes'],
                $fixture['checkpoints'][$row['checkpoint']]['completeBytes'],
                $fixture['trust']['snapshot'],
                $fixture['times']['verifiedAt'],
            );
        }
        self::addToAssertionCount(count($fixture['checkpoints']) + count($fixture['receipts']));
    }

    public function testTrustFailurePrecedesMathematicalSignatureFailure(): void
    {
        $fixture = $this->fixture();
        $row = $fixture['checkpoints']['systemOne'];
        $badSignature = AuditCheckpointCodec::complete($row['unsigned'], str_repeat("\0", 64));
        $trust = $fixture['trust']['snapshot'];
        $trust['hash'] = str_repeat('0', 64);
        $this->assertFailure(
            static function () use ($badSignature, $trust, $fixture): void {
                AuditArtifactVerifier::checkpoint($badSignature, $trust, $fixture['times']['verifiedAt']);
            },
            AuditArtifactFailure::TrustInvalid,
        );
    }

    public function testMalformedArtifactPrecedesBadTrust(): void
    {
        $fixture = $this->fixture();
        $trust = $fixture['trust']['snapshot'];
        $trust['hash'] = str_repeat('0', 64);
        $this->assertFailure(
            static function () use ($trust, $fixture): void {
                AuditArtifactVerifier::checkpoint('{}', $trust, $fixture['times']['verifiedAt']);
            },
            AuditArtifactFailure::ArtifactInvalid,
        );

        $canary = 'verifier-wire-frame-canary';
        $previousIgnoreArgs = ini_get('zend.exception_ignore_args');
        self::assertIsString($previousIgnoreArgs);
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            try {
                AuditArtifactVerifier::checkpoint(
                    '{"kind":"' . $canary . '"}',
                    $fixture['trust']['snapshot'],
                    $fixture['times']['verifiedAt'],
                );
                self::fail('malformed canary artifact was accepted');
            } catch (AuditArtifactException $exception) {
                self::assertSame(AuditArtifactFailure::ArtifactInvalid, $exception->failure());
                self::assertStringNotContainsString($canary, $exception->getMessage());
                self::assertNull($exception->getPrevious());
                $this->assertSensitiveFrame($exception, AuditArtifactVerifier::class, 'checkpoint', 3, [$canary]);
            }
        } finally {
            self::assertNotFalse(ini_set('zend.exception_ignore_args', $previousIgnoreArgs));
        }
    }

    public function testInvalidSignaturePrecedesFutureSignedAtTrustFailure(): void
    {
        $fixture = $this->fixture();
        $unsigned = $fixture['checkpoints']['systemOne']['unsigned'];
        $unsigned['signedAt'] = '2030-01-02T04:00:00.000000Z';
        $badSignature = AuditCheckpointCodec::complete($unsigned, str_repeat("\0", 64));
        $this->assertFailure(
            static function () use ($badSignature, $fixture): void {
                AuditArtifactVerifier::checkpoint(
                    $badSignature,
                    $fixture['trust']['snapshot'],
                    $fixture['times']['verifiedAt'],
                );
            },
            AuditArtifactFailure::SignatureInvalid,
        );

        $anchorSeed = hex2bin($fixture['seeds']['anchor']);
        self::assertIsString($anchorSeed);
        $oppositeRole = $this->signCheckpoint($unsigned, $anchorSeed);
        $this->assertFailure(
            static function () use ($oppositeRole, $fixture): void {
                AuditArtifactVerifier::checkpoint(
                    $oppositeRole,
                    $fixture['trust']['snapshot'],
                    $fixture['times']['verifiedAt'],
                );
            },
            AuditArtifactFailure::SignatureInvalid,
        );

        $checkpointSeed = hex2bin($fixture['seeds']['checkpoint']);
        self::assertIsString($checkpointSeed);
        $alternativeDomain = $this->signCheckpointWithDomain(
            $unsigned,
            $checkpointSeed,
            'blackops.audit.anchor.signature.v1',
        );
        $this->assertFailure(
            static function () use ($alternativeDomain, $fixture): void {
                AuditArtifactVerifier::checkpoint(
                    $alternativeDomain,
                    $fixture['trust']['snapshot'],
                    $fixture['times']['verifiedAt'],
                );
            },
            AuditArtifactFailure::SignatureInvalid,
        );

        $validFuture = $this->signCheckpoint($unsigned, $checkpointSeed);
        $this->assertFailure(
            static function () use ($validFuture, $fixture): void {
                AuditArtifactVerifier::checkpoint(
                    $validFuture,
                    $fixture['trust']['snapshot'],
                    $fixture['times']['verifiedAt'],
                );
            },
            AuditArtifactFailure::TrustInvalid,
        );
    }

    public function testReceiptBindingPrecedesCheckpointSignatureVerification(): void
    {
        $fixture = $this->fixture();
        $receipt = $fixture['receipts']['receiptV1'];
        $mismatchedCheckpoint = AuditCheckpointCodec::complete($fixture['checkpoints']['tenantOne']['unsigned'], str_repeat(
            "\0",
            64,
        ));
        $this->assertFailure(
            static function () use ($receipt, $mismatchedCheckpoint, $fixture): void {
                AuditArtifactVerifier::anchorReceipt(
                    $receipt['completeBytes'],
                    $mismatchedCheckpoint,
                    $fixture['trust']['snapshot'],
                    $fixture['times']['verifiedAt'],
                );
            },
            AuditArtifactFailure::ArtifactInvalid,
        );
    }

    public function testReceiptRoleAndSignatureDomainFailuresAreMathematical(): void
    {
        $fixture = $this->fixture();
        $receipt = $fixture['receipts']['receiptV1'];
        $checkpointBytes = $fixture['checkpoints'][$receipt['checkpoint']]['completeBytes'];
        $checkpointSeed = hex2bin($fixture['seeds']['checkpoint']);
        self::assertIsString($checkpointSeed);
        $oppositeRole = $this->signReceiptWithDomain(
            $receipt['unsigned'],
            $checkpointSeed,
            'blackops.audit.anchor.signature.v1',
        );
        $this->assertFailure(
            static function () use ($oppositeRole, $checkpointBytes, $fixture): void {
                AuditArtifactVerifier::anchorReceipt(
                    $oppositeRole,
                    $checkpointBytes,
                    $fixture['trust']['snapshot'],
                    $fixture['times']['verifiedAt'],
                );
            },
            AuditArtifactFailure::SignatureInvalid,
        );

        $anchorSeed = hex2bin($fixture['seeds']['anchor']);
        self::assertIsString($anchorSeed);
        $alternativeDomain = $this->signReceiptWithDomain(
            $receipt['unsigned'],
            $anchorSeed,
            'blackops.audit.checkpoint.signature.v1',
        );
        $this->assertFailure(
            static function () use ($alternativeDomain, $checkpointBytes, $fixture): void {
                AuditArtifactVerifier::anchorReceipt(
                    $alternativeDomain,
                    $checkpointBytes,
                    $fixture['trust']['snapshot'],
                    $fixture['times']['verifiedAt'],
                );
            },
            AuditArtifactFailure::SignatureInvalid,
        );
    }

    /** @param array<string,mixed> $unsigned */
    private function signCheckpoint(array $unsigned, string $seed): string
    {
        $input = AuditCheckpointCodec::signingInput($unsigned);
        $keyPair = sodium_crypto_sign_seed_keypair($seed);
        $secretKey = sodium_crypto_sign_secretkey($keyPair);
        try {
            $signature = sodium_crypto_sign_detached($input['preimage'], $secretKey);
        } finally {
            sodium_memzero($secretKey);
            sodium_memzero($keyPair);
        }
        return AuditCheckpointCodec::complete($unsigned, $signature);
    }

    /** @param array<string,mixed> $unsigned */
    private function signCheckpointWithDomain(array $unsigned, string $seed, string $domain): string
    {
        $input = AuditCheckpointCodec::signingInput($unsigned);
        $rawHash = hex2bin($input['hash']);
        self::assertIsString($rawHash);
        $preimage = 's:' . strlen($domain) . ':' . $domain . 'b:' . strlen($rawHash) . ':' . $rawHash;
        $keyPair = sodium_crypto_sign_seed_keypair($seed);
        $secretKey = sodium_crypto_sign_secretkey($keyPair);
        try {
            $signature = sodium_crypto_sign_detached($preimage, $secretKey);
        } finally {
            sodium_memzero($secretKey);
            sodium_memzero($keyPair);
        }
        return AuditCheckpointCodec::complete($unsigned, $signature);
    }

    /** @param array<string,mixed> $unsigned */
    private function signReceiptWithDomain(array $unsigned, string $seed, string $domain): string
    {
        $input = AuditAnchorReceiptCodec::signingInput($unsigned);
        $rawHash = hex2bin($input['hash']);
        self::assertIsString($rawHash);
        $preimage = 's:' . strlen($domain) . ':' . $domain . 'b:' . strlen($rawHash) . ':' . $rawHash;
        $keyPair = sodium_crypto_sign_seed_keypair($seed);
        $secretKey = sodium_crypto_sign_secretkey($keyPair);
        try {
            $signature = sodium_crypto_sign_detached($preimage, $secretKey);
        } finally {
            sodium_memzero($secretKey);
            sodium_memzero($keyPair);
        }
        return AuditAnchorReceiptCodec::complete($unsigned, $signature);
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

    private function assertFailure(\Closure $operation, AuditArtifactFailure $failure): void
    {
        try {
            $operation();
            self::fail('invalid verification result was accepted');
        } catch (AuditArtifactException $exception) {
            self::assertSame($failure, $exception->failure());
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
