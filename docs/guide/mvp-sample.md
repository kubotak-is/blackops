# Quickstart and Skeleton

このページは公開済みExperimental Stable `1.2.0` Applicationを準備し、Header Authentication、Inline HTTP、Database Transaction、After Commit、Deferred Workerを一続きで確認します。公開PackageのInstallは[Install](installation.md)で先に完了してください。ExperimentalなAuthentication／AuthorizationとDatabase／Transactionの制約は[Releases](mvp-status.md)で確認してください。

:::warning[Experimental Stable 1.2.0]
このページのStep 2以降はExperimental Stable `1.2.0`のSurfaceです。公開済みSkeletonのInstall後に実行し、提供範囲は[Releases](mvp-status.md)で確認してください。
:::

## 1. 実行Channelを選ぶ

### Stable 1.2.0

公開済みSkeletonだけを試す場合はVersionを固定し、[First Operation](first-operation.md)のStep 1〜3へ進みます。

```bash
composer create-project blackops/skeleton my-app 1.2.0
```

Stable `1.2.0`にはGlobal Middleware、Authentication、`#[Authorize]`、Frontend Operation Bridgeが含まれます。[First Operation](first-operation.md)のStep 1〜3（Generator、Value、Outcome）を実行し、Releaseの正確な提供範囲は[Releases](mvp-status.md)で確認してください。

### Stable 1.2.0 Authentication and Deferred Journey

公開済み`1.2.0` Packageから作成したApplicationで認証付きJourneyを実行します。Local Path Repository、Framework Source mount、既存Composer Cacheは使用しません。

```bash
composer create-project blackops/skeleton my-app 1.2.0
cd my-app
php bin/setup
```

`SAMPLE_API_TOKEN=local-example`は`.env.example`からLocal Applicationへ入ります。未設定または空文字の場合、Sample Authenticatorは既知値へFallbackせずRuntime構成を失敗させます。以降は`my-app`をProject Rootとして実行します。

### Stable 1.2.0 --no-scripts Authentication and Deferred Journey

Composer Scriptを実行しない場合も、公開済み`1.2.0` Packageを同じProject Rootへ作成し、Setupを明示実行してから共通Key Stepへ合流します。

```bash
composer create-project --no-scripts blackops/skeleton my-app 1.2.0
cd my-app
php bin/setup
```

normal／`--no-scripts`のどちらも、Setup直後に次の同じ必須Key Stepを実行します。

公開済み`1.2.0` Quickstart Runtimeには32-byte Base64のLocal Development Key／Local `StorageKeyProvider`が必要です。Key値は表示せず、Gitへ保存せず、ProductionではApplication-owned Secret Manager／KMS Providerへ置き換えます。

```bash
(
    set -euo pipefail
    umask 077
    chmod 600 .env
    test "$(stat -c '%a' .env)" = 600
    storage_key="$(head -c 32 /dev/urandom | base64 -w 0)"
    test -n "${storage_key}"
    decoded_storage_key_length="$(printf '%s' "${storage_key}" | base64 --decode | wc -c)"
    test "${decoded_storage_key_length}" -eq 32
    sed -i "s|^BLACKOPS_STORAGE_KEY=.*|BLACKOPS_STORAGE_KEY=${storage_key}|" .env
    test "$(grep -c '^BLACKOPS_STORAGE_KEY=' .env)" -eq 1
    test "$(grep -c '^BLACKOPS_STORAGE_KEY=$' .env)" -eq 0
)
```

接続できない、またはContainerの状態が分からない場合は[Troubleshooting](troubleshooting.md)を参照してください。

### Repository main Preview

このAnchorは旧PreviewからのMigration Linkを壊さないために残しています。現在のInstallと認証付きJourneyは、上記の公開済みExperimental Stable `1.2.0` Packageを使用してください。未公開RoadmapやLocal Path Repositoryを現行手順として案内しません。

## 2. Image、Artifact、Databaseを準備する

