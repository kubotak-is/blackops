<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Generator;

use BlackOps\Internal\Generator\AuthGenerator;
use BlackOps\Internal\Generator\ProjectFileWriter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuthGeneratorTest extends TestCase
{
    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            $this->remove($directory);
        }
    }

    public function testGeneratesCompleteLayeredAuthenticationStarter(): void
    {
        $directory = $this->directory();
        $result = $this->generator($directory)->generate();

        self::assertCount(27, $result->created);
        self::assertSame([], $result->updated);
        self::assertFalse($result->current);
        foreach ($result->created as $relative) {
            self::assertFileExists($directory . '/' . $relative);
        }

        $domain = implode("\n", array_map(
            static fn(string $path): string => (string) file_get_contents($path),
            glob($directory . '/app/Domain/Identity/*.php') ?: [],
        ));
        self::assertStringNotContainsString('BlackOps\\', $domain);
        self::assertStringNotContainsString('Doctrine\\', $domain);
        self::assertStringNotContainsString('Symfony\\', $domain);

        $register = (string) file_get_contents($directory . '/app/Feature/Identity/Register/Register.php');
        self::assertStringContainsString("#[Route(method: 'POST', path: '/auth/register')]", $register);
        self::assertStringContainsString("#[ExecuteWith('BlackOps\\\\Core\\\\Execution\\\\Inline')]", $register);
        self::assertStringContainsString('#[Transactional]', $register);
        self::assertStringContainsString('$this->identity->register(', $register);
        self::assertStringNotContainsString('password_hash(', $register);
        self::assertStringNotContainsString('fetchAssociative(', $register);

        $passwords = (string) file_get_contents($directory . '/app/Domain/Identity/PasswordHasher.php');
        self::assertStringContainsString('private string $dummyHash;', $passwords);
        self::assertStringContainsString('password_verify($password, $knownHash ?? $this->dummyHash)', $passwords);
        self::assertStringContainsString('return $knownHash !== null && $verified;', $passwords);
        $identity = (string) file_get_contents($directory . '/app/Domain/Identity/IdentityService.php');
        self::assertStringContainsString(
            '$verified = $this->passwords->verifyCredential($password, $user?->passwordHash);',
            $identity,
        );
        self::assertStringContainsString('if ($user === null || !$verified)', $identity);

        $config = (string) file_get_contents($directory . '/config/auth.php');
        self::assertStringContainsString("'generator_version' => 1", $config);
        self::assertStringContainsString('SessionServiceProvider::bearer(', $config);
        self::assertStringContainsString("positiveInt('AUTH_SESSION_TTL_SECONDS', 28_800)", $config);
    }

    public function testCurrentCompleteStateIsNoopWithoutComparingApplicationOwnedContents(): void
    {
        $directory = $this->directory();
        $generator = $this->generator($directory);
        $generator->generate();
        $user = $directory . '/app/Domain/Identity/User.php';
        file_put_contents($user, "<?php\n// application-owned\n");

        $result = $generator->generate();

        self::assertTrue($result->current);
        self::assertSame([], $result->created);
        self::assertSame([], $result->updated);
        self::assertSame("<?php\n// application-owned\n", file_get_contents($user));
    }

    public function testPartialStateWritesNothingAndReportsOnlyRelativePath(): void
    {
        $directory = $this->directory();
        mkdir($directory . '/app/Domain/Identity', recursive: true);
        $existing = $directory . '/app/Domain/Identity/User.php';
        file_put_contents($existing, 'application-owned');

        try {
            $this->generator($directory)->generate();
            self::fail('Expected partial state failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('app/Domain/Identity/User.php', $exception->getMessage());
            self::assertStringNotContainsString($directory, $exception->getMessage());
        }

        self::assertSame('application-owned', file_get_contents($existing));
        self::assertSame(['app/Domain/Identity/User.php', 'composer.json'], $this->files($directory));
    }

    public function testForceUpdatesOnlyFrameworkOwnedFilesAndPreservesMigrationsAndDomain(): void
    {
        $directory = $this->directory();
        $generator = $this->generator($directory);
        $generator->generate();
        $domain = $directory . '/app/Domain/Identity/User.php';
        $migration = $directory . '/migrations/Version20260722000000.php';
        $repository = $directory . '/app/Infrastructure/Identity/DoctrineUserRepository.php';
        file_put_contents($domain, 'custom-user');
        file_put_contents($migration, 'custom-migration');
        file_put_contents($repository, 'custom-repository');
        $config = $directory . '/config/auth.php';
        file_put_contents($config, str_replace(
            "'generator_version' => 1",
            "'generator_version' => 0",
            (string) file_get_contents($config),
        ));

        $result = $generator->generate(force: true);

        self::assertSame(
            [
                'app/AuthServiceProvider.php',
                'app/Infrastructure/Identity/ApplicationSessionIdentityProvider.php',
                'config/auth.php',
            ],
            $result->updated,
        );
        self::assertSame('custom-user', file_get_contents($domain));
        self::assertSame('custom-migration', file_get_contents($migration));
        self::assertSame('custom-repository', file_get_contents($repository));
        self::assertStringContainsString("'generator_version' => 1", (string) file_get_contents($config));
    }

    public function testOldVersionRequiresForceAndUnknownMarkerIsRejected(): void
    {
        $directory = $this->directory();
        $generator = $this->generator($directory);
        $generator->generate();
        $config = $directory . '/config/auth.php';
        file_put_contents($config, str_replace(
            "'generator_version' => 1",
            "'generator_version' => 0",
            (string) file_get_contents($config),
        ));

        try {
            $generator->generate();
            self::fail('Expected old version failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('--force', $exception->getMessage());
        }

        file_put_contents($config, '<?php return [];');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('marker is invalid');
        $generator->generate(force: true);
    }

    public function testFirstRunWriteFailureRollsBackEveryFileAndDirectory(): void
    {
        $directory = $this->directory();
        $writes = 0;
        $writer = new ProjectFileWriter(static function (string $path, string $contents) use (&$writes): int {
            ++$writes;
            if ($writes === 4) {
                file_put_contents($path, 'partial');

                return 0;
            }

            return file_put_contents($path, $contents);
        });

        try {
            $this->generator($directory, $writer)->generate();
            self::fail('Expected write failure.');
        } catch (InvalidArgumentException) {
            self::assertSame(['composer.json'], $this->files($directory));
        }
    }

    public function testForceFailureRestoresAllReplacedFiles(): void
    {
        $directory = $this->directory();
        $this->generator($directory)->generate();
        $config = $directory . '/config/auth.php';
        file_put_contents($config, str_replace(
            "'generator_version' => 1",
            "'generator_version' => 0",
            (string) file_get_contents($config),
        ));
        $before = $this->contents($directory, [
            'app/AuthServiceProvider.php',
            'app/Infrastructure/Identity/ApplicationSessionIdentityProvider.php',
            'config/auth.php',
        ]);
        $writer = new ProjectFileWriter(beforePublish: static function (
            string $_temporary,
            string $_target,
            int $index,
        ): void {
            if ($index === 1) {
                throw new RuntimeException('simulated replacement failure');
            }
        });

        try {
            $this->generator($directory, $writer)->generate(force: true);
            self::fail('Expected force failure.');
        } catch (InvalidArgumentException) {
            self::assertSame($before, $this->contents($directory, array_keys($before)));
            self::assertSame([], glob($directory . '/**/.blackops-*') ?: []);
        }
    }

    public function testForceRacePreservesCompetitorAndRestoresEarlierReplacement(): void
    {
        $directory = $this->directory();
        $this->generator($directory)->generate();
        $serviceProvider = $directory . '/app/AuthServiceProvider.php';
        $identityProvider = $directory . '/app/Infrastructure/Identity/ApplicationSessionIdentityProvider.php';
        $config = $directory . '/config/auth.php';
        file_put_contents($config, str_replace(
            "'generator_version' => 1",
            "'generator_version' => 0",
            (string) file_get_contents($config),
        ));
        $serviceProviderBefore = (string) file_get_contents($serviceProvider);
        $configBefore = (string) file_get_contents($config);
        $writer = new ProjectFileWriter(beforePublish: static function (
            string $_temporary,
            string $target,
            int $index,
        ) use ($identityProvider): void {
            if ($index === 1) {
                self::assertSame($identityProvider, $target);
                file_put_contents($target, 'competing-update');
            }
        });

        try {
            $this->generator($directory, $writer)->generate(force: true);
            self::fail('Expected force race failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('changed while updating', $exception->getMessage());
            self::assertStringNotContainsString($directory, $exception->getMessage());
            self::assertSame($serviceProviderBefore, file_get_contents($serviceProvider));
            self::assertSame('competing-update', file_get_contents($identityProvider));
            self::assertSame($configBefore, file_get_contents($config));
        }
    }

    public function testForcePostRenameRaceRestoresCompetingContentAndEarlierReplacement(): void
    {
        $directory = $this->directory();
        $this->generator($directory)->generate();
        $serviceProvider = $directory . '/app/AuthServiceProvider.php';
        $identityProvider = $directory . '/app/Infrastructure/Identity/ApplicationSessionIdentityProvider.php';
        $config = $directory . '/config/auth.php';
        file_put_contents($config, str_replace(
            "'generator_version' => 1",
            "'generator_version' => 0",
            (string) file_get_contents($config),
        ));
        $serviceProviderBefore = (string) file_get_contents($serviceProvider);
        $configBefore = (string) file_get_contents($config);
        $writer = new ProjectFileWriter(afterBackup: static function (string $backup, string $target, int $index) use (
            $identityProvider,
        ): void {
            if ($index === 1) {
                self::assertSame($identityProvider, $target);
                file_put_contents($backup, 'post-rename-competing-update');
            }
        });

        try {
            $this->generator($directory, $writer)->generate(force: true);
            self::fail('Expected post-rename force race failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('changed while updating', $exception->getMessage());
            self::assertStringNotContainsString($directory, $exception->getMessage());
            self::assertSame($serviceProviderBefore, file_get_contents($serviceProvider));
            self::assertSame('post-rename-competing-update', file_get_contents($identityProvider));
            self::assertSame($configBefore, file_get_contents($config));
        }
    }

    public function testDirectoryTargetIsRejectedWithoutWriting(): void
    {
        $directory = $this->directory();
        mkdir($directory . '/app/Domain/Identity/User.php', recursive: true);

        try {
            $this->generator($directory)->generate();
            self::fail('Expected directory target rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('must be a regular file', $exception->getMessage());
            self::assertStringContainsString('app/Domain/Identity/User.php', $exception->getMessage());
            self::assertStringNotContainsString($directory, $exception->getMessage());
            self::assertSame(['composer.json'], $this->files($directory));
            self::assertDirectoryExists($directory . '/app/Domain/Identity/User.php');
        }
    }

    public function testSymlinkAncestorIsRejectedWithoutWritingOutsideProject(): void
    {
        $directory = $this->directory();
        $outside = $this->directory();
        symlink($outside, $directory . '/app');

        try {
            $this->generator($directory)->generate();
            self::fail('Expected symlink ancestor rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Generated file path resolves outside the application.', $exception->getMessage());
            self::assertStringNotContainsString($directory, $exception->getMessage());
            self::assertSame(['app', 'composer.json'], $this->files($directory));
            self::assertSame(['composer.json'], $this->files($outside));
        }
    }

    public function testInsideRootSymlinkAncestorIsRejectedWithoutWriting(): void
    {
        $directory = $this->directory();
        mkdir($directory . '/real-app');
        symlink($directory . '/real-app', $directory . '/app');

        try {
            $this->generator($directory)->generate();
            self::fail('Expected inside-root symlink ancestor rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Generated file ancestor must not resolve through a symbolic link.',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString($directory, $exception->getMessage());
            self::assertSame(['app', 'composer.json'], $this->files($directory));
            self::assertSame([], $this->files($directory . '/real-app'));
        }
    }

    public function testSymlinkTargetAndInvalidComposerAreRejectedBeforeWriting(): void
    {
        $directory = $this->directory();
        $outside = $this->directory();
        mkdir($directory . '/app/Domain/Identity', recursive: true);
        symlink($outside . '/outside.php', $directory . '/app/Domain/Identity/User.php');

        try {
            $this->generator($directory)->generate();
            self::fail('Expected symlink rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('must not be a symlink', $exception->getMessage());
            self::assertStringNotContainsString($directory, $exception->getMessage());
        }

        unlink($directory . '/app/Domain/Identity/User.php');
        file_put_contents($directory . '/composer.json', '{}');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('App\\ PSR-4');
        $this->generator($directory)->generate();
    }

    public function testMissingAndInvalidStubDoNotExposeFrameworkPathOrWrite(): void
    {
        $directory = $this->directory();
        $missing = $directory . '/private/framework/stubs';

        try {
            new AuthGenerator($directory, $missing)->generate();
            self::fail('Expected missing stub failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Authentication generator stub is unavailable.', $exception->getMessage());
            self::assertStringNotContainsString($missing, $exception->getMessage());
            self::assertSame(['composer.json'], $this->files($directory));
        }

        mkdir($missing, recursive: true);
        foreach (glob(dirname(__DIR__, levels: 3) . '/resources/stubs/auth-*.php.stub') ?: [] as $stub) {
            copy($stub, $missing . '/' . basename($stub));
        }
        file_put_contents($missing . '/auth-user.php.stub', '<?php {{ unknown }}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('stub is invalid');
        new AuthGenerator($directory, $missing)->generate();
    }

    private function generator(string $directory, ?ProjectFileWriter $writer = null): AuthGenerator
    {
        return new AuthGenerator(
            $directory,
            dirname(__DIR__, levels: 3) . '/resources/stubs',
            $writer ?? new ProjectFileWriter(),
        );
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir() . '/blackops-auth-generator-' . bin2hex(random_bytes(8));
        mkdir($directory);
        file_put_contents($directory . '/composer.json', json_encode([
            'autoload' => ['psr-4' => ['App\\' => 'app/']],
        ], JSON_THROW_ON_ERROR));
        $this->directories[] = $directory;

        return $directory;
    }

    /** @return list<string> */
    private function files(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $directory,
            \FilesystemIterator::SKIP_DOTS,
        ));
        foreach ($iterator as $entry) {
            if ($entry->isFile() || $entry->isLink()) {
                $files[] = str_replace($directory . '/', '', $entry->getPathname());
            }
        }
        sort($files);

        return $files;
    }

    /**
     * @param list<string> $paths
     * @return array<string, string>
     */
    private function contents(string $directory, array $paths): array
    {
        $contents = [];
        foreach ($paths as $path) {
            $contents[$path] = (string) file_get_contents($directory . '/' . $path);
        }

        return $contents;
    }

    private function remove(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($directory);
    }
}
