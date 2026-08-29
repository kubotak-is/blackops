<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Record;

use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Record\AuditChainPosition;
use BlackOps\Internal\Audit\Record\AuditLogicalRecordCandidate;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use BlackOps\Internal\Audit\Record\CompleteAuditRecord;
use PHPUnit\Framework\TestCase;

final class AuditRecordCodecTest extends TestCase
{
    public function testFixtureRecordsHaveStableLogicalContentRecordAndCompleteWires(): void
    {
        $fixture = $this->fixture();
        $codec = $this->codec();
        foreach ($fixture['records'] as $name => $entry) {
            $logical = $this->canonical($entry['logical']);
            self::assertSame($entry['logicalBytes'], $codec->logicalBytes($logical), $name . ' logical bytes');
            self::assertSame($entry['contentHash'], $codec->contentHash($logical), $name . ' content hash');
            self::assertSame(
                $entry['contentHash'],
                $codec->contentHashFromSnapshot($entry['logical']),
                $name . ' snapshot hash',
            );
            $complete = $codec->complete($logical, new AuditChainPosition(...array_values($entry['chain'])));
            self::assertSame($entry['completeBytes'], $complete->bytes(), $name . ' complete bytes');
            self::assertSame($entry['recordHash'], $complete->integrity()->recordHash, $name . ' record hash');
            self::assertSame("\n", substr($complete->bytes(), -1));
            self::assertSame(1, substr_count($complete->bytes(), "\n"));
            self::assertSame($complete->bytes(), $codec->decode($complete->bytes())->bytes());
        }
    }

    public function testFixtureCompleteBytesUseCanonicalJcsAndOneLf(): void
    {
        $fixture = $this->fixture();
        $codec = $this->codec();
        $logical = $this->canonical($fixture['records']['systemGenesis']['logical']);
        $complete = $codec->complete(
            $logical,
            new AuditChainPosition('system:global', '01941f29-7c00-7000-8000-000000000101', 1, null),
        );
        self::assertSame(new AuditJcsCodec()->canonicalJson($complete->toArray()) . "\n", $complete->bytes());
        self::assertStringNotContainsString("\r", $complete->bytes());
        self::assertSame(1384, strlen($complete->bytes()));
    }

    public function testIntegritySubstitutionsAndWireShapeChangesFailClosed(): void
    {
        $fixture = $this->fixture();
        $codec = $this->codec();
        $entry = $fixture['records']['tenantNonGenesis'];
        $complete = $codec->complete(
            $this->canonical($entry['logical']),
            new AuditChainPosition(...array_values($entry['chain'])),
        );

        foreach ([
            'contentHash',
            'recordHash',
            'previousRecordHash',
            'partitionId',
            'segmentId',
            'sequence',
            'hashSuite',
        ] as $field) {
            $value = $complete->toArray();
            $value['integrity'][$field] = match ($field) {
                'contentHash', 'recordHash', 'previousRecordHash' => str_repeat('0', 64),
                'partitionId' => 'system:global',
                'segmentId' => '01941f29-7c00-7000-8000-000000000199',
                'sequence' => 3,
                'hashSuite' => 'other-suite',
            };
            $this->assertValidationFailure($codec, $this->json($value));
        }

        foreach ([
            substr($complete->bytes(), 0, -1),
            $complete->bytes() . "\n",
            str_replace("\n", "\r\n", $complete->bytes()),
        ] as $bytes) {
            $this->assertValidationFailure($codec, $bytes);
        }
        $reordered = array_reverse($complete->toArray(), true);
        $this->assertValidationFailure($codec, json_encode($reordered, JSON_THROW_ON_ERROR) . "\n");
    }

