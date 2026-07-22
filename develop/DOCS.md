# Operation-driven PHP Framework

> この文書の本文は、フレームワークの思想と概念を育てた歴史的な設計ノートである。
> 現行仕様の正本は `spec/`、MVPの実装状態は `../docs/guide/mvp-status.md`、現行Architectureは `../docs/internal/architecture.md` を参照する。

## Current Documentation

- [MVP Status and Definition of Done](../docs/guide/mvp-status.md)
- [Installed Application Status](../docs/internal/installed-application-status.md)
- [Remote Installation and Runtime Bootstrap](../docs/guide/runtime-bootstrap.md)
- [Skeleton Publication](../docs/internal/skeleton-publication.md)
- [Phase 8 Closeout Report](orchestration/reports/P8-004-phase-8-closeout.md)
- [MVP Sample](../docs/guide/mvp-sample.md)
- [Runtime Bootstrap](../docs/guide/runtime-bootstrap.md)
- [Data Retention](../docs/guide/retention.md)
- [Database Seeding](../docs/guide/database-seeding.md)
- [Database Seeding Internals](../docs/internal/database-seeding.md)
- [Architecture and Sequences](../docs/internal/architecture.md)
- [MVP and Consumer End-to-End](../docs/internal/mvp-e2e.md)
- [Framework Specification Index](spec/README.md)
- [Guide Index](../docs/guide/README.md)
- [Internals Index](../docs/internal/README.md)
- [Current Orchestration Checkpoint](STATE.md)

## Legacy Terminology Note

以下の歴史的本文には設計初期の仮称が残る。これらは現行API名ではない。

| 初期の仮称 | 現行名称／境界 |
| --- | --- |
| Immediate | Inline Execution Strategy |
| Durable | Deferred Execution Strategy |
| Dispatch Mode / `DispatchMode` | Execution Strategy / `Inline` / `Deferred` |
| Journal Entry | `JournalRecord` |
| Durable acknowledgement | `DeferredAcknowledgement` |
| SQLite／Filesystem Local Transport | PostgreSQL Reference Execution Transport |

初期サンプルの `CreateOrder` は実装仕様ではない。MVPの実サンプルは `ShowWelcome` と `GenerateReport` である。以下の説明と現行仕様が矛盾する場合は `spec/`を優先する。

設計上の未決事項と実装タスクは [TODO.md](TODO.md) で管理する。

設計上の対話と意思決定は [decisions/](decisions/) で管理する。

合意済みの仕様は [spec/](spec/) を正本として分野別に管理する。

## AI-DLC形式の設計対話

本プロジェクトでは、人間とAIの壁打ちをMarkdown上で行い、設計の過程と判断理由をリポジトリに残す。

### 基本的な流れ

1. AIが `decisions/` に連番付きの設計対話ファイルを作成する
2. AIが論点、背景、選択肢、推奨案を記述する
3. ユーザーが各 `[ANSWER]` ブロックへ回答を記入する
4. AIが回答を読み、矛盾、影響範囲、追加論点を提示する
5. 追加質問があれば、同じファイルへ質問と `[ANSWER]` ブロックを追記する
6. 合意後、AIが `[DECISION]` と `[CONSEQUENCES]` を記録する
7. AIが `spec/` の該当仕様、README、TODOを決定内容に合わせて更新する

### ファイル命名規則

```text
decisions/
  001-operation-definition.md
  002-operation-lifecycle.md
  003-dispatch-semantics.md
```

番号は議論を開始した順番を表す。論点が密接に関係する場合は一つのファイル内で複数の質問を扱ってよいが、独立して判断できる論点は分割する。

### 設計対話の形式

```md
# D001: 論点の名前

Status: Discussing

## Context

この判断が必要な背景。

## Question 1

質問内容。

### Options

- A: 選択肢A
- B: 選択肢B

### Recommendation

AIの推奨案とその理由。

[ANSWER]

<!-- ユーザーがここへ回答する -->

[/ANSWER]

## Decision

[DECISION]

<!-- 合意後にAIが記録する -->

[/DECISION]

## Consequences

[CONSEQUENCES]

<!-- 決定によって得られる利点、制約、後続タスクをAIが記録する -->

[/CONSEQUENCES]
```

### ブロックの責任

