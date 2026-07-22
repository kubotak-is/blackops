<?php

declare(strict_types=1);

namespace BlackOps\Internal\Seeder;

use BlackOps\Database\Seeder;
use BlackOps\Database\SeederRunner;
use Psr\Container\ContainerInterface;
use Throwable;

final class CompiledSeederRunner implements SeederRunner
{
    /** @var array<string, true> */
    private array $active = [];

    public function __construct(
        private readonly ContainerInterface $seeders,
    ) {}

    public function run(string ...$seeders): void
    {
        foreach ($seeders as $seeder) {
            $this->runOne($seeder);
        }
    }

    private function runOne(string $seeder): void
    {
        if (array_key_exists($seeder, $this->active)) {
            throw new SeederRuntimeException('Seeder execution cycle detected.');
        }
        if (!$this->seeders->has($seeder)) {
            throw new SeederRuntimeException('Seeder is not available in the compiled application.');
        }

        $this->active[$seeder] = true;

        try {
            /** @var mixed $resolved */
            $resolved = $this->seeders->get($seeder);
            if (!$resolved instanceof Seeder) {
                throw new SeederRuntimeException('Compiled seeder service is invalid.');
            }

            $resolved->run();
        } catch (SeederRuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new SeederRuntimeException('Seeder execution failed.');
        } finally {
            unset($this->active[$seeder]);
        }
    }
}
