<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime;

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Aop\ProxyProfileArtifact\ProxyProfileArtifactManifest;
use InvalidArgumentException;

/** Validates a complete profile unit before loading any generated PHP. */
final readonly class ProxyProfileArtifactLoader
{
    public function __construct(
        private FrameworkProxyArtifactLoader $framework = new FrameworkProxyArtifactLoader(),
    ) {}

    public function load(
        string $unitDirectory,
        string $expectedBuildId,
        string $expectedManifestHash,
        string|FrameworkProxyProfile $expectedProfile,
    ): ProxyProfileArtifactManifest {
        $profile = FrameworkProxyProfile::from($expectedProfile);
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
            || !$manifest->profile->equals($profile)
            || $manifest->canonicalHash() !== $manifest->contentHash
            || basename($unitDirectory) !== $expectedBuildId . '-' . $manifest->contentHash
        ) {
            throw new InvalidArgumentException('Proxy profile artifact identity is invalid.');
        }
        if ($expectedManifestHash !== $manifest->contentHash) {
            throw new InvalidArgumentException('Proxy profile artifact hash is invalid.');
        }
        if ($profile->equals(FrameworkProxyProfile::RAY)) {
            $this->validateRay($unitDirectory, $manifest);
            foreach ($manifest->files as $relative => $hash) {
                $this->validateIdentities(
                    $unitDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative),
                );
            }
            foreach ($manifest->files as $relative => $hash) {
                $file = $unitDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if ($this->hasUndeclaredIdentity($file)) {
                    require_once $file;
                }
                $this->validateIdentities($file);
            }
        } else {
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
                ) {
                    throw new InvalidArgumentException('Framework proxy artifact directory is invalid.');
                }
                if ($manifest->frameworkManifestHash === null) {
                    throw new InvalidArgumentException('Framework proxy artifact hash is invalid.');
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
                $this->framework->load($directory, $expectedBuildId, $manifest->frameworkManifestHash, $profile);
            } elseif ($manifest->frameworkManifestHash !== null || $expectedManifestHash !== $manifest->contentHash) {
                throw new InvalidArgumentException('Framework proxy artifact identity is invalid.');
            }
        }
        return $manifest;
    }

    private function validateIdentities(string $file): void
    {
        $identities = $this->declaredIdentities($file);
        if ($identities === []) {
            throw new InvalidArgumentException('Ray proxy artifact class identity is missing.');
        }
        foreach ($identities as $identity) {
            if ($this->isLoaded($identity)) {
                $reflection = new \ReflectionClass($identity);
                $loadedPath = realpath((string) $reflection->getFileName());
                if ($loadedPath !== realpath($file)) {
                    throw new InvalidArgumentException('Ray proxy artifact class identity collides.');
                }
            }
        }
    }

    private function hasUndeclaredIdentity(string $file): bool
    {
        foreach ($this->declaredIdentities($file) as $identity) {
            if (!$this->isLoaded($identity)) {
                return true;
            }
        }
        return false;
    }

    /** @return list<class-string> */
    private function declaredIdentities(string $file): array
    {
        try {
            $tokens = token_get_all((string) file_get_contents($file), TOKEN_PARSE);
        } catch (\ParseError) {
            throw new InvalidArgumentException('Ray proxy artifact syntax is invalid.');
        }
        $namespace = '';
        $declared = [];
        $previousSignificant = null;
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $namespace = '';
                for ($index++; $index < $count; $index++) {
                    $part = $tokens[$index];
                    if (is_string($part) && $part === ';')
                        break;
                    if (is_array($part) && in_array($part[0], [T_STRING, T_NAME_QUALIFIED], true))
                        $namespace .= $part[1];
                }
            }
            if (
                is_array($token)
                && in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)
                && !in_array($previousSignificant, [T_NEW, T_DOUBLE_COLON], true)
            ) {
                for ($probe = $index + 1; $probe < $count; $probe++) {
                    $name = $tokens[$probe];
                    if (is_string($name) && $name === '{')
                        break;
                    if (is_array($name) && $name[0] === T_STRING) {
                        $declared[] = ltrim($namespace . '\\' . $name[1], '\\');
                        break;
                    }
                }
            }
            $previousSignificant = is_array($token) ? $token[0] : $token;
        }
        return $declared;
    }

    private function isLoaded(string $identity): bool
    {
        return (
            class_exists($identity, false)
            || interface_exists($identity, false)
            || trait_exists($identity, false)
            || enum_exists($identity, false)
        );
    }

    private function validateRay(string $unitDirectory, ProxyProfileArtifactManifest $manifest): void
    {
        $expected = ['manifest.json'];
        if ($manifest->files !== []) {
            $expected[] = 'aop';
        }
        $entries = array_values(array_filter(
            scandir($unitDirectory) ?: [],
            static fn(string $entry): bool => $entry !== '.' && $entry !== '..',
        ));
        sort($entries);
        $sortedExpected = $expected;
        sort($sortedExpected);
        if ($entries !== $sortedExpected) {
            throw new InvalidArgumentException('Ray proxy artifact inventory is invalid.');
        }
        $aopEntries = [];
        if ($manifest->files !== []) {
            if (
                !is_dir($unitDirectory . DIRECTORY_SEPARATOR . 'aop')
                || is_link($unitDirectory . DIRECTORY_SEPARATOR . 'aop')
            ) {
                throw new InvalidArgumentException('Ray proxy artifact inventory is invalid.');
            }
            $aopEntries = array_values(array_filter(
                scandir($unitDirectory . DIRECTORY_SEPARATOR . 'aop') ?: [],
                static fn(string $entry): bool => $entry !== '.' && $entry !== '..',
            ));
            sort($aopEntries);
            $expectedAop = array_map(static fn(string $path): string => basename($path), array_keys($manifest->files));
            sort($expectedAop);
            if ($aopEntries !== $expectedAop) {
                throw new InvalidArgumentException('Ray proxy artifact inventory is invalid.');
            }
        }
        foreach ($manifest->files as $relative => $hash) {
            if (
                $relative === ''
                || str_contains($relative, '\\')
                || str_contains($relative, '..')
                || str_starts_with($relative, '/')
                || !str_starts_with($relative, 'aop/')
            ) {
                throw new InvalidArgumentException('Ray proxy artifact inventory is invalid.');
            }
            $file = $unitDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $actualHash = is_file($file) && !is_link($file) ? hash_file('sha256', $file) : false;
            if (!is_string($actualHash) || $actualHash !== $hash) {
                throw new InvalidArgumentException('Ray proxy artifact file integrity is invalid.');
            }
        }
    }

    /** @param array<string,mixed> $data */
    private function manifest(array $data): ProxyProfileArtifactManifest
    {
        $filesValue = $data['files'] ?? [];
        if (!is_array($filesValue)) {
            throw new InvalidArgumentException('Proxy profile artifact inventory is invalid.');
        }
        /** @var array<string,mixed> $files */
        $files = $filesValue;
        if (
            !is_int($data['schema_version'] ?? null)
            || !is_string($data['application_build_id'] ?? null)
            || preg_match('/^[A-Za-z0-9._-]+$/D', $data['application_build_id']) !== 1
            || !is_string($data['content_hash'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $data['content_hash']) !== 1
            || !is_string($data['profile'] ?? null)
            || !in_array($data['profile'], [FrameworkProxyProfile::RAY, FrameworkProxyProfile::FRAMEWORK], true)
            || $data['framework_directory'] !== null && !is_string($data['framework_directory'])
            || $data['framework_manifest_hash'] !== null
            && (
                !is_string($data['framework_manifest_hash'])
                || preg_match('/^[a-f0-9]{64}$/D', $data['framework_manifest_hash']) !== 1
            )
        ) {
            throw new InvalidArgumentException('Proxy profile artifact manifest is invalid.');
        }
        $normalized = [];
        foreach ($files as $path => $hash) {
            if (!is_string($hash)) {
                throw new InvalidArgumentException('Proxy profile artifact inventory is invalid.');
            }
            if (preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
                throw new InvalidArgumentException('Proxy profile artifact inventory is invalid.');
            }
            $normalized[$path] = $hash;
        }
        ksort($normalized);
        $schemaVersion = $data['schema_version'];
        $buildId = $data['application_build_id'];
        $profile = $data['profile'];
        $contentHash = $data['content_hash'];
        $frameworkDirectory = $data['framework_directory'];
        $frameworkManifestHash = $data['framework_manifest_hash'];
        return new ProxyProfileArtifactManifest(
            $schemaVersion,
            $buildId,
            FrameworkProxyProfile::from($profile),
            $contentHash,
            $normalized,
            $frameworkDirectory,
            $frameworkManifestHash,
        );
    }
}
