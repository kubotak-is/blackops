<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class SystemDiagnosticsReport
{
    public const int SCHEMA_VERSION = 1;

    /** @var list<array{code: string, family: string, status: string}> */
    public array $checks;

    /**
     * @param list<array{code: string, family: string, status: string}> $checks
     */
    public function __construct(
        public DateTimeImmutable $checkedAt,
        public string $status,
        public string $projectionBuildSet,
        array $checks,
    ) {
        if (!in_array($status, ['pass', 'fail'], strict: true)) {
            throw new InvalidArgumentException('Diagnostics report status is invalid.');
        }
        if (!in_array(
            $projectionBuildSet,
            [
                'available',
                'absent',
                'malformed',
                'unsupported',
                'stale',
                'build_mismatch',
                'configuration_invalid',
                'read_failed',
            ],
            strict: true,
        )) {
            throw new InvalidArgumentException('Diagnostics projection status is invalid.');
        }

        foreach ($checks as $check) {
            if (!is_array($check) || array_keys($check) !== ['code', 'family', 'status']) {
                throw new InvalidArgumentException('Diagnostics report checks are invalid.');
            }
            if (!is_string($check['code']) || !is_string($check['family']) || !is_string($check['status'])) {
                throw new InvalidArgumentException('Diagnostics report checks are invalid.');
            }
            if (!in_array($check['family'], ['projection_build_set', 'readiness'], strict: true)) {
                throw new InvalidArgumentException('Diagnostics report check family is invalid.');
            }
            if (!in_array($check['status'], ['pass', 'fail'], strict: true)) {
                throw new InvalidArgumentException('Diagnostics report check status is invalid.');
            }
        }

        $this->checks = array_values($checks);
    }

    /** @return array{checkedAt: string, checks: list<array{code: string, family: string, status: string}>, schemaVersion: int, status: string} */
    public function toArray(): array
    {
        return [
            'checkedAt' => $this->checkedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            'checks' => $this->checks,
            'schemaVersion' => self::SCHEMA_VERSION,
            'status' => $this->status,
        ];
    }

    public function isPassing(): bool
    {
        return $this->status === 'pass';
    }
}
