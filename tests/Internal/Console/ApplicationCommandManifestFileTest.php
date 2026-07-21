<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Console\ApplicationCommandManifestFile;
use BlackOps\Internal\Console\ApplicationCommandMetadata;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;

final class ApplicationCommandManifestFileTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/blackops-command-manifest-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testWritesAndLoadsDeterministicSchemaOneArtifactAtomically(): void
    {
        $path = $this->directory . '/commands.php';
        $file = new ApplicationCommandManifestFile();
        $file->write(
            [
                $this->metadata(SecondManifestCommand::class, 'z:last'),
                $this->metadata(FirstManifestCommand::class, 'a:first', ['a:alias']),
            ],
            $path,
            'manifest-build',
        );

        $artifact = $file->loadArtifact($path);

        self::assertSame(1, $artifact->schemaVersion);
        self::assertSame('manifest-build', $artifact->applicationBuildId);
        self::assertSame(
            ['a:first', 'z:last'],
            array_map(static fn(ApplicationCommandMetadata $command): string => $command->name, $artifact->commands),
        );
        self::assertStringContainsString("'schema_version' => 1", (string) file_get_contents($path));
        self::assertSame([], glob($this->directory . '/command-manifest-*') ?: []);

        $file->write([], $path, 'manifest-build');
        self::assertSame([], $file->loadArtifact($path)->commands);
    }

    public function testRejectsInvalidSchemaShapeOrderingAndDuplicateNamesWithoutLeakingSource(): void
    {
        $path = $this->directory . '/invalid.php';
        $cases = [
            ['schema_version' => 2, 'application_build_id' => 'build', 'commands' => []],
            ['schema_version' => 1, 'application_build_id' => '', 'commands' => []],
            ['schema_version' => 1, 'application_build_id' => 'build', 'commands' => 'invalid'],
            [
                'schema_version' => 1,
                'application_build_id' => 'build',
                'commands' => [
                    $this->entry(SecondManifestCommand::class, 'z:last'),
                    $this->entry(FirstManifestCommand::class, 'a:first'),
                ],
            ],
            [
                'schema_version' => 1,
                'application_build_id' => 'build',
                'commands' => [
                    $this->entry(FirstManifestCommand::class, 'a:first', ['same:name']),
                    $this->entry(SecondManifestCommand::class, 'same:name'),
                ],
            ],
        ];

        foreach ($cases as $case) {
            file_put_contents($path, '<?php return ' . var_export($case, return: true) . ';');
            try {
                new ApplicationCommandManifestFile()->loadArtifact($path);
                self::fail('Expected invalid command manifest rejection.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringNotContainsString($this->directory, $exception->getMessage());
            }
        }
    }

    /**
     * @param class-string<Command> $class
     * @param list<string> $aliases
     */
    private function metadata(string $class, string $name, array $aliases = []): ApplicationCommandMetadata
    {
        return new ApplicationCommandMetadata($class, $name, 'Description', $aliases, false, 'Help', ['usage']);
    }

    /**
     * @param class-string<Command> $class
     * @param list<string> $aliases
     * @return array<string, mixed>
     */
    private function entry(string $class, string $name, array $aliases = []): array
    {
        return [
            'class' => $class,
            'name' => $name,
            'description' => null,
            'aliases' => $aliases,
            'hidden' => false,
            'help' => null,
            'usages' => [],
        ];
    }
}

final class FirstManifestCommand extends Command {}

final class SecondManifestCommand extends Command {}