```bash
docker compose build app http
pnpm install --frozen-lockfile
docker compose run --rm app php blackops database:migrate
docker compose run --rm app php blackops build:compile
docker compose run --rm app php blackops database:seed
docker compose run --rm app php blackops frontend:generate
docker compose run --rm app php blackops frontend:check
pnpm test
docker compose up -d
```

BuildはSourceからOperation／HTTP／Frontend Contract ManifestとDI Containerを生成します。`frontend:generate`はContractから`resources/js/blackops/`へTypeScript ESMを生成し、`frontend:check`は現在のContractとPath／Bytesが一致するかを非破壊で確認します。`pnpm test`はDOMなしStrict TypeScriptでGenerated SourceとApplication-owned Consumer Sourceを検査します。

Migration、Build、Seed、Frontend生成はHTTP起動時に暗黙実行されません。Install直後の空Root Seederも明示的に実行し、`database:migrate -> build:compile -> database:seed`のDeployment順序を確認します。`docker compose up -d`はHealthyなPostgreSQLとWorker Mode HTTPだけを起動し、Deferred WorkerやSchedulerを勝手に常駐させません。Frontendを使わないApplicationはpnpm以降のFrontend Stepを省略できます。Classic Modeは`classic-mode` Profileの明示Fallbackです。

## 3. PHP Operationを先に確認する

Generated ClientはPHP OperationのContractを複製しません。公開済み`1.2.0` Quickstartに含まれる`examples/quickstart`の実装を先に確認します。`app/Feature/Welcome/ShowWelcome/ShowWelcome.php`は`GET /welcome`、Operation Type `welcome.show`を持つInline Operationです。`app/Feature/Welcome/ShowWelcome/WelcomeValue.php`は空のInput、`WelcomeShown.php`は`message` Outcomeを返します。

```php
use App\Security\SampleUserAuthorizationPolicy;
use BlackOps\Core\Attribute\Authorize;

#[Route(method: 'GET', path: '/welcome')]
#[OperationType('welcome.show')]
#[Authorize(SampleUserAuthorizationPolicy::class)]
final readonly class ShowWelcome implements Operation
{
    public function handle(WelcomeValue $value): WelcomeShown
    {
        return new WelcomeShown('Welcome to BlackOps');
    }
}
```

これは公開済み`1.2.0`の`/welcome`です。`1.2.0`はSample Authentication／Authorizationと`#[Authorize]`を追加します。`ShowWelcome`は`#[Authorize(SampleUserAuthorizationPolicy::class)]`で保護され、`X-Sample-Token: local-example`が`quickstart-user` Actorとして認証されるとHTTP `200`を返します。Header省略はAnonymousとして`401`、不正値はOperation受付前の`401`です。

```php
// 1.2.0のWelcomeValueはSample AuthenticationがHeaderを消費するため空です。
final readonly class WelcomeValue implements OperationValue {}

final readonly class WelcomeShown implements Outcome
{
    public function __construct(public string $message) {}
}
```

公開済みStable Tag `1.2.0`ではAuthentication MiddlewareがHeaderを消費し、`WelcomeValue`は空のOperation Valueです。SensitiveなTokenはApplication Operation Value、Transport、Journalへ保存されません。

`app/Feature/Report/GenerateReport/GenerateReport.php`は`POST /reports`、Operation Type `report.generate`を持つDeferred Operationです。`GenerateReportValue`の`reportName`とwrite-only `recipientEmail`から、`ReportGenerated`の`reportName`と`location`を返します。

## 4. Generated Operation Objectから呼ぶ

PHP Operationを手書きでTypeScriptへ複製しません。まずProject Rootの`tests/Frontend/try-client.ts`へ、生成RootとNode 24のglobal `fetch`を使う最小Clientを書きます。追加Dependencyは不要です。

```ts
import { createBlackOpsClient } from '../../resources/js/blackops';

const blackops = createBlackOpsClient({
  baseUrl: 'http://127.0.0.1:8080',
  fetch,
  headers: { 'X-Sample-Token': 'local-example' },
});

async function main(): Promise<void> {
  const response = await blackops.ShowWelcome.fetch({});
  if (response.ok && response.kind === 'completed') {
    console.log(response.status, response.data);
    return;
  }

  throw new Error('Welcome request did not complete.');
}

main().catch(() => {
  throw new Error('try-client failed.');
});
```

