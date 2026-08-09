# Journal Documentation

## Purpose

JournalをLandingの特徴紹介だけで終わらせず、利用者がLifecycleの読み方、Observed JSONLの構造、設定、Replay、安全な運用、Repository `main`で利用可能なOpenTelemetry API-only接続まで正確に理解できる独立Guideを提供する。

## Public Information Architecture

- Source: `docs/guide/journal.md`
- H1: `Journal`
- Public Route: `/concepts/journal`
- Sidebar: `Operation` Sectionの`Lifecycle`直後
- Landing: Journal Feature CTAから直接遷移

`docs/guide/README.md`、Core Concepts、Lifecycle、Observer ReplayからJournal Pageへ相互に辿れること。

## Reader Contract

Journal Pageは次を利用者の順序で説明する。

1. JournalがOperationの受理、試行、Retry、拒否、完了をOperation IDと単調増加`sequence`で記録すること
2. Canonical JournalとObserved Journalの目的、保存先、Sensitive境界の違い
3. 現在のLifecycle Event 10個
4. Observed JSONLの正確なRecord構造
5. Eventごとに`data`が異なること
6. JSONL Observerの設定と配送失敗Policy
7. Observer Replay、Retention、Access Control、保存時暗号化
8. Stable `1.1.0`とRepository `main`のOpenTelemetry API-only境界

Application Log、Outcome、Execution TransportをJournalと同一視しない。

## Observed JSONL Contract

JSON例は`JsonlJournalRecordEncoder`の現在の出力へ一致させる。

- Top Level: `schemaVersion`, `kind`, `recordId`, `event`, `occurredAt`, `sequence`, `operation`, `attempt`, `data`
- `kind`: `journal`
- `occurredAt`: UTC、Microseconds付きRFC 3339形式
- `operation`: `id`, `type`, `schemaVersion`, `strategy`, `correlationId`, `causationId`, `actors`, `tenant`, optional `schedule`
- `actors`: `origin`, `authorization`, `execution`
- Actor: `{ "id": "[masked]", "type": string }`または`null`
- Tenant: `{ "id": "[masked]", "type": string }`または`null`
- Schedule: `{ "name": string, "scheduledAt": UTC microseconds RFC 3339 string }`
- `attempt`: `null`または`id`, `number`, `startedAt`
- `data`: Event固有のSensitive Projection済みObject

Empty DataとNested Empty OutcomeはJSON Object `{}`、Empty ListはJSON Array `[]`として区別する。Event Data内のFramework IdentifierはUUIDv7 String、日時はUTC、Microseconds付きRFC 3339 Stringである。

公開例はObserved Recordであり、Canonical PostgreSQL PayloadのSerialization Contractではない。

Reader-facing PageではJSONL例の直後に、文章によるField列挙ではなくMarkdown TableでParameterを説明する。

- Top-level Record
- `operation`
- `operation.actors`とActor
- `attempt`
- Event固有`data`

各Tableは少なくともParameter Path、Type／Nullable、意味を持つ。Event固有`data`は10 Lifecycle Eventすべてを扱い、空Objectを持つEventも省略しない。DesktopとMobileでPage全体の横Overflowを発生させず、Mobileでは内容を欠落させない。

`operation.received`は通常Operationの`data.value`とEphemeral Outcomeの空`data`でShapeが異なるため、Event固有Data Tableで別Variantとして説明する。

## Lifecycle Events

現在のEvent集合を省略せず記載する。

```text
operation.received
operation.accepted
attempt.started
attempt.succeeded
attempt.failed
attempt.retry_scheduled
operation.completed
operation.rejected
operation.failed
operation.dead_lettered
```

`operation.accepted`はDurableなDeferred受理にだけ現れる。InlineとDeferredでEvent列が異なることはLifecycle Pageへ接続して説明する。

## Security and Operations

- Canonical Journalは再現・復旧に必要な型付きRecordであり、PostgreSQL側の保護対象とする。
- Observed Journalは共通Sensitive Projection後にObserverへ渡す。
- `#[Sensitive]`はObserved ProjectionのOmit／Mask／Hashを制御するが、Canonical Storeの保存時暗号化を提供するものではない。
- JSONLは絶対Pathと書込み可能な既存Parent Directoryを必要とする。
- Deliveryは`best_effort`または`required`。
- File権限、Sink Access Control、Rotation、Backup、Retention、保存時暗号化、Key管理はApplication／運用責務との境界を明記する。
- Observer ReplayとCanonical Operation Replayを混同しない。

## OpenTelemetry Boundary

Stable `1.1.0`にはOpenTelemetry Adapter、Exporter、Operational Health Queryは含まれない。Repository `main`ではFrameworkが`open-telemetry/api`だけをProduction Dependencyとして持ち、ApplicationがSDK、Exporter、Resource、Endpoint、Credential、Provider Compositionを所有するAPI-only Surfaceを提供する。FrameworkはSDK／Exporter／Remote Deliveryを所有せず、CollectorをDefault Composeへ追加しない。

Repository `main`でGuideが説明する利用可能な境界は次である。

- Operation／AttemptからSpanを構成する
- Lifecycle EventをSpan Eventへ変換する
- Retry、Rejected、Failure、Dead LetterをMetricへ集約する
- Correlation／CausationをTrace Contextへ接続する
- AdapterへはCanonical PayloadではなくObserved Projectionを渡す
- W3C `traceparent`／`tracestate`をHTTP、Deferred、Worker、Outboxへ伝播する
- Provider／Exporter FailureをBest-effortで扱い、Primary Operation／Readinessを変更しない
- Applicationが明示したHealth Route／CLIと、Consumer専用Local Docker Collectorを接続する

Vendor Backend、Dashboard、Alert、Production Collector Deployは対象外である。Semantic Convention、Sampling、Remote Exporterの選択はApplication責務であり、Stable `1.1.0`のPublic Contractとして記載しない。

## Verification

- Journal Source、Content Map、Sidebar、Landing CTAが同じRouteへ同期する
- Page H1とSidebar Labelが一致する
- JSON／JSONL Exampleを機械的にParseできる
- Example FieldとLifecycle Eventが実装へ一致する
- JSONL ParameterがTop-level、Operation、Actors、Attempt、Event固有DataのTableで説明される
- Canonical／Observed／Sensitive／Replay／Retentionの境界がRegressionで固定される
- Stable `1.1.0`とRepository `main`のOpenTelemetry Surfaceを混同させない
- Blume Check、Build、Link／Artifact Guardが成功する
- Desktop Light／DarkとMobile 390pxで、Table、Code、Sidebar Current State、Page Overflowを実Browser確認する

## Traceability

- Decision: [D127 Journal Documentation](../decisions/127-journal-documentation.md)
- Journal Schema: [Specification 22](22-journal-record-schema.md)
- Public Journal API: [Specification 23](23-journal-record-api.md)
- Lifecycle Data: [Specification 24](24-lifecycle-event-data.md)
- Retention: [Specification 38](38-data-retention-and-deletion.md)
- Website: [Specification 83](83-blume-documentation-experience.md)
