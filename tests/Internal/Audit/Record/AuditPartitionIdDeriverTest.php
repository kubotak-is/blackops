<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Record;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use PHPUnit\Framework\TestCase;

final class AuditPartitionIdDeriverTest extends TestCase
{
    public function testNullTenantUsesTheSystemPartition(): void
    {
        self::assertSame('system:global', $this->deriver()->derive(null));
    }

    public function testPresentTenantUsesTheCanonicalJcsHash(): void
    {
        $tenant = new AuditTypedReference('account', 'tenant-a');
        self::assertSame(
            'tenant:59f58bfcb476fcc98fe5e95c6bf81a5f8125a721f27e53ed70ba7345ae64eae1',
            $this->deriver()->derive($tenant),
        );
        self::assertSame(71, strlen($this->deriver()->derive($tenant)));
    }

    public function testPartitionFixtureMatchesExactPhpGoldenForTwoTenantsAndSeparators(): void
    {
        $fixture = $this->fixture();
        $jcs = new AuditJcsCodec();
        foreach ($fixture['scalarPartitions'] as $name => $entry) {
            self::assertIsArray($entry, (string) $name);
            self::assertIsArray($entry['tenant'], (string) $name);
            $tenant = new AuditTypedReference($entry['tenant']['type'], $entry['tenant']['id']);
            self::assertSame($entry['tenantJcs'], $jcs->canonicalJson($tenant->toArray()), (string) $name);
            self::assertSame($entry['partitionId'], $this->deriver()->derive($tenant), (string) $name);
        }
        self::assertStringContainsString("\u{2028}", $fixture['scalarPartitions']['lineSeparator']['tenant']['id']);
        self::assertStringContainsString(
            "\u{2029}",
            $fixture['scalarPartitions']['paragraphSeparator']['tenant']['id'],
        );
    }

    public function testTwoTenantPartitionsPreserveLineAndParagraphSeparators(): void
    {
        $line = new AuditTypedReference('account', "line\u{2028}separator");
        $paragraph = new AuditTypedReference('account', "paragraph\u{2029}separator");
        $linePartition = $this->deriver()->derive($line);
        $paragraphPartition = $this->deriver()->derive($paragraph);

        self::assertMatchesRegularExpression('/^tenant:[a-f0-9]{64}$/', $linePartition);
        self::assertMatchesRegularExpression('/^tenant:[a-f0-9]{64}$/', $paragraphPartition);
        self::assertNotSame($linePartition, $paragraphPartition);
    }

    public function testTenantTypeAndIdAcceptTheInclusive256ByteBoundary(): void
    {
        $tenant = new AuditTypedReference(str_repeat('t', 256), str_repeat('i', 256));
        self::assertSame(71, strlen($this->deriver()->derive($tenant)));
    }

    public function testDistinctTenantsCannotShareASeparatorConcatenation(): void
    {
        $first = $this->deriver()->derive(new AuditTypedReference('a', 'b:c'));
        $second = $this->deriver()->derive(new AuditTypedReference('a:b', 'c'));
        self::assertNotSame($first, $second);
    }

    public function testTenantTextPolicyRejectsNfcAndControlValues(): void
    {
        foreach (["e\u{0301}", "bad\u{0000}"] as $id) {
            try {
                new AuditTypedReference('account', $id);
                self::fail('invalid tenant accepted');
            } catch (AuditValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTenantTextPolicyRejectsInvalidValues(): void
    {
        foreach (["bad\xFF", str_repeat('x', 257)] as $id) {
            try {
                new AuditTypedReference('account', $id);
                self::fail('invalid tenant accepted');
            } catch (AuditValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private function deriver(): AuditPartitionIdDeriver
    {
        return new AuditPartitionIdDeriver(new AuditJcsCodec());
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
}
