<?php

declare(strict_types=1);

namespace BlackOps\Internal\Generator;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

final readonly class MigrationGenerator
{
    private ClockInterface $clock;

    /** @var Closure(string): void */
    private Closure $beforeStubRead;

    /** @param null|Closure(string): void $beforeStubRead */
    public function __construct(
        private string $basePath,
        private string $stubDirectory,
        ?ClockInterface $clock = null,
        private ProjectFileWriter $writer = new ProjectFileWriter(),
        ?Closure $beforeStubRead = null,
    ) {
        $this->clock = $clock
        ?? new readonly class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('now');
            }
        };
        $this->beforeStubRead = $beforeStubRead ?? static function (string $_path): void {};
    }

    public function generate(MigrationGeneratorInput $input): string
    {
        $version = 'Version' . $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('YmdHis');
        $relative = 'migrations/' . $version . '.php';
        $this->writer->write($this->basePath, [$relative => $this->render($version, $input)]);

        return $relative;
    }

    private function render(string $version, MigrationGeneratorInput $input): string
    {
        $path = rtrim($this->stubDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'migration.php.stub';
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('Migration generator stub is unavailable.');
        }

        ($this->beforeStubRead)($path);
        set_error_handler(static fn(int $_severity, string $_message, string $_file, int $_line): bool => true);

        try {
            $template = file_get_contents($path);
        } finally {
            restore_error_handler();
        }

        if (!is_string($template)) {
            throw new InvalidArgumentException('Migration generator stub is unavailable.');
        }

        $rendered = strtr($template, [
            '{{ version }}' => $version,
            '{{ description }}' => $input->description,
        ]);

        if (str_contains($rendered, '{{')) {
            throw new InvalidArgumentException('Migration generator stub is invalid.');
        }

        return $rendered;
    }
}