HTTPを起動したProject Rootで実行します。

```bash
pnpm exec tsc --ignoreConfig --ignoreDeprecations 6.0 --strict --target ES2022 \
  --module CommonJS --moduleResolution Node10 --lib ES2022,DOM --rootDir . \
  --outDir .build tests/Frontend/try-client.ts
node tests/Frontend/write-runtime-package.mjs
node .build/tests/Frontend/try-client.js
```

期待結果は`200 { message: 'Welcome to BlackOps' }`です。SvelteKitではServer-side Requestの補足として`event.fetch`を渡せますが、汎用JavaScriptの前提にはしません。

生成Rootから`createBlackOpsClient()`をImportし、Server RequestごとにBase URL、Fetch、Credentialを一度Bindingします。

```ts
import {
  createBlackOpsClient,
} from './resources/js/blackops';

const blackops = createBlackOpsClient({
  baseUrl: 'http://127.0.0.1:8080',
  fetch: event.fetch,
  headers: { 'X-Sample-Token': 'local-example' },
  credentials: 'same-origin',
});
```

このFactoryはServer-only Moduleで作ります。Browser BundleへPrivate Base URLやCredentialを含めず、ApplicationはSessionから安全なHeaderだけを組み立てます。

まず`.url()`へURLに必要なInputを渡します。Quickstartの4 OperationはPath／Query Parameterがないため引数は不要です。

```ts
const url = blackops.ShowWelcome.url();
```

```text
/welcome
```

`.toRequest()`は送信せず、Inputと呼出単位のCredentialからRequestを作ります。Sensitiveな`recipientEmail`は送信するWrite-only Inputですが、Generated SourceやResultへ値を埋め込みません。

```ts
const request = blackops.GenerateReport.toRequest(
  {
    reportName: 'weekly',
    recipientEmail: 'reports@example.com',
  },
);
```

```json
{
  "url": "http://127.0.0.1:8080/reports",
  "method": "POST",
  "headers": {
    "X-Sample-Token": "local-example",
    "Content-Type": "application/json"
  },
  "body": "{\"reportName\":\"weekly\",\"recipientEmail\":\"reports@example.com\"}"
}
```

`.fetch()`は同じBindingで実HTTPへ送り、`ok`と`kind`で判別できるResultを返します。入力と出力は次の対になります。Operation IDと時刻は実行ごとに変わります。

```ts
const welcome = await blackops.ShowWelcome.fetch({});
```

```json
{"ok":true,"kind":"completed","status":200,"data":{"message":"Welcome to BlackOps"}}
```

```ts
const report = await blackops.GenerateReport.fetch(
  { reportName: 'weekly', recipientEmail: 'reports@example.com' },
  { idempotencyKey: 'report-weekly-001' },
);
```

```json
{"ok":true,"kind":"accepted","status":202,"data":{"operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","acceptedAt":"2026-07-14T01:23:45.678901Z"}}
```

```ts
const validation = await blackops.GenerateReport.fetch(
  { reportName: '', recipientEmail: 'reports@example.com' },
);
```

```json
{"ok":false,"kind":"validation","status":422,"error":{"code":"validation.failed","operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687698","violations":[{"field":"reportName","rule":"not_blank","code":"validation.not_blank"}]}}
```

```ts
const order = await blackops.CreateOrder.fetch(
  { reference: 'order-frontend-001' },
  { idempotencyKey: 'order-frontend-001' },
);
```

```json
{"ok":true,"kind":"completed","status":200,"data":{"reference":"order-frontend-001","status":"created"}}
```

```ts
const internal = await blackops.TriggerFailure.fetch(
  { reference: 'incident-frontend-001', sensitiveNote: 'private note' },
);
```

