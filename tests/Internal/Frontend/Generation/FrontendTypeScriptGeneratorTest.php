<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Frontend\Generation;

use BlackOps\Internal\Frontend\FrontendContractManifest;
use BlackOps\Internal\Frontend\FrontendContractManifestArtifact;
use BlackOps\Internal\Frontend\FrontendContractManifestCodec;
use BlackOps\Internal\Frontend\FrontendOperationContract;
use BlackOps\Internal\Frontend\FrontendOutcomeContract;
use BlackOps\Internal\Frontend\FrontendOutcomeFieldContract;
use BlackOps\Internal\Frontend\FrontendValueContract;
use BlackOps\Internal\Frontend\FrontendValueFieldContract;
use BlackOps\Internal\Frontend\Generation\FrontendGenerationMarker;
use BlackOps\Internal\Frontend\Generation\FrontendTypeScriptGenerator;
use BlackOps\Tests\Fixtures\Frontend\FrontendContractFixture;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FrontendTypeScriptGeneratorTest extends TestCase
{
    public function testGeneratesDeterministicFrameworkNeutralOperationObjectTree(): void
    {
        $generator = new FrontendTypeScriptGenerator();
        $first = $generator->generate(FrontendContractFixture::artifact());
        $second = $generator->generate(FrontendContractFixture::artifact());

        self::assertSame($first->files, $second->files);
        self::assertSame(
            [
                'client.ts',
                'manifest.json',
                'operations/order/create-order.ts',
                'types.ts',
            ],
            array_keys($first->files),
        );

        $marker = FrontendGenerationMarker::decode($first->files['manifest.json']);
        self::assertSame('frontend-generation-build', $marker->applicationBuildId);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $marker->contractHash);
        self::assertStringContainsString('"schemaVersion": 2', $first->files['manifest.json']);

        $operation = $first->files['operations/order/create-order.ts'];
        self::assertStringContainsString('export const CreateOrder = Object.freeze({', $operation);
        self::assertStringContainsString('type: "order.create" as const', $operation);
        self::assertStringContainsString('method: "POST" as const', $operation);
        self::assertStringContainsString('path: "/accounts/{accountId}/orders" as const', $operation);
        self::assertStringContainsString('strategy: "inline" as const', $operation);
        self::assertStringContainsString('url(parameters: CreateOrderUrlParameters): string', $operation);
        self::assertStringContainsString(
            'toRequest(value: CreateOrderValue, options?: OperationRequestOptions)',
            $operation,
        );
        self::assertStringContainsString('fetch(value: CreateOrderValue, options?: OperationCallOptions)', $operation);
        self::assertStringContainsString('Promise<CreateOrderResult>', $operation);
        self::assertStringContainsString('export type CreateOrderOutcome', $operation);
        self::assertStringContainsString('export type CreateOrderField =', $operation);
        self::assertStringContainsString('export type CreateOrderResult =', $operation);
    }

    public function testGeneratesValueAndUrlTypesFromPropertyNamesAndBindingSources(): void
    {
        $operation = new FrontendTypeScriptGenerator()->generate(
            FrontendContractFixture::artifact(),
        )->files['operations/order/create-order.ts'];

        self::assertStringContainsString('readonly accountId: number;', $operation);
        self::assertStringContainsString('readonly filter?: string | null;', $operation);
        self::assertStringContainsString('readonly requestToken: string;', $operation);
        self::assertStringContainsString('export type CreateOrderUrlParameters', $operation);
        self::assertSame(2, substr_count($operation, 'readonly accountId: number;'));
        self::assertSame(2, substr_count($operation, 'readonly active: boolean;'));
        self::assertSame(2, substr_count($operation, 'readonly filter?: string | null;'));
        self::assertStringNotContainsString('readonly requestToken:', substr(
            $operation,
            strpos($operation, 'export type CreateOrderUrlParameters'),
            strpos($operation, 'const bindings') - strpos($operation, 'export type CreateOrderUrlParameters'),
        ));
        self::assertStringContainsString('"transportName":"X-Request-Token"', $operation);
        self::assertStringContainsString('"transportName":"amount"', $operation);
    }

    public function testGeneratedClientOwnsCanonicalScalarHeaderBodyAndBaseUrlSafety(): void
    {
        $client = new FrontendTypeScriptGenerator()->generate(FrontendContractFixture::artifact())->files['client.ts'];

        foreach ([
            'Number.isSafeInteger(value)',
            'Number.isFinite(value)',
            "return scalar ? 'true' : 'false'",
            "headers['Content-Type'] = 'application/json'",
            "const hasBody = bindings.some((binding): boolean => binding.source === 'body')",
            'protectedHeaders.has(name.toLowerCase())',
            'protectedHeaders.add(binding.transportName.toLowerCase())',
            'assertHeader(binding.transportName, headerValue)',
            'if (fieldValue === null && binding.nullable)',
            "if (!binding.nullable || binding.source === 'path')",
            "binding.source === 'path' || binding.source === 'query'",
            "throw new Error('Operation path contains an unresolved parameter.')",
            "authority.includes('@')",
            'number >= 0 && number <= 65535',
            '/%(?![0-9a-f]{2})/i',
        ] as $contract) {
            self::assertStringContainsString($contract, $client);
        }
        self::assertLessThan(
            strpos($client, 'const fieldValue = readValue(value, binding);'),
            strpos($client, 'protectedHeaders.add(binding.transportName.toLowerCase());'),
        );

        self::assertStringNotContainsString('local-example', $client);
        self::assertStringNotContainsString('default-must-not-appear', $client);
        self::assertStringNotContainsString('credential-secret', $client);
        self::assertStringNotContainsString('RequestInit', $client);
        self::assertStringNotContainsString('AbortSignal', $client);
        self::assertStringNotContainsString('hasBody = true', $client);
        self::assertStringContainsString("\n  const headers: Record<string, string> = {};", $client);
        self::assertStringContainsString(
            "\n  for (const binding of bindings) {\n    if (binding.source === 'header')",
            $client,
        );
        self::assertStringContainsString("\n      assertHeader(binding.transportName, headerValue);", $client);
        self::assertStringNotContainsString("\nconst headers: Record<string, string> = {};", $client);
    }

    public function testGeneratedClientSerializesEmptyObjectWhenOnlyOptionalBodyBindingsAreOmitted(): void
    {
        $client = new FrontendTypeScriptGenerator()->generate(FrontendContractFixture::artifact())->files['client.ts'];

        $bodyDefinition = strpos(
            $client,
            "const hasBody = bindings.some((binding): boolean => binding.source === 'body');",
        );
        $bodyBranch = strpos($client, "if (binding.source === 'body') {", $bodyDefinition);
        $optionalOmission = strpos($client, "if (fieldValue === undefined) {\n        continue;", $bodyBranch);
        $contentType = strpos($client, "headers['Content-Type'] = 'application/json';", $optionalOmission);
        $serialization = strpos($client, 'request.body = JSON.stringify(body);', $contentType);

        self::assertIsInt($bodyDefinition);
        self::assertIsInt($bodyBranch);
        self::assertIsInt($optionalOmission);
        self::assertIsInt($contentType);
        self::assertIsInt($serialization);
        self::assertLessThan($bodyBranch, $bodyDefinition);
        self::assertLessThan($optionalOmission, $bodyBranch);
        self::assertLessThan($contentType, $optionalOmission);
        self::assertLessThan($serialization, $contentType);
    }

    public function testGeneratedBaseUrlContractRejectsUnsafeAuthorityAndSuffixForms(): void
    {
        $client = new FrontendTypeScriptGenerator()->generate(FrontendContractFixture::artifact())->files['client.ts'];

        foreach ([
            'query and fragment' => '/^(https?):\\/\\/([^/?#]+)(\\/[^?#]*)?$/i',
            'credential' => "authority.includes('@')",
            'malformed authority' => '/[\\s\\\\]/.test(authority)',
            'invalid port' => 'number >= 0 && number <= 65535',
            'malformed path escape' => '/%(?![0-9a-f]{2})/i',
            'malformed ipv6' => "address.includes(':::')",
        ] as $boundary => $guard) {
            self::assertStringContainsString($guard, $client, $boundary);
        }
        self::assertStringContainsString(<<<'TYPESCRIPT'
            return `${baseUrl.replace(/\/+$/, '')}/${relativeUrl.replace(/^\/+/, '')}`;
            TYPESCRIPT, $client);
    }

    public function testGeneratesEmptyValueAndArgumentlessUrlWithoutChangingCallSignature(): void
    {
        $operation = new FrontendOperationContract(
            'welcome.show',
            'App\\ShowWelcome',
            'ShowWelcome',
            'operations/welcome/show-welcome.ts',
            'GET',
            '/welcome',
            'inline',
            new FrontendValueContract('App\\WelcomeValue', []),
            new FrontendOutcomeContract('BlackOps\\Core\\EmptyOutcome', 'void', []),
        );
        $source = new FrontendTypeScriptGenerator()->generate(
            new FrontendContractManifestArtifact(
                FrontendContractManifestCodec::SCHEMA_VERSION,
                'empty-build',
                new FrontendContractManifest([$operation]),
            ),
        )->files[$operation->module];

        self::assertStringContainsString('export type ShowWelcomeValue = Readonly<Record<never, never>>;', $source);
        self::assertStringContainsString('url(): string', $source);
        self::assertStringContainsString('toRequest(value: ShowWelcomeValue', $source);
        self::assertStringContainsString('export type ShowWelcomeOutcome = undefined;', $source);
        self::assertStringContainsString('export type ShowWelcomeField = never;', $source);
        self::assertStringContainsString(
            'export type ShowWelcomeResult = InlineVoidOperationResult<ShowWelcomeField>;',
            $source,
        );
        self::assertStringContainsString('mode: "inline_void" as const', $source);
        self::assertStringContainsString('Promise<ShowWelcomeResult>', $source);
    }

    public function testGeneratesStructuralFetchAndNarrowableCommonResultTypes(): void
    {
        $types = new FrontendTypeScriptGenerator()->generate(FrontendContractFixture::artifact())->files['types.ts'];

        foreach ([
            'export type OperationFetchHeaders',
            'get(name: string): string | null',
            'export type OperationFetchResponse',
            'text(): Promise<string>',
            'export type OperationFetchRequest',
            'export type OperationFetch = (',
            'export type OperationCallOptions = OperationRequestOptions',
            "kind: 'completed'; status: 200",
            "kind: 'completed'; status: 204",
            "kind: 'accepted'; status: 202",
            "kind: 'protocol'; status: 400",
            "kind: 'rejected'",
            "kind: 'validation'",
            "kind: 'internal'; status: 500",
            "kind: 'transport'",
            'status: null',
            "'missing_fetch' | 'invalid_base_url' | 'network_error' | 'aborted' | 'unexpected_response'",
        ] as $contract) {
            self::assertStringContainsString($contract, $types);
        }
        foreach (['Window', 'RequestInit', 'NodeJS', 'react', 'vue', 'svelte'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $types);
        }
    }

    public function testGeneratesOperationSpecificOutcomeFieldAndResultContracts(): void
    {
        $operation = new FrontendOperationContract(
            'report.create',
            'App\\CreateReport',
            'CreateReport',
            'operations/report/create-report.ts',
            'POST',
            '/reports',
            'inline',
            new FrontendValueContract('App\\CreateReportValue', [
                new FrontendValueFieldContract('reference', 'string', false, true, 'body', 'reference', false, []),
                new FrontendValueFieldContract('attempt', 'integer', true, false, 'body', 'attempt', false, []),
            ]),
            new FrontendOutcomeContract('App\\ReportCreated', 'outcome', [
                new FrontendOutcomeFieldContract('reportId', 'string', false),
                new FrontendOutcomeFieldContract('sequence', 'integer', false),
                new FrontendOutcomeFieldContract('ratio', 'float', true),
                new FrontendOutcomeFieldContract('visible', 'boolean', false),
            ]),
        );
        $source = new FrontendTypeScriptGenerator()->generate(
            new FrontendContractManifestArtifact(
                FrontendContractManifestCodec::SCHEMA_VERSION,
                'result-build',
                new FrontendContractManifest([$operation]),
            ),
        )->files[$operation->module];

        self::assertStringContainsString('readonly reportId: string;', $source);
        self::assertStringContainsString('readonly sequence: number;', $source);
        self::assertStringContainsString('readonly ratio: number | null;', $source);
        self::assertStringContainsString('readonly visible: boolean;', $source);
        self::assertStringContainsString('export type CreateReportField = "reference" | "attempt";', $source);
        self::assertStringContainsString(
            'InlineOutcomeOperationResult<CreateReportOutcome, CreateReportField>',
            $source,
        );
        self::assertStringContainsString('mode: "inline_outcome" as const', $source);
        self::assertStringContainsString('Object.freeze({"name":"ratio","type":"float","nullable":true})', $source);
        self::assertStringContainsString('return fetchOperation<CreateReportOutcome, CreateReportField>(', $source);
    }

    public function testGeneratesDeferredResultWithoutInlineSuccessVariants(): void
    {
        $operation = new FrontendOperationContract(
            'report.generate',
            'App\\GenerateReport',
            'GenerateReport',
            'operations/report/generate-report.ts',
            'POST',
            '/reports',
            'deferred',
            new FrontendValueContract('App\\GenerateReportValue', []),
            new FrontendOutcomeContract('App\\ReportGenerated', 'outcome', [
                new FrontendOutcomeFieldContract('reportId', 'string', false),
            ]),
        );
        $source = new FrontendTypeScriptGenerator()->generate(
            new FrontendContractManifestArtifact(
                FrontendContractManifestCodec::SCHEMA_VERSION,
                'deferred-build',
                new FrontendContractManifest([$operation]),
            ),
        )->files[$operation->module];

        self::assertStringContainsString(
            'export type GenerateReportResult = DeferredOperationResult<GenerateReportField>;',
            $source,
        );
        self::assertStringContainsString('mode: "deferred" as const', $source);
        self::assertStringNotContainsString('outcomeFields:', $source);
        self::assertStringNotContainsString('InlineOutcomeOperationResult', $source);
        self::assertStringNotContainsString('InlineVoidOperationResult', $source);
    }

    public function testGeneratedClientStrictlyDecodesHttpResponseAndOperationFields(): void
    {
        $client = new FrontendTypeScriptGenerator()->generate(FrontendContractFixture::artifact())->files['client.ts'];

        foreach ([
            'response.status === 204',
            "contract.mode === 'inline_void' && rawBody === ''",
            "response.status === 200 && contract.mode === 'inline_outcome'",
            "response.status === 202 && contract.mode === 'deferred'",
            "response.status === 400 && payload.status === 'error'",
            '[400, 401, 403, 404, 409].includes(response.status)',
            'response.status === 422',
            'response.status === 500',
            "payload.status !== 'accepted'",
            "payload.category !== 'validation'",
            "payload.code !== 'validation.failed'",
            "payload.code !== 'internal_error'",
            "status === 401 && payload.status === 'error'",
            "case 400: return 'business_rule'",
            "case 401: return 'unauthorized'",
            "case 403: return 'forbidden'",
            "case 404: return 'not_found'",
            "case 409: return 'conflict'",
            'Number.isSafeInteger(value)',
            'Number.isFinite(value)',
            'hasExactKeys',
            'knownFields.has(violation.field)',
            "typeof value === 'string'",
            "value.split(';', 1)[0].trim().toLowerCase() === 'application/json'",
            'snapshotOperationFetchResponse(received)',
            'getHeader(name: string): unknown',
            'readBody(): Promise<unknown>',
            'getHeader: (name: string): unknown => readHeader.call(headers, name)',
            'readBody: async (): Promise<unknown> => readBody.call(value)',
        ] as $contract) {
            self::assertStringContainsString($contract, $client);
        }
        self::assertStringContainsString(
            "function snapshotOperationFetchResponse(\n  value: unknown,\n): OperationFetchResponseSnapshot | undefined {\n  try {",
            $client,
        );
    }

    public function testGeneratedClientSeparatesTransportErrorsFromProgrammerErrors(): void
    {
        $client = new FrontendTypeScriptGenerator()->generate(FrontendContractFixture::artifact())->files['client.ts'];

        foreach ([
            'error instanceof InvalidOperationBaseUrlError',
            "transportResult('invalid_base_url')",
            "transportResult('missing_fetch')",
            "options.signal?.aborted === true ? 'aborted' : 'network_error'",
            "transportResult('network_error')",
            "transportResult('unexpected_response')",
            'throw error;',
            'const runtime = globalThis as unknown as { fetch?: unknown }',
            'received = await operationFetch(request.url, Object.freeze(fetchRequest))',
        ] as $contract) {
            self::assertStringContainsString($contract, $client);
        }
        foreach (['retry', 'backoff', 'poll', 'credential-secret', 'sensitive-value'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, strtolower($client));
        }
        self::assertStringNotContainsString('message:', $client);
        self::assertStringNotContainsString('stack:', $client);
    }

    public function testRejectsTraversalAndInvalidOrDuplicatePathBindingMetadata(): void
    {
        foreach ([
            $this->operation(module: 'operations/../escape.ts'),
            $this->operation(path: '/orders/{missing}'),
            $this->operation(path: '/orders/{id}/{id}'),
            $this->operation(fields: [
                new FrontendValueFieldContract('id', 'integer', true, true, 'path', 'id', false, []),
            ]),
        ] as $operation) {
            try {
                new FrontendTypeScriptGenerator()->generate(
                    new FrontendContractManifestArtifact(
                        FrontendContractManifestCodec::SCHEMA_VERSION,
                        'invalid-build',
                        new FrontendContractManifest([$operation]),
                    ),
                );
                self::fail('Invalid frontend generation metadata was accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    /** @param list<FrontendValueFieldContract>|null $fields */
    private function operation(
        string $module = 'operations/order/create-order.ts',
        string $path = '/orders/{id}',
        ?array $fields = null,
    ): FrontendOperationContract {
        return new FrontendOperationContract(
            'order.create',
            'App\\CreateOrder',
            'CreateOrder',
            $module,
            'POST',
            $path,
            'inline',
            new FrontendValueContract(
                'App\\CreateOrderValue',
                $fields ?? [
                    new FrontendValueFieldContract('id', 'integer', false, true, 'path', 'id', false, []),
                ],
            ),
            new FrontendOutcomeContract('App\\OrderCreated', 'outcome', []),
        );
    }
}
