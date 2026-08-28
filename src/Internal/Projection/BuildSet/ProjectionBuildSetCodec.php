<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\BuildSet;

use BlackOps\Internal\Scheduling\CronExpression;
use DateTimeZone;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class ProjectionBuildSetCodec
{
    public const SCHEMA_VERSION = 1;
    public const ROOT_MAX_BYTES = 65_536;
    public const PROJECTION_MAX_BYTES = 16_777_216;
    public const MAX_RECORDS = 4096;

    /** @var array<string, string> */
    private const KIND_BY_KEY = [
        'command' => 'blackops_command_projection',
        'route' => 'blackops_route_projection',
        'schedule' => 'blackops_schedule_projection',
    ];

    /**
     * @param array<int, mixed> $records
     */
    public function projection(string $kind, string $applicationBuildId, array $records): ProjectionBuildSetProjection
    {
        $this->validateKind($kind);
        $this->applicationBuildId($applicationBuildId);
        $normalized = $this->normalizeRecords($kind, $records, requireSorted: false);

        $inputSource = [
            'kind' => $kind,
            'schemaVersion' => self::SCHEMA_VERSION,
            'applicationBuildId' => $applicationBuildId,
            'sourceRecords' => $normalized,
        ];
        $inputHash = hash(
            'sha256',
            $this->encodeString('blackops.projection-input.v1')
                . $this->encodeBytes($this->canonicalJson($inputSource)),
        );
        $document = [
            'schemaVersion' => self::SCHEMA_VERSION,
            'kind' => $kind,
            'applicationBuildId' => $applicationBuildId,
            'inputHash' => $inputHash,
            'records' => $normalized,
        ];
        $bytes = $this->storedJson($document, self::PROJECTION_MAX_BYTES);
        $sha256 = hash('sha256', $bytes);
        $prefix = $this->prefixForKind($kind);

        return new ProjectionBuildSetProjection(
            kind: $kind,
            applicationBuildId: $applicationBuildId,
            records: $normalized,
            inputHash: $inputHash,
            bytes: $bytes,
            sha256: $sha256,
            path: $prefix . '-' . $sha256 . '.json',
        );
    }

    /**
     * @param array<int, mixed> $records
     */
    public function encodeProjection(
        string $kind,
        string $applicationBuildId,
        array $records,
    ): ProjectionBuildSetProjection {
        return $this->projection($kind, $applicationBuildId, $records);
    }

    /**
     * @param array<string, ProjectionBuildSetProjection|array<int, array<string, mixed>>> $projections
     * @return array{root: array<string, mixed>, bytes: string, buildSetHash: string, projections: array<string, ProjectionBuildSetProjection>}
     */
    public function root(string $applicationBuildId, array $projections): array
    {
        $this->applicationBuildId($applicationBuildId);
        $normalized = $this->normalizeProjections($applicationBuildId, $projections);
        $descriptors = [];

        foreach (self::KIND_BY_KEY as $key => $kind) {
            $projection = $normalized[$key];
            $descriptors[$key] = [
                'kind' => $kind,
                'inputHash' => $projection->inputHash,
                'path' => $projection->path,
                'sha256' => $projection->sha256,
            ];
        }

        $withoutHash = [
            'schemaVersion' => self::SCHEMA_VERSION,
            'kind' => 'projection_build_set',
            'applicationBuildId' => $applicationBuildId,
            'projections' => $descriptors,
        ];
        $buildSetHash = hash(
            'sha256',
            $this->encodeString('blackops.projection-build-set.v1')
                . $this->encodeBytes($this->canonicalJson($withoutHash)),
        );
        $root = $withoutHash + ['buildSetHash' => $buildSetHash];
        $bytes = $this->storedJson($root, self::ROOT_MAX_BYTES);

        return [
            'root' => $root,
            'bytes' => $bytes,
            'buildSetHash' => $buildSetHash,
            'projections' => $normalized,
        ];
    }

    /**
     * @param array<string, ProjectionBuildSetProjection|array<int, array<string, mixed>>> $projections
     * @return array{root: array<string, mixed>, bytes: string, buildSetHash: string, projections: array<string, ProjectionBuildSetProjection>}
     */
    public function encodeRoot(string $applicationBuildId, array $projections): array
    {
        return $this->root($applicationBuildId, $projections);
    }

    /**
     * @return array{schemaVersion: int, kind: string, applicationBuildId: string, buildSetHash: string, projections: array<string, array{kind: string, inputHash: string, path: string, sha256: string}>}
     */
    public function decodeRoot(string $bytes): array
    {
        $document = $this->decodeStored($bytes, self::ROOT_MAX_BYTES);
        $document = $this->validateRoot($document);

        $withoutHash = $document;
        unset($withoutHash['buildSetHash']);
        $expected = hash(
            'sha256',
            $this->encodeString('blackops.projection-build-set.v1')
                . $this->encodeBytes($this->canonicalJson($withoutHash)),
        );
        if ($document['buildSetHash'] !== $expected) {
            throw new ProjectionBuildSetValidationException('Projection build-set root self-hash is invalid.');
        }

        return $document;
    }

    public function decodeProjection(
        string $bytes,
        ?string $expectedKind = null,
        ?string $expectedApplicationBuildId = null,
        ?string $expectedInputHash = null,
    ): ProjectionBuildSetProjection {
        $document = $this->decodeStored($bytes, self::PROJECTION_MAX_BYTES);
        $document = $this->validateProjectionDocument($document);

        $kind = $document['kind'];
        $applicationBuildId = $document['applicationBuildId'];
        $inputHash = $document['inputHash'];
        if ($expectedKind !== null && $kind !== $expectedKind) {
            throw new ProjectionBuildSetValidationException('Projection kind does not match its descriptor.');
        }
        if ($expectedApplicationBuildId !== null && $applicationBuildId !== $expectedApplicationBuildId) {
            throw new ProjectionBuildSetValidationException(
                'Projection build identity does not match its root.',
                ProjectionBuildSetValidationReason::BUILD_MISMATCH,
            );
        }
        if ($expectedInputHash !== null && $inputHash !== $expectedInputHash) {
            throw new ProjectionBuildSetValidationException(
                'Projection input identity does not match its descriptor.',
                ProjectionBuildSetValidationReason::STALE,
            );
        }

        /** @var array<int, array<string, mixed>> $records */
        $records = $document['records'];
        $normalized = $this->normalizeRecords($kind, $records, requireSorted: true);
        if ($this->canonicalJson($normalized) !== $this->canonicalJson($records)) {
            throw new ProjectionBuildSetValidationException('Projection records are not canonical.');
        }
        $recomputedInputHash = hash(
            'sha256',
            $this->encodeString('blackops.projection-input.v1')
                . $this->encodeBytes($this->canonicalJson([
                    'kind' => $kind,
                    'schemaVersion' => self::SCHEMA_VERSION,
                    'applicationBuildId' => $applicationBuildId,
                    'sourceRecords' => $normalized,
                ])),
        );
        if ($inputHash !== $recomputedInputHash) {
            throw new ProjectionBuildSetValidationException('Projection input hash is invalid.');
        }

        $sha256 = hash('sha256', $bytes);

        return new ProjectionBuildSetProjection(
            kind: $kind,
            applicationBuildId: $applicationBuildId,
            records: $normalized,
            inputHash: $inputHash,
            bytes: $bytes,
            sha256: $sha256,
            path: $this->prefixForKind($kind) . '-' . $sha256 . '.json',
        );
    }

    /** @return array<string, string> */
    public function kinds(): array
    {
        return self::KIND_BY_KEY;
    }

    public function keyForKind(string $kind): string
    {
        foreach (self::KIND_BY_KEY as $key => $candidate) {
            if ($candidate === $kind) {
                return $key;
            }
        }

        throw new ProjectionBuildSetValidationException(
            'Projection kind is unsupported.',
            ProjectionBuildSetValidationReason::UNSUPPORTED,
        );
    }

    public function canonicalJson(mixed $value): string
    {
        $value = $this->canonicalValue($value);
        try {
            $encoded = json_encode(
                $value,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR,
            );
        } catch (Throwable $exception) {
            throw new ProjectionBuildSetValidationException(
                'Projection JSON value is outside the supported scalar domain.',
                previous: $exception,
            );
        }

        if (!is_string($encoded)) {
            throw new ProjectionBuildSetValidationException('Projection JSON could not be encoded.');
        }

        return $encoded;
    }

    public function encodeString(string $value): string
    {
        $this->unicode($value, 'encoded string');

        return 's:' . strlen($value) . ':' . $value;
    }

    public function encodeBytes(string $value): string
    {
        return 'b:' . strlen($value) . ':' . $value;
    }

    /**
     * Validates the closed root shape and descriptor bindings before self-hash verification.
     *
     * @param array<string, mixed> $document
     * @return array{schemaVersion: int, kind: string, applicationBuildId: string, buildSetHash: string, projections: array<string, array{kind: string, inputHash: string, path: string, sha256: string}>}
     * @mago-expect lint:halstead
     */
    private function validateRoot(array $document): array
    {
        $this->closed($document, ['schemaVersion', 'kind', 'applicationBuildId', 'buildSetHash', 'projections']);
        if (!is_int($document['schemaVersion']) || !is_string($document['kind'])) {
            throw new ProjectionBuildSetValidationException('Projection build-set root identity is invalid.');
        }
        if ($document['schemaVersion'] !== self::SCHEMA_VERSION || $document['kind'] !== 'projection_build_set') {
            throw new ProjectionBuildSetValidationException(
                'Projection build-set root identity is unsupported.',
                ProjectionBuildSetValidationReason::UNSUPPORTED,
            );
        }
        $applicationBuildId = $this->applicationBuildId($document['applicationBuildId']);
        $buildSetHash = $this->hex($document['buildSetHash'], 'build-set hash');
        if (!is_array($document['projections']) || array_is_list($document['projections'])) {
            throw new ProjectionBuildSetValidationException('Projection build-set map must be an object.');
        }
        $expectedKeys = array_keys(self::KIND_BY_KEY);
        if (array_keys($document['projections']) !== $expectedKeys) {
            throw new ProjectionBuildSetValidationException('Projection build-set map keys are not exact.');
        }

        /** @var array<string, array{kind: string, inputHash: string, path: string, sha256: string}> $projections */
        $projections = [];
        foreach (self::KIND_BY_KEY as $key => $kind) {
            $descriptorValue = $document['projections'][$key];
            if (!is_array($descriptorValue) || array_is_list($descriptorValue)) {
                throw new ProjectionBuildSetValidationException('Projection descriptor must be an object.');
            }
            /** @var array<string, mixed> $descriptor */
            $descriptor = $descriptorValue;
            $this->closed($descriptor, ['kind', 'inputHash', 'path', 'sha256']);
            if (!is_string($descriptor['kind'])) {
                throw new ProjectionBuildSetValidationException('Projection descriptor kind is swapped.');
            }
            if ($descriptor['kind'] !== $kind) {
                $reason = in_array($descriptor['kind'], array_values(self::KIND_BY_KEY), strict: true)
                    ? ProjectionBuildSetValidationReason::MALFORMED
                    : ProjectionBuildSetValidationReason::UNSUPPORTED;
                throw new ProjectionBuildSetValidationException(
                    'Projection descriptor kind is unsupported or swapped.',
                    $reason,
                );
            }
            $inputHash = $this->hex($descriptor['inputHash'], 'projection input hash');
            $sha256 = $this->hex($descriptor['sha256'], 'projection digest');
            if (!is_string($descriptor['path'])) {
                throw new ProjectionBuildSetValidationException('Projection descriptor path must be a string.');
            }
            $expectedPath = $this->prefixForKind($kind) . '-' . $sha256 . '.json';
            if ($descriptor['path'] !== $expectedPath || $descriptor['path'] !== basename($descriptor['path'])) {
                throw new ProjectionBuildSetValidationException(
                    'Projection descriptor path is not bound to its digest.',
                );
            }
            $this->basename($descriptor['path']);
            if ($inputHash === '' || $sha256 === '') {
                throw new ProjectionBuildSetValidationException('Projection descriptor hashes are invalid.');
            }
            $projections[$key] = [
                'kind' => $kind,
                'inputHash' => $inputHash,
                'path' => $descriptor['path'],
                'sha256' => $sha256,
            ];
        }

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'kind' => 'projection_build_set',
            'applicationBuildId' => $applicationBuildId,
            'buildSetHash' => $buildSetHash,
            'projections' => $projections,
        ];
    }

    /**
     * @param array<string, mixed> $document
     * @return array{schemaVersion: int, kind: string, applicationBuildId: string, inputHash: string, records: array<int, array<string, mixed>>}
     */
    private function validateProjectionDocument(array $document): array
    {
        $this->closed($document, ['schemaVersion', 'kind', 'applicationBuildId', 'inputHash', 'records']);
        if (!is_int($document['schemaVersion'])) {
            throw new ProjectionBuildSetValidationException('Projection schema version is invalid.');
        }
        if ($document['schemaVersion'] !== self::SCHEMA_VERSION) {
            throw new ProjectionBuildSetValidationException(
                'Projection schema version is unsupported.',
                ProjectionBuildSetValidationReason::UNSUPPORTED,
            );
        }
        $kind = $this->validatedKind($document['kind']);
        $applicationBuildId = $this->applicationBuildId($document['applicationBuildId']);
        $inputHash = $this->hex($document['inputHash'], 'projection input hash');
        if (!is_array($document['records']) || !array_is_list($document['records'])) {
            throw new ProjectionBuildSetValidationException('Projection records must be a list.');
        }
        if (count($document['records']) > self::MAX_RECORDS) {
            throw new ProjectionBuildSetValidationException('Projection record count exceeds its bound.');
        }
        foreach ($document['records'] as $record) {
            if (!is_array($record) || array_is_list($record)) {
                throw new ProjectionBuildSetValidationException('Projection record must be an object.');
            }
        }

        /** @var array<int, array<string, mixed>> $records */
        $records = $document['records'];

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'kind' => $kind,
            'applicationBuildId' => $applicationBuildId,
            'inputHash' => $inputHash,
            'records' => $records,
        ];
    }

    /**
     * @param array<int, mixed> $records
     * @return list<array<string, mixed>>
     * @mago-expect lint:no-boolean-flag-parameter
     */
    private function normalizeRecords(string $kind, array $records, bool $requireSorted): array
    {
        if (!array_is_list($records) || count($records) > self::MAX_RECORDS) {
            throw new ProjectionBuildSetValidationException('Projection records must be a bounded list.');
        }
        /** @var list<array<string, mixed>> $normalized */
        $normalized = [];
        foreach ($records as $recordValue) {
            if (!is_array($recordValue) || array_is_list($recordValue)) {
                throw new ProjectionBuildSetValidationException('Projection record must be an object.');
            }
            /** @var array<string, mixed> $record */
            $record = $recordValue;
            $normalized[] = match ($kind) {
                'blackops_command_projection' => $this->commandRecord($record),
                'blackops_route_projection' => $this->routeRecord($record),
                'blackops_schedule_projection' => $this->scheduleRecord($record),
                default => throw new ProjectionBuildSetValidationException('Projection kind is unsupported.'),
            };
        }
        /** @var list<array<string, mixed>> $sorted */
        $sorted = $normalized;
        usort($sorted, fn(array $left, array $right): int => $this->recordCompare($kind, $left, $right));
        if ($requireSorted && $sorted !== $normalized) {
            throw new ProjectionBuildSetValidationException('Projection records are not sorted.');
        }
        for ($index = 1, $length = count($sorted); $index < $length; $index++) {
            if ($this->recordCompare($kind, $sorted[$index - 1], $sorted[$index]) === 0) {
                throw new ProjectionBuildSetValidationException('Projection records contain a duplicate.');
            }
        }

        return $sorted;
    }

    /** @param array<string, mixed> $record */
    private function commandRecord(array $record): array
    {
        $this->closed($record, ['name', 'aliases', 'description', 'origin', 'hidden']);
        $name = $this->commandName($record['name'], 'command name');
        if (!is_array($record['aliases']) || !array_is_list($record['aliases']) || count($record['aliases']) > 32) {
            throw new ProjectionBuildSetValidationException('Command aliases must be a bounded list.');
        }
        $aliases = [];
        foreach ($record['aliases'] as $alias) {
            $aliases[] = $this->commandName($alias, 'command alias');
        }
        usort($aliases, strcmp(...));
        if (
            count(array_unique($aliases, flags: SORT_STRING)) !== count($aliases)
            || in_array(needle: $name, haystack: $aliases, strict: true)
        ) {
            throw new ProjectionBuildSetValidationException('Command aliases are not unique.');
        }
        if (!is_string($record['description'])) {
            throw new ProjectionBuildSetValidationException('Command description must be a string.');
        }
        $description = $this->boundedText($record['description'], 0, 256, 'command description');
        if (str_contains($description, "\u{2028}") || str_contains($description, "\u{2029}")) {
            throw new ProjectionBuildSetValidationException('Command description must be one line.');
        }
        if (
            !is_string($record['origin'])
            || !in_array(needle: $record['origin'], haystack: ['framework', 'application'], strict: true)
        ) {
            throw new ProjectionBuildSetValidationException('Command origin is invalid.');
        }
        if (!is_bool($record['hidden'])) {
            throw new ProjectionBuildSetValidationException('Command hidden must be Boolean.');
        }

        return [
            'name' => $name,
            'aliases' => $aliases,
            'description' => $description,
            'origin' => $record['origin'],
            'hidden' => $record['hidden'],
        ];
    }

    /** @param array<string, mixed> $record */
    private function routeRecord(array $record): array
    {
        $this->closed($record, ['method', 'path', 'strategy', 'operationType', 'origin']);
        if (!is_string($record['method'])) {
            throw new ProjectionBuildSetValidationException('Route method must be a string.');
        }
        $method = $this->boundedText($record['method'], 1, 16, 'route method');
        if (preg_match('/^[A-Z0-9!#$%&\'*+\-.^_`|~]+$/D', $method) !== 1) {
            throw new ProjectionBuildSetValidationException('Route method is not an uppercase HTTP token.');
        }
        if (!is_string($record['path'])) {
            throw new ProjectionBuildSetValidationException('Route path must be a string.');
        }
        $path = $this->boundedText($record['path'], 1, 2048, 'route path');
        if (!str_starts_with($path, '/')) {
            throw new ProjectionBuildSetValidationException('Route path must begin with a slash.');
        }
        $operationType = $this->nullableIdentifier($record['operationType'], 'route operation type');
        $strategy = $this->nullableStrategy($record['strategy'], 'route strategy');
        if (
            !is_string($record['origin'])
            || !in_array(needle: $record['origin'], haystack: ['operation_manifest', 'framework_fixed'], strict: true)
        ) {
            throw new ProjectionBuildSetValidationException('Route origin is invalid.');
        }
        if ($record['origin'] === 'operation_manifest' && ($operationType === null || $strategy === null)) {
            throw new ProjectionBuildSetValidationException('Operation routes require operation metadata.');
        }
        if ($record['origin'] === 'framework_fixed' && ($operationType !== null || $strategy !== null)) {
            throw new ProjectionBuildSetValidationException('Framework routes cannot carry operation metadata.');
        }

        return [
            'method' => $method,
            'path' => $path,
            'strategy' => $strategy,
            'operationType' => $operationType,
            'origin' => $record['origin'],
        ];
    }

    /** @param array<string, mixed> $record */
    private function scheduleRecord(array $record): array
    {
        $this->closed($record, ['name', 'operationType', 'cron', 'timezone', 'strategy']);
        $name = $this->identifier($record['name'], 'schedule name');
        $operationType = $this->identifier($record['operationType'], 'schedule operation type');
        if (!is_string($record['cron'])) {
            throw new ProjectionBuildSetValidationException('Schedule cron must be a string.');
        }
        $cron = $this->boundedText($record['cron'], 1, 256, 'schedule cron');
        $cronFields = preg_split('/[ ]+/', $cron, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($cronFields) || count($cronFields) !== 5) {
            throw new ProjectionBuildSetValidationException('Schedule cron must have five fields.');
        }
        try {
            CronExpression::parse($cron);
        } catch (Throwable $exception) {
            throw new ProjectionBuildSetValidationException(
                'Schedule cron expression is invalid.',
                previous: $exception,
            );
        }
        if (!is_string($record['timezone'])) {
            throw new ProjectionBuildSetValidationException('Schedule timezone must be a string.');
        }
        $timezone = $this->boundedText($record['timezone'], 1, 128, 'schedule timezone');
        if (!in_array(
            needle: $timezone,
            haystack: DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC),
            strict: true,
        )) {
            throw new ProjectionBuildSetValidationException('Schedule timezone is not available.');
        }
        $strategy = $this->strategy($record['strategy'], 'schedule strategy');

        return [
            'name' => $name,
            'operationType' => $operationType,
            'cron' => $cron,
            'timezone' => $timezone,
            'strategy' => $strategy,
        ];
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function recordCompare(string $kind, array $left, array $right): int
    {
        $keys = match ($kind) {
            'blackops_command_projection' => ['name', 'aliases'],
            'blackops_route_projection' => ['path', 'method', 'origin', 'operationType', 'strategy'],
            'blackops_schedule_projection' => ['name', 'operationType'],
            default => throw new ProjectionBuildSetValidationException('Projection kind is unsupported.'),
        };
        foreach ($keys as $key) {
            $comparison = $this->compareValue($left[$key], $right[$key]);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }

    /** @mago-expect lint:halstead */
    private function compareValue(mixed $left, mixed $right): int
    {
        if ($left === $right) {
            return 0;
        }
        if ($left === null) {
            return -1;
        }
        if ($right === null) {
            return 1;
        }
        if (is_array($left) && is_array($right)) {
            $length = min(count($left), count($right));
            for ($index = 0; $index < $length; $index++) {
                $comparison = $this->compareValue($left[$index], $right[$index]);
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return count($left) <=> count($right);
        }
        if (!is_string($left) || !is_string($right)) {
            return strcmp((string) $left, (string) $right);
        }

        return strcmp($left, $right);
    }

    /**
     * @param array<string, ProjectionBuildSetProjection|array<int, array<string, mixed>>> $projections
     * @return array<string, ProjectionBuildSetProjection>
     */
    private function normalizeProjections(string $applicationBuildId, array $projections): array
    {
        /** @var array<string, ProjectionBuildSetProjection> $result */
        $result = [];
        foreach (self::KIND_BY_KEY as $key => $kind) {
            $candidate = $projections[$key] ?? $projections[$kind] ?? null;
            if ($candidate instanceof ProjectionBuildSetProjection) {
                if ($candidate->kind !== $kind || $candidate->applicationBuildId !== $applicationBuildId) {
                    throw new ProjectionBuildSetValidationException('Projection input identity is not exact.');
                }
                $input = $candidate;
                $candidate = $candidate->records;
                $built = $this->projection($kind, $applicationBuildId, $candidate);
                if (
                    $input->inputHash !== '' && $input->inputHash !== $built->inputHash
                    || $input->bytes !== '' && $input->bytes !== $built->bytes
                    || $input->sha256 !== '' && $input->sha256 !== $built->sha256
                    || $input->path !== '' && $input->path !== $built->path
                ) {
                    throw new ProjectionBuildSetValidationException(
                        'Projection input metadata is not bound to its records.',
                    );
                }
                $result[$key] = $built;
                continue;
            }
            if (!is_array($candidate)) {
                throw new ProjectionBuildSetValidationException('Publisher requires all three projection inputs.');
            }
            $result[$key] = $this->projection($kind, $applicationBuildId, $candidate);
        }
        if (count($projections) !== 3) {
            throw new ProjectionBuildSetValidationException('Publisher requires exactly three projection inputs.');
        }

        return $result;
    }

    private function validateKind(mixed $kind): void
    {
        if (!is_string($kind) || !in_array(needle: $kind, haystack: array_values(self::KIND_BY_KEY), strict: true)) {
            throw new ProjectionBuildSetValidationException(
                'Projection kind is unsupported.',
                ProjectionBuildSetValidationReason::UNSUPPORTED,
            );
        }
    }

    private function validatedKind(mixed $kind): string
    {
        $this->validateKind($kind);

        /** @var string $kind */
        return $kind;
    }

    private function prefixForKind(string $kind): string
    {
        return match ($kind) {
            'blackops_command_projection' => 'command',
            'blackops_route_projection' => 'route',
            'blackops_schedule_projection' => 'schedule',
            default => throw new ProjectionBuildSetValidationException('Projection kind is unsupported.'),
        };
    }

    private function applicationBuildId(mixed $value): string
    {
        if (!is_string($value)) {
            throw new ProjectionBuildSetValidationException('Application build identity must be a string.');
        }

        return $this->boundedText($value, 1, 128, 'application build identity');
    }

    private function commandName(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new ProjectionBuildSetValidationException($field . ' must be a string.');
        }
        $value = $this->boundedText($value, 1, 256, $field);
        if (preg_match('/^[^:]+(?::[^:]+)*$/D', $value) !== 1 || preg_match('/[\t,\r\n]/', $value) === 1) {
            throw new ProjectionBuildSetValidationException($field . ' grammar is invalid.');
        }

        return $value;
    }

    private function identifier(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new ProjectionBuildSetValidationException($field . ' must be a string.');
        }
        $value = $this->boundedText($value, 1, 256, $field);
        if (preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)*$/D', $value) !== 1) {
            throw new ProjectionBuildSetValidationException($field . ' grammar is invalid.');
        }

        return $value;
    }

    private function nullableIdentifier(mixed $value, string $field): ?string
    {
        return $value === null ? null : $this->identifier($value, $field);
    }

    private function strategy(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new ProjectionBuildSetValidationException($field . ' must be a string.');
        }
        $value = $this->boundedText($value, 1, 1024, $field);
        if (
            $value !== 'inline'
            && $value !== 'deferred'
            && preg_match('~^custom:[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$~D', $value) !== 1
        ) {
            throw new ProjectionBuildSetValidationException($field . ' wire is invalid.');
        }

        return $value;
    }

    private function nullableStrategy(mixed $value, string $field): ?string
    {
        return $value === null ? null : $this->strategy($value, $field);
    }

    private function boundedText(mixed $value, int $minimum, int $maximum, string $field): string
    {
        if (!is_string($value)) {
            throw new ProjectionBuildSetValidationException($field . ' must be a string.');
        }
        $this->unicode($value, $field);
        $length = strlen($value);
        if ($length < $minimum || $length > $maximum) {
            throw new ProjectionBuildSetValidationException($field . ' is outside its byte bound.');
        }

        return $value;
    }

    private function unicode(string $value, string $field): void
    {
        if (preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x1F\x7F-\x9F]/u', $value) === 1) {
            throw new ProjectionBuildSetValidationException($field . ' must be valid UTF-8 without controls.');
        }
        if (class_exists('Normalizer')) {
            /** @var class-string $normalizer */
            $normalizer = 'Normalizer';
            if ($normalizer::normalize($value) !== $value) {
                throw new ProjectionBuildSetValidationException($field . ' must be NFC.');
            }
            return;
        }
        if (preg_match('/\p{M}/u', $value) === 1) {
            throw new ProjectionBuildSetValidationException($field . ' must be NFC.');
        }
    }

    private function hex(mixed $value, string $field): string
    {
        if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            throw new ProjectionBuildSetValidationException($field . ' must be lowercase SHA-256 hex.');
        }

        return $value;
    }

    /** @param array<string, mixed> $value */
    private function closed(array $value, array $keys): void
    {
        if (array_keys($value) !== $keys) {
            $actual = array_keys($value);
            sort($actual, SORT_STRING);
            $expected = $keys;
            sort($expected, SORT_STRING);
            if ($actual !== $expected) {
                throw new ProjectionBuildSetValidationException(
                    'Projection object has unknown, missing, or duplicate fields.',
                );
            }
        }
    }

    private function basename(string $value): void
    {
        if ($value === '.' || $value === '..' || str_contains($value, '/') || str_contains($value, '\\')) {
            throw new ProjectionBuildSetValidationException('Projection descriptor path must be a single basename.');
        }
    }

    private function storedJson(mixed $value, int $maximum): string
    {
        $bytes = $this->canonicalJson($value) . "\n";
        if (strlen($bytes) > $maximum || str_contains(substr(string: $bytes, offset: 0, length: -1), "\r")) {
            throw new ProjectionBuildSetValidationException('Projection canonical bytes exceed their bound.');
        }

        return $bytes;
    }

    /** @return array<string, mixed> */
    private function decodeStored(string $bytes, int $maximum): array
    {
        if (
            $bytes === ''
            || strlen($bytes) > $maximum
            || !str_ends_with($bytes, "\n")
            || str_ends_with(substr(string: $bytes, offset: 0, length: -1), "\r")
        ) {
            throw new ProjectionBuildSetValidationException('Projection bytes must have exactly one final LF.');
        }
        $body = substr(string: $bytes, offset: 0, length: -1);
        if (
            $body === ''
            || str_contains($body, "\n")
            || str_contains($body, "\r")
            || str_starts_with($body, "\xEF\xBB\xBF")
        ) {
            throw new ProjectionBuildSetValidationException('Projection bytes contain forbidden whitespace or BOM.');
        }
        $decoded = new ProjectionBuildSetJsonParser($body)->parse();
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new ProjectionBuildSetValidationException('Projection document must be an object.');
        }
        foreach (array_keys($decoded) as $key) {
            if (!is_string($key)) {
                throw new ProjectionBuildSetValidationException('Projection object keys must be strings.');
            }
        }
        /** @var array<string, mixed> $decodedObject */
        $decodedObject = $decoded;
        $canonical = $this->canonicalJson($decodedObject) . "\n";
        if ($canonical !== $bytes) {
            throw new ProjectionBuildSetValidationException('Projection bytes are not canonical JSON.');
        }

        return $decodedObject;
    }

    /** @return mixed */
    private function canonicalValue(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map($this->canonicalValue(...), $value);
            }
            $keys = array_keys($value);
            foreach ($keys as $key) {
                if (!is_string($key)) {
                    throw new ProjectionBuildSetValidationException('Projection JSON object keys must be strings.');
                }
                $this->unicode($key, 'projection object key');
            }
            usort($keys, static fn(mixed $left, mixed $right): int => strcmp((string) $left, (string) $right));
            $object = [];
            foreach ($keys as $key) {
                $object[$key] = $this->canonicalValue($value[$key]);
            }

            return $object;
        }
        if (is_string($value)) {
            $this->unicode($value, 'projection JSON string');

            return $value;
        }
        if (is_int($value) || is_bool($value) || $value === null) {
            return $value;
        }

        throw new ProjectionBuildSetValidationException('Projection JSON scalar type is unsupported.');
    }
}

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:single-class-per-file
 */
