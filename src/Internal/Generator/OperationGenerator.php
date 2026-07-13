<?php

declare(strict_types=1);

namespace BlackOps\Internal\Generator;

use Closure;
use InvalidArgumentException;

final readonly class OperationGenerator
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

    /** @return list<string> */
    public function generate(OperationGeneratorInput $input): array
    {
        $directory = sprintf('app/Feature/%s/%s', $input->feature, $input->action);
        $files = [
            $directory . '/' . $input->action . '.php' => $this->render('operation.php.stub', $input),
            $directory . '/' . $input->action . 'Value.php' => $this->render('operation-value.php.stub', $input),
            $directory . '/' . $input->action . 'Outcome.php' => $this->render('operation-outcome.php.stub', $input),
        ];

        $this->writer->write($this->basePath, $files);

        return array_keys($files);
    }

    private function render(string $stub, OperationGeneratorInput $input): string
    {
        $path = rtrim($this->stubDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $stub;
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('Operation generator stub is unavailable.');
        }

        ($this->beforeStubRead)($path);
        $template = $this->readStub($path);

        if (!is_string($template)) {
            throw new InvalidArgumentException('Operation generator stub is unavailable.');
        }

        $rendered = strtr($template, [
            '{{ feature }}' => $input->feature,
            '{{ action }}' => $input->action,
            '{{ operation_type }}' => $input->operationType,
        ]);

        if (str_contains($rendered, '{{')) {
            throw new InvalidArgumentException('Operation generator stub is invalid.');
        }

        return $rendered;
    }

    private function readStub(string $path): string|false
    {
        set_error_handler(static fn(int $_severity, string $_message, string $_file, int $_line): bool => true);

        try {
            return file_get_contents($path);
        } finally {
            restore_error_handler();
        }
    }
}
