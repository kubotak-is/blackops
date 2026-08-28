<?php

declare(strict_types=1);

namespace BlackOps\Internal\About;

use InvalidArgumentException;

final readonly class AboutMetadata
{
    public const string PACKAGE_NAME = 'blackops/framework';
    public const string RUNTIME_SCOPE = 'current_process';

    public function __construct(
        public string $version,
        public string $phpVersion,
        public string $sapi,
    ) {
        self::validate($version, 128, '/\\A[A-Za-z0-9][A-Za-z0-9._+@\\/-]{0,127}\\z/D', 'package version');
        self::validate($phpVersion, 64, '/\\A[A-Za-z0-9][A-Za-z0-9._+\\-]{0,63}\\z/D', 'PHP version');
        self::validate($sapi, 32, '/\\A[a-z0-9][a-z0-9_-]{0,31}\\z/D', 'SAPI');
    }

    /**
     * @return array{package: array{name: string, version: string}, runtime: array{phpVersion: string, sapi: string, scope: string}}
     */
    public function toArray(): array
    {
        return [
            'package' => [
                'name' => self::PACKAGE_NAME,
                'version' => $this->version,
            ],
            'runtime' => [
                'phpVersion' => $this->phpVersion,
                'sapi' => $this->sapi,
                'scope' => self::RUNTIME_SCOPE,
            ],
        ];
    }

    public function packageVersion(): string
    {
        return $this->version;
    }

    private static function validate(string $value, int $maximumBytes, string $pattern, string $field): void
    {
        $length = strlen($value);
        if ($length < 1 || $length > $maximumBytes || preg_match($pattern, $value) !== 1) {
            throw new InvalidArgumentException('About ' . $field . ' is invalid.');
        }
    }
}
