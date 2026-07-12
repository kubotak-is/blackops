<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Registry;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Internal\Registry\TypedSelfHandledSignatureValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TypedSelfHandledSignatureValidatorTest extends TestCase
{
    /** @return iterable<string, array{class-string, class-string<OperationValue>}> */
    public static function invalidSignatures(): iterable
    {
        yield 'private' => [PrivateTypedHandler::class, SignatureValue::class];
        yield 'protected' => [ProtectedTypedHandler::class, SignatureValue::class];
        yield 'static' => [StaticTypedHandler::class, SignatureValue::class];
        yield 'abstract' => [AbstractTypedHandler::class, SignatureValue::class];
        yield 'no parameters' => [NoParameterTypedHandler::class, SignatureValue::class];
        yield 'three parameters' => [ThreeParameterTypedHandler::class, SignatureValue::class];
        yield 'untyped value' => [UntypedValueHandler::class, SignatureValue::class];
        yield 'builtin value' => [BuiltinValueHandler::class, SignatureValue::class];
        yield 'union value' => [UnionValueHandler::class, SignatureValue::class];
        yield 'intersection value' => [IntersectionValueHandler::class, SignatureValue::class];
        yield 'nullable value' => [NullableValueHandler::class, SignatureValue::class];
        yield 'non operation value' => [NonOperationValueHandler::class, SignatureValue::class];
        yield 'mismatched value' => [MismatchedValueHandler::class, SignatureValue::class];
        yield 'optional value' => [OptionalValueHandler::class, SignatureValue::class];
        yield 'referenced value' => [ReferencedValueHandler::class, SignatureValue::class];
        yield 'variadic value' => [VariadicValueHandler::class, SignatureValue::class];
        yield 'wrong context' => [WrongContextHandler::class, SignatureValue::class];
        yield 'optional context' => [OptionalContextHandler::class, SignatureValue::class];
        yield 'referenced context' => [ReferencedContextHandler::class, SignatureValue::class];
        yield 'variadic context' => [VariadicContextHandler::class, SignatureValue::class];
        yield 'missing return' => [MissingReturnHandler::class, SignatureValue::class];
        yield 'builtin return' => [BuiltinReturnHandler::class, SignatureValue::class];
        yield 'union return' => [UnionReturnHandler::class, SignatureValue::class];
        yield 'intersection return' => [IntersectionReturnHandler::class, SignatureValue::class];
        yield 'nullable return' => [NullableReturnHandler::class, SignatureValue::class];
        yield 'wrong return' => [WrongReturnHandler::class, SignatureValue::class];
    }

    /** @param class-string $handler @param class-string<OperationValue> $value */
    #[DataProvider('invalidSignatures')]
    public function testRejectsInvalidSignature(string $handler, string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($handler);

        new TypedSelfHandledSignatureValidator()->validate($handler, $value);
    }
}

final readonly class SignatureValue implements OperationValue {}

final readonly class OtherSignatureValue implements OperationValue {}

interface SignatureA {}

interface SignatureB {}

final readonly class IntersectionSignatureValue implements OperationValue, SignatureA, SignatureB {}

final readonly class NonOperationValue {}

final class PrivateTypedHandler
{
    private function handle(SignatureValue $value): OperationResult {}
}

final class ProtectedTypedHandler
{
    protected function handle(SignatureValue $value): OperationResult {}
}

final class StaticTypedHandler
{
    public static function handle(SignatureValue $value): OperationResult {}
}

abstract class AbstractTypedHandler
{
    abstract public function handle(SignatureValue $value): OperationResult;
}

final class NoParameterTypedHandler
{
    public function handle(): OperationResult {}
}

final class ThreeParameterTypedHandler
{
    public function handle(SignatureValue $value, ExecutionContext $context, int $extra): OperationResult {}
}

final class UntypedValueHandler
{
    public function handle($value): OperationResult {}
}

final class BuiltinValueHandler
{
    public function handle(string $value): OperationResult {}
}

final class UnionValueHandler
{
    public function handle(SignatureValue|OtherSignatureValue $value): OperationResult {}
}

final class IntersectionValueHandler
{
    public function handle(SignatureA&SignatureB $value): OperationResult {}
}

final class NullableValueHandler
{
    public function handle(?SignatureValue $value): OperationResult {}
}

final class NonOperationValueHandler
{
    public function handle(NonOperationValue $value): OperationResult {}
}

final class MismatchedValueHandler
{
    public function handle(OtherSignatureValue $value): OperationResult {}
}

final class OptionalValueHandler
{
    public function handle(SignatureValue $value = new SignatureValue()): OperationResult {}
}

final class ReferencedValueHandler
{
    public function handle(SignatureValue &$value): OperationResult {}
}

final class VariadicValueHandler
{
    public function handle(SignatureValue ...$value): OperationResult {}
}

final class WrongContextHandler
{
    public function handle(SignatureValue $value, OtherSignatureValue $context): OperationResult {}
}

final class OptionalContextHandler
{
    public function handle(SignatureValue $value, ?ExecutionContext $context = null): OperationResult {}
}

final class ReferencedContextHandler
{
    public function handle(SignatureValue $value, ExecutionContext &$context): OperationResult {}
}

final class VariadicContextHandler
{
    public function handle(SignatureValue $value, ExecutionContext ...$context): OperationResult {}
}

final class MissingReturnHandler
{
    public function handle(SignatureValue $value) {}
}

final class BuiltinReturnHandler
{
    public function handle(SignatureValue $value): string {}
}

final class UnionReturnHandler
{
    public function handle(SignatureValue $value): OperationResult|EmptyOutcome {}
}

final class IntersectionReturnHandler
{
    public function handle(SignatureValue $value): SignatureA&SignatureB {}
}

final class NullableReturnHandler
{
    public function handle(SignatureValue $value): ?OperationResult {}
}

final class WrongReturnHandler
{
    public function handle(SignatureValue $value): EmptyOutcome {}
}
