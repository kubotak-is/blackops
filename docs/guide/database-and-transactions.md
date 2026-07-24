# Database and Transactions

BlackOpsはDoctrine DBALの`Connection`をそのまま使い、Transaction境界だけをFrameworkへ統合します。ORM、Active Record、Repository基底Class、SQL Wrapperは提供しません。RepositoryはDefault `Connection`または`DatabaseManager`をConstructor Injectionしてください。

## RepositoryとDefault Connection DI

QuickstartのOrder Featureは業務PortとDoctrine DBAL実装を分けます。通常のRepositoryはNamed Service IDを作らず、Default `Doctrine\DBAL\Connection`を直接受け取れます。

```php
<?php

declare(strict_types=1);

namespace App\Feature\Order;

interface OrderRepository
{
    public function create(string $reference): void;

    public function recordCommitted(string $reference): void;
}
```

```php
<?php

declare(strict_types=1);

namespace App\Feature\Order;

use Doctrine\DBAL\Connection;

final readonly class DoctrineOrderRepository implements OrderRepository
{
    public function __construct(private Connection $connection) {}

    public function create(string $reference): void
    {
        $this->connection->executeStatement(
            'INSERT INTO public.quickstart_orders (reference) VALUES (:reference)',
            ['reference' => $reference],
        );
    }

    public function recordCommitted(string $reference): void
    {
        $this->connection->executeStatement(
            'INSERT INTO public.quickstart_order_commits (reference) VALUES (:reference)',
            ['reference' => $reference],
        );
    }
}
```

複数Databaseを使うServiceだけは`DatabaseManager`をConstructor Injectionし、`$databases->connection('analytics')`のようにNameを明示します。Connection間の原子性は保証しません。

## Long-running ProcessでのConnection再利用

FrankenPHP HTTP WorkerはRequestごと、Deferred WorkerはClaimしたAttemptごとに、Application用Named ConnectionのLifecycleを区切ります。開始時には、それまでに生成されたConnectionへ`SELECT 1`を実行します。まだManagerへ要求されておらず生成されていないNamed ConnectionをHealth Checkのためだけに生成しないため、未使用ConnectionのLazy性は維持されます。前回の失敗でCloseされたConnectionも検査対象になり、この境界で再接続します。

Health Checkが失敗すると、FrameworkはそのConnectionをCloseし、同じDBAL `Connection` Objectで一度だけ再接続を試します。成功すれば、Constructor Injection済みRepositoryを作り直さず処理を続行します。再接続にも失敗した場合はApplication Codeを実行せず、生成済みApplication ConnectionをCloseしてThrowableを返します。

Request／Attemptの正常終了時には、その実行中に初めて生成されたConnectionも含めてActive Transactionが残っていないか検査します。Transaction Leakがあれば該当ConnectionをCloseし、成功ResponseまたはClaim Acknowledgeへ進まずFail-fastします。Handler、Authorization、Transaction、Journal、Outcome、Observer Cleanup等が失敗した場合は、生成済みApplication ConnectionをすべてBest-effortでCloseします。Cleanup Failureが元のThrowableを隠すことはありません。

正常でLeakのないConnectionはCloseせず、次のRequest／Attemptで再利用します。このLifecycleはQueryやTransactionを自動Retryしません。SQL実行結果やCommit結果が不明な処理を安全と推測して再実行する仕組みではないため、ApplicationはIdempotencyと再実行方針を別に設計してください。

Deferred WorkerのHeartbeatはApplication用`DatabaseManager`とは別Managerの専用Connectionを使います。Application ServiceやRepositoryからHeartbeat Connectionを解決することはできず、Application LifecycleによるClose対象にも入りません。

## Transactional Service

DI Containerが管理する非`final` ServiceのClassまたはPublic Methodへ`#[Transactional]`を付けます。Connectionを省略すると`config/database.php`の`default`を使います。

