<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Application\Application;
use BlackOps\Internal\Frontend\FrontendContractManifest;
use BlackOps\Internal\Frontend\FrontendContractManifestFile;
use BlackOps\Internal\Frontend\FrontendOperationContract;
use BlackOps\Tests\Fixtures\Frontend\FrontendContractFixture;
use FilesystemIterator;
use LogicException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class FrontendCheckCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/blackops-frontend-check-command-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        mkdir($this->directory . '/config');
        mkdir($this->directory . '/var/build', recursive: true);
        $this->writeAppConfiguration('check-build');
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
            chmod($entry->getPathname(), $entry->isDir() ? 0o700 : 0o600);
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->directory);
    }

    public function testReportsFreshMissingAndDriftWithStableExitAndOutput(): void
    {
        $this->writeArtifact();
        $application = Application::configure($this->directory)->withConfiguration()->create();

        [$missingExit, $missing] = $this->runCommand($application, 'frontend:check');
        self::assertSame(1, $missingExit);
        self::assertSame("Frontend generated tree is missing in resources/js/blackops.\n", $missing->fetch());
        self::assertSame('', $missing->errorOutput()->fetch());

        [$generateExit] = $this->runCommand($application, 'frontend:generate');
        self::assertSame(0, $generateExit);
        $generated = $this->directory . '/resources/js/blackops';
        $before = $this->treeBytes($generated);

        [$freshExit, $fresh] = $this->runCommand($application, 'frontend:check');
        self::assertSame(0, $freshExit);
        self::assertSame("Frontend generated tree is fresh in resources/js/blackops.\n", $fresh->fetch());
        self::assertSame('', $fresh->errorOutput()->fetch());
        self::assertSame($before, $this->treeBytes($generated));

        file_put_contents($generated . '/client.ts', 'drift');
        [$driftExit, $drift] = $this->runCommand($application, 'frontend:check');
        self::assertSame(1, $driftExit);
        self::assertSame("Frontend generated tree has drift in resources/js/blackops.\n", $drift->fetch());
        self::assertSame('', $drift->errorOutput()->fetch());
        self::assertSame('drift', file_get_contents($generated . '/client.ts'));
    }

    public function testReportsInvalidConfigurationOnlyOnStderrWithoutAbsolutePath(): void
    {
        $this->writeArtifact();
        file_put_contents(
            $this->directory . '/config/frontend.php',
            sprintf("<?php return ['output' => '%s'];\n", dirname($this->directory) . '/outside'),
        );
        $application = Application::configure($this->directory)->withConfiguration()->create();

        [$exit, $output] = $this->runCommand($application, 'frontend:check');
        $stdout = $output->fetch();
        $stderr = $output->errorOutput()->fetch();

        self::assertSame(2, $exit);
        self::assertSame('', $stdout);
        self::assertSame("Frontend check failed: configuration is invalid.\n", $stderr);
        self::assertStringNotContainsString($this->directory, $stdout . $stderr);
    }

    public function testClassifiesArtifactBeforeFrontendOutputConfiguration(): void
    {
        file_put_contents(
            $this->directory . '/config/frontend.php',
            sprintf("<?php return ['output' => '%s'];\n", dirname($this->directory) . '/outside'),
        );
        $application = Application::configure($this->directory)->withConfiguration()->create();

        [$exit, $output] = $this->runCommand($application, 'frontend:check');

        self::assertSame(2, $exit);
        self::assertSame('', $output->fetch());
        self::assertSame("Frontend check failed: contract artifact is invalid.\n", $output->errorOutput()->fetch());
    }

    public function testReportsMissingMalformedAndStaleArtifactsWithoutChangingOutput(): void
    {
        $outputPath = $this->directory . '/resources/js/blackops';
        mkdir($outputPath, recursive: true);
        file_put_contents($outputPath . '/application.ts', 'application-owned');

        foreach (['missing', 'malformed', 'stale'] as $case) {
            if ($case === 'malformed') {
                file_put_contents(
                    $this->directory . '/var/build/frontend.php',
                    "<?php throw new RuntimeException('credential-secret ' . __FILE__);\n",
                );
            } elseif ($case === 'stale') {
                new FrontendContractManifestFile()->write(
                    FrontendContractFixture::manifest(),
                    $this->directory . '/var/build/frontend.php',
                    'stale-build',
                );
            } else {
                $manifest = $this->directory . '/var/build/frontend.php';
                if (is_file($manifest)) {
                    unlink($manifest);
                }
            }
            $application = Application::configure($this->directory)->withConfiguration()->create();

            [$exit, $output] = $this->runCommand($application, 'frontend:check');
            $stdout = $output->fetch();
            $stderr = $output->errorOutput()->fetch();

            self::assertSame(2, $exit);
            self::assertSame('', $stdout);
            self::assertSame("Frontend check failed: contract artifact is invalid.\n", $stderr);
            self::assertStringNotContainsString('credential-secret', $stdout . $stderr);
            self::assertStringNotContainsString($this->directory, $stdout . $stderr);
            self::assertSame('application-owned', file_get_contents($outputPath . '/application.ts'));
        }
    }

    public function testReportsInvalidGeneratedContractWithoutChangingOutput(): void
    {
        $operation = FrontendContractFixture::manifest()->operations[0];
        $invalid = new FrontendOperationContract(
            $operation->typeId,
            $operation->definition,
            $operation->exportName,
            $operation->module,
            'GET',
            $operation->path,
            $operation->strategy,
            $operation->value,
            $operation->outcome,
        );
        new FrontendContractManifestFile()->write(
            new FrontendContractManifest([$invalid]),
            $this->directory . '/var/build/frontend.php',
            'check-build',
        );
        $outputPath = $this->directory . '/resources/js/blackops';
        mkdir($outputPath, recursive: true);
        file_put_contents($outputPath . '/application.ts', 'preserved');
        $application = Application::configure($this->directory)->withConfiguration()->create();

        [$exit, $output] = $this->runCommand($application, 'frontend:check');

        self::assertSame(2, $exit);
        self::assertSame('', $output->fetch());
        self::assertSame("Frontend check failed: generated contract is invalid.\n", $output->errorOutput()->fetch());
        self::assertSame('preserved', file_get_contents($outputPath . '/application.ts'));
    }

    public function testReportsUnreadableGeneratedFileAsInvalidInspection(): void
    {
        $this->writeArtifact();
        $application = Application::configure($this->directory)->withConfiguration()->create();
        [$generateExit] = $this->runCommand($application, 'frontend:generate');
        self::assertSame(0, $generateExit);
        $client = $this->directory . '/resources/js/blackops/client.ts';
        chmod($client, 0o000);

        [$exit, $output] = $this->runCommand($application, 'frontend:check');

        chmod($client, 0o600);
        $stdout = $output->fetch();
        $stderr = $output->errorOutput()->fetch();
        self::assertSame(2, $exit);
        self::assertSame('', $stdout);
        self::assertSame("Frontend check failed: generated tree could not be inspected.\n", $stderr);
        self::assertStringNotContainsString($this->directory, $stdout . $stderr);
    }

    private function writeArtifact(): void
    {
        new FrontendContractManifestFile()->write(
            FrontendContractFixture::manifest(),
            $this->directory . '/var/build/frontend.php',
            'check-build',
        );
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

    /** @return array{int, FrontendCheckSplitOutput} */
    private function runCommand(Application $application, string $command): array
    {
        $output = new FrontendCheckSplitOutput();
        $exit = $application->console()->run(new ArrayInput(['command' => $command]), $output);

        return [$exit, $output];
    }

    /** @return array<string, string> */
    private function treeBytes(string $root): array
    {
        $bytes = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
        ));
        foreach ($iterator as $entry) {
            if ($entry->isFile()) {
                $bytes[substr($entry->getPathname(), strlen($root) + 1)] = (string) file_get_contents(
                    $entry->getPathname(),
                );
            }
        }
        ksort($bytes);

        return $bytes;
    }
}

final class FrontendCheckSplitOutput extends BufferedOutput implements ConsoleOutputInterface
{
    private BufferedOutput $error;

    public function __construct()
    {
        parent::__construct();
        $this->error = new BufferedOutput();
    }

    public function getErrorOutput(): OutputInterface
    {
        return $this->error;
    }

    public function setErrorOutput(OutputInterface $error): void
    {
        if (!$error instanceof BufferedOutput) {
            throw new LogicException('This test output requires a buffered error output.');
        }
        $this->error = $error;
    }

    public function errorOutput(): BufferedOutput
    {
        return $this->error;
    }

    public function section(): ConsoleSectionOutput
    {
        throw new LogicException('Output sections are not used by this test.');
    }
}
