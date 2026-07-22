<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Http\Routing\HttpOperationManifest;
use BlackOps\Internal\Application\ApplicationHttpRuntimeComposer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ApplicationHttpRuntimeComposerTest extends TestCase
{
    public function testRejectsMissingOrMismatchedEphemeralHttpMetadataAtBootstrap(): void
    {
        $metadata = new OperationMetadata(
            'application.ephemeral',
            ApplicationEphemeralOperation::class,
            ApplicationEphemeralValue::class,
            ApplicationEphemeralOperation::class,
            ApplicationEphemeralOutcome::class,
            Inline::class,
        );
        $registry = new OperationRegistry([$metadata]);

        foreach ([
            new HttpOperationManifest([], [], [[], []]),
            new HttpOperationManifest(
                ['POST' => ['/ephemeral' => 'application.ephemeral']],
                ['application.ephemeral' => [
                    'definition' => ApplicationEphemeralOperation::class,
                    'value' => ApplicationEphemeralValue::class,
                    'handler' => ApplicationEphemeralOperation::class,
                    'outcome' => ApplicationEphemeralOutcome::class,
                    'strategy' => Inline::class,
                    'ephemeral' => false,
                ]],
                [[], []],
            ),
        ] as $http) {
            try {
                new ReflectionMethod(ApplicationHttpRuntimeComposer::class, 'validateHttpOperations')->invoke(
                    new ApplicationHttpRuntimeComposer(),
                    $registry,
                    $http,
                );
                self::fail('Expected HTTP and operation manifest mismatch rejection.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('HTTP', $exception->getMessage());
            }
        }
    }
}

final readonly class ApplicationEphemeralOperation implements Operation {}

final readonly class ApplicationEphemeralValue implements OperationValue {}

final readonly class ApplicationEphemeralOutcome implements EphemeralOutcome {}
