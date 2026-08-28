<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\Route;

use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystemException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetReader;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryResult;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetStatusSource;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetValidationException;
use BlackOps\Internal\Projection\Command\CommandProjectionTargetRegistry;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class RouteProjectionListCommand extends Command
{
    public const NAME = 'route:list';

    /** @param array<string, string>|null $expectedInputHashes */
    public function __construct(
        private readonly ?ProjectionBuildSetReader $reader = null,
        private readonly ?string $expectedApplicationBuildId = null,
        private readonly ?array $expectedInputHashes = null,
        private readonly CommandProjectionTargetRegistry $targetRegistry = new CommandProjectionTargetRegistry(),
        private readonly RouteProjectionRenderer $renderer = new RouteProjectionRenderer(),
        private readonly ?ProjectionBuildSetStatusSource $statusSource = null,
    ) {
        parent::__construct(self::NAME);
        /** @var class-string<CommandProjectionTargetRegistry> $registryClass */
        $registryClass = get_class($targetRegistry);
        $target = $registryClass::target(self::NAME);
        $this->setAliases($target->aliases);
        $this->setDescription($target->description);
        $this->setHidden($target->hidden);
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'To output JSON');
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (ExceptionInterface) {
            $this->writeError($output, 'Input error.');

            return self::INVALID;
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $json = $this->jsonOption($input);
        } catch (InvalidArgumentException) {
            $this->writeError($output, 'Input error.');

            return self::INVALID;
        }

        try {
            return $this->withStatus(function (ProjectionBuildSetRecoveryResult $recovery) use ($json, $output): int {
                if (!$recovery->isAvailable() || $recovery->read === null) {
                    $this->writeError($output, 'Route projection is unavailable or invalid.');

                    return self::INVALID;
                }

                /** @var list<array{method: string, path: string, strategy: string|null, operationType: string|null, origin: string}> $validatedRecords */
                $validatedRecords = $recovery->read->records('route');
                $bytes = $this->renderer->render($validatedRecords, json: $json);
                $output->write($bytes, options: OutputInterface::OUTPUT_RAW);

                return self::SUCCESS;
            });
        } catch (ProjectionBuildSetFilesystemException) {
            $this->writeError($output, 'Projection build-set read failed safely.');

            return self::FAILURE;
        } catch (ProjectionBuildSetValidationException) {
            $this->writeError($output, 'Route projection input is invalid.');

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
            return $this->statusSource->withStatus($callback, selectedKey: 'route');
        }
        if (
            $this->reader === null
            || $this->expectedApplicationBuildId === null
            || $this->expectedInputHashes === null
        ) {
            throw new ProjectionBuildSetValidationException('Route projection input is invalid.');
        }

        return $this->reader->withReadStatus(
            $this->expectedApplicationBuildId,
            $this->expectedInputHashes,
            $callback,
            selectedKey: 'route',
        );
    }

    private function jsonOption(InputInterface $input): bool
    {
        $json = $input->getOption('json');
        if (!is_bool($json)) {
            throw new InvalidArgumentException('Invalid JSON option.');
        }

        return $json;
    }

    private function writeError(OutputInterface $output, string $message): void
    {
        if ($output instanceof ConsoleOutputInterface) {
            $output->getErrorOutput()->writeln($message);
        }
    }
}
