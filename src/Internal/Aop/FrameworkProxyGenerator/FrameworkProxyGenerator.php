<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyGenerator;

use BlackOps\Internal\Aop\FrameworkProxyArtifact\FrameworkProxyArtifactBuilder;
use BlackOps\Internal\Aop\FrameworkProxyArtifact\FrameworkProxyArtifactDiagnosticCode;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyContract;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyOwnershipGuard;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use InvalidArgumentException;
use ReflectionClass;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class FrameworkProxyGenerator
{
    public const GENERATOR_VERSION = 'framework-proxy-generator-1';

    public function __construct(
        private FrameworkProxyContract $contract = new FrameworkProxyContract(),
        private FrameworkProxyOwnershipGuard $ownership = new FrameworkProxyOwnershipGuard(),
        private FrameworkProxySourceEmitter $emitter = new FrameworkProxySourceEmitter(),
        private FrameworkProxyArtifactBuilder $artifacts = new FrameworkProxyArtifactBuilder(),
    ) {}

    /**
     * @mago-expect lint:excessive-parameter-list
     *
     * @param class-string|ReflectionClass<object> $sourceClass
     * @param array<string,true>|list<string> $connectionNames
     */
    public function generate(
        string|ReflectionClass $sourceClass,
        string $buildId,
        string $outputDirectory,
        string|FrameworkProxyProfile $profile = FrameworkProxyProfile::FRAMEWORK,
        ?string $serviceId = null,
        ?string $defaultConnection = null,
        array $connectionNames = [],
    ): FrameworkProxyGenerationResult {
        return $this->generateBatch(
            [new FrameworkProxyGenerationTarget($sourceClass, $serviceId, $defaultConnection, $connectionNames)],
            $buildId,
            $outputDirectory,
            $profile,
        );
    }

    /**
     * @param list<FrameworkProxyGenerationTarget|class-string|ReflectionClass<object>> $sourceClasses
     * @param array<string,string> $inputHashes
     */
    public function generateBatch(
        array $sourceClasses,
        string $buildId,
        string $outputDirectory,
        string|FrameworkProxyProfile $profile = FrameworkProxyProfile::FRAMEWORK,
        array $inputHashes = [],
    ): FrameworkProxyGenerationResult {
        $this->ownership->assertProfile($profile, FrameworkProxyProfile::FRAMEWORK);
        ksort($inputHashes);
        foreach ($inputHashes as $name => $hash) {
            if (!is_string($name) || !is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
                throw new InvalidArgumentException(FrameworkProxyArtifactDiagnosticCode::INPUT_INVALID);
            }
        }
        $targets = [];
        $seenSources = [];
        foreach ($sourceClasses as $target) {
            $target = $target instanceof FrameworkProxyGenerationTarget
                ? $target
                : new FrameworkProxyGenerationTarget($target);
            $sourceClass = $target->sourceClass;
            try {
                $reflection = $sourceClass instanceof ReflectionClass
                    ? $sourceClass
                    : new ReflectionClass($sourceClass);
            } catch (\Throwable) {
                throw new InvalidArgumentException(FrameworkProxyArtifactDiagnosticCode::SOURCE_UNAVAILABLE);
            }
            if (isset($seenSources[$reflection->getName()])) {
                throw new InvalidArgumentException(FrameworkProxyArtifactDiagnosticCode::DUPLICATE_IDENTITY);
            }
            $seenSources[$reflection->getName()] = true;
            $metadata = $this->contract->inspect(
                $reflection,
                $profile,
                $target->serviceId,
                $buildId,
                $target->defaultConnection,
                $target->connectionNames,
            );
            if (!$metadata->proxyTarget) {
                continue;
            }
            if ($reflection->hasMethod('__blackopsInitialize')) {
                throw new InvalidArgumentException(FrameworkProxyArtifactDiagnosticCode::GENERATED_MEMBER_COLLISION);
            }
            $sourcePath = $reflection->getFileName();
            if (!is_string($sourcePath) || !is_file($sourcePath))
                throw new InvalidArgumentException(FrameworkProxyArtifactDiagnosticCode::SOURCE_UNAVAILABLE);
            $sourceHash = hash_file('sha256', $sourcePath);
            if (!is_string($sourceHash))
                throw new InvalidArgumentException(FrameworkProxyArtifactDiagnosticCode::SOURCE_HASH);
            $normalizedConnections = array_is_list($target->connectionNames)
                ? array_map('strval', $target->connectionNames)
                : array_keys($target->connectionNames);
            $normalizedConnections = array_values(array_unique($normalizedConnections));
            sort($normalizedConnections);
            $targets[] = [
                $reflection,
                $metadata,
                $sourceHash,
                $target->serviceId,
                $target->defaultConnection,
                $normalizedConnections,
            ];
        }
        usort($targets, static fn(array $left, array $right): int => strcmp($left[0]->getName(), $right[0]->getName()));
        $sources = [];
        $sourceInputs = [];
        $contexts = [];
        foreach ($targets as [$reflection, $metadata, $sourceHash, $service, $default, $connections]) {
            $signatureHash = hash(
                'sha256',
                (string) json_encode(
                    array_map(static fn($method): string => $method->signature, $metadata->methods),
                    JSON_THROW_ON_ERROR,
                ),
            );
            $metadataHash = hash(
                'sha256',
                (string) json_encode([
                    'class' => $metadata->sourceClass,
                    'ownership' => $metadata->ownership->value,
                    'methods' => array_map(static fn($method): array => [
                        $method->name,
                        $method->signature,
                        $method->transactionalConnection,
                        $method->transactional,
                        $method->afterCommit,
                    ], $metadata->methods),
                ], JSON_THROW_ON_ERROR),
            );
            $sources[$reflection->getName()] = [$sourceHash, $signatureHash, $metadataHash];
            $sourceInputs[$reflection->getName()] = $sourceHash;
            foreach ($metadata->methods as $method) {
                try {
                    $declaringPath = $reflection->getMethod($method->name)->getFileName();
                } catch (\Throwable) {
                    throw new InvalidArgumentException(FrameworkProxyArtifactDiagnosticCode::SOURCE_UNAVAILABLE);
                }
                if (!is_string($declaringPath) || !is_file($declaringPath)) {
                    throw new InvalidArgumentException(FrameworkProxyArtifactDiagnosticCode::SOURCE_UNAVAILABLE);
                }
                $declaringHash = hash_file('sha256', $declaringPath);
                if (!is_string($declaringHash)) {
                    throw new InvalidArgumentException(FrameworkProxyArtifactDiagnosticCode::SOURCE_HASH);
                }
                $sourceInputs[$method->declaringClass . '::' . $method->name] = $declaringHash;
            }
            $contexts['context.' . hash('sha256', $reflection->getName())] = hash(
                'sha256',
                (string) json_encode([
                    'source_class' => $reflection->getName(),
                    'service_id' => $service,
                    'default_connection' => $default,
                    'connection_names' => $connections,
                ], JSON_THROW_ON_ERROR),
            );
        }
        $batchHash = FrameworkProxyArtifactBuilder::inputHash(
            $buildId,
            FrameworkProxyProfile::from($profile),
            self::GENERATOR_VERSION,
            $inputHashes,
            $sources,
            $contexts,
            $sourceInputs,
        );
        $items = [];
        foreach ($targets as [$reflection, $metadata]) {
            $proxy = $this->proxyClass($reflection, $batchHash);
            try {
                $sourceCode = $this->emitter->emit($reflection, $metadata, $proxy);
            } catch (\Throwable) {
                throw new InvalidArgumentException(FrameworkProxyArtifactDiagnosticCode::GENERATION_INVALID);
            }
            $items[] = [$reflection, $metadata, $proxy, $sourceCode];
        }
        return $this->artifacts->publishBatch(
            $outputDirectory,
            $buildId,
            FrameworkProxyProfile::from($profile),
            self::GENERATOR_VERSION,
            $items,
            $inputHashes,
            $contexts,
        );
    }

    private function proxyClass(ReflectionClass $source, string $batchHash): string
    {
        $namespace = $source->getNamespaceName();
        $short = '__BlackOpsProxy_' . substr(hash('sha256', $source->getName() . ':' . $batchHash), 0, 48);
        return $namespace === '' ? $short : $namespace . '\\' . $short;
    }
}
