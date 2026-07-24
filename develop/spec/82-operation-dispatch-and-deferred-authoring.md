# Operation Dispatch and Deferred Authoring

## Entry and Execution Axes

Operationの入口とExecution Strategyを独立させる。

| Capability | Responsibility |
| --- | --- |
| `#[Route]` | HTTP入口を公開する |
| `#[ConsoleCommand]` | CLI入口を公開する |
| Attributeなしの内部Operation | Application Coordinationからだけ利用する |
| `#[Deferred]` | 入口に依存せずWorker実行を選ぶ |

HTTP RouteとConsole CommandはInline／Deferredの両方を利用できる。RouteもConsole Commandも持たないDeferred Operationは、親Operationからdispatchされる内部Jobとして利用できる。

将来の`#[ScheduledBy(...)]`は独立した入口Metadataとし、Console公開やDeferred指定を暗黙に追加しない。

## Deferred Attribute

Canonicalな利用者向け記法は`BlackOps\Core\Attribute\Deferred`とする。

```php
use BlackOps\Core\Attribute\Deferred;

#[Deferred]
final readonly class NotifyPostOwner implements Operation
{
}
```

CompilerはこのAttributeをManifest上の`BlackOps\Core\Execution\Deferred` Strategyへ正規化する。AttributeなしはInlineとする。

互換性のため`#[ExecuteWith(...)]`を維持するが、`#[Deferred]`との併置、同じStrategyの重複指定、矛盾するStrategy指定はBuild Errorにする。RuntimeはSource ReflectionへFallbackせず、Compile済みManifestの正規化済みStrategyだけを使う。

Ephemeral Outcomeの明示Inline Contractは本変更で緩和しない。Inline専用Attributeまたは既存`ExecuteWith(Inline::class)`の将来方針は別Taskで決める。

## Transactional Child Dispatch

Application Coordinationは`BlackOps\Execution\Operations`をConstructor Injectionし、Operation ClassとValueをdispatchする。

```php
final readonly class AddComment implements Operation
{
    public function __construct(
        private Operations $operations,
    ) {}

    #[Transactional]
    public function handle(AddCommentValue $value): CommentAdded
    {
        $this->operations->dispatch(
            NotifyPostOwner::class,
            new NotifyPostOwnerValue(...),
        );

        return new CommentAdded(...);
    }
}
```

`Operations::dispatch()`は初期CapabilityとしてDeferred child Operationだけを受理する。登録済みMetadata、Value Type、Deferred Strategyを検証し、Operation DefinitionをApplication側でInstance化しない。

dispatchは次の既存Outbox保証を維持する。

- active parent Operation Context
- Framework-owned root Transaction
- Application Database Configurationと同じNamed Connection Instance
- Application MutationとOutbox Insertの同一Transaction参加
- 新しいchild Operation ID
- 親Correlation IDと親Operation ID由来Causation ID
- origin／authorization Actor継承とOptional execution Actor override
- 親Deadlineを越えないContext
- Optional `availableAt`
- Rollback／Rollback-only時のOutbox Row非残存
- Relay／Retry／Dead Letterで同じOutbox Record／child Operation Identityを維持
- at-least-once delivery

Transaction外、異なるConnection、所有者不明、Manual Transaction変更では登録前に拒否し、Direct TransportへFallbackしない。

Dispatch Receiptはchild Operation IDとUTC dispatch時刻だけを公開する。Outbox Record ID、Payload、Credential、Connection、Raw ErrorはApplication APIへ露出しない。

`TransactionalOutbox`は互換用の低Level APIとして維持する。既存利用者を即時破壊せず、Guide、Generator、Quickstart、Community Boardは`Operations`へ移行する。

## Deferred and Scheduled Separation

`availableAt`は一回のchild dispatchを指定時刻以降に配送可能とする値であり、定期Scheduleではない。

Scheduled Application OperationではCron／Calendar、Timezone、Misfire、Overlap、Schedule Identity、Idempotency、手動実行との関係を別Decisionで定義する。既存Maintenance SchedulerはRetention／Outbox Relay等のFramework保守Runtimeであり、Application Operation Schedulerとして再解釈しない。

## Compatibility

- Existing `#[ExecuteWith(Deferred::class)]`は同じManifest Strategyを生成する。
- Existing `TransactionalOutbox::register()`は同じOutbox Persistenceを利用できる。
- Manifest Schema、Journal Strategy Identity、Transport Payload、Migrationは変更しない。
- Ray.Aop DependencyとTransaction Proxy生成は変更しない。
- `ScheduledBy`は未実装とする。
