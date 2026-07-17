<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Http;

use BlackOps\Core\Codec\OperationCodec;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Http\DeferredHttpOperationAcceptor;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class DeferredHttpOperationAcceptorTest extends TestCase
{
    public function testAcceptKeepsOptionalActorContextForTwoArgumentCompatibility(): void
    {
        $method = new ReflectionMethod(DeferredHttpOperationAcceptor::class, 'accept');

        self::assertCount(3, $method->getParameters());
        self::assertTrue($method->getParameters()[2]->isOptional());
        self::assertNull($method->getParameters()[2]->getDefaultValue());
    }

    public function testAcceptsOnlyRegisteredDeferredOperation(): void
    {
        $deferred = new AcceptorOperation();
        $inline = new OtherAcceptorOperation();
        $acceptor = $this->acceptor(new OperationRegistry([
            $this->metadata(AcceptorOperation::class, Deferred::class),
            $this->metadata(OtherAcceptorOperation::class, Inline::class),
        ]));

        self::assertTrue($acceptor->accepts($deferred));
        self::assertFalse($acceptor->accepts($inline));
        self::assertFalse($acceptor->accepts(new MissingAcceptorOperation()));
    }

    public function testAcceptsProxySubclassFromRegisteredParentMetadata(): void
    {
        $acceptor = $this->acceptor(new OperationRegistry([
            $this->metadata(AcceptorOperation::class, Deferred::class),
        ]));

        self::assertTrue($acceptor->accepts(new ProxiedAcceptorOperation()));
    }

    private function acceptor(OperationRegistry $registry): DeferredHttpOperationAcceptor
    {
        /** @var ExecutionContextFactory $contexts */
        $contexts = new ReflectionClass(ExecutionContextFactory::class)->newInstanceWithoutConstructor();
        /** @var DeferredAcceptanceOrchestrator $orchestrator */
        $orchestrator = new ReflectionClass(DeferredAcceptanceOrchestrator::class)->newInstanceWithoutConstructor();

        return new DeferredHttpOperationAcceptor(
            $registry,
            $contexts,
            new class implements OperationCodec {
                public function encode(
                    OperationMetadata $metadata,
                    OperationValue $value,
                    \BlackOps\Core\ExecutionContext $context,
                ): \BlackOps\Core\Codec\EncodedOperationMessage {
                    throw new \LogicException('Codec must not run while checking acceptance.');
                }

                public function decodeValue(
                    OperationMetadata $metadata,
                    int $schemaVersion,
                    string $encodedPayload,
                ): OperationValue {
                    throw new \LogicException('Codec must not run while checking acceptance.');
                }

                public function decodeContext(
                    int $schemaVersion,
                    string $encodedContext,
                ): \BlackOps\Core\ExecutionContext {
                    throw new \LogicException('Codec must not run while checking acceptance.');
                }
            },
            $orchestrator,
        );
    }

    /**
     * @param class-string<Operation> $definition
     * @param class-string<\BlackOps\Core\Execution\ExecutionStrategy> $strategy
     */
    private function metadata(string $definition, string $strategy): OperationMetadata
    {
        return new OperationMetadata(
            'acceptor.' . strtolower(new ReflectionClass($definition)->getShortName()),
            $definition,
            AcceptorValue::class,
            $definition,
            EmptyOutcome::class,
            $strategy,
        );
    }
}

readonly class AcceptorOperation implements Operation {}

final readonly class ProxiedAcceptorOperation extends AcceptorOperation {}

final readonly class OtherAcceptorOperation implements Operation {}

final readonly class MissingAcceptorOperation implements Operation {}

final readonly class AcceptorValue implements OperationValue {}
