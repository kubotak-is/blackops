<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\Exception\InvalidIdentifierException;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsException;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsFound;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsResult;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsUnavailable;
use Closure;
use JsonException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class OperationInspectCommand extends Command
{
    public const NAME = 'operation:inspect';

    /** @var Closure(OperationId): OperationDiagnosticsResult */
    private Closure $queryFactory;

    /** @param Closure(OperationId): OperationDiagnosticsResult $queryFactory */
    public function __construct(
        Closure $queryFactory,
        private readonly OperationInspectHumanFormatter $human = new OperationInspectHumanFormatter(),
        private readonly OperationInspectJsonEncoder $json = new OperationInspectJsonEncoder(),
    ) {
        parent::__construct(self::NAME);
        $this->queryFactory = $queryFactory;
        $this
            ->setDescription('Inspect one operation lifecycle and outcome.')
            ->addArgument('operation-id', InputArgument::OPTIONAL, 'Required UUID version 7 operation identifier.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Write a versioned JSON object.')
            ->addUsage('<operation-id> [--json]');
    }

    public function getSynopsis(bool $short = false): string
    {
        return 'operation:inspect <operation-id> [--json]';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('json') === true ? OperationInspectFormat::Json : OperationInspectFormat::Human;
        /** @var string|null $operationId */
        $operationId = $input->getArgument('operation-id');
        if (!is_string($operationId)) {
            return $this->error($output, 'operation.invalid_id', $format, 2);
        }

        try {
            $id = OperationId::fromString($operationId);
        } catch (InvalidIdentifierException) {
            return $this->error($output, 'operation.invalid_id', $format, 2);
        }

        try {
            $result = ($this->queryFactory)($id);
            if ($result instanceof OperationDiagnosticsUnavailable) {
                return $this->error($output, $result->code, $format, 3);
            }
            if (!$result instanceof OperationDiagnosticsFound) {
                return $this->error($output, 'diagnostics.integrity_failed', $format, 4);
            }

            $body = match ($format) {
                OperationInspectFormat::Human => $this->human->format($result->diagnostics),
                OperationInspectFormat::Json => $this->json->encode($result->diagnostics),
            };
        } catch (OperationDiagnosticsException $exception) {
            return $this->error($output, $exception->diagnosticsCode->value, $format, 4);
        } catch (JsonException) {
            return $this->error($output, 'diagnostics.decode_failed', $format, 4);
        } catch (Throwable) {
            return $this->error($output, 'diagnostics.storage_failed', $format, 4);
        }

        $output->write($body);

        return Command::SUCCESS;
    }

    private function error(OutputInterface $output, string $code, OperationInspectFormat $format, int $exitCode): int
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $body = match ($format) {
            OperationInspectFormat::Human => $code . "\n",
            OperationInspectFormat::Json => json_encode([
                'schemaVersion' => 1,
                'status' => 'error',
                'code' => $code,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n",
        };
        $error->write($body);

        return $exitCode;
    }
}