final class ProjectionBuildSetJsonParser
{
    private int $position = 0;
    private int $length;

    public function __construct(
        private readonly string $source,
    ) {
        $this->length = strlen($source);
    }

    /** @return array<string, mixed>|array<int, mixed> */
    public function parse(): array
    {
        $value = $this->native($this->value());
        $this->space();
        if ($this->position !== $this->length || !is_array($value)) {
            throw new ProjectionBuildSetValidationException('Projection JSON is invalid.');
        }

        if (array_is_list($value)) {
            /** @var array<int, mixed> $value */
            return $value;
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    private function native(mixed $value): mixed
    {
        if ($value instanceof ProjectionBuildSetJsonObject) {
            $fields = [];
            foreach ($value->fields as $key => $field) {
                $fields[$key] = $this->native($field);
            }

            return $fields;
        }
        if (is_array($value)) {
            return array_map($this->native(...), $value);
        }

        return $value;
    }

    private function value(): mixed
    {
        $this->space();
        if ($this->position >= $this->length) {
            throw new ProjectionBuildSetValidationException('Projection JSON is incomplete.');
        }
        $character = $this->source[$this->position];

        return match ($character) {
            '{' => $this->object(),
            '[' => $this->list(),
            '"' => $this->string(),
            't' => $this->literal('true', true),
            'f' => $this->literal('false', false),
            'n' => $this->literal('null', null),
            default => $this->number(),
        };
    }

    private function object(): ProjectionBuildSetJsonObject
    {
        $this->position++;
        $fields = [];
        $this->space();
        if ($this->take('}')) {
            return new ProjectionBuildSetJsonObject($fields);
        }
        while (true) {
            $this->space();
            if ($this->position >= $this->length || $this->source[$this->position] !== '"') {
                throw new ProjectionBuildSetValidationException('Projection JSON object key is invalid.');
            }
            $key = $this->string();
            if (!is_string($key) || array_key_exists($key, $fields)) {
                throw new ProjectionBuildSetValidationException('Projection JSON object has a duplicate key.');
            }
            $this->space();
            if (!$this->take(':')) {
                throw new ProjectionBuildSetValidationException('Projection JSON object is missing a colon.');
            }
            $fields[$key] = $this->value();
            $this->space();
            if ($this->take('}')) {
                return new ProjectionBuildSetJsonObject($fields);
            }
            if (!$this->take(',')) {
                throw new ProjectionBuildSetValidationException('Projection JSON object is missing a separator.');
            }
        }
    }

    /** @return array<int, mixed> */
    private function list(): array
    {
        $this->position++;
        $values = [];
        $this->space();
        if ($this->take(']')) {
            return $values;
        }
        while (true) {
            $values[] = $this->value();
            $this->space();
            if ($this->take(']')) {
                return $values;
            }
            if (!$this->take(',')) {
                throw new ProjectionBuildSetValidationException('Projection JSON list is missing a separator.');
            }
        }
    }

    private function string(): string
    {
        $start = $this->position;
        $this->position++;
        $escaped = false;
        while ($this->position < $this->length) {
            $character = $this->source[$this->position++];
            if ($escaped) {
                $escaped = false;
                if ($character === 'u') {
                    $hex = substr(string: $this->source, offset: $this->position, length: 4);
                    if (strlen($hex) !== 4 || preg_match('/^[0-9a-fA-F]{4}$/D', $hex) !== 1) {
                        throw new ProjectionBuildSetValidationException('Projection JSON Unicode escape is invalid.');
                    }
                    $this->position += 4;
                    continue;
                }
                if (!str_contains('"\\/bfnrt', $character)) {
                    throw new ProjectionBuildSetValidationException('Projection JSON escape is invalid.');
                }
                continue;
            }
            if ($character === '\\') {
                $escaped = true;
                continue;
            }
            if ($character === '"') {
                $token = substr($this->source, $start, $this->position - $start);
                $decoded = json_decode($token, associative: true, flags: JSON_THROW_ON_ERROR);
                if (!is_string($decoded)) {
                    throw new ProjectionBuildSetValidationException('Projection JSON string is invalid.');
                }

                return $decoded;
            }
            if (ord($character) < 0x20) {
                throw new ProjectionBuildSetValidationException('Projection JSON string contains a control.');
            }
        }

        throw new ProjectionBuildSetValidationException('Projection JSON string is incomplete.');
    }

    private function number(): int
    {
        $remaining = substr($this->source, $this->position);
        if (preg_match('/^-?(?:0|[1-9][0-9]*)/D', $remaining, $matches) !== 1) {
            throw new ProjectionBuildSetValidationException('Projection JSON number is invalid.');
        }
        $token = $matches[0];
        if (hash_equals('-0', $token)) {
            throw new ProjectionBuildSetValidationException('Projection JSON negative zero is unsupported.');
        }
        $this->position += strlen($token);
        if ($this->position < $this->length && str_contains('.eE', $this->source[$this->position])) {
            throw new ProjectionBuildSetValidationException('Projection JSON floating point values are unsupported.');
        }
        $value = filter_var($token, FILTER_VALIDATE_INT);
        if (!is_int($value) || !hash_equals((string) $value, $token)) {
            throw new ProjectionBuildSetValidationException('Projection JSON integer is outside the supported range.');
        }

        return $value;
    }

    private function literal(string $literal, mixed $value): mixed
    {
        if (substr($this->source, $this->position, strlen($literal)) !== $literal) {
            throw new ProjectionBuildSetValidationException('Projection JSON literal is invalid.');
        }
        $this->position += strlen($literal);

        return $value;
    }

    private function take(string $character): bool
    {
        if ($this->position < $this->length && $this->source[$this->position] === $character) {
            $this->position++;

            return true;
        }

        return false;
    }

    private function space(): void
    {
        while ($this->position < $this->length && str_contains(" \t\n\r", $this->source[$this->position])) {
            $this->position++;
        }
    }
}
