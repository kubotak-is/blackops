<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Transaction;

use BlackOps\Database\Exception\TransactionException;
use BlackOps\Internal\Transaction\TransactionRuntimeAccessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TransactionRuntimeAccessorTest extends TestCase
{
    #[DataProvider('uninitializedInvocations')]
    public function testUninitializedRuntimeFailsWithoutInvokingApplicationMethod(\Closure $invoke): void
    {
        $called = false;

        try {
            $invoke(new TransactionRuntimeAccessor(), static function () use (&$called): void {
                $called = true;
            });
            self::fail('Expected uninitialized transaction runtime failure.');
        } catch (TransactionException $exception) {
            self::assertSame('Transaction runtime is not initialized.', $exception->getMessage());
            self::assertStringNotContainsString('database-password', $exception->getMessage());
        }

        self::assertFalse($called);
    }

    /** @return iterable<string, array{\Closure(TransactionRuntimeAccessor, \Closure(): void): void}> */
    public static function uninitializedInvocations(): iterable
    {
        yield 'transactional' => [
            static function (TransactionRuntimeAccessor $accessor, \Closure $callback): void {
                $accessor->transactional('app', $callback);
            },
        ];
        yield 'after commit' => [
            static function (TransactionRuntimeAccessor $accessor, \Closure $callback): void {
                $accessor->afterCommit(self::class, 'callback', $callback);
            },
        ];
    }
}
