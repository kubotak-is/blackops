<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Artifact;

use BlackOps\Internal\Audit\Artifact\AuditArtifactException;
use BlackOps\Internal\Audit\Artifact\AuditArtifactFailure;
use BlackOps\Internal\Audit\Artifact\AuditTrustSnapshotCodec;
use BlackOps\Internal\Audit\AuditJcsCodec;
use PHPUnit\Framework\TestCase;

final class AuditTrustSnapshotCodecTest extends TestCase
{
    public function testFixtureSnapshotHashMaterialAndRoleSelectionAreExact(): void
    {
        $fixture = $this->fixture();
        $snapshot = $fixture['trust']['snapshot'];
        AuditTrustSnapshotCodec::validate($snapshot, $fixture['times']['verifiedAt']);
        foreach (['checkpoint', 'anchor'] as $role) {
            $entry = AuditTrustSnapshotCodec::key(
                $snapshot,
                $fixture['trust']['keyRefs'][$role],
                $role,
                $fixture['times']['verifiedAt'],
            );
            self::assertSame($role, $entry['role']);
            $entry['keyRef'] = 'detached-local';
            self::assertNotSame($entry['keyRef'], $snapshot['entries'][0]['keyRef']);
            AuditTrustSnapshotCodec::assertSignedAt(
                $entry,
                $fixture['times']['signedAt'],
                $fixture['times']['verifiedAt'],
            );
        }
    }

    public function testSelectedKeyDetachesReferenceBearingCallerArrays(): void
    {
        $fixture = $this->fixture();
        $snapshot = $fixture['trust']['snapshot'];
        $entryIndex = null;
        foreach ($snapshot['entries'] as $index => $candidate) {
            if (is_array($candidate) && ($candidate['keyRef'] ?? null) === $fixture['trust']['keyRefs']['checkpoint']) {
                $entryIndex = $index;
                break;
            }
        }
        self::assertIsInt($entryIndex);
        $externalKeyRef = $fixture['trust']['keyRefs']['checkpoint'];
        $externalPublicKey = $snapshot['entries'][$entryIndex]['publicKey'];
        $snapshot['entries'][$entryIndex]['keyRef'] = &$externalKeyRef;
        $snapshot['entries'][$entryIndex]['publicKey'] = &$externalPublicKey;
        $selected = AuditTrustSnapshotCodec::key(
            $snapshot,
            $fixture['trust']['keyRefs']['checkpoint'],
            'checkpoint',
            $fixture['times']['verifiedAt'],
        );
        $selected['keyRef'] = 'returned-mutated';
        $selected['publicKey'] = 'returned-public-mutated';
        self::assertSame($fixture['trust']['keyRefs']['checkpoint'], $externalKeyRef);
        self::assertSame($fixture['trust']['snapshot']['entries'][$entryIndex]['publicKey'], $externalPublicKey);
        self::assertSame($fixture['trust']['keyRefs']['checkpoint'], $snapshot['entries'][$entryIndex]['keyRef']);
        $externalKeyRef = 'caller-mutated';
        $externalPublicKey = 'caller-public-mutated';
        self::assertSame('returned-mutated', $selected['keyRef']);
        self::assertSame('returned-public-mutated', $selected['publicKey']);
        self::assertSame('caller-mutated', $snapshot['entries'][$entryIndex]['keyRef']);
        self::assertSame('caller-public-mutated', $snapshot['entries'][$entryIndex]['publicKey']);
    }

