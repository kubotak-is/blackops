<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\ProxyProfileArtifact;

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;

/** Immutable identity and inventory for one compiled proxy-profile unit. */
final readonly class ProxyProfileArtifactManifest
{
    /** @param array<string,string> $files */
    public function __construct(
        public int $schemaVersion,
        public string $applicationBuildId,
        public FrameworkProxyProfile $profile,
        public string $contentHash,
        public array $files = [],
        public ?string $frameworkDirectory = null,
        public ?string $frameworkManifestHash = null,
    ) {}

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'application_build_id' => $this->applicationBuildId,
            'profile' => $this->profile->value,
            'files' => $this->files,
            'framework_directory' => $this->frameworkDirectory,
            'framework_manifest_hash' => $this->frameworkManifestHash,
        ];
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->payload() + ['content_hash' => $this->contentHash];
    }

    public function canonicalHash(): string
    {
        return hash('sha256', json_encode(
            $this->payload(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
    }
}
