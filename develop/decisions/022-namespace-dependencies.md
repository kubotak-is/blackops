# D022: Namespace Dependencies

Status: Decided

## Context

単一Package内を責務別Namespaceへ分割し、内部実装を `BlackOps\Internal` へ隔離することを決定した。

ディレクトリを分けるだけでは、CoreがHTTPやSQLiteへ依存するような逆流を防げない。ここでは各Namespaceの依存方向と、違反を検出する方法を決める。

## Question 1: 依存方向

次を基本ルールとする案でよいか。

```text
Core
├── Journal
├── Execution
│   └── Transport
├── Http
├── Logging
└── Console
```

詳細な許可関係：

```text
Core       -> 外部Adapter Namespaceへ依存しない
Journal    -> Core
Execution  -> Core, Journal
Transport  -> Core, Journal, Execution
Http       -> Core, Execution
Logging    -> Core, Journal
Console    -> Core, Journal, Execution, Transport
Internal   -> 対応する公開Namespaceおよび採用Library
```

矢印は「左側が右側へ依存できる」を表す。たとえば `Http -> Execution` は許可するが、`Execution -> Http` は禁止する。

### Options

- A: この依存方向を採用する
- B: Core、Application、Adapterの3層ルールへ単純化する
- C: 一部の依存関係を修正する

### Recommendation

Aを推奨する。現在決めた責務別Namespaceを維持したまま、CoreからInfrastructureへの逆流と循環依存を防げる。

[ANSWER]

A,開発ではDeptracを使って依存の方向を制御したら良さそう

[/ANSWER]

## Question 2: 違反の検出

### Options

- A: ルールを文書化し、Code Reviewだけで運用する
- B: Architecture Testを導入し、CIでNamespace依存を検証する
- C: MVP中はCode Review、公開前にArchitecture Testを追加する

### Recommendation

Bを推奨する。

単一Packageでは意図しない依存が容易に成立するため、後から分離するより早期に機械検出した方が安い。具体的な検証Libraryは、PHP 8.5対応状況を確認して技術選定する。

[ANSWER]

Deptrac

[/ANSWER]

## Decision

[DECISION]

次のNamespace依存方向を採用する。

```text
Core       -> 外部Adapter Namespaceへ依存しない
Journal    -> Core
Execution  -> Core, Journal
Transport  -> Core, Journal, Execution
Http       -> Core, Execution
Logging    -> Core, Journal
Console    -> Core, Journal, Execution, Transport
Internal   -> 対応する公開Namespaceおよび採用Library
```

依存違反はDeptracで検出し、CIを失敗させる。`deptrac.yaml` をArchitecture ContractとしてRepositoryで管理する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- CoreからHTTP、Transport、Logging等への依存を禁止する。
- ExecutionからHTTP、Transport等への逆向き依存を禁止する。
- 単一Composer Package内でも循環依存と責務の逆流を機械的に検出できる。
- 新しいNamespace境界を追加する場合は、実装と同時にDeptracのLayerとRulesetを更新する。
- Deptrac自体の依存衝突を避ける必要が生じた場合は、PHARまたは分離したComposer Binaryとして導入できる。
- PHP 8.5の構文解析をCIで実証し、未対応の問題が生じた場合は対応Versionへの更新または一時的な代替手段を取る。

[/CONSEQUENCES]
