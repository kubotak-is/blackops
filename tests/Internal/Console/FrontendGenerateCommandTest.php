<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Application\Application;
use BlackOps\Application\ApplicationBootstrapException;
use BlackOps\Internal\Frontend\FrontendContractManifestFile;
use BlackOps\Internal\Frontend\Generation\FrontendGenerationMarker;
use BlackOps\Tests\Fixtures\Frontend\FrontendContractFixture;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class FrontendGenerateCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/blackops-frontend-command-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        mkdir($this->directory . '/config');
        mkdir($this->directory . '/var/build', recursive: true);
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->directory,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
            ),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->directory);
    }

    public function testGeneratesTreeFromCurrentFrontendManifestUsingDefaultOutput(): void
    {
        $this->writeAppConfiguration('command-build');
        new FrontendContractManifestFile()->write(
            FrontendContractFixture::manifest(),
            $this->directory . '/var/build/frontend.php',
            'command-build',
        );
        $application = Application::configure($this->directory)->withConfiguration()->create();
        $output = new BufferedOutput();

        self::assertSame(0, $application->console()->run(new ArrayInput([
            'command' => 'frontend:generate',
        ]), $output));

        $generated = $this->directory . '/resources/js/blackops';
        self::assertFileExists($generated . '/client.ts');
        self::assertFileExists($generated . '/types.ts');
        self::assertFileExists($generated . '/operations/order/create-order.ts');
        self::assertSame(
            'command-build',
            FrontendGenerationMarker::decode((string) file_get_contents($generated
            . '/manifest.json'))->applicationBuildId,
        );
        self::assertSame("Generated 4 frontend files in resources/js/blackops.\n", $output->fetch());

        $allBytes = implode('', array_map(static fn(string $path): string => (string) file_get_contents($path), [
            $generated . '/client.ts',
            $generated . '/types.ts',
            $generated . '/manifest.json',
            $generated . '/operations/order/create-order.ts',
        ]));
        foreach (['credential-secret', 'local-example', 'sensitive-value', 'default-must-not-appear'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $allBytes);
        }
        self::assertStringContainsString('fetch(', strtolower($allBytes));
        self::assertStringContainsString('status(', strtolower($allBytes));
        self::assertStringContainsString('promise<', strtolower($allBytes));
        foreach ([
            'backoff',
            'poll',
            '.wait(',
            'settimeout',
            'setinterval',
            'react',
            'vue',
            'svelte',
            'inertia',
            'vite',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, strtolower($allBytes));
        }
    }

    public function testRejectsManifestWithDifferentApplicationBuildIdWithoutCreatingTree(): void
    {
        $this->writeAppConfiguration('expected-build');
        new FrontendContractManifestFile()->write(
            FrontendContractFixture::manifest(),
            $this->directory . '/var/build/frontend.php',
            'stale-build',
        );
        $application = Application::configure($this->directory)->withConfiguration()->create();

        try {
            $application->console()->run(new ArrayInput(['command' => 'frontend:generate']), new BufferedOutput());
            self::fail('Stale frontend manifest was accepted.');
        } catch (ApplicationBootstrapException $exception) {
            self::assertStringContainsString('build ID', $exception->getMessage());
        }

        self::assertDirectoryDoesNotExist($this->directory . '/resources/js/blackops');
    }

    private function writeAppConfiguration(string $buildId): void
    {
        $build = $this->directory . '/var/build';
        $source = sprintf(<<<'PHP'
            <?php

            return [
                'build' => [
                    'application_build_id' => '%s',
                    'operation_manifest' => '%s/operations.php',
                    'http_manifest' => '%s/http.php',
                    'frontend_manifest' => '%s/frontend.php',
                    'container' => '%s/container.php',
                    'container_class' => 'CompiledContainer',
                    'container_namespace' => 'App\Generated',
                ],
            ];
            PHP, $buildId, $build, $build, $build, $build);
        file_put_contents($this->directory . '/config/app.php', $source);
    }
}
