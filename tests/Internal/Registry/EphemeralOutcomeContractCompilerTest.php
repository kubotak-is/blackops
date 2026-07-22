<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Registry;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\ConsoleCommand;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\OutcomeData;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Http\Attribute\Route;
use BlackOps\Internal\Registry\OperationMetadataCompiler;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class EphemeralOutcomeContractCompilerTest extends TestCase
{
    public function testPublicContractAddsOnlyTheMarkerInterface(): void
    {
        $marker = new ReflectionClass(EphemeralOutcome::class);

        self::assertTrue($marker->isInterface());
        self::assertTrue($marker->implementsInterface(\BlackOps\Core\Outcome::class));
        self::assertSame([], $marker->getMethods());
        self::assertCount(1, $marker->getAttributes(PublicApi::class));
        self::assertFalse(new ReflectionClass(OperationMetadata::class)->hasProperty('ephemeral'));
    }

    public function testCompilesTypedAndLegacyEphemeralOutcomes(): void
    {
        $typed = new OperationMetadataCompiler()->compile(ValidEphemeralOperation::class);
        $legacy = new OperationMetadataCompiler()->compile(LegacyEphemeralOperation::class);

        self::assertTrue(is_a($typed->outcome, EphemeralOutcome::class, allow_string: true));
        self::assertSame(TokenIssued::class, $typed->outcome);
        self::assertTrue(is_a($legacy->outcome, EphemeralOutcome::class, allow_string: true));
        self::assertSame(TokenIssued::class, $legacy->outcome);
    }

    #[DataProvider('invalidOperationProvider')]
    public function testRejectsOperationsOutsideExplicitInlineHttpBoundary(string $operation, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new OperationMetadataCompiler()->compile($operation);
    }

    /** @return iterable<string, array{class-string<Operation>, string}> */
    public static function invalidOperationProvider(): iterable
    {
        yield 'implicit inline' => [ImplicitInlineEphemeralOperation::class, 'explicit Inline'];
        yield 'deferred' => [DeferredEphemeralOperation::class, 'explicit Inline'];
        yield 'route-less' => [RouteLessEphemeralOperation::class, 'exactly one HTTP Route'];
        yield 'console' => [ConsoleEphemeralOperation::class, 'must not declare ConsoleCommand'];
    }

    #[DataProvider('invalidOutcomeProvider')]
    public function testRejectsUnsafeEphemeralOutcomeShapes(string $operation, string $message): void
    {
        try {
            new OperationMetadataCompiler()->compile($operation);
            self::fail('Expected unsafe ephemeral outcome rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
            self::assertStringNotContainsString('raw-secret-must-not-appear', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{class-string<Operation>, string}> */
    public static function invalidOutcomeProvider(): iterable
    {
        yield 'reserved credential key' => [MissingSensitiveTokenOperation::class, 'requires Sensitive'];
        yield 'nested sensitive field' => [NestedSensitiveOperation::class, 'nested field'];
        yield 'unsupported type' => [UnsupportedEphemeralOperation::class, 'unsupported native type'];
        yield 'not final readonly' => [MutableEphemeralOperation::class, 'final readonly'];
    }
}

final readonly class EphemeralValue implements OperationValue
{
    public function __construct(
        #[Sensitive]
        public string $password,
    ) {}
}

final readonly class TokenIssued implements EphemeralOutcome
{
    public function __construct(
        #[Sensitive]
        public string $token,
        public string $expiresAt,
    ) {}
}

#[OperationType('ephemeral.valid')]
#[Route('POST', '/ephemeral')]
#[ExecuteWith(Inline::class)]
final readonly class ValidEphemeralOperation implements Operation
{
    public function handle(EphemeralValue $value): TokenIssued
    {
        return new TokenIssued('raw-secret-must-not-appear', '2026-07-22T00:00:00+00:00');
    }
}

final readonly class LegacyEphemeralHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed(new TokenIssued('raw-secret-must-not-appear', 'later'));
    }
}

#[OperationType('ephemeral.legacy')]
#[Accepts(EphemeralValue::class)]
#[HandledBy(LegacyEphemeralHandler::class)]
#[Returns(TokenIssued::class)]
#[Route('POST', '/ephemeral-legacy')]
#[ExecuteWith(Inline::class)]
final readonly class LegacyEphemeralOperation implements Operation {}

#[OperationType('ephemeral.implicit')]
#[Route('POST', '/ephemeral-implicit')]
final readonly class ImplicitInlineEphemeralOperation implements Operation
{
    public function handle(EphemeralValue $value): TokenIssued
    {
        return new TokenIssued('raw-secret-must-not-appear', 'later');
    }
}

#[OperationType('ephemeral.deferred')]
#[Route('POST', '/ephemeral-deferred')]
#[ExecuteWith(Deferred::class)]
final readonly class DeferredEphemeralOperation implements Operation
{
    public function handle(EphemeralValue $value): TokenIssued
    {
        return new TokenIssued('raw-secret-must-not-appear', 'later');
    }
}

#[OperationType('ephemeral.route.less')]
#[ExecuteWith(Inline::class)]
final readonly class RouteLessEphemeralOperation implements Operation
{
    public function handle(EphemeralValue $value): TokenIssued
    {
        return new TokenIssued('raw-secret-must-not-appear', 'later');
    }
}

#[OperationType('ephemeral.console')]
#[Route('POST', '/ephemeral-console')]
#[ExecuteWith(Inline::class)]
#[ConsoleCommand('ephemeral:unsafe')]
final readonly class ConsoleEphemeralOperation implements Operation
{
    public function handle(EphemeralValue $value): TokenIssued
    {
        return new TokenIssued('raw-secret-must-not-appear', 'later');
    }
}

final readonly class MissingSensitiveToken implements EphemeralOutcome
{
    public function __construct(
        public string $token = 'raw-secret-must-not-appear',
    ) {}
}

#[OperationType('ephemeral.missing.sensitive')]
#[Route('POST', '/ephemeral-missing-sensitive')]
#[ExecuteWith(Inline::class)]
final readonly class MissingSensitiveTokenOperation implements Operation
{
    public function handle(EphemeralValue $value): MissingSensitiveToken
    {
        return new MissingSensitiveToken();
    }
}

final readonly class NestedCredential implements OutcomeData
{
    public function __construct(
        #[Sensitive]
        public string $secret,
    ) {}
}

final readonly class NestedSensitiveOutcome implements EphemeralOutcome
{
    public function __construct(
        public NestedCredential $data,
    ) {}
}

#[OperationType('ephemeral.nested.sensitive')]
#[Route('POST', '/ephemeral-nested-sensitive')]
#[ExecuteWith(Inline::class)]
final readonly class NestedSensitiveOperation implements Operation
{
    public function handle(EphemeralValue $value): NestedSensitiveOutcome
    {
        return new NestedSensitiveOutcome(new NestedCredential('raw-secret-must-not-appear'));
    }
}

final readonly class UnsupportedEphemeralOutcome implements EphemeralOutcome
{
    public function __construct(
        public object $payload,
    ) {}
}

#[OperationType('ephemeral.unsupported')]
#[Route('POST', '/ephemeral-unsupported')]
#[ExecuteWith(Inline::class)]
final readonly class UnsupportedEphemeralOperation implements Operation
{
    public function handle(EphemeralValue $value): UnsupportedEphemeralOutcome
    {
        return new UnsupportedEphemeralOutcome(new \stdClass());
    }
}

final class MutableEphemeralOutcome implements EphemeralOutcome {}

#[OperationType('ephemeral.mutable')]
#[Route('POST', '/ephemeral-mutable')]
#[ExecuteWith(Inline::class)]
final readonly class MutableEphemeralOperation implements Operation
{
    public function handle(EphemeralValue $value): MutableEphemeralOutcome
    {
        return new MutableEphemeralOutcome();
    }
}