    public function testCompleteAndDecodeDeriveTheTenantPartitionBeforeHashing(): void
    {
        $fixture = $this->fixture();
        $codec = $this->codec();
        $entry = $fixture['records']['tenantGenesis'];
        $logical = $this->canonical($entry['logical']);
        try {
            $codec->complete($logical, new AuditChainPosition('system:global', $entry['chain']['segmentId'], 1, null));
            self::fail('tenant logical record accepted with the system partition');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }

        $complete = $codec->complete($logical, new AuditChainPosition(...array_values($entry['chain'])));
        $value = $complete->toArray();
        $value['integrity']['partitionId'] = 'system:global';
        $this->assertFailureCode($codec, $this->json($value), 'semantic_invalid');

        $system = $fixture['records']['systemGenesis'];
        $systemComplete = $codec->complete(
            $this->canonical($system['logical']),
            new AuditChainPosition(
                $system['chain']['partitionId'],
                $system['chain']['segmentId'],
                $system['chain']['sequence'],
                $system['chain']['previousRecordHash'],
            ),
        );
        $systemValue = $systemComplete->toArray();
        $systemValue['integrity']['partitionId'] = $entry['chain']['partitionId'];
        $this->assertFailureCode($codec, $this->json($systemValue), 'semantic_invalid');
    }

    public function testIntegrityGrammarAndSemanticsPrecedeHashEquality(): void
    {
        $fixture = $this->fixture();
        $codec = $this->codec();
        $entry = $fixture['records']['tenantNonGenesis'];
        $complete = $codec->complete(
            $this->canonical($entry['logical']),
            new AuditChainPosition(...array_values($entry['chain'])),
        );

        $grammarAndHash = $complete->toArray();
        $grammarAndHash['integrity']['contentHash'] = 'g' . str_repeat('0', 63);
        $grammarAndHash['integrity']['recordHash'] = str_repeat('0', 64);
        $this->assertFailureCode($codec, $this->json($grammarAndHash), 'grammar_invalid');

        $suiteAndHash = $complete->toArray();
        $suiteAndHash['integrity']['hashSuite'] = 'audit-other-v1';
        $suiteAndHash['integrity']['recordHash'] = str_repeat('0', 64);
        $this->assertFailureCode($codec, $this->json($suiteAndHash), 'semantic_invalid');

        $chainAndHash = $complete->toArray();
        $chainAndHash['integrity']['sequence'] = 1;
        $chainAndHash['integrity']['previousRecordHash'] = str_repeat('0', 64);
        $chainAndHash['integrity']['recordHash'] = str_repeat('0', 64);
        $this->assertFailureCode($codec, $this->json($chainAndHash), 'semantic_invalid');
    }

    public function testFutureCapacityUsesSyntheticMaximumChainValues(): void
    {
        $fixture = $this->fixture();
        $codec = $this->codec();
        $codec->assertFutureCompleteCapacity($this->canonical($fixture['records']['systemGenesis']['logical']));
    }

    public function testLogicalAndCompleteCapsAreInclusiveAtTheCodecBoundary(): void
    {
        $codec = $this->codec();
        $fixture = $this->fixture();
        $logical = $this->canonical($fixture['records']['systemGenesis']['logical']);
        $complete = $codec->complete(
            $logical,
            new AuditChainPosition('system:global', '01941f29-7c00-7000-8000-000000000101', 1, null),
        );
        self::assertLessThanOrEqual(AuditRecordCodec::MAX_BYTES, strlen($codec->logicalBytes($logical)));
        self::assertLessThanOrEqual(AuditRecordCodec::MAX_BYTES, strlen($complete->bytes()));
    }

