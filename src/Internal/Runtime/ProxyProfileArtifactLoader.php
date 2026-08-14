<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime;

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Aop\ProxyProfileArtifact\ProxyProfileArtifactManifest;
use InvalidArgumentException;

/** Validates a complete Framework profile unit before loading generated PHP. */
final readonly class ProxyProfileArtifactLoader
{
    public function __construct(
        private FrameworkProxyArtifactLoader $framework = new FrameworkProxyArtifactLoader(),
    ) {}

    public function load(
        string $unitDirectory,
        string $expectedBuildId,
        string $expectedManifestHash,
    ): ProxyProfileArtifactManifest {
        if (!is_dir($unitDirectory) || is_link($unitDirectory)) {
            throw new InvalidArgumentException('Proxy profile artifact unit is invalid.');
        }
        $manifestPath = $unitDirectory . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!is_file($manifestPath) || is_link($manifestPath)) {
            throw new InvalidArgumentException('Proxy profile artifact manifest is invalid.');
        }
        $decoded = json_decode((string) @file_get_contents($manifestPath), true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Proxy profile artifact manifest is invalid.');
        }
        /** @var array<string,mixed> $data */
        $data = $decoded;
        $keys = array_keys($data);
        sort($keys);
        if (
            $keys !== [
                'application_build_id',
                'content_hash',
                'files',
                'framework_directory',
                'framework_manifest_hash',
                'profile',
                'schema_version',
            ]
        ) {
            throw new InvalidArgumentException('Proxy profile artifact manifest is invalid.');
        }
        $manifest = $this->manifest($data);
        if ($decoded !== $manifest->toArray()) {
            throw new InvalidArgumentException('Proxy profile artifact manifest is invalid.');
        }
        if (
            $manifest->schemaVersion !== 1
            || $manifest->applicationBuildId !== $expectedBuildId
            || !$manifest->profile->equals(FrameworkProxyProfile::FRAMEWORK)
            || $manifest->canonicalHash() !== $manifest->contentHash
            || basename($unitDirectory) !== $expectedBuildId . '-' . $manifest->contentHash
            || $expectedManifestHash !== $manifest->contentHash
        ) {
            throw new InvalidArgumentException('Proxy profile artifact identity is invalid.');
        }

        $entries = array_values(array_filter(
            scandir($unitDirectory) ?: [],
            static fn(string $entry): bool => $entry !== '.' && $entry !== '..',
        ));
        if ($entries !== ['manifest.json']) {
            throw new InvalidArgumentException('Framework proxy artifact inventory is invalid.');
        }
        if ($manifest->frameworkDirectory !== null) {
            if (
                preg_match('#^\.\./framework-proxies/([A-Za-z0-9._-]+)$#D', $manifest->frameworkDirectory, $matches)
                    !== 1
                || !str_starts_with($matches[1], $expectedBuildId . '-')
                || preg_match('/^[a-f0-9]{64}$/D', substr($matches[1], strlen($expectedBuildId) + 1)) !== 1
                || $manifest->frameworkManifestHash === null
            ) {
                throw new InvalidArgumentException('Framework proxy artifact directory is invalid.');
            }
            $frameworkRootPath = dirname($unitDirectory) . DIRECTORY_SEPARATOR . '../framework-proxies';
            if (is_link($frameworkRootPath)) {
                throw new InvalidArgumentException('Framework proxy artifact directory is invalid.');
            }
            $frameworkRoot = realpath($frameworkRootPath);
            $directory = realpath(dirname($unitDirectory) . DIRECTORY_SEPARATOR . $manifest->frameworkDirectory);
            if ($frameworkRoot === false || $directory === false || dirname($directory) !== $frameworkRoot) {
                throw new InvalidArgumentException('Framework proxy artifact directory is invalid.');
            }
            $this->framework->load($directory, $expectedBuildId, $manifest->frameworkManifestHash);
        } elseif ($manifest->frameworkManifestHash !== null) {
            throw new InvalidArgumentException('Proxy profile artifact identity is invalid.');
        }

        return $manifest;
    }

    /** @param array<string,mixed> $data */
    private function manifest(array $data): ProxyProfileArtifactManifest
    {
        $files = $data['files'] ?? null;
        if (
            !is_int($data['schema_version'] ?? null)
            || !is_string($data['application_build_id'] ?? null)
            || preg_match('/^[A-Za-z0-9._-]+$/D', $data['application_build_id']) !== 1
            || !is_string($data['content_hash'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $data['content_hash']) !== 1
            || $data['profile'] !== FrameworkProxyProfile::FRAMEWORK
            || !is_array($files)
            || $files !== []
            || $data['framework_directory'] !== null && !is_string($data['framework_directory'])
            || $data['framework_manifest_hash'] !== null
            && (
                !is_string($data['framework_manifest_hash'])
                || preg_match('/^[a-f0-9]{64}$/D', $data['framework_manifest_hash']) !== 1
            )
        ) {
            throw new InvalidArgumentException('Proxy profile artifact manifest is invalid.');
        }

        return new ProxyProfileArtifactManifest(
            $data['schema_version'],
            $data['application_build_id'],
            FrameworkProxyProfile::framework(),
            $data['content_hash'],
            [],
            $data['framework_directory'],
            $data['framework_manifest_hash'],
        );
    }
}
