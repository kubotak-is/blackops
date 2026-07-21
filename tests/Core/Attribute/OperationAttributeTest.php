<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Attribute;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\ConsoleCommand;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OperationAttributeTest extends TestCase
{
    public function testAttributesArePublicFinalReadonlyClassAttributes(): void
    {
        foreach ([
            OperationType::class,
            Accepts::class,
            HandledBy::class,
            Returns::class,
            ExecuteWith::class,
            Authorize::class,
            ConsoleCommand::class,
        ] as $type) {
            $reflection = new ReflectionClass($type);

            self::assertTrue($reflection->isFinal());
            self::assertTrue($reflection->isReadOnly());
            self::assertCount(1, $reflection->getAttributes(PublicApi::class));
            self::assertCount(1, $reflection->getAttributes(\Attribute::class));
        }
    }

    public function testConsoleCommandIsClassOnlyNotRepeatableAndKeepsSafeMetadata(): void
    {
        $reflection = new ReflectionClass(ConsoleCommand::class);
        $attribute = $reflection->getAttributes(\Attribute::class)[0]->newInstance();
        $command = new ConsoleCommand('order:create', 'Create an order.');

        self::assertSame(\Attribute::TARGET_CLASS, $attribute->flags);
        self::assertSame(0, $attribute->flags & \Attribute::IS_REPEATABLE);
        self::assertSame('order:create', $command->name);
        self::assertSame('Create an order.', $command->description);
    }

    public static function invalidConsoleNames(): array
    {
        return [[''], [':foo'], ['foo:'], ['foo::bar'], ['foo bar'], ["foo\nbar"], ['foo|bar']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidConsoleNames')]
    public function testConsoleCommandRejectsInvalidCanonicalNames(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ConsoleCommand($name);
    }

    public function testAuthorizeIsClassOnlyAndNotRepeatable(): void
    {
        $reflection = new ReflectionClass(Authorize::class);
        $attribute = $reflection->getAttributes(\Attribute::class)[0]->newInstance();
        $authorize = new Authorize(AttributeAuthorizationPolicy::class);

        self::assertSame(\Attribute::TARGET_CLASS, $attribute->flags);
        self::assertSame(0, $attribute->flags & \Attribute::IS_REPEATABLE);
        self::assertSame(AttributeAuthorizationPolicy::class, $authorize->policy);
    }

    public function testAuthorizeDoesNotRequirePolicyClassToExistAtConstruction(): void
    {
        $authorize = new ReflectionClass(Authorize::class)->newInstance('Application\\MissingAuthorizationPolicy');

        self::assertInstanceOf(Authorize::class, $authorize);
        self::assertSame('Application\\MissingAuthorizationPolicy', $authorize->policy);
    }

    public static function invalidTypeIds(): array
    {
        return [[''], ['Welcome.Show'], ['welcome show'], ['.welcome'], ['welcome.'], ['welcome..show']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidTypeIds')]
    public function testInvalidOperationTypeIsRejected(string $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OperationType($id);
    }
}

final readonly class AttributeAuthorizationPolicy implements AuthorizationPolicy
{
    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        return AuthorizationDecision::allow();
    }
}
