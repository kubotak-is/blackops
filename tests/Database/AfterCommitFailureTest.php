<?php

declare(strict_types=1);

namespace BlackOps\Tests\Database;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Database\AfterCommitFailure;
use BlackOps\Database\AfterCommitFailureReporter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class AfterCommitFailureTest extends TestCase
{
    public function testFailureValueExposesOnlyCallbackIdentityCauseAndOptionalContext(): void
    {
        $cause = new RuntimeException('expected');
        $failure = new AfterCommitFailure('App\\MailService', 'send', $cause);

        self::assertSame('App\\MailService', $failure->serviceClass());
        self::assertSame('send', $failure->method());
        self::assertSame($cause, $failure->cause());
        self::assertNull($failure->context());
        self::assertNotEmpty(new ReflectionClass($failure)->getAttributes(PublicApi::class));
    }

    public function testReporterIsAPublicSingleMethodContract(): void
    {
        $reflection = new ReflectionClass(AfterCommitFailureReporter::class);

        self::assertTrue($reflection->isInterface());
        self::assertNotEmpty($reflection->getAttributes(PublicApi::class));
        self::assertSame(
            ['report'],
            array_map(static fn(\ReflectionMethod $method): string => $method->getName(), $reflection->getMethods()),
        );
    }
}
