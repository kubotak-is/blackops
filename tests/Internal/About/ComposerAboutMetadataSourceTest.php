<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\About;

use BlackOps\Internal\About\ComposerAboutMetadataSource;
use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Throwable;

final class ComposerAboutMetadataSourceTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(true)]
    public function testDefaultAuthorityReadsTheFrameworkDependencyNotTheComposerRoot(): void
    {
        $root = dirname(__DIR__, 3);
        require_once $root . '/vendor/autoload.php';
        require_once $root . '/src/Internal/About/AboutMetadataSource.php';
        require_once $root . '/src/Internal/About/AboutMetadata.php';
        require_once $root . '/src/Internal/About/ComposerAboutMetadataSource.php';

        $this->withInstalledData('1.3.x-dev', function (): void {
            $metadata = new ComposerAboutMetadataSource(phpVersion: '8.5.0', sapi: 'cli')->read();

            self::assertSame('1.3.x-dev', $metadata->version);
            self::assertSame('blackops/framework', $metadata->toArray()['package']['name']);
        });
    }

    public function testDependencyShapedInstalledMetadataIsReadAndRestored(): void
    {
        $original = $this->rawInstalledData();
        $this->withInstalledData('1.3.0', function (): void {
            $metadata = new ComposerAboutMetadataSource(phpVersion: '8.5.0', sapi: 'cli')->read();

            self::assertSame('blackops/framework', $metadata->toArray()['package']['name']);
            self::assertSame('1.3.0', $metadata->version);
            self::assertSame('8.5.0', $metadata->phpVersion);
            self::assertSame('cli', $metadata->sapi);
        });

        self::assertSame($original, $this->rawInstalledData());
        self::assertSame(
            $original['versions']['blackops/framework']['pretty_version'],
            new ComposerAboutMetadataSource()->read()->version,
        );
    }

    public function testDependencyVersionsAndDevelopmentAliasesAreAccepted(): void
    {
        foreach (['1.3.0', 'v1.3.0', '1.2.0@dev', '1.3.x-dev', 'dev-main', 'dev-feature/x'] as $version) {
            $this->withInstalledData($version, function () use ($version): void {
                $installed = $this->installedData($version);
                self::assertSame(
                    $version,
                    new ComposerAboutMetadataSource(
                        static fn(): mixed => $installed['versions']['blackops/framework']['pretty_version'],
                    )->read()->version,
                );
            });
        }
    }

    public function testInjectedLookupIsCalledOnceAndInvalidValuesFailClosed(): void
    {
        $calls = 0;
        $source = new ComposerAboutMetadataSource(
            static function () use (&$calls): string {
                ++$calls;

                return 'dev-main';
            },
            '8.5.0',
            'cli',
        );

        self::assertSame('dev-main', $source->read()->version);
        self::assertSame(1, $calls);

        foreach ([null, '', str_repeat('a', 129), '1.2.0 dev', "1.2.0\nsecret", '1.2.0é'] as $invalid) {
            try {
                new ComposerAboutMetadataSource(static fn(): mixed => $invalid, '8.5.0', 'cli')->read();
                self::fail('Expected invalid installed version to be rejected.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testMissingOrThrowingComposerMetadataIsNotReinterpreted(): void
    {
        $this->expectException(Throwable::class);
        new ComposerAboutMetadataSource(
            static function (): mixed {
                throw new \RuntimeException('reference=/secret/password');
            },
            '8.5.0',
            'cli',
        )->read();
    }

    private function withInstalledData(string $version, callable $callback): void
    {
        $original = $this->rawInstalledData();
        $installedByVendor = $this->installedByVendor();
        $installedIsLocalDir = $this->installedIsLocalDir();
        $loaders = ClassLoader::getRegisteredLoaders();
        try {
            $installed = $this->installedData($version);
            InstalledVersions::reload($installed);
            $byVendor = [];
            foreach (array_keys($loaders) as $vendorDirectory) {
                $byVendor[$vendorDirectory] = $installed;
            }
            $this->setInstalledByVendor($byVendor);
            $callback();
        } finally {
            InstalledVersions::reload($original);
            $this->setInstalledByVendor($installedByVendor);
            $this->setInstalledIsLocalDir($installedIsLocalDir);
        }

        self::assertSame($original, $this->rawInstalledData());
    }

    /** @return array<string, mixed> */
    private function rawInstalledData(): array
    {
        /** @var array<string, mixed> $data */
        $data = @InstalledVersions::getRawData();

        return $data;
    }

    /** @return array<string, array<string, mixed>> */
    private function installedByVendor(): array
    {
        $reflection = new ReflectionClass(InstalledVersions::class);
        $property = $reflection->getProperty('installedByVendor');

        /** @var array<string, array<string, mixed>> $data */
        $data = $property->getValue();

        return $data;
    }

    /** @param array<string, array<string, mixed>> $data */
    private function setInstalledByVendor(array $data): void
    {
        $reflection = new ReflectionClass(InstalledVersions::class);
        $property = $reflection->getProperty('installedByVendor');
        $property->setValue(null, $data);
    }

    private function installedIsLocalDir(): mixed
    {
        $reflection = new ReflectionClass(InstalledVersions::class);
        $property = $reflection->getProperty('installedIsLocalDir');

        return $property->getValue();
    }

    private function setInstalledIsLocalDir(mixed $value): void
    {
        $reflection = new ReflectionClass(InstalledVersions::class);
        $property = $reflection->getProperty('installedIsLocalDir');
        $property->setValue(null, $value);
    }

    /** @return array{root: array<string, mixed>, versions: array<string, array<string, mixed>>} */
    private function installedData(string $version): array
    {
        return [
            'root' => [
                'name' => 'example/application',
                'pretty_version' => '9.9.9',
                'version' => '9.9.9.0',
                'reference' => 'root-secret',
                'type' => 'project',
                'install_path' => '/private/root',
                'aliases' => [],
                'dev' => false,
            ],
            'versions' => [
                'example/application' => [
                    'pretty_version' => '9.9.9',
                    'version' => '9.9.9.0',
                    'reference' => 'root-secret',
                    'type' => 'project',
                    'install_path' => '/private/root',
                    'dev_requirement' => false,
                ],
                'blackops/framework' => [
                    'pretty_version' => $version,
                    'version' => '1.3.0.0',
                    'reference' => 'framework-secret',
                    'type' => 'library',
                    'install_path' => '/private/framework',
                    'dev_requirement' => false,
                ],
            ],
        ];
    }
}
