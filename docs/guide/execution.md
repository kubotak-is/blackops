# HTTP、Inline、Deferredの実行

Operationの実行経路はDirectoryではなくMetadataで決まります。HTTP Routeを持つOperationはCompile済みHTTP Manifestへ登録され、Execution Strategyを指定しない場合はInline、`Deferred`を指定した場合はDurable受付になります。

```mermaid
sequenceDiagram
    accTitle: InlineとDeferredの実行順序
    accDescr: InlineはHTTP Request内でAttemptを開始してOutcomeを返す。DeferredはOperationをDurableにAcceptedとして202を返し、後でWorkerがClaimしてAttemptとOutcomeを完了する。
    actor Client
    participant HTTP as HTTP Adapter
    participant Journal
    participant Operation
    participant Store as Durable Store
    participant Worker
    alt Inline
        Client->>HTTP: Request
        HTTP->>Journal: operation.received
        HTTP->>Journal: attempt.started
        HTTP->>Operation: handle(value, context)
        Operation-->>HTTP: Outcome
        HTTP->>Journal: attempt.succeeded / operation.completed
        HTTP-->>Client: HTTP Response
    else Deferred
        Client->>HTTP: Request
        HTTP->>Journal: operation.received
        HTTP->>Store: Value / ContextをDurable保存
        HTTP->>Journal: operation.accepted
        HTTP-->>Client: 202 + Operation ID
        Worker->>Store: Claim
        Worker->>Journal: attempt.started
        Worker->>Operation: handle(value, context)
        Operation-->>Worker: Outcome
        Worker->>Store: Outcome保存
        Worker->>Journal: attempt.succeeded / operation.completed
    end
```

InlineはHTTP Request内で`operation.received`から直接Attemptを開始し、OperationのOutcomeをHTTP Responseへ変換して返します。DeferredはValueとContextをDurable Storeへ保存し、`operation.accepted`の後にHTTP 202とOperation IDを返します。Workerは後から[Claim](glossary.md#claim)を取得し、Attempt、Outcome保存、完了Journalを実行します。

## Inline HTTP

```php
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'GET', path: '/welcome')]
#[OperationType('welcome.show')]
final readonly class ShowWelcome implements Operation
{
    public function handle(WelcomeValue $value): WelcomeShown
    {
        return new WelcomeShown('Welcome to BlackOps');
    }
}
```

HTTP HandlerはCompile済みRouteを照合し、RequestをValueへBindして、ContainerからOperationを解決します。Handler実行とLifecycle JournalをRequest内で完了し、OutcomeをHTTP Responseへ変換します。

## Deferred HTTP

```php
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/reports')]
#[OperationType('report.generate')]
#[ExecuteWith(Deferred::class)]
final readonly class GenerateReport implements Operation
{
    public function handle(GenerateReportValue $value, ExecutionContext $context): ReportGenerated
    {
        return new ReportGenerated($value->reportName, $context->operationId()->toString());
    }
}
```

Deferred RouteはHTTP 202とOperation IDを返し、HandlerをHTTP Process内で実行しません。Operation Value、Context、受付JournalをPostgreSQLへDurableに保存します。

## Frontendから受付と完了を分ける

Generated Operation Objectは三つの異なる操作を明示します。

| Method | 通信 | Result |
| --- | --- | --- |
| `.fetch(value, options)` | Operation Routeへ1 Request | Inline完了、またはDeferred受付202。自動Pollingしない |
| `.status(operationId, options)` | Status Resourceへ1 GET | 7 Lifecycle State、または401／404／410／500／Transport Failure |
| `.wait(operationId, options)` | `Retry-After`に従う有限のStatus GET | Completed／Rejected／Failed／Dead Lettered、またはFailure |

```ts
const accepted = await GenerateReport.fetch(value, options);

if (accepted.ok && accepted.kind === 'accepted') {
  const current = await GenerateReport.status(accepted.data.operationId, options);
  const controller = new AbortController();
  const terminal = await GenerateReport.wait(accepted.data.operationId, {
    ...options,
    signal: controller.signal,
    maxWaitMilliseconds: 15_000,
  });

  void current;
  void terminal;
}
```

`.wait()`は正のSafe Integer Deadlineと購読可能なAbort Signalを必須にします。Non-terminalだけをServerの正整数`Retry-After`に従って再取得し、401、404、410、500、Network Error、不正Responseでは停止します。無限待機、独自Backoff、Global Mutable Clientは提供しません。

## Worker

Project CLIからWorkerを起動します。

```bash
php blackops worker:run --idle-sleep-milliseconds=1000
```

Workerは期限切れAttemptをRecoveryしてから[Claim](glossary.md#claim)し、一度に最大1 Claimを処理します。Smoke Testでは`--iterations=N`でLoop回数を制限できます。常駐ProcessはProcess ManagerまたはCompose Worker Profileで監督してください。

PCNTL [Heartbeat](glossary.md#heartbeat)はHandler実行中だけ[Lease](glossary.md#lease)を更新します。Heartbeat間隔はLeaseより短い正数にし、Heartbeat用DBAL ConnectionをClaim／Settlement用Connectionと分離します。

`SIGTERM`／`SIGINT`では新しいClaimを停止し、Grace Period内で実行中Handlerの完了を待ちます。Heartbeat失敗やGrace Timeout時はClaimを成功扱いせず、Lease ExpiryとRecoveryへ委ねます。

## Runtime Boundary

HTTPとWorkerはCompile済みOperation Manifest、HTTP Manifest、DI Containerだけを読み込みます。Runtime起動時にSource Discovery、Artifact Compile、Database MigrationへFallbackしません。Artifact不足、Schema Version不正、Build ID不一致は起動エラーです。

BuildとRuntimeの入口は[Project CLI](project-cli.md)、Contextの読み取りは[Execution Context](execution-context.md)を参照してください。
