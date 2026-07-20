<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use LogicException;
use PHPUnit\Framework\TestCase;

final class SensitiveProjectionFilterTest extends TestCase
{
    public function testProjectsObjectWithSensitiveAttributeModes(): void
    {
        $projection = new SensitiveProjectionFilter('projection-secret')->projectObject(
            new SensitiveProjectionFixture('alice', 'secret-password', 'alice@example.test', 'customer-1'),
        );

        self::assertSame('alice', $projection['name']);
        self::assertArrayNotHasKey('password', $projection);
        self::assertSame('[masked]', $projection['email']);
        self::assertStringStartsWith('hmac-sha256:', $projection['customerId']);
        self::assertSame(
            'hmac-sha256:' . hash_hmac('sha256', 'customer-1', 'projection-secret'),
            $projection['customerId'],
        );
    }

    public function testProjectsArrayAndOmitsReservedKeys(): void
    {
        $projection = new SensitiveProjectionFilter()->projectArray([
            'name' => 'alice',
            'password' => 'secret',
            'api_token' => 'token',
            'nested' => [
                'value' => 'safe',
                'clientSecret' => 'hidden',
            ],
        ]);

        self::assertSame(
            [
                'name' => 'alice',
                'nested' => [
                    'value' => 'safe',
                ],
            ],
            $projection,
        );
    }

    public function testHashModeRequiresHmacKey(): void
    {
        $this->expectException(LogicException::class);

        new SensitiveProjectionFilter()->projectObject(new SensitiveHashOnlyFixture('customer-1'));
    }

    public function testRecursivelyProjectsNestedObjectsAndPreservesListKeysAndOrder(): void
    {
        $projection = new SensitiveProjectionFilter('projection-secret')->projectObject(new SensitiveListFixture([
            new SensitiveProjectionFixture('alice', 'secret-a', 'alice@example.test', 'customer-1'),
            new SensitiveProjectionFixture('bob', 'secret-b', 'bob@example.test', 'customer-2'),
        ]));

        self::assertSame([0, 1], array_keys($projection['items']));
        self::assertSame(['alice', 'bob'], array_column($projection['items'], 'name'));
        self::assertArrayNotHasKey('password', $projection['items'][0]);
        self::assertSame('[masked]', $projection['items'][1]['email']);
        self::assertSame(
            'hmac-sha256:' . hash_hmac('sha256', 'customer-2', 'projection-secret'),
            $projection['items'][1]['customerId'],
        );
    }

    public function testListElementsAreNotTreatedAsReservedAssociativeKeys(): void
    {
        $projection = new SensitiveProjectionFilter()->projectArray([
            ['token' => 'secret', 'value' => 'first'],
            ['password' => 'secret', 'value' => 'second'],
        ]);

        self::assertSame(
            [
                0 => ['value' => 'first'],
                1 => ['value' => 'second'],
            ],
            $projection,
        );
    }
}

final readonly class SensitiveProjectionFixture
{
    public function __construct(
        public string $name,
        #[Sensitive]
        public string $password,
        #[Sensitive(SensitiveMode::Mask)]
        public string $email,
        #[Sensitive(SensitiveMode::Hash)]
        public string $customerId,
    ) {}
}

final readonly class SensitiveHashOnlyFixture
{
    public function __construct(
        #[Sensitive(SensitiveMode::Hash)]
        public string $customerId,
    ) {}
}

final readonly class SensitiveListFixture
{
    /** @param list<SensitiveProjectionFixture> $items */
    public function __construct(
        public array $items,
    ) {}
}
