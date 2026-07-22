<?php

declare(strict_types=1);

namespace App\Tests\Board;

use App\ApplicationServiceProvider;
use App\Feature\Digest\DigestAttemptGate;
use App\Infrastructure\Deferred\FailFirstDigestAttemptGate;
use App\Infrastructure\Deferred\NoOpDigestAttemptGate;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DigestCompositionTest extends TestCase
{
    /** @return iterable<string, array{bool, class-string}> */
    public static function gateConfigurations(): iterable
    {
        yield 'false' => [false, NoOpDigestAttemptGate::class];
        yield 'true' => [true, FailFirstDigestAttemptGate::class];
    }

    /** @param class-string $expected */
    #[DataProvider('gateConfigurations')]
    public function testProviderSelectsAttemptGateDuringRegistration(bool $value, string $expected): void
    {
        $registry = new RecordingServiceRegistry();
        new ApplicationServiceProvider($value)->register($registry);
        self::assertSame($expected, $registry->classes[DigestAttemptGate::class]);
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
