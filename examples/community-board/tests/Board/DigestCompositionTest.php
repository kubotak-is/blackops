<?php

declare(strict_types=1);

namespace App\Tests\Board;

use App\ApplicationServiceProvider;
use App\Feature\Digest\DigestAttemptGate;
use App\Infrastructure\Deferred\FailFirstDigestAttemptGate;
use App\Infrastructure\Deferred\NoOpDigestAttemptGate;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DigestCompositionTest extends TestCase
{
    /** @return iterable<string, array{?string, class-string}> */
    public static function gateConfigurations(): iterable
    {
        yield 'default' => [null, NoOpDigestAttemptGate::class];
        yield 'false' => ['false', NoOpDigestAttemptGate::class];
        yield 'true' => ['true', FailFirstDigestAttemptGate::class];
    }

    /** @param class-string $expected */
    #[DataProvider('gateConfigurations')]
    public function testProviderSelectsAttemptGateDuringRegistration(?string $value, string $expected): void
    {
        $original = $_ENV['DIGEST_FAIL_FIRST_ATTEMPT'] ?? null;
        try {
            if ($value === null) {
                unset($_ENV['DIGEST_FAIL_FIRST_ATTEMPT']);
            } else {
                $_ENV['DIGEST_FAIL_FIRST_ATTEMPT'] = $value;
            }
            $registry = new RecordingServiceRegistry();
            new ApplicationServiceProvider()->register($registry);
            self::assertSame($expected, $registry->classes[DigestAttemptGate::class]);
        } finally {
            if ($original === null) {
                unset($_ENV['DIGEST_FAIL_FIRST_ATTEMPT']);
            } else {
                $_ENV['DIGEST_FAIL_FIRST_ATTEMPT'] = $original;
            }
        }
    }

    public function testProviderFailsFastForNonCanonicalFlag(): void
    {
        $original = $_ENV['DIGEST_FAIL_FIRST_ATTEMPT'] ?? null;
        try {
            $_ENV['DIGEST_FAIL_FIRST_ATTEMPT'] = 'TRUE';
            $this->expectException(InvalidArgumentException::class);
            new ApplicationServiceProvider()->register(new RecordingServiceRegistry());
        } finally {
            if ($original === null) {
                unset($_ENV['DIGEST_FAIL_FIRST_ATTEMPT']);
            } else {
                $_ENV['DIGEST_FAIL_FIRST_ATTEMPT'] = $original;
            }
        }
    }
}

final class RecordingServiceRegistry implements ServiceRegistry
{
    /** @var array<string, class-string> */
    public array $classes = [];

    public function autowire(string $id, ?string $class = null): void
    {
        if ($class !== null) {
            $this->classes[$id] = $class;
        }
    }

    public function set(string $id, object $service): void {}
}