    public function testSnapshotShapeIsBoundedBeforeCanonicalization(): void
    {
        $fixture = $this->fixture();
        $snapshot = $fixture['trust']['snapshot'];
        $snapshot['entries'][0]['metadata'] = ['nested' => true];
        $this->assertTrustFailure(static function () use ($snapshot, $fixture): void {
            AuditTrustSnapshotCodec::validate($snapshot, $fixture['times']['verifiedAt']);
        });
        $snapshot = $fixture['trust']['snapshot'];
        $snapshot['entries'] = array_fill(0, 257, $snapshot['entries'][0]);
        $this->assertTrustFailure(static function () use ($snapshot, $fixture): void {
            AuditTrustSnapshotCodec::validate($snapshot, $fixture['times']['verifiedAt']);
        });
        $snapshot['entries'] = [['keyRef' => 'not-an-entry']];
        $this->assertTrustFailure(static function () use ($snapshot, $fixture): void {
            AuditTrustSnapshotCodec::validate($snapshot, $fixture['times']['verifiedAt']);
        });

        $canary = 'trust-frame-canary';
        $snapshot = $fixture['trust']['snapshot'];
        $snapshot['ref'] = $canary;
        $snapshot = $this->withHash($snapshot);
        $previousIgnoreArgs = ini_get('zend.exception_ignore_args');
        self::assertIsString($previousIgnoreArgs);
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            try {
                AuditTrustSnapshotCodec::validate($snapshot, $canary);
                self::fail('invalid trust time was accepted');
            } catch (AuditArtifactException $exception) {
                self::assertSame(AuditArtifactFailure::TrustInvalid, $exception->failure());
                self::assertStringNotContainsString($canary, $exception->getMessage());
                self::assertNull($exception->getPrevious());
                $this->assertSensitiveFrame($exception, AuditTrustSnapshotCodec::class, 'validate', 2, [$canary]);
            }
        } finally {
            self::assertNotFalse(ini_set('zend.exception_ignore_args', $previousIgnoreArgs));
        }
    }

    public function testDuplicateMaterialAndUnsignedByteOrderingAreRejected(): void
    {
        $fixture = $this->fixture();
        $snapshot = $fixture['trust']['snapshot'];
        $snapshot['entries'][1]['publicKey'] = $snapshot['entries'][0]['publicKey'];
        $snapshot['entries'][1]['publicKeyFingerprint'] = $snapshot['entries'][0]['publicKeyFingerprint'];
        $snapshot = $this->withHash($snapshot);
        $this->assertTrustFailure(static function () use ($snapshot, $fixture): void {
            AuditTrustSnapshotCodec::validate($snapshot, $fixture['times']['verifiedAt']);
        });
        $snapshot = $fixture['trust']['snapshot'];
        [$snapshot['entries'][0], $snapshot['entries'][1]] = [$snapshot['entries'][1], $snapshot['entries'][0]];
        $snapshot = $this->withHash($snapshot);
        $this->assertTrustFailure(static function () use ($snapshot, $fixture): void {
            AuditTrustSnapshotCodec::validate($snapshot, $fixture['times']['verifiedAt']);
        });
        $snapshot['entries'][1]['keyRef'] = $snapshot['entries'][0]['keyRef'];
        $snapshot = $this->withHash($snapshot);
        $this->assertTrustFailure(static function () use ($snapshot, $fixture): void {
            AuditTrustSnapshotCodec::validate($snapshot, $fixture['times']['verifiedAt']);
        });
        $snapshot = $fixture['trust']['snapshot'];
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $last = substr($snapshot['entries'][0]['publicKey'], -1);
        $index = strpos($alphabet, $last);
        self::assertIsInt($index);
        $prefix = substr($snapshot['entries'][0]['publicKey'], 0, -1);
        self::assertIsString($prefix);
        $snapshot['entries'][0]['publicKey'] = $prefix . $alphabet[$index + 1];
        $snapshot = $this->withHash($snapshot);
        $this->assertTrustFailure(static function () use ($snapshot, $fixture): void {
            AuditTrustSnapshotCodec::validate($snapshot, $fixture['times']['verifiedAt']);
        });
    }

    public function testStatusAndWindowEdgesRejectAtOneMicrosecond(): void
    {
        $fixture = $this->fixture();
        $snapshot = $fixture['trust']['snapshot'];
        $snapshot['entries'][0]['status'] = 'revoked';
        $snapshot['entries'][0]['revokedAt'] = null;
        $snapshot = $this->withHash($snapshot);
        $this->assertTrustFailure(static function () use ($snapshot, $fixture): void {
            AuditTrustSnapshotCodec::validate($snapshot, $fixture['times']['verifiedAt']);
        });

        $snapshot = $fixture['trust']['snapshot'];
        $snapshot['validUntil'] = $fixture['times']['verifiedAt'];
        $snapshot = $this->withHash($snapshot);
        $this->assertTrustFailure(static function () use ($snapshot, $fixture): void {
            AuditTrustSnapshotCodec::validate($snapshot, $fixture['times']['verifiedAt']);
        });

        $entry = $fixture['trust']['snapshot']['entries'][0];
        $this->assertTrustFailure(static function () use ($entry, $fixture): void {
            AuditTrustSnapshotCodec::assertSignedAt(
                $entry,
                '2030-01-02T03:04:05.123457Z',
                $fixture['times']['verifiedAt'],
            );
        });
        $entry['notBefore'] = $fixture['times']['signedAt'];
        AuditTrustSnapshotCodec::assertSignedAt($entry, $fixture['times']['signedAt'], $fixture['times']['verifiedAt']);
        $this->assertTrustFailure(static function () use ($entry, $fixture): void {
            AuditTrustSnapshotCodec::assertSignedAt(
                $entry,
                '2030-01-02T03:03:59.999999Z',
                $fixture['times']['verifiedAt'],
            );
        });
        $entry['notAfter'] = $fixture['times']['verifiedAt'];
        $this->assertTrustFailure(static function () use ($entry, $fixture): void {
            AuditTrustSnapshotCodec::assertSignedAt(
                $entry,
                $fixture['times']['signedAt'],
                $fixture['times']['verifiedAt'],
            );
        });
        $currentBoundary = $this->entry('direct-current-boundary', 'checkpoint', str_repeat("\xaa", 32), [
            'notBefore' => $fixture['times']['verifiedAt'],
        ]);
        AuditTrustSnapshotCodec::assertSignedAt(
            $currentBoundary,
            $fixture['times']['verifiedAt'],
            $fixture['times']['verifiedAt'],
        );
        $this->assertTrustFailure(static function () use ($fixture): void {
            AuditTrustSnapshotCodec::key(
                $fixture['trust']['snapshot'],
                $fixture['trust']['keyRefs']['anchor'],
                'checkpoint',
                $fixture['times']['verifiedAt'],
            );
        });

        $active = $fixture['trust']['snapshot']['entries'][0];
        AuditTrustSnapshotCodec::assertSignedAt(
            $active,
            $fixture['times']['signedAt'],
            $fixture['times']['verifiedAt'],
        );
        $revoked = $this->entry('direct-revoked', 'checkpoint', str_repeat("\x77", 32), [
            'revokedAt' => $fixture['times']['observedAt'],
            'status' => 'revoked',
        ]);
        $this->assertTrustFailure(static function () use ($revoked, $fixture): void {
            AuditTrustSnapshotCodec::assertSignedAt(
                $revoked,
                $fixture['times']['signedAt'],
                $fixture['times']['verifiedAt'],
            );
        });
        $future = $this->entry('direct-future', 'checkpoint', str_repeat("\x88", 32), [
            'notBefore' => '2030-01-02T03:04:05.123457Z',
        ]);
        $this->assertTrustFailure(static function () use ($future, $fixture): void {
            AuditTrustSnapshotCodec::assertSignedAt(
                $future,
                '2030-01-02T03:04:05.123457Z',
                $fixture['times']['verifiedAt'],
            );
        });
        $equalNotAfter = $this->entry('direct-equal-not-after', 'checkpoint', str_repeat("\x99", 32), [
            'notAfter' => $fixture['times']['verifiedAt'],
        ]);
        $this->assertTrustFailure(static function () use ($equalNotAfter, $fixture): void {
            AuditTrustSnapshotCodec::assertSignedAt(
                $equalNotAfter,
                $fixture['times']['signedAt'],
                $fixture['times']['verifiedAt'],
            );
        });
    }

    public function testUnusedFutureRevokedAndExpiredEntriesRemainStructuralOnly(): void
    {
        $fixture = $this->fixture();
        $snapshot = $fixture['trust']['snapshot'];
        $snapshot['entries'][] = $this->entry('expired-key', 'checkpoint', str_repeat("\x66", 32), [
            'notAfter' => $fixture['times']['verifiedAt'],
            'status' => 'expired',
        ]);
        $snapshot['entries'][] = $this->entry('future-key', 'checkpoint', str_repeat("\x44", 32), [
            'notBefore' => '2030-01-02T03:04:05.123457Z',
        ]);
        $snapshot['entries'][] = $this->entry('revoked-key', 'anchor', str_repeat("\x55", 32), [
            'revokedAt' => $fixture['times']['observedAt'],
            'status' => 'revoked',
        ]);
        usort($snapshot['entries'], static fn(array $left, array $right): int => strcmp(
            $left['keyRef'],
            $right['keyRef'],
        ));
        $snapshot = $this->withHash($snapshot);
        AuditTrustSnapshotCodec::validate($snapshot, $fixture['times']['verifiedAt']);
        foreach (['expired-key', 'future-key'] as $keyRef) {
            $this->assertTrustFailure(static function () use ($snapshot, $keyRef, $fixture): void {
                AuditTrustSnapshotCodec::key($snapshot, $keyRef, 'checkpoint', $fixture['times']['verifiedAt']);
            });
        }
        $this->assertTrustFailure(static function () use ($snapshot, $fixture): void {
            AuditTrustSnapshotCodec::key($snapshot, 'revoked-key', 'anchor', $fixture['times']['verifiedAt']);
        });
        $entry = null;
        foreach ($snapshot['entries'] as $candidate) {
            if (!is_array($candidate) || ($candidate['keyRef'] ?? null) !== 'expired-key') {
                continue;
            }
            $entry = $candidate;
            break;
        }
        self::assertIsArray($entry);
        $this->assertTrustFailure(static function () use ($entry, $fixture): void {
            AuditTrustSnapshotCodec::assertSignedAt(
                $entry,
                $fixture['times']['signedAt'],
                $fixture['times']['verifiedAt'],
            );
        });
    }

    public function testOneAndTwoHundredFiftySixEntrySnapshotsRemainBounded(): void
    {
        $fixture = $this->fixture();
        $single = $fixture['trust']['snapshot'];
        $single['entries'] = [$single['entries'][0]];
        $single = $this->withHash($single);
        AuditTrustSnapshotCodec::validate($single, $fixture['times']['verifiedAt']);

        $snapshot = $fixture['trust']['snapshot'];
        $entries = [];
        for ($index = 0; $index < 256; ++$index) {
            $raw = str_repeat(chr($index), 32);
            $entries[] = [
                'keyRef' => sprintf('bulk-%03d', $index),
                'role' => 'export',
                'publicKey' => sodium_bin2base64($raw, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
                'publicKeyFingerprint' => hash('sha256', $raw),
                'notBefore' => '2029-01-01T00:00:00.000000Z',
                'notAfter' => '2031-01-01T00:00:00.000000Z',
                'revokedAt' => null,
                'status' => 'active',
            ];
        }
        $snapshot['entries'] = $entries;
        AuditTrustSnapshotCodec::validate($this->withHash($snapshot), $fixture['times']['verifiedAt']);
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function entry(string $keyRef, string $role, string $raw, array $overrides = []): array
    {
        return [
            'keyRef' => $keyRef,
            'role' => $role,
            'publicKey' => sodium_bin2base64($raw, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
            'publicKeyFingerprint' => hash('sha256', $raw),
            'notBefore' => '2029-01-01T00:00:00.000000Z',
            'notAfter' => '2031-01-01T00:00:00.000000Z',
            'revokedAt' => null,
            'status' => 'active',
            ...$overrides,
        ];
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return array<string,mixed>
     */
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

    private function assertTrustFailure(\Closure $operation): void
    {
        try {
            $operation();
            self::fail('invalid trust value was accepted');
        } catch (AuditArtifactException $exception) {
            self::assertSame(AuditArtifactFailure::TrustInvalid, $exception->failure());
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
