# D020: Package Architecture

Status: Decided

## Context

BlackOpsのMVP実装を開始する前に、Composer Packageの分割単位を決める必要がある。

最初から細かく分割すると依存関係とRelease管理が増える。一方、すべてを単一Packageへ固定すると、Core、HTTP、Deferred Execution、Loggingなどの境界が曖昧になりやすい。

ここでは、Repository構成と利用者へ公開するComposer Packageの単位を分けて考える。

## Question 1: MVPのPackage構成

MVPではどの単位でComposer Packageを公開するか。

### Options

- A: `blackops/framework` 単一Packageとして実装・公開する
- B: Monorepo内をComponentに分けるが、MVPでは `blackops/framework` のみ公開する
- C: 最初から `blackops/core`、`blackops/http`、`blackops/deferred` 等を個別公開する

### Recommendation

Bを推奨する。

内部のNamespaceとディレクトリでは責務を明確に分けつつ、MVPの依存解決、Version管理、利用開始手順は単一Packageに保てる。実利用から独立性が確認できたComponentだけ、後から個別Packageへ切り出せる。

[ANSWER]

A

[/ANSWER]

## Question 2: Component境界

BまたはCの場合、MVPの内部Componentを次の単位に分ける。

```text
Core        Operation、Envelope、Context、Handler、Outcome
Journal     Journal Record、Observer、Codec
Execution   Dispatcher、Strategy、Supervision
Transport   Deferred Transport、SQLite Adapter、Worker
Http        Route、Binding、Responder、PSR Adapter
Logging     Contextual Logger、Journal Logger
Console     CLI Command
```

### Options

- A: この7 Componentを基本境界とする
- B: MVPでは `Core`、`Http`、`Runtime` の3境界程度にまとめる
- C: 別の境界を提案する

### Recommendation

Aを推奨する。ただしComponentは当面Composer Packageではなく、Namespaceと依存方向を管理する設計境界とする。

[ANSWER]

<!-- 選択肢、理由、条件、懸念点を自由に記入してください。 -->

[/ANSWER]

## Decision

[DECISION]

MVPは `blackops/framework` 単一Composer Packageとして実装・公開する。

MVP時点ではComponentごとのComposer Package分割および独立Releaseを行わない。Question 2はBまたはCを選択した場合の条件付き質問であるため、今回の決定対象外とする。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 利用者は一つのPackageを導入すれば、MVPの全機能を利用できる。
- Package間のVersion整合、循環依存、分割ReleaseをMVPで管理する必要がない。
- Core、HTTP、Journal等の責務分離は、単一Package内のNamespaceとソース配置として別途設計する。
- Componentの個別Package化は、実利用から独立性と需要が確認された後に再検討する。

[/CONSEQUENCES]