- `[ANSWER]` はユーザーが記入する
- `[DECISION]` は回答を基にAIが整理し、ユーザーとの合意後に確定する
- `[CONSEQUENCES]` はAIが影響範囲と後続タスクを記入する
- AIはユーザーが記入した `[ANSWER]` の内容を無断で書き換えない
- 回答が曖昧または矛盾する場合、AIは推測で決定せず追加質問を作成する
- `spec/` には `Decided` になった内容だけを反映する

### Status

各設計対話ファイルは、次のいずれかの状態を持つ。

| Status | 意味 |
| --- | --- |
| `Draft` | AIが質問を準備している |
| `Awaiting Answer` | ユーザーの回答待ち |
| `Discussing` | 回答を基に追加検討している |
| `Decided` | 合意し、設計へ反映済み |
| `Superseded` | 後の設計判断によって置き換えられた |

### TODOとの関係

- `Awaiting Answer` と `Discussing` の論点はTODO上では未完了とする
- `Decided` になった項目はTODOを完了にする
- 決定から発生した実装・検証作業は、新しいTODOとして追加する
- 決定が変更された場合、以前の記録は削除せず `Superseded` として残す

### 文書ごとの役割

| 文書 | 役割 |
| --- | --- |
| `README.md` | プロジェクトの思想、概要、設計対話の進め方 |
| `spec/*.md` | 分野別に管理する合意済み仕様の正本 |
| `decisions/*.md` | 質問、回答、判断理由、変更履歴 |
| `TODO.md` | 未決事項と実装・検証タスク |

## 概要

本プロジェクトは、すべての入力と処理を **Operation** という単位で捉える、PHP向けWebアプリケーションフレームワークを目指す。

一般的なWebフレームワークでは、ルーティング、HTTPリクエスト、キュー、ログなどが別々の仕組みとして扱われる。本フレームワークでは、それらの起点をOperationへ統一し、そのライフサイクルをJournalへ記録する。

OperationとJournalは次の役割を持つ。

- Operationは、要求の受付から最終結果まで続く論理的な処理単位である
- Operationは、対応するHandlerと型付けされた業務入力を表す
- Operation Envelopeは、ID、時刻、Context、実行方式などを保持する
- Journalは、Operationの受付、実行、再試行、完了などを追記型で記録する
- 即時処理と永続的な遅延処理を同じOperationモデルで扱う

これにより、HTTP、CLI、Cron、メッセージ、内部呼び出しなど、異なる入口から来た要求を同じ方法で追跡・実行できるようにする。

## 背景

この構想は、イベントソーシング、CQRS、Actor Model、Akkaなどから着想を得ている。

イベントを単位として出来事を記録する仕組みは、次の点で有用である。

- ユーザーの行動やシステムの処理を追跡できる
- 障害発生時の調査材料を残せる
- 監査証跡を構築できる
- 非同期処理の受付、実行、失敗を追跡できる
- アプリケーションの成長後も一貫した観測基盤を維持できる

一方、PHPによる一般的なWebアプリケーションで、Akkaのような常駐プロセス、Actor、メールボックス、監督戦略などをそのまま再現するのは現実的ではない。また、純粋なイベントソーシングやCQRSは、一般的なWeb開発者にとって導入時の概念的・運用的負担が大きい。

そこで本フレームワークは、それらを完全に再現するのではなく、**記録可能な処理単位、追跡可能性、非同期実行、結果整合性**という利点を、通常のPHP Web開発へ持ち込むことを狙う。

## 設計原則

### Operationを処理の起点にする

アプリケーションへの要求はOperationとして表現する。HTTPリクエストはOperationを生成する入口の一つであり、OperationそのものをHTTPに依存させない。

### OperationとJournal Entryは不変である

一度作成されたOperationと、記録されたJournal Entryの内容は変更しない。状態の変化は、新たなJournal Entryの追記として表現する。

### 観測可能性を後付けにしない

ログやトレースは任意の補助機能ではなく、フレームワークの基本機能として扱う。Operation IDを軸に、受付から処理結果までを追跡可能にする。

### 実行方法と業務処理を分離する

同じOperationを、呼び出し元の処理中に実行することも、永続化してWorkerから実行することも可能にする。業務処理は実行方式に可能な限り依存させない。

