<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\Command;

use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystemException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetReader;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryResult;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetStatusSource;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetValidationException;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Exception\NamespaceNotFoundException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Keeps Symfony-compatible input binding, recovery, and output selection in
 * one non-activating command boundary so no application command is resolved.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class CommandProjectionListCommand extends Command
{
    public const NAME = 'list';

    /**
     * @param array<string, string> $expectedInputHashes
     */
    public function __construct(
        private readonly ?ProjectionBuildSetReader $reader = null,
        private readonly ?string $expectedApplicationBuildId = null,
        private readonly ?array $expectedInputHashes = null,
        private readonly CommandProjectionTargetRegistry $targetRegistry = new CommandProjectionTargetRegistry(),
        private readonly CommandProjectionRenderer $renderer = new CommandProjectionRenderer(),
        private readonly CommandProjectionNamespaceResolver $namespaceResolver = new CommandProjectionNamespaceResolver(),
        private readonly ?ProjectionBuildSetStatusSource $statusSource = null,
    ) {
        parent::__construct(self::NAME);
        /** @var class-string<CommandProjectionTargetRegistry> $registryClass */
        $registryClass = get_class($targetRegistry);
        $target = $registryClass::target(self::NAME);
        $this->setAliases($target->aliases);
        $this->setDescription($target->description);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('namespace', InputArgument::OPTIONAL, 'The namespace name')
            ->addOption('raw', null, InputOption::VALUE_NONE, 'To output raw command list')
            ->addOption('short', null, InputOption::VALUE_NONE, 'To skip describing commands\' arguments')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (txt, json, xml, or md)', 'txt')
            ->addOption('json', null, InputOption::VALUE_NONE, 'To output JSON')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Include hidden command rows');
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (NamespaceNotFoundException $exception) {
            throw $exception;
        } catch (ExceptionInterface) {
            $this->writeError($output, 'Input error.');

            return self::INVALID;
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $options = $this->options($input);
        } catch (InvalidArgumentException) {
            $this->writeError($output, 'Input error.');

            return self::INVALID;
        }

        try {
            return $this->withStatus(function (ProjectionBuildSetRecoveryResult $recovery) use (
                $options,
                $output,
            ): int {
                $records = $recovery->isAvailable() && $recovery->read !== null
                    ? $recovery->read->records('command')
                    : $this->embeddedRecords();
                $records = array_values(array_filter(
                    $records,
                    static fn(array $record): bool => $options['all'] || $record['hidden'] === false,
                ));
                /** @var list<array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}> $commandRecords */
                $commandRecords = $records;

                $records = $this->namespaceResolver->select($commandRecords, $options['namespace']);

                $bytes = $this->renderer->render(
                    $records,
                    $recovery->status,
                    $options['raw'] && $options['format'] === 'txt' ? 'raw' : $options['format'],
                    $options['all'],
                );
                $output->write($bytes, options: OutputInterface::OUTPUT_RAW);
                if ($options['format'] === 'txt' || $options['format'] === 'raw') {
                    $this->writeError($output, 'applicationProjectionStatus=' . $recovery->status->value);
                }

                return self::SUCCESS;
            });
        } catch (ProjectionBuildSetFilesystemException) {
            $this->writeError($output, 'Projection build-set read failed safely.');

            return self::FAILURE;
        } catch (ProjectionBuildSetValidationException) {
            $this->writeError($output, 'Projection build-set input is invalid.');

            return self::INVALID;
        }
    }

    /**
     * @template T
     * @param callable(ProjectionBuildSetRecoveryResult): T $callback
     * @return T
     */
    private function withStatus(callable $callback): mixed
    {
        if ($this->statusSource !== null) {
            return $this->statusSource->withStatus($callback);
        }
        if (
            $this->reader === null
            || $this->expectedApplicationBuildId === null
            || $this->expectedInputHashes === null
        ) {
            throw new ProjectionBuildSetValidationException('Application projection input is invalid.');
        }

        return $this->reader->withReadStatus($this->expectedApplicationBuildId, $this->expectedInputHashes, $callback);
    }

    /**
     * @return array{namespace: ?string, format: string, raw: bool, all: bool}
     */
    private function options(InputInterface $input): array
    {
        $format = $input->getOption('format');
        if (!is_string($format) || !in_array($format, ['txt', 'json', 'xml', 'md'], strict: true)) {
            throw new InvalidArgumentException('Unsupported command projection format.');
        }
        $json = $input->getOption('json');
        if (!is_bool($json)) {
            throw new InvalidArgumentException('Invalid JSON option.');
        }
        if ($json && $input->hasParameterOption('--format')) {
            throw new InvalidArgumentException('JSON and explicit format cannot be combined.');
        }
        if ($json) {
            $format = 'json';
        }
        $namespace = $input->getArgument('namespace');
        if ($namespace !== null && !is_string($namespace)) {
            throw new InvalidArgumentException('Invalid command namespace.');
        }
        $all = $input->getOption('all');
        if (!is_bool($all)) {
            throw new InvalidArgumentException('Invalid all option.');
        }
        $raw = $input->getOption('raw');
        if (!is_bool($raw)) {
            throw new InvalidArgumentException('Invalid raw option.');
        }

        return ['namespace' => $namespace, 'format' => $format, 'raw' => $raw, 'all' => $all];
    }

    /** @return list<array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}> */
    private function embeddedRecords(): array
    {
        /** @var class-string<CommandProjectionTargetRegistry> $registryClass */
        $registryClass = get_class($this->targetRegistry);

        return array_map(static fn(CommandProjectionTarget $target): array => $target->record(
            'framework',
        ), $registryClass::all());
    }

    private function writeError(OutputInterface $output, string $message): void
    {
        if ($output instanceof ConsoleOutputInterface) {
            $output->getErrorOutput()->writeln($message);
        }
    }
}
