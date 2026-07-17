<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ActorRefTest extends TestCase
{
    public function testIsFinalReadonlyClassMarkedPublicApi(): void
    {
        $reflection = new ReflectionClass(ActorRef::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
    }

    public function testStoresTrimmedIdAndType(): void
    {
        $actor = new ActorRef(' user-123 ', ' user ');

        self::assertSame('user-123', $actor->id());
        self::assertSame('user', $actor->type());
    }

    #[DataProvider('invalidActorValues')]
    public function testRejectsEmptyIdOrType(string $id, string $type): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ActorRef($id, $type);
    }

    /**
     * @return iterable<string, array{id: string, type: string}>
     */
    public static function invalidActorValues(): iterable
    {
        yield 'empty id' => ['id' => '', 'type' => 'user'];
        yield 'blank id' => ['id' => " \t\n", 'type' => 'user'];
        yield 'empty type' => ['id' => 'user-123', 'type' => ''];
        yield 'blank type' => ['id' => 'user-123', 'type' => " \t\n"];
    }
}
