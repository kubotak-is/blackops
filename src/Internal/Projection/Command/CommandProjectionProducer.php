<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\Command;

use BlackOps\Internal\Console\ApplicationCommandCollisionValidator;
use BlackOps\Internal\Console\ApplicationCommandMetadata;
use BlackOps\Internal\Console\FrameworkCommandNames;
use BlackOps\Internal\Console\OperationConsoleCommandMetadata;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetProjection;

final readonly class CommandProjectionProducer
{
    public function __construct(
        private ApplicationCommandCollisionValidator $validator = new ApplicationCommandCollisionValidator(),
        private ProjectionBuildSetCodec $codec = new ProjectionBuildSetCodec(),
    ) {}

    /**
     * @param list<ApplicationCommandMetadata> $explicit
     * @param list<ApplicationCommandMetadata> $discovered Result of collision validator merge().
     * @param list<OperationConsoleCommandMetadata> $operations
     */
    public function produce(
        string $applicationBuildId,
        array $explicit,
        array $discovered,
        array $operations,
    ): ProjectionBuildSetProjection {
        $frameworkNames = FrameworkCommandNames::all();
        $application = [...$explicit, ...$discovered];

        // Validate already-materialized metadata without rediscovering or changing its source order.
        $this->validator->merge($application, [], $frameworkNames);
        $this->validator->validateOperationCommands($application, $operations, $frameworkNames);

        $records = [];
        foreach (CommandProjectionTargetRegistry::all() as $target) {
            $records[] = $target->record('framework');
        }
        foreach ($application as $command) {
            $records[] = [
                'name' => $command->name,
                'aliases' => $command->aliases,
                'description' => $command->description ?? '',
                'origin' => 'application',
                'hidden' => $command->hidden,
            ];
        }
        foreach ($operations as $operation) {
            $records[] = [
                'name' => $operation->name,
                'aliases' => [],
                'description' => $operation->description,
                'origin' => 'application',
                'hidden' => false,
            ];
        }

        return $this->codec->projection(
            kind: 'blackops_command_projection',
            applicationBuildId: $applicationBuildId,
            records: $records,
        );
    }
}
