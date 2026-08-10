<?php

declare(strict_types=1);

require dirname(__DIR__, 4) . '/vendor/autoload.php';
require __DIR__ . '/FrameworkProxyCompatibilityTest.php';

$root = $argv[1] ?? '';
if ($root === '') {
    fwrite(STDERR, "build root missing\n");
    exit(2);
}

$configuration = new \BlackOps\Internal\Application\ApplicationConfigurationSnapshot(
    dirname(__DIR__, 4),
    [
        'app' => ['build' => [
            'operation_manifest' => $root . '/operation.php',
            'http_manifest' => $root . '/http.php',
            'frontend_manifest' => $root . '/frontend.php',
            'container' => $root . '/container.php',
            'container_class' => 'RayNeverContainer' . bin2hex(random_bytes(4)),
            'container_namespace' => 'BlackOps\\Tests\\Fixtures\\Aop\\FrameworkProxyCompatibility\\RayNever',
            'application_build_id' => 'compatibility-ray-never',
        ]],
        'database' => [
            'default' => 'app',
            'connections' => ['app' => ['driver' => 'pdo_pgsql']],
            'framework' => ['connection' => 'app', 'schema' => 'blackops'],
        ],
    ],
    [],
    [\BlackOps\Tests\Internal\Aop\FrameworkProxyCompatibility\NeverSignatureServiceProvider::class],
    [],
);

new \Symfony\Component\Console\Tester\CommandTester(
    new \BlackOps\Internal\Console\ApplicationBuildCompileCommand($configuration),
)->execute(['--proxy-profile' => \BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile::RAY]);
