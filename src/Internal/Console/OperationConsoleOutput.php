<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use JsonException;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class OperationConsoleOutput
{
    public function __construct(
        private OperationConsolePayloadValidator $validator = new OperationConsolePayloadValidator(),
        private OperationConsoleHumanOutput $human = new OperationConsoleHumanOutput(),
    ) {}

    /** @mago-expect lint:no-boolean-flag-parameter */
    public function write(OperationConsoleInvocationResult $result, bool $json, OutputInterface $output): int
    {
        if (!$this->validator->valid($result->payload)) {
            return $this->internal($json, $output);
        }
        if ($json) {
            return $this->json($result, $output);
        }

        return $this->human->write($result, $output);
    }

    private function json(OperationConsoleInvocationResult $result, OutputInterface $output): int
    {
        try {
            $encoded = json_encode($result->payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $output->writeln('{"schemaVersion":1,"status":"error","code":"internal_error"}');

            return 1;
        }
        $output->writeln($encoded);

        return $result->exitCode;
    }

    /** @mago-expect lint:no-boolean-flag-parameter */
    private function internal(bool $json, OutputInterface $output): int
    {
        $output->writeln(
            $json
                ? '{"schemaVersion":1,"status":"error","code":"internal_error"}'
                : 'Operation failed [internal_error].',
        );

        return 1;
    }
}
