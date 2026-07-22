<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Frontend;

use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Http\Attribute\Route;
use BlackOps\Http\Routing\HttpOperationManifestArtifact;
use BlackOps\Http\Routing\HttpOperationManifestArtifactCodec;
use BlackOps\Http\Routing\HttpRouteCompiler;
use BlackOps\Internal\Frontend\FrontendContractCompiler;
use BlackOps\Internal\Frontend\FrontendContractManifestArtifact;
use BlackOps\Internal\Frontend\FrontendContractManifestCodec;
use BlackOps\Internal\Frontend\Generation\FrontendTypeScriptGenerator;
use BlackOps\Internal\Registry\OperationManifestArtifact;
use BlackOps\Internal\Registry\OperationManifestFile;
use BlackOps\Internal\Registry\OperationMetadataCompiler;
use PHPUnit\Framework\TestCase;

final class EphemeralFrontendContractTest extends TestCase
{
    public function testCompilesAndGeneratesFetchOnlyEphemeralOperation(): void
    {
        $metadata = new OperationMetadataCompiler()->compile(FrontendLoginOperation::class);
        $registry = new OperationRegistry([$metadata]);
        $http = new HttpRouteCompiler($registry)->compileManifest([FrontendLoginOperation::class]);
        $manifest = new FrontendContractCompiler()->compile(
            new OperationManifestArtifact(OperationManifestFile::SCHEMA_VERSION, 'ephemeral-build', $registry),
            new HttpOperationManifestArtifact(
                HttpOperationManifestArtifactCodec::SCHEMA_VERSION,
                'ephemeral-build',
                $http,
            ),
        );

        self::assertTrue($manifest->operations[0]->ephemeral);
        self::assertSame(
            ['expiresAt', 'token'],
            array_map(static fn($field): string => $field->name, $manifest->operations[0]->outcome->fields),
        );

        $codec = new FrontendContractManifestCodec();
        $encoded = $codec->encode($manifest, 'ephemeral-build');
        self::assertTrue($encoded['payload']['operations'][0]['ephemeral']);
        self::assertStringNotContainsString('raw-secret-must-not-appear', var_export($encoded, true));
        $decoded = $codec->decode($encoded);
        self::assertTrue($decoded->manifest->operations[0]->ephemeral);

        $tree = new FrontendTypeScriptGenerator()->generate(
            new FrontendContractManifestArtifact(
                FrontendContractManifestCodec::SCHEMA_VERSION,
                'ephemeral-build',
                $decoded->manifest,
            ),
        );
        $operation = $tree->files['operations/auth/frontend-login-operation.ts'];
        self::assertStringContainsString('fetch(value: FrontendLoginOperationValue', $operation);
        self::assertStringContainsString('toRequest(value: FrontendLoginOperationValue', $operation);
        self::assertStringContainsString('url(): string', $operation);
        self::assertStringNotContainsString('status(operationId', $operation);
        self::assertStringNotContainsString('wait(operationId', $operation);
        self::assertStringNotContainsString('FrontendLoginOperationStatusResult', $operation);
        self::assertStringNotContainsString('FrontendLoginOperationWaitResult', $operation);

        $index = $tree->files['index.ts'];
        self::assertStringContainsString('BoundFrontendLoginOperationOperation', $index);
        self::assertStringNotContainsString('FrontendLoginOperationStatusResult', $index);
        self::assertStringNotContainsString('FrontendLoginOperationWaitResult', $index);
        self::assertStringNotContainsString('FrontendLoginOperation.status', $index);
        self::assertStringNotContainsString('FrontendLoginOperation.wait', $index);
    }
}

final readonly class FrontendLoginValue implements OperationValue
{
    public function __construct(
        public string $email,
        #[Sensitive]
        public string $password,
    ) {}
}

final readonly class FrontendTokenIssued implements EphemeralOutcome
{
    public function __construct(
        #[Sensitive]
        public string $token,
        public string $expiresAt,
    ) {}
}

#[OperationType('auth.login')]
#[Route('POST', '/auth/login')]
#[ExecuteWith(Inline::class)]
final readonly class FrontendLoginOperation implements Operation
{
    public function handle(FrontendLoginValue $value): FrontendTokenIssued
    {
        return new FrontendTokenIssued('raw-secret-must-not-appear', 'later');
    }
}