```json
{"ok":false,"kind":"internal","status":500,"error":{"code":"internal_error","operationId":"019f76f1-3fdc-7c18-9d62-b182d42df100"}}
```

Network Errorや到達不能はHTTP Statusを捏造せずTransport Resultになります。Thrown ErrorのMessageやRaw BodyはResultへ含みません。

```ts
const unavailable = createBlackOpsClient({
  baseUrl: 'http://127.0.0.1:8080',
  fetch: async () => {
    throw new Error('connection detail must stay private');
  },
});
const transport = await unavailable.ShowWelcome.fetch({});
```

```json
{"ok":false,"kind":"transport","status":null,"error":{"code":"network_error"}}
```

MetadataはReadonly Literalとして同じObjectから参照できます。

```ts
blackops.ShowWelcome.type;     // 'welcome.show'
blackops.ShowWelcome.method;   // 'GET'
blackops.ShowWelcome.path;     // '/welcome'
blackops.ShowWelcome.strategy; // 'inline'
```

Generated ObjectはCallable／Thenableではありません。通信は`.fetch()`、一回の状態取得は`.status()`、有限待機は`.wait()`、Request参照は`.toRequest()`、URL参照は`.url()`と明示します。`.fetch()`は202後に自動Pollingしません。

202で得たOperation IDを使い、現在状態を一回だけ取得します。

```ts
const current = await blackops.GenerateReport.status(report.data.operationId);
```

```json
{"ok":true,"kind":"accepted","status":200,"data":{"schemaVersion":1,"operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","operationType":"report.generate","state":"accepted"},"retryAfterSeconds":1}
```

BrowserでTerminal Stateまで待つ場合はnative `AbortController`と有限Deadlineを渡します。

```ts
const controller = new AbortController();
const terminal = await blackops.GenerateReport.wait(report.data.operationId, {
  signal: controller.signal,
  maxWaitMilliseconds: 15_000,
});

if (terminal.ok && terminal.kind === 'completed') {
  terminal.data.outcome.reportName;
  terminal.data.operationId;
  terminal.data.outcome.location;
}
```

```json
{"ok":true,"kind":"completed","status":200,"data":{"schemaVersion":1,"operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","operationType":"report.generate","state":"completed","outcome":{"reportName":"weekly","location":"/reports/generated/weekly.json"}}}
```

Worker未起動の別Operationへ短いDeadlineを指定すると、無限に待たず`poll_timeout`で停止します。これはOperationのCancelではなく、Workerは後から同じOperationを処理できます。

```ts
const timedOut = await blackops.GenerateReport.wait(otherOperationId, {
  signal: new AbortController().signal,
  maxWaitMilliseconds: 150,
});
```

```json
{"ok":false,"kind":"transport","status":null,"error":{"code":"poll_timeout"}}
```

## 5. Inline Operationをcurlで呼ぶ

```bash
curl -sS -H 'X-Sample-Token: local-example' http://127.0.0.1:8080/welcome
```

```json
{"message":"Welcome to BlackOps"}
```

`GET /welcome`はRequest内で`ShowWelcome::handle()`を実行し、Typed OutcomeをHTTP 200へ変換します。

`X-Sample-Token`を省略するとOperation ID付き401、不正値を送るとOperation IDなし401になります。Header Credential自体はOperation ValueとJournalへ入りません。

```bash
curl -i http://127.0.0.1:8080/welcome
curl -i -H 'X-Sample-Token: invalid' http://127.0.0.1:8080/welcome
```

## 6. 失敗をOperation IDで調べる

Quickstartの`diagnostics.failure.trigger`は、認証済みInline Operationを意図的に失敗させるLocal Exampleです。入力の`reference`はApplication Logの相関用、`sensitiveNote`はSafe SurfaceのMask確認用です。

```bash
curl -sS -X POST -H 'Content-Type: application/json' \
  -H 'X-Sample-Token: local-example' \
  -d '{"reference":"incident-demo-001","sensitiveNote":"private diagnostic note"}' \
  http://127.0.0.1:8080/failures
```

