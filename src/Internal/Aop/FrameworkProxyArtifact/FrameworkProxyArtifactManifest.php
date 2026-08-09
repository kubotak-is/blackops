<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyArtifact;

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;

/**
 * @phpstan-type ProxyEntry array{
 *     source_class: string,
 *     source_path: null,
 *     source_hash: string,
 *     proxy_class: string,
 *     path: string,
 *     hash: string,
 *     signature_hash: string,
 *     metadata_hash: string,
 * }
 */
final readonly class FrameworkProxyArtifactManifest
{
    /**
     * @param list<ProxyEntry> $proxies
     * @param array<string,string> $files
     * @param array<string,string> $classMap
     * @param array<string,string> $sourceInputs
     */
    /** @mago-expect lint:excessive-parameter-list */
    public function __construct(
        public int $schemaVersion,
        public string $applicationBuildId,
        public FrameworkProxyProfile $profile,
        public string $generatorVersion,
        public string $phpVersion,
        public string $inputHash,
        /** @var list<ProxyEntry> */
        public array $proxies,
        /** @var array<string,string> */
        public array $files,
        /** @var array<string,string> */
        public array $classMap,
        /** @var array<string,string> */
        public array $sourceInputs,
        public string $manifestHash,
        public string $abiVersion = 'framework-proxy-invocation-1',
        public string $initializer = '__blackopsInitialize',
    ) {}

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'application_build_id' => $this->applicationBuildId,
            'profile' => $this->profile->value,
            'generator_version' => $this->generatorVersion,
            'php_version' => $this->phpVersion,
            'input_hash' => $this->inputHash,
            'proxies' => $this->proxies,
            'files' => $this->files,
            'class_map' => $this->classMap,
            'source_inputs' => $this->sourceInputs,
            'abi_version' => $this->abiVersion,
            'initializer' => $this->initializer,
        ];
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->payload() + ['manifest_hash' => $this->manifestHash];
    }

    public function canonicalHash(): string
    {
        return hash(
            'sha256',
            (string) json_encode(
                $this->payload(),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ),
        );
    }
}
