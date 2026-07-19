<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Frontend\Generation;

use BlackOps\Internal\Frontend\Generation\FrontendGeneratedTree;
use BlackOps\Internal\Frontend\Generation\FrontendGenerationMarker;
use BlackOps\Internal\Frontend\Generation\FrontendOutputWriter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FrontendOutputWriterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/blackops-frontend-output-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);
    }

    public function testWritesAndReplacesOwnedTreeWithoutLeavingTemporaryDirectories(): void
    {
        $output = $this->directory . '/resources/js/blackops';
        $writer = new FrontendOutputWriter();

        self::assertSame(2, $writer->write($this->tree('first', 'old'), $output));
        self::assertSame('old', file_get_contents($output . '/client.ts'));
        self::assertSame(2, $writer->write($this->tree('second', 'new'), $output));
        self::assertSame('new', file_get_contents($output . '/client.ts'));
        self::assertSame([], glob(dirname($output) . '/.blackops-frontend-*') ?: []);
    }

    public function testReplacesKnownLegacyOwnedTreeWithCurrentMarker(): void
    {
        foreach ([1, 2, 3] as $legacyVersion) {
            $output = $this->directory . '/legacy-' . $legacyVersion;
            mkdir($output);
            file_put_contents($output . '/client.ts', 'legacy');
            file_put_contents($output . '/manifest.json', $this->markerWithSchema($legacyVersion, 'legacy-build'));

            self::assertSame(2, new FrontendOutputWriter()->write($this->tree('current-build', 'current'), $output));
            self::assertSame('current', file_get_contents($output . '/client.ts'));
            self::assertStringContainsString(
                '"schemaVersion": 4',
                (string) file_get_contents($output . '/manifest.json'),
            );
        }
    }

    public function testRejectsUnknownMarkerVersionWithoutChangingTree(): void
    {
        $output = $this->directory . '/unknown';
        mkdir($output);
        file_put_contents($output . '/client.ts', 'application-owned');
        file_put_contents($output . '/manifest.json', $this->markerWithSchema(99, 'unknown-build'));

        try {
            new FrontendOutputWriter()->write($this->tree('current-build', 'generated'), $output);
            self::fail('Unknown generated frontend marker was accepted.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('marker is invalid', $exception->getMessage());
        }

        self::assertSame('application-owned', file_get_contents($output . '/client.ts'));
        self::assertStringContainsString('"schemaVersion": 99', (string) file_get_contents($output . '/manifest.json'));
    }

    public function testRejectsNonMarkerDirectoryAndSymlinkWithoutChangingThem(): void
    {
        $nonMarker = $this->directory . '/non-marker';
        mkdir($nonMarker);
        file_put_contents($nonMarker . '/application.ts', 'owned by application');

        try {
            new FrontendOutputWriter()->write($this->tree('build', 'generated'), $nonMarker);
            self::fail('Non-marker directory was replaced.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('not owned', $exception->getMessage());
        }
        self::assertSame('owned by application', file_get_contents($nonMarker . '/application.ts'));

        $target = $this->directory . '/target';
        mkdir($target);
        $link = $this->directory . '/link';
        symlink($target, $link);

        foreach ([$link, $link . '/nested'] as $unsafeOutput) {
            try {
                new FrontendOutputWriter()->write($this->tree('build', 'generated'), $unsafeOutput);
                self::fail('Symbolic-link output was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('symbolic links', $exception->getMessage());
            }
        }
    }

    public function testReplacementFailureRestoresOldTreeAndCleansTemporaryDirectories(): void
    {
        $output = $this->directory . '/generated';
        new FrontendOutputWriter()->write($this->tree('old-build', 'preserved'), $output);
        $renameCalls = 0;
        $writer = new FrontendOutputWriter(static function (string $from, string $to) use (&$renameCalls): bool {
            ++$renameCalls;
            if ($renameCalls === 2) {
                return false;
            }

            return rename($from, $to);
        });

        try {
            $writer->write($this->tree('new-build', 'replacement'), $output);
            self::fail('Replacement failure was not reported.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('moved into place', $exception->getMessage());
        }

        self::assertSame('preserved', file_get_contents($output . '/client.ts'));
        self::assertSame([], glob($this->directory . '/.blackops-frontend-*') ?: []);
        self::assertSame(
            'old-build',
            FrontendGenerationMarker::decode((string) file_get_contents($output
            . '/manifest.json'))->applicationBuildId,
        );
    }

    private function tree(string $buildId, string $client): FrontendGeneratedTree
    {
        return new FrontendGeneratedTree([
            'client.ts' => $client,
            'manifest.json' => new FrontendGenerationMarker($buildId, str_repeat('a', 64))->encode(),
        ]);
    }

    private function markerWithSchema(int $schemaVersion, string $buildId): string
    {
        return str_replace(
            '"schemaVersion": 4',
            sprintf('"schemaVersion": %d', $schemaVersion),
            new FrontendGenerationMarker($buildId, str_repeat('a', 64))->encode(),
        );
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $this->remove($path . '/' . $entry);
        }
        rmdir($path);
    }
}