```json
{"status":"error","code":"internal_error","operationId":"019f76f1-3fdc-7c18-9d62-b182d42df100"}
```

HTTPはException MessageやSensitive Noteを返さず、調査の入口となるUUIDv7 Operation IDを返します。以下のIDと時刻はExampleであり、実行ごとに変わります。

Human形式でLifecycleを読みます。

```bash
docker compose run --rm app php blackops operation:inspect \
  019f76f1-3fdc-7c18-9d62-b182d42df100
```

```text
Operation
  ID: 019f76f1-3fdc-7c18-9d62-b182d42df100
  Type: diagnostics.failure.trigger
  Strategy: inline
  Schema Version: 1
  Correlation ID: 019f76f1-3fdc-7c18-9d62-b182d42df100
  Causation ID: none
State
  Current: failed
  Terminal: yes
  Authority Source: journal
Availability
  Transport Payload: not_applicable
  Journal: available
  Outcome: not_applicable
  Dead Letter: not_applicable
Actors
  Origin: [masked] (user)
  Authorization: [masked] (user)
  Execution: [masked] (user)
Timeline
  #1 2026-07-18T20:35:49.867594Z operation.received | Attempt: none | Data: {"reference":"incident-demo-001","sensitiveNote":"[masked]"}
  #2 2026-07-18T20:35:49.869481Z attempt.started | Attempt: 019f76f1-3fed-76f4-bf86-338242667b74 (#1) | Data: {}
  #3 2026-07-18T20:35:49.872029Z attempt.failed | Attempt: 019f76f1-3fed-76f4-bf86-338242667b74 (#1) | Data: {"errorType":"RuntimeException","retryable":false}
  #4 2026-07-18T20:35:49.873693Z operation.failed | Attempt: 019f76f1-3fed-76f4-bf86-338242667b74 (#1) | Data: {"errorType":"RuntimeException","retryable":false}
Attempts
  #1 019f76f1-3fed-76f4-bf86-338242667b74 | Started At: 2026-07-18T20:35:49.869481Z | Sequences: 2, 3, 4
Outcome
  Availability: not_applicable
  Value: none
```

HTTP後のnon-root `operation:inspect`が`diagnostics.storage_failed`になる場合は、bind-mountされた`var/log/journal.jsonl`がHTTP Processでroot-ownedになっている制約を確認してください。Journalのmasked dataだけを確認し、このownership limitationとして扱います。

ScriptやSupport Toolから読む場合は同じIDをJSONで取得します。

```bash
docker compose run --rm app php blackops operation:inspect \
  019f76f1-3fdc-7c18-9d62-b182d42df100 --json
```

```json
{
  "schemaVersion": 1,
  "status": "found",
  "operation": {
    "operationId": "019f76f1-3fdc-7c18-9d62-b182d42df100",
    "type": "diagnostics.failure.trigger",
    "schemaVersion": 1,
    "strategy": "inline",
    "correlationId": "019f76f1-3fdc-7c18-9d62-b182d42df100",
    "causationId": null,
    "actors": {
      "origin": {"id": "[masked]", "type": "user"},
      "authorization": {"id": "[masked]", "type": "user"},
      "execution": {"id": "[masked]", "type": "user"}
    }
  },
  "state": {"current": "failed", "terminal": true, "source": "journal"},
  "availability": {
    "transportPayload": "not_applicable",
    "journal": "available",
    "outcome": "not_applicable",
    "deadLetter": "not_applicable"
  },
  "timeline": [
    {"sequence": 1, "event": "operation.received", "occurredAt": "2026-07-18T20:35:49.867594Z", "attemptId": null, "attemptNumber": null, "data": {"reference": "incident-demo-001", "sensitiveNote": "[masked]"}},
    {"sequence": 2, "event": "attempt.started", "occurredAt": "2026-07-18T20:35:49.869481Z", "attemptId": "019f76f1-3fed-76f4-bf86-338242667b74", "attemptNumber": 1, "data": {}},
    {"sequence": 3, "event": "attempt.failed", "occurredAt": "2026-07-18T20:35:49.872029Z", "attemptId": "019f76f1-3fed-76f4-bf86-338242667b74", "attemptNumber": 1, "data": {"errorType": "RuntimeException", "retryable": false}},
    {"sequence": 4, "event": "operation.failed", "occurredAt": "2026-07-18T20:35:49.873693Z", "attemptId": "019f76f1-3fed-76f4-bf86-338242667b74", "attemptNumber": 1, "data": {"errorType": "RuntimeException", "retryable": false}}
  ],
  "attempts": [{"attemptId": "019f76f1-3fed-76f4-bf86-338242667b74", "number": 1, "startedAt": "2026-07-18T20:35:49.869481Z", "events": [2, 3, 4]}],
  "outcome": null
}
```

