# HTTP、Inline、Deferred Execution

Operationの実行経路はDirectoryではなくMetadataで決まります。HTTP Routeを持つOperationはCompile済みHTTP Manifestへ登録され、Execution Strategyを指定しない場合はInline、`Deferred`を指定した場合はDurable受付になります。

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

## Worker

Project CLIからWorkerを起動します。

```bash
php bin/blackops blackops:worker:run --idle-sleep-milliseconds=1000
```

Workerは期限切れAttemptをRecoveryしてからClaimし、一度に最大1 Claimを処理します。Smoke Testでは`--iterations=N`でLoop回数を制限できます。常駐ProcessはProcess ManagerまたはCompose Worker Profileで監督してください。

PCNTL HeartbeatはHandler実行中だけLeaseを更新します。Heartbeat間隔はLeaseより短い正数にし、Heartbeat用DBAL ConnectionをClaim／Settlement用Connectionと分離します。

`SIGTERM`／`SIGINT`では新しいClaimを停止し、Grace Period内で実行中Handlerの完了を待ちます。Heartbeat失敗やGrace Timeout時はClaimを成功扱いせず、Lease ExpiryとRecoveryへ委ねます。

## Runtime Boundary

HTTPとWorkerはCompile済みOperation Manifest、HTTP Manifest、DI Containerだけを読み込みます。Runtime起動時にSource Discovery、Artifact Compile、Database MigrationへFallbackしません。Artifact不足、Schema Version不正、Build ID不一致は起動エラーです。

BuildとRuntimeの入口は[Project CLI](project-cli.md)、Contextの読み取りは[Execution Context](execution-context.md)を参照してください。
