<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Application\Application;
use BlackOps\Application\ApplicationBootstrapException;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApplicationBuilderTest extends TestCase
{
    use ApplicationTestDirectories;

    public function testNormalizesBasePathAndUsesSafeDefaultsWithoutConfigDirectory(): void
    {
        $directory = $this->directory();
        $snapshot = $this->snapshot(
            Application::configure($directory . '/.')
                ->withConfiguration()
                ->create(),
        );

        self::assertSame($directory, $snapshot->basePath());
        self::assertSame([], $snapshot->configuration());
        self::assertSame([], $snapshot->operationProviders());
        self::assertSame([], $snapshot->serviceProviders());
        self::assertSame([], $snapshot->commands());
        self::assertFalse(method_exists(ApplicationConfigurationSnapshot::class, 'environment'));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidBasePaths(): iterable
    {
        yield 'empty' => [''];
        yield 'missing' => ['/definitely/missing/blackops-application'];
    }

    #[DataProvider('invalidBasePaths')]
    public function testRejectsInvalidBasePath(string $path): void
    {
        $this->expectException(ApplicationBootstrapException::class);

        Application::configure($path);
    }

    public function testRejectsFileAsBasePath(): void
    {
        $directory = $this->directory();
        $path = $directory . '/not-a-directory';
        file_put_contents(filename: $path, data: 'file');

        $this->expectException(ApplicationBootstrapException::class);

        Application::configure($path);
    }

    public function testCapturesExplicitEnvironmentWithoutExposingInvalidValue(): void
    {
        Application::configure($this->directory())->withEnvironment(['APP_ENV' => 'testing'])->create();

        try {
            Application::configure($this->directory())->withEnvironment(['PASSWORD' => ['plain-secret']]);
            self::fail('Expected invalid environment value.');
        } catch (ApplicationBootstrapException $exception) {
            self::assertStringContainsString('PASSWORD', $exception->getMessage());
            self::assertStringNotContainsString('plain-secret', $exception->getMessage());
        }
    }

    public function testCapturesProcessEnvironmentOnlyWhenRequested(): void
    {
        putenv('BLACKOPS_CAPTURE_TEST=before');

        try {
            $directory = $this->directory();
            $config = $directory . '/config';
            mkdir($config);
            $this->writeConfig(
                $config,
                'app',
                'use BlackOps\\Application\\Environment; return static fn (Environment $env): array => ["value" => $env->string("BLACKOPS_CAPTURE_TEST")];',
            );
            $builder = Application::configure($directory)->withEnvironment()->withConfiguration();
            putenv('BLACKOPS_CAPTURE_TEST=after');

            self::assertSame('before', $this->snapshot($builder->create())->configuration()['app']['value']);
        } finally {
            putenv('BLACKOPS_CAPTURE_TEST');
        }
    }

    public function testLoadsOptionalEnvironmentFileWithProcessPrecedence(): void
    {
        $directory = $this->directory();
        file_put_contents($directory . '/.env', "FROM_FILE=file\nOVERRIDDEN=file\n");
        putenv('OVERRIDDEN=process');

        try {
            $config = $directory . '/config';
            mkdir($config);
            $this->writeConfig(
                $config,
                'app',
                'use BlackOps\\Application\\Environment; return static fn (Environment $env): array => ["from_file" => $env->string("FROM_FILE"), "overridden" => $env->string("OVERRIDDEN")];',
            );

            $snapshot = $this->snapshot(
                Application::configure($directory)->withEnvironmentFile()->withConfiguration()->create(),
            );

            self::assertSame('file', $snapshot->configuration()['app']['from_file']);
            self::assertSame('process', $snapshot->configuration()['app']['overridden']);
        } finally {
            putenv('OVERRIDDEN');
        }
    }

    public function testMissingEnvironmentFileIsOptionalAndExplicitSourceReplacesPreviousSource(): void
    {
        $directory = $this->directory();

        $snapshot = $this->snapshot(
            Application::configure($directory)
                ->withEnvironment(['SOURCE' => 'array'])
                ->withEnvironmentFile($directory . '/missing.env')
                ->withConfiguration()
                ->create(),
        );

        self::assertSame([], $snapshot->configuration());

        $config = $directory . '/config';
        mkdir($config);
        $this->writeConfig(
            $config,
            'app',
            'use BlackOps\\Application\\Environment; return static fn (Environment $env): array => ["source" => $env->string("SOURCE")];',
        );
        $snapshot = $this->snapshot(
            Application::configure($directory)
                ->withEnvironmentFile()
                ->withEnvironment(['SOURCE' => 'array'])
                ->withConfiguration()
                ->create(),
        );

        self::assertSame('array', $snapshot->configuration()['app']['source']);
    }

    public function testExistingEnvironmentDirectoryFailsSafely(): void
    {
        $directory = $this->directory();
        mkdir($directory . '/.env');

        try {
            Application::configure($directory)->withEnvironmentFile()->create();
            self::fail('Expected an environment directory to fail bootstrap.');
        } catch (ApplicationBootstrapException $exception) {
            self::assertSame('Application environment file must be a regular file.', $exception->getMessage());
        }
    }

    public function testEnvironmentFileFailureDoesNotExposeRawSecretOrParserDetail(): void
    {
        $directory = $this->directory();
        file_put_contents($directory . '/.env', "SECRET=plain-secret\nINVALID-NAME=broken\n");

        try {
            Application::configure($directory)->withEnvironmentFile()->create();
            self::fail('Expected an invalid environment file to fail bootstrap.');
        } catch (ApplicationBootstrapException $exception) {
            self::assertSame('Application environment file could not be loaded safely.', $exception->getMessage());
            self::assertStringNotContainsString('plain-secret', $exception->getMessage());
            self::assertStringNotContainsString('unterminated', $exception->getMessage());
        }
    }

    public function testEnvironmentFileIsReadOncePerCreate(): void
    {
        $directory = $this->directory();
        file_put_contents($directory . '/.env', "VALUE=before\n");
        $config = $directory . '/config';
        mkdir($config);
        $this->writeConfig(
            $config,
            'app',
            'use BlackOps\\Application\\Environment; return static fn (Environment $env): array => ["value" => $env->string("VALUE")];',
        );

        $builder = Application::configure($directory)->withEnvironmentFile()->withConfiguration();
        $first = $this->snapshot($builder->create());
        file_put_contents($directory . '/.env', "VALUE=after\n");

        self::assertSame('before', $first->configuration()['app']['value']);
        self::assertSame('after', $this->snapshot($builder->create())->configuration()['app']['value']);
    }

    public function testSynchronizesResolvedSnapshotToEnvWithoutMutatingProcessEnvironment(): void
    {
        $directory = $this->directory();
        $fileKey = 'BLACKOPS_SYNC_FILE';
        $processKey = 'BLACKOPS_SYNC_PROCESS';
        $previous = [];

        foreach ([$fileKey, $processKey] as $key) {
            $previous[$key] = array_key_exists($key, $_ENV) ? $_ENV[$key] : null;
            $previous[$key . '_defined'] = array_key_exists($key, $_ENV);
            unset($_ENV[$key]);
        }

        file_put_contents($directory . '/.env', "$fileKey=file\n$processKey=file\n");
        putenv("$processKey=process");

        try {
            Application::configure($directory)->withEnvironmentFile()->create();

            self::assertSame('file', $_ENV[$fileKey]);
            self::assertSame('process', $_ENV[$processKey]);
            self::assertFalse(getenv($fileKey));
            self::assertSame('process', getenv($processKey));
        } finally {
            putenv($processKey);
            foreach ([$fileKey, $processKey] as $key) {
                if ($previous[$key . '_defined']) {
                    $_ENV[$key] = $previous[$key];
                } else {
                    unset($_ENV[$key]);
                }
            }
        }
    }

    public function testSynchronizesProcessValuesWhenEnvironmentFileIsMissing(): void
    {
        $directory = $this->directory();
        $key = 'BLACKOPS_SYNC_MISSING';
        $wasDefined = array_key_exists($key, $_ENV);
        $previous = $_ENV[$key] ?? null;
        unset($_ENV[$key]);
        putenv("$key=process");

        try {
            Application::configure($directory)->withEnvironmentFile()->create();

            self::assertSame('process', $_ENV[$key]);
        } finally {
            putenv($key);
            if ($wasDefined) {
                $_ENV[$key] = $previous;
            } else {
                unset($_ENV[$key]);
            }
        }
    }

    public function testEnvironmentFileFailureDoesNotPartiallySynchronizeEnv(): void
    {
        $directory = $this->directory();
        $key = 'BLACKOPS_SYNC_FAILURE';
        $wasDefined = array_key_exists($key, $_ENV);
        $previous = $_ENV[$key] ?? null;
        $_ENV[$key] = 'before';
        file_put_contents($directory . '/.env', "$key=after\nINVALID-NAME=broken\n");

        try {
            Application::configure($directory)->withEnvironmentFile()->create();
            self::fail('Expected an invalid environment file to fail bootstrap.');
        } catch (ApplicationBootstrapException $exception) {
            self::assertSame('Application environment file could not be loaded safely.', $exception->getMessage());
            self::assertSame('before', $_ENV[$key]);
        } finally {
            if ($wasDefined) {
                $_ENV[$key] = $previous;
            } else {
                unset($_ENV[$key]);
            }
        }
    }
}
