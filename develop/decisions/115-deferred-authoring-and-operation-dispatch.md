# D115: Deferred Authoring and Operation Dispatch

Status: Decided

## Context

BlackOpsのOperation入口はHTTP `#[Route]`、`#[ConsoleCommand]`、Application内部のchild dispatchに分かれ、実行方式は入口と独立してInlineまたはDeferredを選ぶ。

現行のDeferred指定は次のclass-string Attributeである。

```php
#[ExecuteWith(Deferred::class)]
```

ただし、実装済みStrategyはInlineとDeferredだけで、Inlineは既定値である。さらにRay.AopのTokenizer gapにより、複数のclass-valued Attributeを持つTransactional Operationでは次のliteral workaroundが必要になっている。

```php
#[ExecuteWith('BlackOps\\Core\\Execution\\Deferred')]
```

これは利用者へDependency固有の回避を露出し、Rename、Import、静的検査、Documentationの一貫性を損なう。

Phase 19では`TransactionalOutbox`によるDeferred child Operation発行を実装したが、Application側はDI依存を持つOperation Definitionを自分で構築して`register()`へ渡す。

```php
$this->outbox->register(
    new NotifyPostOwner($this->notifications),
    new NotifyPostOwnerValue(...),
);
```

Application CoordinationではOperation ClassとValueだけを指定し、DefinitionのDI解決とOutbox PersistenceをFrameworkへ委ねる方が自然である。

## Decision

### Deferred Authoring

利用者向けのCanonical Deferred記法を引数なしの`#[Deferred]`とする。

```php
use BlackOps\Core\Attribute\Deferred;

#[Deferred]
final readonly class NotifyPostOwner implements Operation
{
}
```

- Attributeなしは引き続きInlineとする。
- `#[Deferred]`はOperation ClassだけをTargetにする。
- `#[ExecuteWith(...)]`は既存Applicationとの互換性のため維持する。
- 同じOperationへ`#[Deferred]`と`#[ExecuteWith(...)]`を併置した場合は、意味が同じでもBuild Errorにする。
- `BlackOps\Core\Execution\Deferred`はManifest、Journal、Runtimeで使うStrategy Identityとして維持する。
- Reader-facing GuideとGenerator／Exampleは`#[Deferred]`を使用する。
- Ray.Aopの置換はPhase 21の責務であり、本変更でVendor Source、Proxy生成、Transaction Interceptionを変更しない。

### Application Child Dispatch

Application Coordination向けに`BlackOps\Execution\Operations`をPublic APIとして追加する。

```php
$this->operations->dispatch(
    NotifyPostOwner::class,
    new NotifyPostOwnerValue(...),
);
```

最初のCapabilityは、Framework管理TransactionへDeferred child Operationを登録する操作に限定する。

- 第一引数は登録済みOperation Definitionのclass-stringとする。
- 対象Operationは`#[Deferred]`または互換`#[ExecuteWith(Deferred::class)]`でDeferredと確定していなければならない。
- Operation DefinitionのConstructor DependencyはCompiled Container／Workerが解決し、呼び出し側はDefinitionを`new`しない。
- active Operation ContextとFramework-owned root Transactionを必須とする。
- 同じNamed ConnectionのTransactionへOutbox Recordを登録する。
- Transaction外、所有者不明、異なるConnection、Manual Commit／Nesting変更ではFail-fastし、Direct TransportへFallbackしない。
- 親Correlation、Causation、Actor、Deadlineと新しいchild Operation IDに関する既存Outbox Contractを維持する。
- Optional `availableAt`とexecution Actor overrideを維持する。
- 戻り値はOutbox Record IDを露出せず、child Operation IDとUTC dispatch時刻を持つDispatch Receiptとする。
- `TransactionalOutbox`は低Level互換APIとして維持するが、GuideとApplication Exampleは`Operations`を優先する。

Inline実行は既存`BlackOps\Execution\Dispatcher`の責務とし、本Taskで`Operations::dispatch()`へ統合しない。Direct Deferred Acceptanceを任意PHP Contextへ公開することも本Taskに含めない。

### Future Scheduled Entry

定期実行は将来の独立したOperation入口として扱う。

```php
#[ScheduledBy(...)]
#[Deferred]
final readonly class GenerateDailyReport implements Operation
{
}
```

- `#[ScheduledBy(...)]`と`#[ConsoleCommand(...)]`は独立させる。
- 手動実行を公開したいOperationだけが`#[ConsoleCommand]`を併用する。
- Scheduled Application Operationは既存のFramework Maintenance Schedulerと分離する。
- Schedule expression、Timezone、Misfire、Overlap、Identity、Idempotencyは別Decisionで確定する。
- 本Decisionは`ScheduledBy`を実装しない。

## Consequences

- 利用者はRay.Aop workaroundやStrategy class-stringを記述せずDeferred実行を宣言できる。
- Applicationはchild OperationのService Dependencyを構築せず、ClassとValueだけをdispatchできる。
- `dispatch()`はJobに近い語感を持つが、初期ContractはTransactional child dispatchであり、任意Contextの汎用Busではない。
- Existing Manifest／Journal／Transport Identityは`BlackOps\Core\Execution\Deferred`のまま変わらない。
- Scheduled Operationを追加するときも入口MetadataとExecution Strategyを独立して設計できる。

## Traceability

- [Execution](../spec/03-execution.md)
- [Durable Journal and Transactions](../spec/11-durable-journal-and-transactions.md)
- [Application Ergonomics](../spec/74-application-ergonomics.md)
- [Reliability and Delivery](../spec/80-reliability-and-delivery.md)
- [Operation Dispatch and Deferred Authoring](../spec/82-operation-dispatch-and-deferred-authoring.md)
- [D108 Ray.Aop Upstream and Phase Order](108-ray-aop-upstream-and-phase-order.md)
- [D109 Idempotency and Outbox](109-phase-18-idempotency-and-outbox.md)
