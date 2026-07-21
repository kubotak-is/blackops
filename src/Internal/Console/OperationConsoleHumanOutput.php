<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use JsonException;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class OperationConsoleHumanOutput
{
    public function write(OperationConsoleInvocationResult $result, OutputInterface $output): int
    {
        /** @var string $status */
        $status = $result->payload['status'];

        return match ($status) {
            'completed' => $this->completed($result, $output),
            'accepted' => $this->accepted($result, $output),
            'rejected' => $this->rejected($result, $output),
            default => $this->internal($result, $output),
        };
    }

    private function completed(OperationConsoleInvocationResult $result, OutputInterface $output): int
    {
        /** @var array<string, mixed>|\stdClass $outcome */
        $outcome = $result->payload['outcome'];
        $encoded = null;
        if (is_array($outcome) && $outcome !== []) {
            try {
                $encoded = json_encode($outcome, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $output->writeln('Operation failed [internal_error].');

                return 1;
            }
        }
        $output->writeln('Completed.');
        if ($encoded !== null) {
            $output->writeln($encoded);
        }

        return $result->exitCode;
    }

    private function accepted(OperationConsoleInvocationResult $result, OutputInterface $output): int
    {
        /** @var string $operationId */
        $operationId = $result->payload['operationId'];
        $output->writeln('Accepted operation ' . $operationId . '.');

        return $result->exitCode;
    }

    private function rejected(OperationConsoleInvocationResult $result, OutputInterface $output): int
    {
        /** @var string $category */
        $category = $result->payload['category'];
        /** @var string $code */
        $code = $result->payload['code'];
        $line = 'Rejected [' . $category . ':' . $code . ']';
        if (array_key_exists('operationId', $result->payload)) {
            /** @var string $operationId */
            $operationId = $result->payload['operationId'];
            $line .= ' operation ' . $operationId;
        }
        $output->writeln($line . '.');
        /** @var list<array{field: string, rule: string, code: string}> $violations */
        $violations = $result->payload['violations'];
        foreach ($violations as $violation) {
            $output->writeln(sprintf('  %s: %s (%s)', $violation['field'], $violation['rule'], $violation['code']));
        }

        return $result->exitCode;
    }

    private function internal(OperationConsoleInvocationResult $result, OutputInterface $output): int
    {
        $line = 'Operation failed [internal_error]';
        if (array_key_exists('operationId', $result->payload)) {
            /** @var string $operationId */
            $operationId = $result->payload['operationId'];
            $line .= ' operation ' . $operationId;
        }
        $output->writeln($line . '.');

        return $result->exitCode;
    }
}
