<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Application\Application;
use BlackOps\Application\ApplicationBootstrapException;
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
        self::assertSame([], $snapshot->environment());
        self::assertSame([], $snapshot->configuration());
        self::assertSame([], $snapshot->operationProviders());
        self::assertSame([], $snapshot->serviceProviders());
        self::assertSame([], $snapshot->commands());
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
        $snapshot = $this->snapshot(
            Application::configure($this->directory())->withEnvironment(['APP_ENV' => 'testing'])->create(),
        );
        self::assertSame(['APP_ENV' => 'testing'], $snapshot->environment());

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
            $builder = Application::configure($this->directory())->withEnvironment();
            putenv('BLACKOPS_CAPTURE_TEST=after');

            self::assertSame('before', $this->snapshot($builder->create())->environment()['BLACKOPS_CAPTURE_TEST']);
        } finally {
            putenv('BLACKOPS_CAPTURE_TEST');
        }
    }
}