Docker-only Quickstartでは、Host BrowserからLocal Viewerを利用できません。PostgreSQLをHostへPublishしておらず、Applicationの`POSTGRES_HOST=postgres`はCompose Network内だけで解決します。また、Viewerは起動したCLI Processの`127.0.0.1`だけにBindするため、Containerの外から到達できません。この構成では、前述の`docker compose run --rm app php blackops operation:inspect ...` Human／JSONを利用してください。Non-loopback Bindへ緩めて回避してはいけません。

ApplicationのViewer確認はViewerとHTTP Clientを同じnamed CLI Container、同じLocal Network Namespaceで動かし、Loopback限定のままToken／Session／Read-only動作を確認します。これはHost BrowserへViewerを公開する手順ではありません。

BrowserでViewerを使う場合は、Application／PHP CLI／PostgreSQL／Browserが同じLocal Network Namespaceから相互に到達できるNative Runtimeを準備します。Database HostもそのNative Runtimeから解決可能であることを確認したうえで、Project Rootから明示起動します。

```bash
php blackops operation:viewer
```

```text
http://127.0.0.1:8082/?token=<one-time-bootstrap-token>
```

このURLを一度開くとSession Cookieへ交換し、`/operations/019f76f1-3fdc-7c18-9d62-b182d42df100`でHuman表示と同じFailed State、Timeline、Attempt、Mask済みValue／Actorを表示します。Tokenなしは404、POSTは405です。Bootstrap URLを貼り付けたり保存したりせず、調査後はProcessを終了します。

`var/log/application.jsonl`にはApplication RecordとFramework Failure Recordが同じOperation／Attempt／Correlation IDで残ります。`reference`はApplication Contextにありますが、`sensitiveNote`、Exception Message、Credential、Raw Actor IDはありません。Canonical Journalの復元可能なRecordはBOPD v1 Envelopeで保護され、Tenant／Operation／Sequenceなどの最小MetadataだけがQuery用にClearです。Database／Volume At-rest Encryption、Access Control、RetentionはEnvelopeに加える運用層です。

## 7. Transactional OperationでOrderを作る

Install直後のOrder FeatureはRepository、Transactional Command、Transactional Operation、After Commit Serviceの関係を実行可能な形で示します。まずInputを送ります。

```bash
curl -sS -X POST -H 'Content-Type: application/json' \
  -H 'X-Sample-Token: local-example' \
  -d '{"reference":"order-001"}' \
  http://127.0.0.1:8080/orders
```

HTTP 200と次のTyped Outcomeが返ります。

```json
{"reference":"order-001","status":"created"}
```

同じOrderのLifecycleを確認するため、Protected `encoded_record`を読む代わりに、`operation_type`が明示されたClear MetadataだけからOperation IDを取得します。POST直後にProject Rootで次を実行し、返った一行を後続のStatus／Inspectへそのまま渡します。

```bash
ORDER_OPERATION_ID="$(docker compose exec -T postgres psql -U blackops -d blackops -Atc "
SELECT operation_id
FROM blackops.journal
WHERE event = 'operation.received'
  AND operation_type = 'order.create'
ORDER BY occurred_at DESC, sequence DESC
LIMIT 1;")"
test -n "${ORDER_OPERATION_ID}"
docker compose run --rm app php blackops operation:inspect "${ORDER_OPERATION_ID}" --json
```

