<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Frontend;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Validation\Attribute\Choice;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Core\Validation\Attribute\Range;
use BlackOps\Http\Attribute\FromHeader;
use BlackOps\Http\Attribute\FromPath;
use BlackOps\Http\Attribute\FromQuery;
use BlackOps\Http\Routing\HttpOperationManifest;
use BlackOps\Http\Routing\HttpOperationManifestArtifact;
use BlackOps\Http\Routing\HttpOperationManifestArtifactCodec;
use BlackOps\Http\Routing\HttpRouteCompiler;
use BlackOps\Internal\Frontend\FrontendContractCompiler;
use BlackOps\Internal\Frontend\FrontendContractManifestCodec;
use BlackOps\Internal\Registry\OperationManifestArtifact;
use BlackOps\Internal\Registry\OperationManifestFile;
use BlackOps\Internal\Registry\OperationMetadataCompiler;
use BlackOps\Tests\Fixtures\Frontend\One\CollisionOperation as FirstCollisionOperation;
use BlackOps\Tests\Fixtures\Frontend\Two\CollisionOperation as SecondCollisionOperation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FrontendContractCompilerTest extends TestCase
{
    public function testCompilesTheFourQuickstartHttpOperations(): void
    {
        $root = dirname(__DIR__, 3) . '/examples/quickstart';
        $loader = static function (string $class) use ($root): void {
            if (!str_starts_with($class, 'App\\')) {
                return;
            }
            $path = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        };
        spl_autoload_register($loader);

        try {
            $definitions = [
                \App\Feature\Diagnostics\TriggerFailure\TriggerFailure::class,
                \App\Feature\Order\CreateOrder\CreateOrder::class,
                \App\Feature\Report\GenerateReport\GenerateReport::class,
                \App\Feature\Welcome\ShowWelcome\ShowWelcome::class,
            ];
            $metadata =
                new OperationMetadataCompiler(defaultTransactionConnection: 'app', knownTransactionConnections: [
                    'app',
                ]);
            $registry = new OperationRegistry(array_map($metadata->compile(...), $definitions));
            $http = new HttpRouteCompiler($registry)->compileManifest($definitions);
            $manifest = new FrontendContractCompiler()->compile(
                $this->artifact($registry),
                new HttpOperationManifestArtifact(
                    HttpOperationManifestArtifactCodec::SCHEMA_VERSION,
                    'frontend-build',
                    $http,
                ),
            );

            self::assertSame(
                [
                    'diagnostics.failure.trigger',
                    'order.create',
                    'report.generate',
                    'welcome.show',
                ],
                array_map(static fn($operation): string => $operation->typeId, $manifest->operations),
            );
            self::assertTrue($manifest->operations[0]->value->fields[1]->sensitive);
            self::assertSame('deferred', $manifest->operations[2]->strategy);
            self::assertSame('inline', $manifest->operations[3]->strategy);
            $artifact = var_export(
                new FrontendContractManifestCodec()->encode($manifest, 'quickstart-frontend-build'),
                true,
            );
            self::assertStringNotContainsString('credential', $artifact);
            self::assertStringNotContainsString('local-example', $artifact);
            self::assertStringNotContainsString('sensitive-', $artifact);
            self::assertStringNotContainsString($root, $artifact);
        } finally {
            spl_autoload_unregister($loader);
        }
    }

    public function testCompilesDeterministicHttpContractsAndExcludesRouteLessOperations(): void
    {
        $operations = new OperationRegistry([
            $this->metadata(FrontendCreateOrder::class, FrontendCreateValue::class, FrontendCreated::class),
            $this->metadata(
                FrontendDeferredReport::class,
                FrontendEmptyValue::class,
                EmptyOutcome::class,
                Deferred::class,
                'report.generate',
            ),
            $this->metadata(
                FrontendInternalOperation::class,
                FrontendEmptyValue::class,
                EmptyOutcome::class,
                Inline::class,
                'internal.only',
            ),
        ]);
        $http = $this->http([
            'POST' => [
                '/reports' => 'report.generate',
                '/orders/{orderId}' => 'order.create',
            ],
        ], [
            'report.generate' => [
                'definition' => FrontendDeferredReport::class,
                'value' => FrontendEmptyValue::class,
                'strategy' => Deferred::class,
            ],
            'order.create' => [
                'definition' => FrontendCreateOrder::class,
                'value' => FrontendCreateValue::class,
                'outcome' => FrontendCreated::class,
            ],
        ]);

        $manifest = new FrontendContractCompiler()->compile($this->artifact($operations), $http);
        self::assertSame(
            ['order.create', 'report.generate'],
            array_map(static fn($operation): string => $operation->typeId, $manifest->operations),
        );

        $order = $manifest->operations[0];
        self::assertSame('FrontendCreateOrder', $order->exportName);
        self::assertSame('operations/order/frontend-create-order.ts', $order->module);
        self::assertSame('inline', $order->strategy);
        self::assertSame('outcome', $order->outcome->mode);
        self::assertSame(
            ['created', 'note', 'total'],
            array_map(static fn($field): string => $field->name, $order->outcome->fields),
        );
        self::assertSame(
            ['boolean', 'string', 'number'],
            array_map(static fn($field): string => $field->type, $order->outcome->fields),
        );

        $fields = [];
        foreach ($order->value->fields as $field) {
            $fields[$field->name] = $field;
        }
        self::assertSame(['path', 'orderId'], [$fields['id']->source, $fields['id']->transportName]);
        self::assertSame(['header', 'X-Request-ID'], [
            $fields['requestId']->source,
            $fields['requestId']->transportName,
        ]);
        self::assertSame(['query', 'filter'], [$fields['filter']->source, $fields['filter']->transportName]);
        self::assertFalse($fields['filter']->required);
        self::assertTrue($fields['filter']->nullable);
        self::assertTrue($fields['secret']->sensitive);
        self::assertSame(
            ['choice'],
            array_map(static fn($validation): string => $validation->rule, $fields['state']->validations),
        );
        self::assertSame(
            ['length', 'not_blank'],
            array_map(static fn($validation): string => $validation->rule, $fields['reference']->validations),
        );
        self::assertSame(['max' => 64], $fields['reference']->validations[0]->parameters);
        self::assertSame(['max' => 5000, 'min' => 1], $fields['total']->validations[0]->parameters);

        $report = $manifest->operations[1];
        self::assertSame('deferred', $report->strategy);
        self::assertSame('void', $report->outcome->mode);
        self::assertSame([], $report->outcome->fields);

        $encoded = var_export(new FrontendContractManifestCodec()->encode($manifest, 'frontend-build'), true);
        self::assertStringNotContainsString('default-must-not-appear', $encoded);
        self::assertStringNotContainsString('credential', $encoded);
        self::assertStringNotContainsString(__FILE__, $encoded);
    }

    public function testRejectsBuildIdAndMetadataMismatch(): void
    {
        $registry = new OperationRegistry([$this->metadata(
            FrontendCreateOrder::class,
            FrontendCreateValue::class,
            FrontendCreated::class,
        )]);
        $http = $this->http(
            ['POST' => ['/orders/{orderId}' => 'order.create']],
            [
                'order.create' => [
                    'definition' => FrontendCreateOrder::class,
                    'value' => FrontendEmptyValue::class,
                ],
            ],
            'different-build',
        );

        try {
            new FrontendContractCompiler()->compile($this->artifact($registry), $http);
            self::fail('Expected build ID mismatch.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('build IDs', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('metadata do not match');
        new FrontendContractCompiler()->compile(
            $this->artifact($registry),
            $this->http(['POST' => ['/orders/{orderId}' => 'order.create']], [
                'order.create' => [
                    'definition' => FrontendCreateOrder::class,
                    'value' => FrontendEmptyValue::class,
                ],
            ]),
        );
    }

    public function testRejectsHandlerOutcomeAndStrategyMetadataMismatch(): void
    {
        $metadata = $this->metadata(FrontendCreateOrder::class, FrontendCreateValue::class, FrontendCreated::class);

        foreach (['handler', 'outcome', 'strategy'] as $field) {
            $httpMetadata = [
                'definition' => $metadata->definition,
                'value' => $metadata->value,
                'handler' => $metadata->handler,
                'outcome' => $metadata->outcome,
                'strategy' => $metadata->strategy,
            ];
            $httpMetadata[$field] = FrontendEmptyValue::class;

            try {
                new FrontendContractCompiler()->compile(
                    $this->artifact(new OperationRegistry([$metadata])),
                    $this->http(['POST' => ['/orders/{orderId}' => 'order.create']], [
                        'order.create' => $httpMetadata,
                    ]),
                );
                self::fail('Expected duplicated HTTP metadata mismatch.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('metadata do not match', $exception->getMessage());
            }
        }
    }

    public function testRejectsUnsupportedTypeSensitiveOutcomeAndNamingCollision(): void
    {
        foreach ([
            [
                $this->metadata(FrontendArrayOperation::class, FrontendArrayValue::class, EmptyOutcome::class),
                'scalar type',
            ],
            [
                $this->metadata(
                    FrontendSensitiveOutcomeOperation::class,
                    FrontendEmptyValue::class,
                    FrontendSensitiveOutcome::class,
                    Inline::class,
                    'sensitive.outcome',
                ),
                'sensitive outcome',
            ],
        ] as [$metadata, $message]) {
            $typeId = $metadata->typeId;
            try {
                new FrontendContractCompiler()->compile(
                    $this->artifact(new OperationRegistry([$metadata])),
                    $this->http(['POST' => ['/test' => $typeId]], [
                        $typeId => [
                            'definition' => $metadata->definition,
                            'value' => $metadata->value,
                            'outcome' => $metadata->outcome,
                        ],
                    ]),
                );
                self::fail('Expected unsupported frontend contract.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString($message, $exception->getMessage());
            }
        }

        $left = $this->metadata(
            FirstCollisionOperation::class,
            FrontendEmptyValue::class,
            EmptyOutcome::class,
            Inline::class,
            'collision.left',
        );
        $right = $this->metadata(
            SecondCollisionOperation::class,
            FrontendEmptyValue::class,
            EmptyOutcome::class,
            Inline::class,
            'collision.right',
        );
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('collides');
        new FrontendContractCompiler()->compile(
            $this->artifact(new OperationRegistry([$left, $right])),
            $this->http(['POST' => ['/left' => 'collision.left', '/right' => 'collision.right']], [
                'collision.left' => ['definition' => $left->definition, 'value' => $left->value],
                'collision.right' => ['definition' => $right->definition, 'value' => $right->value],
            ]),
        );
    }

    private function artifact(OperationRegistry $registry): OperationManifestArtifact
    {
        return new OperationManifestArtifact(OperationManifestFile::SCHEMA_VERSION, 'frontend-build', $registry);
    }

    /**
     * @param array<string, array<string, string>> $routes
     * @param array<string, array<string, string>> $operations
     */
    private function http(
        array $routes,
        array $operations,
        string $buildId = 'frontend-build',
    ): HttpOperationManifestArtifact {
        foreach ($operations as $typeId => $metadata) {
            $operations[$typeId] = [
                ...$metadata,
                'handler' => $metadata['handler'] ?? $metadata['definition'],
                'outcome' => $metadata['outcome'] ?? EmptyOutcome::class,
                'strategy' => $metadata['strategy'] ?? Inline::class,
            ];
        }

        return new HttpOperationManifestArtifact(
            HttpOperationManifestArtifactCodec::SCHEMA_VERSION,
            $buildId,
            new HttpOperationManifest($routes, $operations, [[], []]),
        );
    }

    /**
     * @param class-string<Operation> $definition
     * @param class-string<OperationValue> $value
     * @param class-string<Outcome> $outcome
     * @param class-string<\BlackOps\Core\Execution\ExecutionStrategy> $strategy
     */
    private function metadata(
        string $definition,
        string $value,
        string $outcome,
        string $strategy = Inline::class,
        string $typeId = 'order.create',
    ): OperationMetadata {
        return new OperationMetadata($typeId, $definition, $value, $definition, $outcome, $strategy);
    }
}

final readonly class FrontendCreateValue implements OperationValue
{
    public function __construct(
        #[FromPath('orderId')]
        public string $id,
        #[FromHeader('X-Request-ID')]
        public string $requestId,
        #[NotBlank]
        #[Length(max: 64)]
        public string $reference,
        #[Range(min: 1, max: 5000)]
        public float $total,
        #[Choice(['draft', 'confirmed'])]
        public string $state,
        #[FromQuery]
        public ?string $filter = 'default-must-not-appear',
        #[Sensitive]
        public string $secret = 'sensitive-default-must-not-appear',
    ) {}
}

final readonly class FrontendCreated implements Outcome
{
    public function __construct(
        public bool $created,
        public float $total,
        public ?string $note,
    ) {}
}

final readonly class FrontendEmptyValue implements OperationValue {}

final readonly class FrontendCreateOrder implements Operation {}

final readonly class FrontendDeferredReport implements Operation {}

final readonly class FrontendInternalOperation implements Operation {}

final readonly class FrontendArrayValue implements OperationValue
{
    public function __construct(
        public array $items,
    ) {}
}

final readonly class FrontendArrayOperation implements Operation {}

final readonly class FrontendSensitiveOutcome implements Outcome
{
    public function __construct(
        #[Sensitive]
        public string $token,
    ) {}
}

final readonly class FrontendSensitiveOutcomeOperation implements Operation {}
