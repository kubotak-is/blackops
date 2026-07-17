<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Database;

use BlackOps\Internal\Database\DoctrineDatabaseManager;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DoctrineDatabaseManagerTest extends TestCase
{
    public function testLazilyCreatesAndReusesDefaultAndNamedConnections(): void
    {
        $default = $this->createStub(Connection::class);
        $analytics = $this->createStub(Connection::class);
        $created = [];
        $manager = new DoctrineDatabaseManager(
            'app',
            [
                'app' => ['label' => 'app'],
                'analytics' => ['label' => 'analytics'],
            ],
            static function (array $parameters) use (&$created, $default, $analytics): Connection {
                $created[] = $parameters['label'];

                return $parameters['label'] === 'app' ? $default : $analytics;
            },
        );

        self::assertSame([], $created);
        self::assertSame($analytics, $manager->connection(' analytics '));
        self::assertSame(['analytics'], $created);
        self::assertSame($analytics, $manager->connection('analytics'));
        self::assertSame($default, $manager->connection());
        self::assertSame($default, $manager->connection('app'));
        self::assertSame(['analytics', 'app'], $created);
    }

    public function testRejectsUnknownNameWithoutExposingConnectionParameters(): void
    {
        $credential = 'credential-that-must-not-appear';
        $manager = new DoctrineDatabaseManager('app', ['app' => ['password' => $credential]]);

        try {
            $manager->connection('missing');
            self::fail('Expected unknown connection rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString($credential, $exception->getMessage());
            self::assertStringNotContainsString('missing', $exception->getMessage());
        }
    }

    public function testConnectionFactoryFailureDoesNotExposeParametersOrPreviousException(): void
    {
        $credential = 'credential-that-must-not-appear';
        $manager = new DoctrineDatabaseManager(
            'app',
            ['app' => ['password' => $credential]],
            static fn(array $parameters): Connection => throw new RuntimeException($parameters['password']),
        );

        try {
            $manager->connection();
            self::fail('Expected connection creation failure.');
        } catch (RuntimeException $exception) {
            self::assertStringNotContainsString($credential, $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }
}
