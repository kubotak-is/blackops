<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Discovery;

use BlackOps\Internal\Discovery\PhpTokenClassScanner;
use InvalidArgumentException;
use ParseError;
use PHPUnit\Framework\TestCase;

final class PhpTokenClassScannerTest extends TestCase
{
    public function testFindsNamedClassesWithoutUsingFileOrNamespaceConventions(): void
    {
        $path = $this->path('unrelated-name');
        file_put_contents($path, <<<'PHP'
            <?php

            namespace Example\First {
                interface ExampleInterface {}
                trait ExampleTrait {}
                enum ExampleEnum { case Value; }
                abstract class FirstClass {}
                new class {};
                FirstClass::class;
            }

            namespace Example\Second {
                final class SecondClass {}
            }
            PHP);

        self::assertSame(
            ['Example\\First\\FirstClass', 'Example\\Second\\SecondClass'],
            new PhpTokenClassScanner()->scan($path),
        );
    }

    public function testRejectsUnreadableOrMissingSource(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PhpTokenClassScanner()->scan($this->path('missing'));
    }

    public function testInvalidPhpSyntaxFailsFast(): void
    {
        $path = $this->path('invalid');
        file_put_contents($path, '<?php final class');

        $this->expectException(ParseError::class);

        new PhpTokenClassScanner()->scan($path);
    }

    private function path(string $name): string
    {
        return sys_get_temp_dir() . '/blackops-token-scan-' . $name . '-' . bin2hex(random_bytes(8)) . '.php';
    }
}
