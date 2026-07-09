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
