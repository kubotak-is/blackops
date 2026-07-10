<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Internal\ArchitectureFixture\InvalidInternalPublicApi;
use BlackOps\Tests\Architecture\Fixture\PublicApiWithInternalSignatures;
use BlackOps\Tests\Architecture\Fixture\ValidPublicApi;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixture/PublicApiArchitectureFixtures.php';

final class PublicApiArchitectureTest extends TestCase
{
    private PublicApiArchitectureGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new PublicApiArchitectureGuard();
    }

    public function testEverySourceTypeRespectsThePublicApiBoundary(): void
    {
        $types = new SourceTypeDiscovery(dirname(__DIR__, 2) . '/src', 'BlackOps')->discover();
        $violations = $this->guard->violations($types);

        self::assertNotEmpty($types);
        self::assertSame([], $violations, implode("\n", $violations));
    }

    public function testValidPublicApiSignaturePasses(): void
    {
        self::assertSame([], $this->guard->violations([ValidPublicApi::class]));
    }

    public function testInternalTypesInPublicSignaturesAreReportedRecursively(): void
    {
        $violations = $this->guard->violations([PublicApiWithInternalSignatures::class]);
        $message = implode("\n", $violations);

        self::assertStringContainsString('PublicApiWithInternalSignatures parent exposes internal type', $message);
        self::assertStringContainsString('PublicApiWithInternalSignatures interface exposes internal type', $message);
        self::assertStringContainsString('__construct() parameter $dependency exposes internal type', $message);
        self::assertStringContainsString('::$dependency property exposes internal type', $message);
        self::assertStringContainsString('union() parameter $dependency exposes internal type', $message);
        self::assertStringContainsString('union() return exposes internal type', $message);
        self::assertStringContainsString('intersection() parameter $dependency exposes internal type', $message);
        self::assertStringContainsString('intersection() return exposes internal type', $message);
    }

    public function testInternalTypeCannotBeDeclaredAsPublicApi(): void
    {
        self::assertSame(
            [
                'BlackOps\\Internal\\ArchitectureFixture\\InvalidInternalPublicApi is internal and must not be declared as PublicApi.',
            ],
            $this->guard->violations([InvalidInternalPublicApi::class]),
        );
    }
}
