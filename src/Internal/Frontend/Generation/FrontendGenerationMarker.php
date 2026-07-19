<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend\Generation;

use InvalidArgumentException;
use JsonException;

final readonly class FrontendGenerationMarker
{
    public const SCHEMA_VERSION = 4;

    public function __construct(
        public string $applicationBuildId,
        public string $contractHash,
    ) {
        if (trim($applicationBuildId) === '' || preg_match('/^[a-f0-9]{64}$/D', $contractHash) !== 1) {
            throw new InvalidArgumentException('Generated frontend marker is invalid.');
        }
    }

    public function encode(): string
    {
        try {
            return json_encode(
                [
                    'schemaVersion' => self::SCHEMA_VERSION,
                    'applicationBuildId' => $this->applicationBuildId,
                    'contractHash' => $this->contractHash,
                ],
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ) . "\n";
        } catch (JsonException) {
            throw new InvalidArgumentException('Generated frontend marker could not be encoded.');
        }
    }

    public static function decode(string $contents): self
    {
        return self::decodeVersioned($contents, [self::SCHEMA_VERSION]);
    }

    public static function decodeOwned(string $contents): self
    {
        return self::decodeVersioned($contents, [1, 2, 3, self::SCHEMA_VERSION]);
    }

    /** @param non-empty-list<int> $schemaVersions */
    private static function decodeVersioned(string $contents, array $schemaVersions): self
    {
        try {
            $data = self::arrayFromDecoded(json_decode($contents, associative: true, flags: JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            throw new InvalidArgumentException('Generated frontend marker is invalid.');
        }

        if (
            !in_array($data['schemaVersion'] ?? null, $schemaVersions, strict: true)
            || !is_string($data['applicationBuildId'] ?? null)
            || !is_string($data['contractHash'] ?? null)
        ) {
            throw new InvalidArgumentException('Generated frontend marker is invalid.');
        }

        return new self($data['applicationBuildId'], $data['contractHash']);
    }

    /** @return array<array-key, mixed> */
    private static function arrayFromDecoded(mixed $data): array
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('Generated frontend marker is invalid.');
        }

        return $data;
    }
}