このQueryは先行するWelcome／Failure／Console Operationを選ばず、直前に送った`order.create`の最新受付だけを選びます。`encoded_record`、Payload、Outcome BlobのDecode／JSON Castは行いません。

`CreateOrder::handle()`の`#[Transactional]`が最外Transactionを開きます。`CreateOrderCommand::execute()`も`#[Transactional]`ですが、同じDefault Connectionなので新しいDBAL Transactionを作らずNested Requiredで参加します。`DoctrineOrderRepository`はConstructor InjectionされたDefault DBAL `Connection`でParameterized SQLを実行します。

Business RowとAfter Commit Rowを入力と対にして確認します。

```bash
docker compose exec -T postgres psql -U blackops -d blackops -Atc "
SELECT reference FROM quickstart_orders WHERE reference = 'order-001';
SELECT reference FROM quickstart_order_commits WHERE reference = 'order-001';
"
```

```text
order-001
order-001
```

`RecordOrderCommit::record()`はTransaction内で呼び出しますが、`#[AfterCommit]`により最外Commit後までQueueされます。そのため、二つ目のRowはOrder Rowと成功Terminal JournalのCommit後に追加されます。

Canonical JournalのTerminalは、Protected BlobをDatabaseから読むのではなく、認可済みのStatus／Inspect経路で確認します。ここでも同じOrderの受付を表す`${ORDER_OPERATION_ID}`を再利用し、別OperationのIDへ置き換えません。

```bash
docker compose run --rm app php blackops operation:inspect "${ORDER_OPERATION_ID}" --json
```

```json
{"schemaVersion":1,"status":"completed","operationId":"<operation-id>","events":["operation.received","attempt.started","attempt.succeeded","operation.completed"]}
```

After Commitは同期Best-effortで、Callback失敗やProcess Crashを越えた自動Retryを行いません。Email、Webhook、Message Publishなどのat-least-once DeliveryにはTransactional Outboxを使い、Relayの停止／再開、Retry、Dead Letter再開を明示的に運用します。詳しい保証差は[Transaction](database-and-transactions.md)を参照してください。

## 8. Deferred Operationを受け付ける

```bash
curl -sS -X POST -H 'Content-Type: application/json' \
  -H 'X-Sample-Token: local-example' \
  -d '{"reportName":"weekly","recipientEmail":"reports@example.com"}' \
  http://127.0.0.1:8080/reports
```

```json
{"status":"accepted","operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","acceptedAt":"2026-07-14T01:23:45.678901Z"}
```

HTTP 202はHandler完了ではなく、ValueとContextをPostgreSQLへDurableに保存した合図です。`operationId`と`acceptedAt`は実行ごとに変わります。

同じCredentialでPublic Status Resourceを読むと、Worker未起動中は`accepted`と正整数`Retry-After`を返します。

```bash
OPERATION_ID='<operation-id-from-accepted-response>'
curl -i -H 'X-Sample-Token: local-example' \
  "http://127.0.0.1:8080/operations/${OPERATION_ID}"
```

```http
HTTP/1.1 200 OK
Content-Type: application/json
Cache-Control: private, no-store
Retry-After: 1

{"schemaVersion":1,"operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","operationType":"report.generate","state":"accepted"}
```

空の`reportName`は宣言的Validationで受付前にHTTP 422となります。Inline／DeferredのどちらもValidation Failureを202にせず、Handlerを実行しません。

```bash
curl -sS -X POST -H 'Content-Type: application/json' \
  -H 'X-Sample-Token: local-example' \
  -d '{"reportName":"","recipientEmail":"reports@example.com"}' \
  http://127.0.0.1:8080/reports
```

```json
{"status":"rejected","operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687698","category":"validation","code":"validation.failed","violations":[{"field":"reportName","rule":"not_blank","code":"validation.not_blank"}]}
```

## 9. Workerで完了させる

Sample Reportは一回目のAttemptでRetryを要求し、二回目で成功します。

