# What's BlackOps

BlackOpsは、HTTPやWorkerから受けた処理を一つの処理単位として、同じIDで受付・再試行・完了を確認できるPHP 8.5向けFrameworkです。この処理単位をOperationと呼びます。HeadlessなのでUIを持たず、生成したJavaScript Client CodeをNext.js、Nuxt、SvelteKitなどのFrontendへ接続できます。

InlineはRequest内で完了する実行、Deferred Workerは受付後にWorkerが続ける実行です。Lifecycle Journalは受理された処理の実行事実を順序付きで記録します。Operationは型付きInput、Outcome、Execution Contextをこの実行境界へ集約します。

同期HTTPと非同期Jobを別々のModelで実装すると、同じ業務上の意図でもLifecycle、Retry、Trace、Outcomeの扱いが実行経路ごとに分かれます。障害調査ではController、Queue、Worker、Application Logを横断し、「処理を受理したのか」「何回試したのか」「最終結果は何か」を組み立て直さなければなりません。

BlackOpsは、Applicationが実行したい一つの意図を[Operation](glossary.md#operation)として表し、Request内で実行するInlineと、Durable受付後にWorkerが実行するDeferredをExecution Strategyの違いとして扱います。Operation、型付きInput、Outcome、追跡Contextは実行経路を変えても同じです。

## Headless Operation Framework

BlackOpsのHeadlessはUIを提供せず、JavaScript Client Codeを生成してFrontendへ接続するRuntimeです。Session Core、Authentication Middleware、Authorization PolicyのFramework境界を提供し、User、Password、Registration、Cookie／CSRF、Role、Tenant、Resource PolicyはApplicationが所有します。Domain OperationはHTTP Controller、BlackOps CLI、Deferred Workerなどの入口から分離できます。

BlackOpsはWeb Application全体を置き換えません。既存のRouter、Authentication、Template、Frontendと組み合わせながら、追跡可能にしたい処理をOperationとして実行できます。

## 一つのOperation Model

同じOperationをInlineまたはDeferredで実行しても、次の境界は変わりません。

- `OperationValue`が型付きInputを表します。
- Operationの`handle()`が業務処理を実行します。
- 正常完了時は型付き`Outcome`を返します。
- `ExecutionContext`がOperation ID、Correlation、Causation、Attemptを伝播します。
- `Journal`がLifecycleで起きた事実を追記します。

実行経路ごとの差は[Core Concepts](core-concepts.md)と[Inline and Deferred](execution.md)で確認できます。

## Lifecycle Journalで実行事実を追跡する

FrameworkがOperationとして受理した処理は、Inline／Deferredを問わずLifecycle Journalへ記録します。受理、Attempt開始、完了、業務拒否、失敗、Retryなどの事実をOperation IDから追跡できます。

この原則には明確な境界があります。Route不一致、壊れたJSON、必要Headerの欠落など、Operationとして受理する前のProtocol ErrorはHTTP等の入力Adapterの責務です。受理前のInputにはOperation Lifecycleがまだ存在しないため、Lifecycle Journalの対象にはなりません。入力AdapterのAccess LogやError Responseで観測してください。

## Laravel／Symfony経験からの対応

次の表はBlackOpsの概念を理解するためのMental Modelです。

| Laravel／Symfonyで馴染みのある概念 | BlackOpsの概念 | 主な違い |
| --- | --- | --- |
| Controller / Action | Operation | HTTPに限定されず、CLIやDeferred Workerからも同じOperationを実行できます。 |
| FormRequest / Request DTO | OperationValue | Operation Inputの型とValidation／Sensitive Metadataの境界です。 |
| API Resource / Response DTO | Outcome | 正常完了した業務Outputであり、Presentation Serializerそのものではありません。 |
| Job / Messenger Message / Queue | Deferred Execution Strategy | Operation本体ではなく、同じOperationをDurable受付後に実行するStrategyです。 |
| Request／Jobの実行履歴 | Lifecycle Journal | Operationとして受理されたLifecycleの事実を記録します。汎用Business／Security Audit Trailや任意のApplication LogはApplicationが所有します。Retention／Replay／Rotationなどの個別運用Eventは、Lifecycle Journalとは別のFramework運用契約で扱います。 |

この対応は一対一のAPI移植表ではありません。Controllerを機械的にOperationへRenameしたり、既存Queue MessageをそのままOperationへ置換したりするものではありません。入口から独立させたい業務上の意図、型付きInput／Output、追跡境界を見つけるために使ってください。

次は[Core Concepts](core-concepts.md)で、Operationと周辺概念の関係を一枚の図から確認します。
