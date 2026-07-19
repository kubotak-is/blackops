<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Frontend\Generation;

use BlackOps\Internal\Frontend\FrontendContractManifest;
use BlackOps\Internal\Frontend\FrontendContractManifestArtifact;
use BlackOps\Internal\Frontend\FrontendContractManifestCodec;
use BlackOps\Internal\Frontend\FrontendOperationContract;
use BlackOps\Internal\Frontend\FrontendOutcomeContract;
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
        self::assertStringNotContainsString('fetch(', $operation);
        self::assertStringNotContainsString('Promise<', $operation);
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
        self::assertStringNotContainsString('fetch(', $client);
        self::assertStringNotContainsString('Promise<', $client);
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
