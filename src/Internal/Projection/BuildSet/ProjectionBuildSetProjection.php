<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\BuildSet;

final readonly class ProjectionBuildSetProjection
{
    /**
     * @param array<int, array<string, mixed>> $records
     */
    public function __construct(
        public string $kind,
        public string $applicationBuildId,
        public array $records,
        public string $inputHash = '',
        public string $bytes = '',
        public string $sha256 = '',
        public string $path = '',
    ) {}

    /**
     * @param array<int, array<string, mixed>> $records
     */
    public static function fromRecords(
        string $kind,
        string $applicationBuildId,
        array $records,
        ?ProjectionBuildSetCodec $codec = null,
    ): self {
        return ($codec ?? new ProjectionBuildSetCodec())->projection(
            kind: $kind,
            applicationBuildId: $applicationBuildId,
            records: $records,
        );
    }

    /**
     * @return array{schemaVersion: int, kind: string, applicationBuildId: string, inputHash: string, records: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'schemaVersion' => 1,
            'kind' => $this->kind,
            'applicationBuildId' => $this->applicationBuildId,
            'inputHash' => $this->inputHash,
            'records' => $this->records,
        ];
    }
}
