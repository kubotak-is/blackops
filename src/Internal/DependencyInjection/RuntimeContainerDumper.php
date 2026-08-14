<?php

declare(strict_types=1);

namespace BlackOps\Internal\DependencyInjection;

use BlackOps\Internal\Aop\FrameworkProxyArtifact\FrameworkProxyArtifactManifest;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Aop\ProxyProfileArtifact\ProxyProfileArtifactManifest;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

final readonly class RuntimeContainerDumper
{
    public function dump(
        ContainerBuilder $builder,
        string $path,
        string $class,
        string $namespace = '',
        string|FrameworkProxyProfile $profile = FrameworkProxyProfile::FRAMEWORK,
        ?FrameworkProxyArtifactManifest $frameworkManifest = null,
        ?string $frameworkArtifactDirectory = null,
        ?ProxyProfileArtifactManifest $profileArtifact = null,
        ?string $profileArtifactDirectory = null,
    ): void {
        $this->assertIdentifier($class, 'container class');

        if ($namespace !== '') {
            foreach (explode('\\', $namespace) as $part) {
                $this->assertIdentifier($part, 'container namespace');
            }
        }

        $directory = dirname($path);

        if (!is_dir($directory)) {
            throw new InvalidArgumentException('Runtime container dump directory does not exist.');
        }

        $source = new PhpDumper($builder)->dump([
            'class' => $class,
            'namespace' => $namespace,
            'as_files' => false,
        ]);

        if (!is_string($source)) {
            throw new RuntimeException('Runtime container dump must be a single PHP file.');
        }

        $profile = FrameworkProxyProfile::from($profile);
        if (!$profile->equals(FrameworkProxyProfile::FRAMEWORK)) {
            throw new InvalidArgumentException('Runtime container requires the Framework proxy profile.');
        }
        if (($profileArtifact === null) !== ($profileArtifactDirectory === null)) {
            throw new InvalidArgumentException('Runtime proxy profile artifact manifest and directory must be paired.');
        }
        if ($profileArtifact !== null) {
            if (
                $frameworkManifest !== null
                || $frameworkArtifactDirectory !== null
                || !$profileArtifact->profile->equals($profile)
            ) {
                throw new InvalidArgumentException('Runtime container cannot mix proxy profile artifact inputs.');
            }
            $source .= $this->profileArtifactSource($profileArtifact, $profileArtifactDirectory, $directory);
            $this->write($source, $directory, $path);
            return;
        }
        if (($frameworkManifest === null) !== ($frameworkArtifactDirectory === null)) {
            throw new InvalidArgumentException('Runtime container cannot mix or omit Framework proxy artifacts.');
        }
        $source .= $this->frameworkFileSource($frameworkManifest, $frameworkArtifactDirectory, $directory);

        $this->write($source, $directory, $path);
    }

    private function profileArtifactSource(
        ProxyProfileArtifactManifest $manifest,
        ?string $artifactDirectory,
        string $containerDirectory,
    ): string {
        if (
            $artifactDirectory === null
            || !is_dir($artifactDirectory)
            || basename($artifactDirectory) !== $manifest->applicationBuildId . '-' . $manifest->contentHash
        ) {
            throw new InvalidArgumentException('Runtime proxy profile artifact is invalid.');
        }
        if (dirname($artifactDirectory) !== $containerDirectory . DIRECTORY_SEPARATOR . 'proxy-profiles') {
            throw new InvalidArgumentException('Runtime proxy profile artifact location is invalid.');
        }
        return sprintf(
            "\n(new \\BlackOps\\Internal\\Runtime\\ProxyProfileArtifactLoader())->load(__DIR__ . '/proxy-profiles/%s', %s, %s);\n",
            basename($artifactDirectory),
            var_export($manifest->applicationBuildId, true),
            var_export($manifest->contentHash, true),
        );
    }

    private function frameworkFileSource(
        ?FrameworkProxyArtifactManifest $manifest,
        ?string $artifactDirectory,
        string $containerDirectory,
    ): string {
        if ($manifest === null) {
            return '';
        }
        if ($manifest->profile->value !== FrameworkProxyProfile::FRAMEWORK || $manifest->files === []) {
            throw new InvalidArgumentException('Runtime framework proxy artifact is invalid.');
        }
        if (
            $artifactDirectory === null
            || !is_dir($artifactDirectory)
            || basename($artifactDirectory) !== $manifest->applicationBuildId . '-' . $manifest->inputHash
        ) {
            throw new InvalidArgumentException('Runtime framework proxy artifact is invalid.');
        }
        $root = realpath($artifactDirectory);
        if ($root === false || realpath($containerDirectory) === false) {
            throw new InvalidArgumentException('Runtime framework proxy artifact is invalid.');
        }
        if (dirname($artifactDirectory) !== $containerDirectory . DIRECTORY_SEPARATOR . 'framework-proxies') {
            throw new InvalidArgumentException('Runtime framework proxy artifact location is invalid.');
        }
        $relativeRoot = 'framework-proxies/' . basename($artifactDirectory);
        return sprintf(
            "\n(new \\BlackOps\\Internal\\Runtime\\FrameworkProxyProfileLoader())->load(__DIR__ . '/%s', %s, %s);\n",
            $relativeRoot,
            var_export($manifest->applicationBuildId, true),
            var_export($manifest->manifestHash, true),
        );
    }

    private function write(string $source, string $directory, string $path): void
    {
        $temporary = $directory . DIRECTORY_SEPARATOR . 'container-' . bin2hex(random_bytes(16)) . '.tmp';
        $written = file_put_contents($temporary, $source, LOCK_EX);

        if ($written === false || $written !== strlen($source)) {
            throw new RuntimeException('Runtime container dump could not be written.');
        }

        try {
            if (!rename($temporary, $path)) {
                throw new RuntimeException('Runtime container dump could not be moved into place.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function assertIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException('Runtime ' . $label . ' is invalid.');
        }
    }
}