### 既存のPHP Web開発から段階的に導入できる

イベントソーシングを採用しなければ利用できない設計にはしない。一般的なRDBによる状態管理や、従来型のMVC/サービス層とも共存できることを重視する。

## Operation

Operationは、一般的なWebフレームワークにおけるルーティング対象と型付けされた入力を統合した、論理的な処理単位である。利用者が定義するOperationは業務入力だけを保持する。

```php
final readonly class CreateOrder implements Operation
{
    public function __construct(
        public CustomerId $customerId,
        public array $items,
    ) {
    }
}
```

FWはOperationを受け取ると、実行と追跡に必要なメタデータを含むOperation Envelopeを自動生成する。

| 項目 | 内容 |
| --- | --- |
| Operation ID | FWが生成するUUIDv7 |
| Created At | Operationを受け付けた時刻 |
| Operation | 利用者が定義した型付き業務入力 |
| Context | Trace ID、Correlation ID、Causation IDなど |
| Idempotency Key | 呼び出し元が任意指定する重複防止キー |
| Dispatch Mode | Operationをどのように実行するか |

Operationの型によって、対応するHandlerとOutcomeの型を定義する。

```php
final readonly class OperationEnvelope
{
    public function __construct(
        public OperationId $id,
        public DateTimeImmutable $createdAt,
        public Operation $operation,
        public OperationContext $context,
        public DispatchMode $mode,
    ) {
    }
}
```

Operationと、変更され得る処理ロジックはHandlerへ分離する。

```text
CreateOrder Operation
    -> CreateOrderHandler
    -> CreateOrderOutcome
```

## Journal

JournalはOperationそのものではなく、Operationのライフサイクルを表すJournal Entryの追記型ログである。

```text
OperationReceived
OperationAccepted
AttemptStarted
AttemptFailed
RetryScheduled
OperationCompleted
```

一つのOperationに複数のAttemptを関連付けることで、再試行を含む処理全体を同じOperation IDで追跡する。

## Outcome

**Outcome** は、Operationを処理した最終的な結果を表す仮称である。

HTTP固有の `Response` ではなくOutcomeと呼ぶことで、CLI、Worker、メッセージ処理などでも同じ概念を利用できる。Outcomeは成功だけでなく、拒否や業務上の失敗も表現できるものとする。

遅延処理では受付時点に最終Outcomeが存在しない。そのため、受付結果を表す **Acknowledgement** と、処理後のOutcomeは区別する。

```text
Immediate
    -> Journalを実行
    -> Outcomeを返す

Durable
    -> Journalを永続化
    -> Acknowledgementを返す
    -> WorkerがJournalを実行
    -> Outcomeを生成・記録する
```

`Outcome` および `Acknowledgement` は仮称であり、フレームワーク全体の語彙に合わせて今後検討する。

## 実行方式

当初の名称は `sync / defer` とする。ただし、遅延時間ではなく実行時の保証を表すため、次の名称を候補とする。

```php
enum DispatchMode
{
    case Immediate;
    case Durable;
}
```

### Immediate

Journalを呼び出し元の処理中に実行し、そのOutcomeを返す。

### Durable

Journalを信頼できる記憶装置へ先に保存し、Workerが後から実行する。呼び出し元には受付結果を返し、最終結果は結果整合的に反映される。

保存先はアダプタにより交換可能とし、RDB、KVS、メッセージキューなどを利用できる設計を目指す。

## 記録と観測

Operationのライフサイクルは、Operation IDを相関キーとしてJournalへ記録する。

想定する記録例：

- Journalの受付
- 永続化の完了
- Handlerの実行開始
- Handlerの実行完了
- Outcome
- 例外および失敗
- 再試行
- デッドレターへの移動

出力先はアダプタによって交換可能とする。

- 標準的なログ出力
- OpenTelemetry
- Amazon CloudWatch
- その他の監視・監査基盤

ただし、次の責務は分離する。

| 責務 | 用途 |
| --- | --- |
| Journal Observer | ログ、メトリクス、トレースなどの観測 |
| Journal Store | Journal EntryとDurable Operationを失わずに扱うための保存 |
| Outcome Store | 非同期処理結果の保存と参照 |
| Audit Store | 要件に応じた改ざん耐性のある監査記録 |

