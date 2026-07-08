<?php

declare(strict_types=1);

namespace BlackOps\Http\Console;

use BlackOps\Core\Operation;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Http\Routing\HttpRouteCompiler;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Dump the HTTP operation manifest to a PHP array file.')]
final class DumpHttpManifestCommand extends Command
{
    public const NAME = 'blackops:http-manifest:dump';

    /**
     * @var list<Operation>
     */
    private array $definitions;

    /**
     * @param iterable<Operation> $definitions
     */
    public function __construct(
        private readonly OperationRegistry $operations,
        iterable $definitions,
        private readonly HttpOperationManifestFile $files = new HttpOperationManifestFile(),
    ) {
        parent::__construct(self::NAME);

        $this->definitions = $this->definitionList($definitions);
    }

    protected function configure(): void
    {
        $this->addArgument('output', InputArgument::REQUIRED, 'Path to the generated PHP manifest file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $this->outputPath($input);
        $manifest = new HttpRouteCompiler($this->operations)->compileManifest($this->definitions);
        $this->files->write($manifest, $path);
        $output->writeln('HTTP manifest written.');

        return Command::SUCCESS;
    }

    /**
     * @param iterable<Operation> $definitions
     *
     * @return list<Operation>
     */
    private function definitionList(iterable $definitions): array
    {
        if (is_array($definitions)) {
            return array_values($definitions);
        }

        return iterator_to_array($definitions, preserve_keys: false);
    }

    private function outputPath(InputInterface $input): string
    {
        if (!is_string($input->getArgument('output')) || $input->getArgument('output') === '') {
            throw new InvalidArgumentException('HTTP manifest output path must be a non-empty string.');
        }

        return (string) $input->getArgument('output');
    }
}
