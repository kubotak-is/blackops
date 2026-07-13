<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Registry;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Internal\Registry\OperationManifestMetadataCodec;
use BlackOps\Internal\Registry\OperationMetadataCompiler;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OperationManifestMetadataCodecTest extends TestCase
{
    public function testRoundTripsTypedInvocationMetadata(): void
    {
        $metadata = new OperationMetadataCompiler()->compile(ManifestTypedOperation::class);
        $codec = new OperationManifestMetadataCodec();
        $decoded = $codec->decode($codec->encode(new OperationRegistry([$metadata])))[0];

        self::assertTrue($decoded->typedSelfHandled);
        self::assertTrue($decoded->typedSelfHandledContext);
        self::assertSame(ManifestValue::class, $decoded->value);
        self::assertSame('result', $decoded->typedSelfHandledMode);
    }

    public function testRejectsTypedSignatureAndManifestValueMismatch(): void
    {
        $data = $this->encoded();
        $data['operations'][0]['value'] = OtherManifestValue::class;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('accepted OperationValue');

        new OperationManifestMetadataCodec()->decode($data);
    }

    public function testRejectsAbstractTypedDefinitionFromManifest(): void
    {
        $data = $this->encoded();
        $data['operations'][0]['definition'] = AbstractManifestTypedOperation::class;
        $data['operations'][0]['handler'] = AbstractManifestTypedOperation::class;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('instantiable');

        new OperationManifestMetadataCodec()->decode($data);
    }

    public function testLoadsTypedMetadataFromManifestWithoutInvocationFlags(): void
    {
        $data = $this->encoded();
        unset(
            $data['operations'][0]['typedSelfHandled'],
            $data['operations'][0]['typedSelfHandledContext'],
            $data['operations'][0]['typedSelfHandledMode'],
        );

        $decoded = new OperationManifestMetadataCodec()->decode($data)[0];

        self::assertTrue($decoded->typedSelfHandled);
        self::assertTrue($decoded->typedSelfHandledContext);
        self::assertSame('result', $decoded->typedSelfHandledMode);
    }

    public function testRejectsTamperedTypedInvocationMode(): void
    {
        $data = $this->encoded();
        $data['operations'][0]['typedSelfHandledContext'] = false;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invocation metadata');

        new OperationManifestMetadataCodec()->decode($data);
    }

    public function testRejectsTamperedNativeOutcomeMode(): void
    {
        $data = $this->encoded();
        $data['operations'][0]['typedSelfHandledMode'] = 'void';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invocation metadata');

        new OperationManifestMetadataCodec()->decode($data);
    }

    public function testRejectsTamperedCompatibilityOutcome(): void
    {
        $data = $this->encoded();
        $data['operations'][0]['outcome'] = OtherManifestOutcome::class;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invocation metadata');

        new OperationManifestMetadataCodec()->decode($data);
    }

    /** @return array{operations: list<array<string, string|bool>>} */
    private function encoded(): array
    {
        $metadata = new OperationMetadataCompiler()->compile(ManifestTypedOperation::class);

        return new OperationManifestMetadataCodec()->encode(new OperationRegistry([$metadata]));
    }
}

final readonly class ManifestValue implements OperationValue {}

final readonly class OtherManifestValue implements OperationValue {}

final readonly class OtherManifestOutcome implements Outcome {}

abstract class AbstractManifestTypedOperation implements Operation
{
    abstract public function handle(ManifestValue $value): OperationResult;
}

#[OperationType('manifest.typed')]
#[Accepts(ManifestValue::class)]
#[Returns(EmptyOutcome::class)]
final readonly class ManifestTypedOperation implements Operation
{
    public function handle(ManifestValue $value, ExecutionContext $context): OperationResult
    {
        return OperationResult::completed();
    }
}
