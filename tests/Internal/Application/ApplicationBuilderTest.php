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
}