```bash
docker compose run --rm app php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
sleep 2
docker compose run --rm app php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
```

```text
Worker stopped. Processed claims: 0
Worker stopped. Processed claims: 1
```

Canonical Journalではauthorization Actorが`quickstart-user`のまま維持され、Worker Eventのexecution Actorだけが`quickstart-worker-1`／`system`になります。Workerは各Attemptで同じPolicyを再評価します。

`var/log/journal.jsonl`はHTTP ProcessのObserved Projectionです。Inline Welcomeと、HTTP内で完了するValidation Rejection等ではActor IDと`recipientEmail`を`[masked]`にしますが、Worker Eventは追記しません。Valid Deferred Reportの完了をJSONLで待たないでください。Header CredentialはMask対象として保存するのではなく、最初からValue／Transport／Journalへ含めません。

```bash
VALIDATION_OPERATION_ID='<operation-id-from-validation-response>'
grep "$VALIDATION_OPERATION_ID" var/log/journal.jsonl
```

Deferred Reportの受理から完了まではCanonical PostgreSQL Journalで確認します。

```bash
OPERATION_ID='<operation-id-from-accepted-response>'
docker compose exec -T postgres psql -U blackops -d blackops -Atc "
SELECT sequence || '|' || event || '|' || operation_id || '|' || occurred_at
FROM blackops.journal
WHERE operation_id = '${OPERATION_ID}'::uuid
ORDER BY sequence;
"
```

```text
1|operation.received|<operation-id>|2026-07-14T01:23:45.678901Z
2|operation.accepted|<operation-id>|2026-07-14T01:23:45.678902Z
3|attempt.started|<operation-id>|2026-07-14T01:23:45.678903Z
4|attempt.failed|<operation-id>|2026-07-14T01:23:45.678904Z
5|attempt.retry_scheduled|<operation-id>|2026-07-14T01:23:45.678905Z
6|attempt.started|<operation-id>|2026-07-14T01:23:46.678903Z
7|attempt.succeeded|<operation-id>|2026-07-14T01:23:46.678904Z
8|operation.completed|<operation-id>|2026-07-14T01:23:46.678905Z
```

Canonical JournalはOperation Lifecycleの正本ですが、復元可能なBusiness DataはBOPD v1 Envelopeへ保存されます。汎用Business／Security Audit TrailのAction、Resource、ReasonはApplicationの責務です。Public SQLはProtected Blobをdecode／JSON castせず、Clear lifecycle metadataまたは認可済みStatus／Inspect Queryだけを使います。Envelope、Access Control、Retention、Database At-rest Encryptionの責務を混同しないでください。

Worker完了後は同じStatus ResourceがTyped Outcomeを返し、Terminal Responseに`Retry-After`は付きません。

```json
{"schemaVersion":1,"operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","operationType":"report.generate","state":"completed","outcome":{"reportName":"weekly","location":"/reports/generated/weekly.json"}}
```

Quickstartの`SampleOperationStatusAuthorizer`は、Current Actorと受付時のOrigin Actorがともに`user`で、ID／Typeが完全一致するときだけAllowします。これはLocal Exampleであり、ProductionのTenant／Role／Resource Policyではありません。Header欠落はAnonymousのためUnknown／Denyと同じ404、不正TokenはSubject読取前の401です。Operation IDはSecretではありませんが、知っているだけでは参照権限を得ません。

PHP AdapterからOutcomeを読む場合はDefault-deny `OperationOutcomeQuery`へTenant／Actor／Purposeを渡します。Pending、Terminal、Expiredを区別する主経路はStatus Resourceです。詳しくは[Outcome](outcome-retrieval.md)と[Tenant and Storage Protection](tenant-protection.md)を参照してください。

## 10. 終了する

```bash
docker compose down
```

次は[First Operation](first-operation.md)で、Generatorが作った3 FileへRoute、Value Validation、Deferred Strategyを追加します。起動、Token、Migration、Buildの問題は[Troubleshooting](troubleshooting.md)へ戻ってください。
