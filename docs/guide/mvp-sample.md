# Quickstart

Repository `main`のPreview Applicationを準備し、Header Authentication、Inline HTTP、Database Transaction、After Commit、Deferred Workerを一続きで確認します。ここで説明するAuthentication／AuthorizationとDatabase／Transaction Exampleは未Release Surfaceです。Experimental Stable `1.1.0`との差は[Current Status](mvp-status.md)で確認してください。

## 1. 実行Channelを選ぶ

### Stable 1.1.0

公開済みSkeletonだけを試す場合はVersionを固定します。

```bash
composer create-project blackops/skeleton my-app 1.1.0
```

StableにはGlobal Middleware、Authentication、`#[Authorize]`がないため、このPageのStep 2以降は実行できません。Stableの正確な提供範囲は[Current Status](mvp-status.md#stableとmain)で確認してください。

### Repository main Preview

以降の認証付きJourneyには、RepositoryのFramework SourceとQuickstartをLocal Path Repositoryで組み合わせたPreviewを使います。これはConsumer E2Eと同じ`symlink: false`／Version Mappingです。公開VersionのInstall手順ではありません。

```bash
git clone https://github.com/kubotak-is/blackops.git blackops-framework
cd blackops-framework

PREVIEW_DIR="$(pwd)/../blackops-preview"
mkdir -p "$PREVIEW_DIR"
cp -a examples/quickstart/. "$PREVIEW_DIR/"

docker run --rm --user "$(id -u):$(id -g)" \
  -v "$PWD:/framework:ro" -v "$PREVIEW_DIR:/app" -w /app composer:2 \
  composer config repositories.framework \
  '{"type":"path","url":"/framework","options":{"symlink":false,"versions":{"blackops/framework":"1.1.0"}}}'

docker run --rm --user "$(id -u):$(id -g)" \
  -v "$PWD:/framework:ro" -v "$PREVIEW_DIR:/app" -w /app composer:2 \
  composer install --no-interaction --prefer-dist

cd "$PREVIEW_DIR"
cp .env.example .env
mkdir -p var/build var/log
```

`SAMPLE_API_TOKEN=local-example`は`.env.example`からLocal Previewへ入ります。未設定または空文字の場合、Sample Authenticatorは既知値へFallbackせずRuntime構成を失敗させます。以降は`blackops-preview`をProject Rootとして実行します。

## 2. Image、Artifact、Databaseを準備する

```bash
docker compose build app http
docker compose run --rm app php blackops build:compile
docker compose run --rm app php blackops database:migrate
docker compose up -d
```

BuildはSourceからOperationとHTTP Manifest、DI Containerを生成します。MigrationとBuildはHTTP起動時に暗黙実行されません。`docker compose up -d`はHealthyなPostgreSQLとWorker Mode HTTPだけを起動し、Deferred WorkerやSchedulerを勝手に常駐させません。Classic Modeは`classic-mode` Profileの明示Fallbackです。

## 3. Inline Operationを呼ぶ

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

## 4. Transactional OperationでOrderを作る

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

Canonical JournalのTerminalも確認できます。

```bash
docker compose exec -T postgres psql -U blackops -d blackops -Atc "
SELECT event
FROM blackops.journal
WHERE operation_id = (
  SELECT operation_id
  FROM blackops.journal
  WHERE event = 'operation.received'
    AND convert_from(encoded_record, 'UTF8') LIKE '%order-001%'
  LIMIT 1
)
ORDER BY sequence;
"
```

```text
operation.received
attempt.started
attempt.succeeded
operation.completed
```

After Commitは同期Best-effortで、Callback失敗やProcess Crashを越えた自動Retryを行いません。Email、Webhook、Message Publishなどの確実なDeliveryにはTransactional Outboxが必要ですが、現行FrameworkはOutbox Persistence／Relayをまだ提供していません。詳しい保証差は[Database and Transactions](database-and-transactions.md)を参照してください。

## 5. Deferred Operationを受け付ける

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

## 6. Workerで完了させる

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
VALIDATION_OPERATION_ID='019f32ab-2be0-7b38-a0a7-1ab2f9687698'
grep "$VALIDATION_OPERATION_ID" var/log/journal.jsonl
```

Deferred Reportの受理から完了まではCanonical PostgreSQL Journalで確認します。

```bash
OPERATION_ID='019f32ab-2be0-7b38-a0a7-1ab2f9687697'
docker compose exec -T postgres psql -U blackops -d blackops -Atc "
SELECT sequence || '|' || event || '|' ||
       (convert_from(encoded_record, 'UTF8')::jsonb #>> '{operation,actors,authorization,id}') || '|' ||
       (convert_from(encoded_record, 'UTF8')::jsonb #>> '{operation,actors,execution,id}')
FROM blackops.journal
WHERE operation_id = '${OPERATION_ID}'::uuid
ORDER BY sequence;
"
```

```text
1|operation.received|quickstart-user|quickstart-user
2|operation.accepted|quickstart-user|quickstart-user
3|attempt.started|quickstart-user|quickstart-worker-1
4|attempt.failed|quickstart-user|quickstart-worker-1
5|retry.scheduled|quickstart-user|quickstart-worker-1
6|attempt.started|quickstart-user|quickstart-worker-1
7|attempt.succeeded|quickstart-user|quickstart-worker-1
8|operation.completed|quickstart-user|quickstart-worker-1
```

Canonical JournalはRaw Business ValueとActor IDを保持する監査正本です。暗号化、Access Control、RetentionをApplication／運用で構成してください。

Outcomeの取得にはPublic `OutcomeReader`をApplicationのHTTP／CLI入口から利用します。現行FrameworkはOutcome参照用の既成HTTP endpointを提供しません。詳しい取得例は[チュートリアル](first-operation.md#outcomeを読む)を参照してください。

## 7. 終了する

```bash
docker compose down
```

次は[チュートリアル: Operationを作る](first-operation.md)で、Generatorが作った3 FileへRoute、Value Validation、Deferred Strategyを追加します。
