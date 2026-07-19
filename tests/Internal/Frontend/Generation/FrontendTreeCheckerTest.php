<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Frontend\Generation;

use BlackOps\Internal\Frontend\Generation\FrontendGeneratedTree;
use BlackOps\Internal\Frontend\Generation\FrontendTreeChecker;
use BlackOps\Internal\Frontend\Generation\FrontendTreeCheckFilesystem;
use BlackOps\Internal\Frontend\Generation\FrontendTreeCheckInspectionException;
use BlackOps\Internal\Frontend\Generation\FrontendTreeCheckState;
use PHPUnit\Framework\TestCase;

final class FrontendTreeCheckerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/blackops-frontend-check-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);
    }

    public function testReturnsFreshWithoutChangingTreeAndIgnoresEmptyDirectories(): void
    {
        $output = $this->directory . '/generated';
        mkdir($output . '/operations/order', recursive: true);
        mkdir($output . '/empty/nested', recursive: true);
        file_put_contents($output . '/client.ts', 'client');
        file_put_contents($output . '/operations/order/create.ts', 'operation');
        $tree = new FrontendGeneratedTree([
            'client.ts' => 'client',
            'operations/order/create.ts' => 'operation',
        ]);
        $before = $this->snapshot($output);

        self::assertSame(FrontendTreeCheckState::Fresh, new FrontendTreeChecker()->check($tree, $output));
        self::assertSame($before, $this->snapshot($output));
    }

    public function testReturnsMissingWhenOutputDirectoryDoesNotExist(): void
    {
        self::assertSame(FrontendTreeCheckState::Missing, new FrontendTreeChecker()->check(new FrontendGeneratedTree([
            'client.ts' => 'client',
        ]), $this->directory . '/missing'));
    }

    public function testReturnsDriftForMissingChangedAndExtraFiles(): void
    {
        $expected = new FrontendGeneratedTree(['client.ts' => 'expected', 'types.ts' => 'types']);

        foreach (['missing', 'changed', 'extra'] as $case) {
            $output = $this->directory . '/' . $case;
            mkdir($output);
            file_put_contents($output . '/client.ts', $case === 'changed' ? 'changed' : 'expected');
            if ($case !== 'missing') {
                file_put_contents($output . '/types.ts', 'types');
            }
            if ($case === 'extra') {
                file_put_contents($output . '/application.ts', 'extra');
            }

            self::assertSame(FrontendTreeCheckState::Drift, new FrontendTreeChecker()->check($expected, $output));
        }
    }

    public function testTreatsExpectedAndExtraNestedSymlinksAsDriftWithoutFollowingThem(): void
    {
        $target = $this->directory . '/outside';
        mkdir($target);
        file_put_contents($target . '/secret.ts', 'must not be read');
        $expectedPosition = $this->directory . '/expected-link';
        mkdir($expectedPosition);
        symlink($target . '/secret.ts', $expectedPosition . '/client.ts');
        $extraPosition = $this->directory . '/extra-link';
        mkdir($extraPosition);
        file_put_contents($extraPosition . '/client.ts', 'client');
        symlink($target, $extraPosition . '/linked');
        $tree = new FrontendGeneratedTree(['client.ts' => 'client']);

        self::assertSame(FrontendTreeCheckState::Drift, new FrontendTreeChecker()->check($tree, $expectedPosition));
        self::assertSame(FrontendTreeCheckState::Drift, new FrontendTreeChecker()->check($tree, $extraPosition));
        self::assertSame('must not be read', file_get_contents($target . '/secret.ts'));
    }

    public function testReportsStatusListingAndReadFailuresAsInvalidInspection(): void
    {
        $output = $this->directory . '/failures';
        mkdir($output);
        file_put_contents($output . '/client.ts', 'client');
        $tree = new FrontendGeneratedTree(['client.ts' => 'client']);

        $checkers = [];
        foreach (['status', 'list', 'read'] as $failure) {
            $checkers[] = new FrontendTreeChecker(new FrontendTreeCheckFilesystemFixture($failure));
        }

        foreach ($checkers as $checker) {
            try {
                $checker->check($tree, $output);
                self::fail('Inspection failure was accepted.');
            } catch (FrontendTreeCheckInspectionException) {
                self::assertTrue(true);
            }
        }
    }

    public function testRejectsFileIdentityChangeAcrossReadBoundary(): void
    {
        $output = $this->directory . '/identity-change';
        mkdir($output);
        $file = $output . '/client.ts';
        file_put_contents($file, 'client');
        $checker = new FrontendTreeChecker(new FrontendTreeCheckFilesystemFixture('identity'));

        $this->expectException(FrontendTreeCheckInspectionException::class);

        $checker->check(new FrontendGeneratedTree(['client.ts' => 'client']), $output);
    }

    /** @return array<string, array<string, int|string>> */
    private function snapshot(string $root): array
    {
        $snapshot = [];
        $paths = [$root];
        while ($paths !== []) {
            $path = array_pop($paths);
            if (!is_string($path)) {
                continue;
            }
            $relative = substr($path, strlen($root));
            $status = lstat($path);
            self::assertIsArray($status);
            $snapshot[$relative] = [
                'inode' => $status['ino'],
                'mode' => $status['mode'],
                'mtime' => $status['mtime'],
                'bytes' => is_file($path) && !is_link($path) ? (string) file_get_contents($path) : '',
            ];
            if (!is_dir($path) || is_link($path)) {
                continue;
            }
            foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
                $paths[] = $path . '/' . $entry;
            }
        }
        ksort($snapshot);

        return $snapshot;
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            chmod($path, 0o600);
            unlink($path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        chmod($path, 0o700);
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $this->remove($path . '/' . $entry);
        }
        rmdir($path);
    }
}

final class FrontendTreeCheckFilesystemFixture implements FrontendTreeCheckFilesystem
{
    private int $fileStatusCalls = 0;

    public function __construct(
        private readonly string $failure,
    ) {}

    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /** @return array<array-key, int>|false */
    public function status(string $path): array|false
    {
        if ($this->failure === 'status') {
            return false;
        }

        $status = lstat($path);
        if ($this->failure === 'identity' && str_ends_with($path, '/client.ts') && is_array($status)) {
            ++$this->fileStatusCalls;
            if ($this->fileStatusCalls === 2) {
                $status['ino']++;
            }
        }

        return $status;
    }

    /** @return array<int, string>|false */
    public function list(string $path): array|false
    {
        return $this->failure === 'list' ? false : scandir($path);
    }

    public function read(string $path): string|false
    {
        return $this->failure === 'read' ? false : file_get_contents($path);
    }
}