```php
<?php

declare(strict_types=1);

namespace App\Feature\Order\CreateOrder;

use App\Feature\Order\OrderRepository;
use App\Feature\Order\RecordOrderCommit;
use BlackOps\Database\Attribute\Transactional;

readonly class CreateOrderCommand
{
    public function __construct(
        private OrderRepository $orders,
        private RecordOrderCommit $commits,
    ) {}

    #[Transactional]
    public function execute(string $reference): void
    {
        $this->orders->create($reference);
        $this->commits->record($reference);
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

readonly class RecordOrderCommit
{
    public function __construct(private OrderRepository $orders) {}

    #[AfterCommit]
    public function record(string $reference): void
    {
        $this->orders->recordCommitted($reference);
    }
}
```

Callbackは同期Best-effortです。一つが失敗しても後続CallbackとCommit済みDatabaseを変更せず、自動Retryもしません。Application固有の`AfterCommitFailureReporter`をService Providerで登録すると、Service Class、Method、Cause、登録時の任意`ExecutionContext`を受け取れます。Reporter自身が失敗しても後続Callbackを止めません。

Process Crashを越えるDeliveryが必要なら、After Commitではなく`TransactionalOutbox`へDeferred child Operationを登録してください。Outbox Persistenceは利用できますが、Relay／Retry／Dead Letterはまだ提供していないため、現時点ではRowの原子的な保存までが保証範囲です。外部Email／Webhook／Message Brokerへの送信完了は表現しません。

## Operationとの保証差

一般Serviceの`#[Transactional]`はMethod Return時にCommitします。Non-transactional OperationからTransactional Commandを呼ぶ場合、Command Commitと、その後のOperation Terminal Journal／Outcome保存は原子的ではありません。

Operation Definitionまたは自己処理`handle()`へ`#[Transactional]`を付けると、Framework固定LifecycleがTransactionを所有します。Method-level指定はClass-level指定を上書きします。`#[HandledBy]`で分離したHandler側だけにAttributeを付けた場合は一般Service Transactionとなり、Operation Terminalとの原子性は追加されません。

```php
<?php

declare(strict_types=1);

namespace App\Feature\Order\CreateOrder;

use App\Security\SampleUserAuthorizationPolicy;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/orders')]
#[OperationType('order.create')]
#[Authorize(SampleUserAuthorizationPolicy::class)]
readonly class CreateOrder implements Operation
{
    public function __construct(private CreateOrderCommand $command) {}

    #[Transactional]
    public function handle(CreateOrderValue $value): OrderCreated
    {
        $this->command->execute($value->reference);

        return new OrderCreated($value->reference, 'created');
    }
}
```

BuildはConnection省略をDefault Nameへ解決し、解決済みNameだけをOperation Manifestへ保存します。Database Configurationがない場合や未知のNamed Connectionは、Runtimeまで延期せず`php blackops build:compile`で拒否します。CredentialやConnection ParameterはManifestへ入りません。

実行順序は、Received／Attempt Startedの記録、Authorization、Application Transaction、Handler、成功Terminal、Commitです。Authorizationの拒否またはBackend FailureではApplication Transactionを開始しません。HandlerがRejected Resultを返すか`OperationRejectedException`を投げた場合、またはThrowable／Rollback-onlyが発生した場合は、業務更新をRollbackしてから既存のRejected／Failure Lifecycleを別Transactionで記録します。

Operation ConnectionとFramework Store Connectionが同じ`DatabaseManager`から返された同一`Connection` Instanceなら、次を一つのCommitへ含めます。

- Inline: 業務更新、`attempt.succeeded`、`operation.completed`
- Deferred: 業務更新、Claim Fencing、State／Sequence、Terminal Journal、Typed Outcome

InlineのObserved Journal配送とAfter Commit CallbackはCommit成功後だけ実行します。DeferredのFencing、Journal、Outcomeのどれかが失敗した場合は業務更新もRollbackし、成功StateやOutcomeを残さず既存Supervisionへ渡します。Retry／Dead LetterはRollback後のFramework Transactionで記録されます。

Operation ConnectionとFramework Store Connectionが異なる場合、Application TransactionはHandler完了時にCommitし、その後でFramework Terminalを保存します。後段のTerminal保存に失敗してもCommit済み業務更新は戻せません。BlackOpsはこの境界で二相Commit、Exactly-once、複数Connection間の原子性を保証しません。確実な連携にはTransactional Outboxを使ってください。
