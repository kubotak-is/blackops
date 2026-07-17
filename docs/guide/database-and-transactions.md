# Database and Transactions

BlackOpsはDoctrine DBALの`Connection`をそのまま使い、Transaction境界だけをFrameworkへ統合します。ORM、Active Record、Repository基底Class、SQL Wrapperは提供しません。RepositoryはDefault `Connection`または`DatabaseManager`をConstructor Injectionしてください。

## Transactional Service

DI Containerが管理する非`final` ServiceのClassまたはPublic Methodへ`#[Transactional]`を付けます。Connectionを省略すると`config/database.php`の`default`を使います。

```php
<?php

declare(strict_types=1);

namespace App\Feature\Order;

use BlackOps\Database\Attribute\Transactional;

#[Transactional]
readonly class CreateOrderCommand
{
    public function __construct(private OrderRepository $orders) {}

    public function execute(CreateOrderInput $input): OrderId
    {
        return $this->orders->create($input);
    }
}
```

FrameworkはMethod呼出前にTransactionを開始し、正常ReturnでCommit、ThrowableでRollbackします。Direct `new`で作ったInstanceはProxyを通らないため対象外です。必ずService ProviderまたはOperation依存としてCompiled Containerから解決してください。

## Nested Required

同じNamed ConnectionのNested `#[Transactional]`は外側Transactionへ参加します。DBALのNested TransactionやSavepointは作りません。Inner MethodがThrowableを投げると外側ScopeはRollback-onlyになります。Outer MethodがそのThrowableを捕捉してReturnしてもCommitせず、`TransactionException`を投げます。

異なるNamed Connectionは独立したTransactionです。Inner ConnectionはInner MethodのReturn時にCommitし、その後Outer ConnectionがRollbackしても戻りません。BlackOpsは複数Connection間の原子性や二相Commitを保証しません。

## Manual Transaction

Savepoint、短い局所範囲、複数Databaseの明示制御が必要なら、`#[Transactional]`を外してDoctrine DBALのManual Transactionを使ってください。開始済みManual Transaction内でAttributed Methodを呼ぶと、FrameworkはMethod本体を実行する前にFail-fastします。Attributed Method内でTransaction Nesting Levelを変更したままReturnした場合もRollbackして`TransactionException`を投げます。

## After Commit

`#[AfterCommit]`付きMethodは通常どおり呼び出します。ActiveなFramework Transaction内ではInvocationがQueueされ、最外Commit成功後に登録順で一度実行されます。Rollback、Rollback-only、Commit失敗ではQueueを破棄します。Transaction外の呼出は即時実行です。

```php
<?php

declare(strict_types=1);

namespace App\Feature\Order;

use BlackOps\Database\Attribute\AfterCommit;

readonly class OrderNotification
{
    #[AfterCommit]
    public function send(OrderId $orderId): void
    {
        // Commit後に同期実行される
    }
}
```

Callbackは同期Best-effortです。一つが失敗しても後続CallbackとCommit済みDatabaseを変更せず、自動Retryもしません。Application固有の`AfterCommitFailureReporter`をService Providerで登録すると、Service Class、Method、Cause、登録時の任意`ExecutionContext`を受け取れます。Reporter自身が失敗しても後続Callbackを止めません。

Process Crashを越えるDelivery、Email／Webhook／Message Publishの確実な再送が必要なら、After CommitではなくTransactional Outboxを使ってください。Outbox Persistence／Relayは後続Phaseで提供します。

## Operationとの保証差

一般Serviceの`#[Transactional]`はMethod Return時にCommitします。Non-transactional OperationからTransactional Commandを呼ぶ場合、Command Commitと、その後のOperation Terminal Journal／Outcome保存は原子的ではありません。

Operation Definitionまたは`handle()`自身の`#[Transactional]`はFramework固定Lifecycleが所有します。現段階ではBuild ValidationとProxy生成だけを行い、一般Service用InterceptorからTransactionを開始しません。Operationの業務更新とTerminal Journal／Outcomeを同じConnectionでCommitする保証は、Operation Transaction Lifecycleの導入後に有効になります。
