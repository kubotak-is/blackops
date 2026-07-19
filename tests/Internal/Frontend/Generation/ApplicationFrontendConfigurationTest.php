<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Frontend\Generation;

use BlackOps\Internal\Application\ApplicationFrontendConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ApplicationFrontendConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/blackops-frontend-config-' . bin2hex(random_bytes(8));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        if (is_link($this->root . '/linked')) {
            unlink($this->root . '/linked');
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testUsesDefaultOutputAndNormalizesExplicitApplicationPath(): void
    {
        $default = ApplicationFrontendConfiguration::fromConfiguration($this->root, []);
        self::assertSame($this->root . '/resources/js/blackops', $default->output);
        self::assertSame('resources/js/blackops', $default->relativeOutput);

        $explicit = ApplicationFrontendConfiguration::fromConfiguration($this->root, [
            'frontend' => ['output' => $this->root . '/assets/../frontend/generated'],
        ]);
        self::assertSame($this->root . '/frontend/generated', $explicit->output);
        self::assertSame('frontend/generated', $explicit->relativeOutput);
    }

    public function testRejectsRootExternalRelativeFileAndSymlinkOutputs(): void
    {
        $external = dirname($this->root) . '/outside';
        $file = $this->root . '/file';
        file_put_contents($file, 'not a directory');
        $target = sys_get_temp_dir();
        symlink($target, $this->root . '/linked');

        foreach ([
            $this->root,
            $external,
            'resources/js/blackops',
            $file . '/generated',
            $this->root . '/linked/generated',
        ] as $output) {
            try {
                ApplicationFrontendConfiguration::fromConfiguration($this->root, [
                    'frontend' => ['output' => $output],
                ]);
                self::fail('Unsafe frontend output was accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }

        unlink($this->root . '/linked');
        unlink($file);
    }
}