    public function testLogicalCompleteAndFutureCapsUseInclusiveUtf8ByteBoundaries(): void
    {
        $fixture = $this->fixture();
        $entry = $fixture['records']['systemGenesis'];
        $logical = $this->canonical($entry['logical']);
        $position = new AuditChainPosition(...array_values($entry['chain']));

        $atLimit = new AuditRecordCodec(
            $this->validator(),
            new SizedAuditJcsCodec(AuditRecordCodec::MAX_BYTES, AuditRecordCodec::MAX_BYTES - 1, true),
        );
        self::assertSame(AuditRecordCodec::MAX_BYTES, strlen($atLimit->logicalBytes($logical)));
        self::assertSame(AuditRecordCodec::MAX_BYTES, strlen($atLimit->complete($logical, $position)->bytes()));
        $atLimit->assertFutureCompleteCapacity($logical);

        $overLogical = new AuditRecordCodec(
            $this->validator(),
            new SizedAuditJcsCodec(AuditRecordCodec::MAX_BYTES + 1, AuditRecordCodec::MAX_BYTES - 1, true),
        );
        $this->assertValidationCode(static fn(): string => $overLogical->logicalBytes($logical), 'jcs_bound_invalid');

        $overComplete = new AuditRecordCodec(
            $this->validator(),
            new SizedAuditJcsCodec(1024, AuditRecordCodec::MAX_BYTES, true),
        );
        $this->assertValidationCode(
            static fn(): CompleteAuditRecord => $overComplete->complete($logical, $position),
            'jcs_bound_invalid',
        );

        $overFuture = new AuditRecordCodec(
            $this->validator(),
            new SizedAuditJcsCodec(AuditRecordCodec::MAX_BYTES, AuditRecordCodec::MAX_BYTES, true),
        );
        $this->assertValidationCode(
            static function () use ($overFuture, $logical): void {
                $overFuture->assertFutureCompleteCapacity($logical);
            },
            'jcs_bound_invalid',
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

    private function codec(): AuditRecordCodec
    {
        return new AuditRecordCodec($this->validator(), new AuditJcsCodec());
    }

    private function validator(): AuditRecordValidator
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        return new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog]));
    }

    /** @param array<string,mixed> $snapshot */
    private function canonical(array $snapshot): CanonicalAuditLogicalRecord
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $validator = new AuditRecordValidator(new ImmutableAuditEventCatalogLookup([$catalog]));
        return new CanonicalAuditLogicalRecord($validator->validateLogical(new AuditLogicalRecordCandidate($snapshot)));
    }

    /** @param array<string,mixed> $value */
    private function json(array $value): string
    {
        return new AuditJcsCodec()->canonicalJson($value) . "\n";
    }

    private function assertValidationFailure(AuditRecordCodec $codec, string $bytes): void
    {
        try {
            $codec->decode($bytes);
            self::fail('invalid complete wire accepted');
        } catch (AuditValidationException) {
            self::addToAssertionCount(1);
        }
    }

    private function assertFailureCode(AuditRecordCodec $codec, string $bytes, string $expected): void
    {
        try {
            $codec->decode($bytes);
            self::fail('invalid complete wire accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame($expected, $exception->code());
        }
    }

    private function assertValidationCode(callable $callback, string $expected): void
    {
        try {
            $callback();
            self::fail('invalid boundary accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame($expected, $exception->code());
        }
    }
}

final class SizedAuditJcsCodec extends AuditJcsCodec
{
    public function __construct(
        private int $logicalLength,
        private int $completeLength,
        private bool $multibyte,
    ) {}

    public function canonicalJson(mixed $value): string
    {
        $base = parent::canonicalJson($value);
        $target = is_array($value) && array_key_exists('integrity', $value)
            ? $this->completeLength
            : $this->logicalLength;
        if ($target < strlen($base)) {
            return $base;
        }
        if (!$this->multibyte) {
            return $base . str_repeat(' ', $target - strlen($base));
        }
        $prefix = '{"padding":"';
        $suffix = '"}';
        $available = $target - strlen($prefix) - strlen($suffix);
        $multibyteLength = intdiv($available, 3);
        $asciiLength = $available - ($multibyteLength * 3);
        return $prefix . str_repeat('あ', $multibyteLength) . str_repeat('x', $asciiLength) . $suffix;
    }
}
