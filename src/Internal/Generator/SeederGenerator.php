<?php

declare(strict_types=1);

namespace BlackOps\Internal\Generator;

use Closure;
use InvalidArgumentException;

final readonly class SeederGenerator
{
    /** @var Closure(string): void */
    private Closure $beforeStubRead;

    /** @param null|Closure(string): void $beforeStubRead */
    public function __construct(
        private string $basePath,
        private string $stubDirectory,
        private ProjectFileWriter $writer = new ProjectFileWriter(),
        ?Closure $beforeStubRead = null,
    ) {
        $this->beforeStubRead = $beforeStubRead ?? static function (string $_path): void {};
    }

    public function generate(SeederGeneratorInput $input): string
    {
        $segments = $input->segments;
        $class = array_pop($segments);
        $directory = 'app/Infrastructure/Seed' . ($segments === [] ? '' : '/' . implode('/', $segments));
        $relative = $directory . '/' . $class . '.php';
        $namespace = 'App\\Infrastructure\\Seed' . ($segments === [] ? '' : '\\' . implode('\\', $segments));
        $this->writer->write($this->basePath, [$relative => $this->render($namespace, $class)]);

        return $relative;
    }

    private function render(string $namespace, string $class): string
    {
        $path = rtrim($this->stubDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'seeder.php.stub';
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('Seeder generator stub is unavailable.');
        }

        ($this->beforeStubRead)($path);
        set_error_handler(static fn(int $_severity, string $_message, string $_file, int $_line): bool => true);

        try {
            $template = file_get_contents($path);
        } finally {
            restore_error_handler();
        }

        if (!is_string($template)) {
            throw new InvalidArgumentException('Seeder generator stub is unavailable.');
        }

        $rendered = strtr($template, ['{{ namespace }}' => $namespace, '{{ class }}' => $class]);
        if (str_contains($rendered, '{{')) {
            throw new InvalidArgumentException('Seeder generator stub is invalid.');
        }

        return $rendered;
    }
}
