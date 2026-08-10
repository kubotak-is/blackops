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
            'container_class' => 'RayNamedContainer' . bin2hex(random_bytes(4)),
            'container_namespace' => 'BlackOps\\Tests\\Fixtures\\Aop\\FrameworkProxyCompatibility\\RayNamed',
            'application_build_id' => 'compatibility-ray-named',
        ]],
        'database' => [
            'default' => 'app',
            'connections' => ['app' => ['driver' => 'pdo_pgsql']],
            'framework' => ['connection' => 'app', 'schema' => 'blackops'],
        ],
    ],
    [],
    [\BlackOps\Tests\Internal\Aop\FrameworkProxyCompatibility\SignatureMatrixServiceProvider::class],
    [],
);
new \Symfony\Component\Console\Tester\CommandTester(
    new \BlackOps\Internal\Console\ApplicationBuildCompileCommand($configuration),
)->execute(['--proxy-profile' => \BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile::RAY]);

$class =
    'BlackOps\\Tests\\Fixtures\\Aop\\FrameworkProxyCompatibility\\RayNamed\\'
    . $configuration->configuration()['app']['build']['container_class'];
$process = proc_open(
    [PHP_BINARY, __DIR__ . '/runtime-runner.php', $root . '/container.php', $class, 'signature'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
);
if (!is_resource($process)) {
    exit(1);
}
$stream_set = stream_set_blocking($pipes[1], false) && stream_set_blocking($pipes[2], false);
if (!$stream_set) {
    proc_terminate($process);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    exit(1);
}
$output = '';
$started = microtime(true);
while (true) {
    $status = proc_get_status($process);
    $output .= (string) stream_get_contents($pipes[1]);
    $output .= (string) stream_get_contents($pipes[2]);
    if (!$status['running']) {
        break;
    }
    if ((microtime(true) - $started) > 15.0) {
        proc_terminate($process);
        $output .= (string) stream_get_contents($pipes[1]);
        $output .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        fwrite(STDERR, "named variadic runtime timed out\n");
        exit(124);
    }
    usleep(10_000);
}
$output .= (string) stream_get_contents($pipes[1]);
$output .= (string) stream_get_contents($pipes[2]);
fwrite(STDOUT, $output);
fclose($pipes[1]);
fclose($pipes[2]);
exit(proc_close($process));
