<?php

declare(strict_types=1);

namespace BlackOps\Tests\Http;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Http\Routing\FastRouteDispatcherDataCompiler;
use BlackOps\Http\Routing\HttpOperationManifest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HttpOperationManifestTest extends TestCase
{
    public function testResolvesExactOperationInstance(): void
    {
        $exact = new ManifestOperation();
        $registry = $this->manifest()->toRegistry([$exact]);

        self::assertSame($exact, $registry->match('GET', '/manifest')?->route->operation);
    }

    public function testResolvesSubclassInstanceAndKeepsContainerInstance(): void
    {
        $proxy = new ProxiedManifestOperation();
        $registry = $this->manifest()->toRegistry([$proxy]);

        self::assertSame($proxy, $registry->match('GET', '/manifest')?->route->operation);
    }

    public function testPrefersExactInstanceOverSubclassInstance(): void
    {
        $proxy = new ProxiedManifestOperation();
        $exact = new ManifestOperation();
        $registry = $this->manifest()->toRegistry([$proxy, $exact]);

        self::assertSame($exact, $registry->match('GET', '/manifest')?->route->operation);
    }

    public function testPrefersDirectProxyOverMoreDistantSubclass(): void
    {
        $distant = new ProxiedDerivedManifestOperation();
        $direct = new ProxiedManifestOperation();
        $registry = $this->manifest()->toRegistry([$distant, $direct]);

        self::assertSame($direct, $registry->match('GET', '/manifest')?->route->operation);
    }

    public function testRejectsUnrelatedOperationInstance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HTTP manifest requires operation definition instances.');

        $this->manifest()->toRegistry([new UnrelatedManifestOperation()]);
    }

    private function manifest(): HttpOperationManifest
    {
        $routes = ['GET' => ['/manifest' => 'manifest.show']];

        return new HttpOperationManifest(
            $routes,
            [
                'manifest.show' => [
                    'definition' => ManifestOperation::class,
                    'value' => ManifestValue::class,
                    'handler' => ManifestOperation::class,
                    'outcome' => EmptyOutcome::class,
                    'strategy' => Inline::class,
                    'ephemeral' => false,
                ],
            ],
            new FastRouteDispatcherDataCompiler()->compile($routes),
        );
    }
}

class ManifestOperation implements Operation {}

final class ProxiedManifestOperation extends ManifestOperation {}

class DerivedManifestOperation extends ManifestOperation {}

final class ProxiedDerivedManifestOperation extends DerivedManifestOperation {}

final class UnrelatedManifestOperation implements Operation {}

final readonly class ManifestValue implements OperationValue {}