観測用ログだけを、非同期実行や監査の信頼できるデータ源として扱わない。

## セキュリティとプライバシー

「すべてを追跡可能にする」ことと、「すべての生データを無条件に出力する」ことは区別する。

パスワード、認証トークン、決済情報、個人情報などは、マスク、除外、暗号化、保持期間の制御が必要である。Journalの値には、観測可能な表現を明示する仕組みを用意する。

候補：

```php
final readonly class LoginValue
{
    public function __construct(
        public string $email,
        #[Sensitive]
        public string $password,
    ) {
    }
}
```

## 障害耐性

Journalを記録するだけでは、システムが自動的に障害へ強くなるわけではない。障害耐性は、記録に加えて再実行可能性と実行保証を定義することで成立する。

Durable実行では、まず次を基本方針の候補とする。

- at-least-once delivery
- Operation IDまたは冪等性キーによる重複実行対策
- リトライ回数とバックオフ
- タイムアウト
- デッドレター
- Worker停止後の処理再開
- 実行中にWorkerが停止した場合のLease回収

「exactly once」を抽象的に保証するのではなく、重複が起こり得ることを前提に、安全に処理できるモデルを提供する。

## イベントソーシングとの関係

本フレームワークのOperationはアプリケーションに対する要求を起点とする。Journal EntryはOperationのライフサイクル上で発生した事実を表すが、業務状態を再構築するためのDomain Eventとは区別する。

```text
CreateOrder           -> Operation
OperationReceived     -> Journal Entry
OrderCreated          -> Domain Event または Outcome
```

したがって、Journalの保存だけでドメイン状態を再構築できるとは限らず、本フレームワーク自体をイベントソーシング実装とは定義しない。

一方で、JournalとOutcomeまたはDomain Eventを永続化するアダプタを組み合わせることで、イベントソーシングを採用するアプリケーションにも発展できる余地を残す。

## 暫定用語

| 用語 | 意味 |
| --- | --- |
| Operation | 要求の受付から最終結果まで続く論理的な処理単位 |
| Operation Envelope | OperationとFW管理メタデータをまとめた内部の実行単位 |
| Operation ID | Operationを追跡するためFWが生成するUUIDv7 |
| Journal | Operationのライフサイクルを記録する追記型ログ |
| Journal Entry | Journalへ追記される個々の事実 |
| Attempt | Handlerによる一回の実行試行 |
| Handler | Operationに対応する処理 |
| Outcome | Operationを処理した最終結果 |
| Acknowledgement | Durable Operationを受け付けたことを示す結果 |
| Dispatch Mode | Operationの実行方式 |
| Immediate | 呼び出し元の処理中に実行する方式 |
| Durable | 永続化後、Workerで実行する方式 |
| Journal Store | Journal EntryとDurable Operationの保存先 |
| Journal Observer | ログ、トレース、メトリクスの出力先 |
| Worker | 保存されたOperationを取得して実行するプロセス |

## 未決事項

- Journalは「要求」だけを指すのか、処理中に発生した記録全体を含むのか
- JournalからHandlerを解決する登録方法
- JournalとOutcomeの型関係をPHPでどこまで静的に表現するか
- Outcome、Acknowledgement、Dispatch Modeの正式名称
- Immediate処理も永続化するか、観測ログだけに留めるか
- Durable処理のトランザクション境界
- Journal Storeと業務DBを同一トランザクションで扱うか
- JournalおよびOutcomeのスキーマバージョニング
- 順序保証と並列実行の単位
- キャンセル、期限切れ、優先度の表現
- リトライ可能な失敗と不可能な失敗の分類
- ログ、監査記録、イベントストアの保持期間
- 個人情報の削除要求と不変ログをどう両立するか
- HTTPステータスやHeaderなど、プロトコル固有情報への変換場所

## 現時点での到達点

本フレームワークは、純粋なイベントソーシングやActor FrameworkをPHPで再現するものではない。

目指すのは、通常のPHP Webアプリケーションの作りやすさを保ちながら、要求と処理結果をOperation IDで一貫して追跡し、即時処理と永続的な非同期処理を同じモデルで扱える基盤である。

その結果として、アプリケーションが成長した後も、障害調査、監査、観測、再実行を支える情報が最初から残るフレームワークを目指す。
