# D021: Source Layout

Status: Decided

## Context

MVPは `blackops/framework` 単一Composer Packageと決定した。

単一Packageであっても、すべてのClassを平坦に置くと責務と依存方向が見えなくなる。一方、初期段階から厳密なComponent分割を導入すると、Class数が少ないうちは移動と抽象化の負担が大きい。

ここではComposer Packageを分割せず、`src/` 内のNamespaceとディレクトリ境界を決める。

## Question 1: `src/` の基本構造

### Option A: 責務別

```text
src/
  Core/
  Journal/
  Execution/
  Transport/
  Http/
  Logging/
  Console/
```

### Option B: 最小構成

```text
src/
  Core/
  Http/
  Runtime/
```

必要になった時点で細分化する。

### Option C: レイヤー別

```text
src/
  Contract/
  Application/
  Infrastructure/
  UserInterface/
```

### Recommendation

Aを推奨する。

BlackOps固有の概念がNamespaceへ直接現れ、Journal、Execution Transport、HTTP Adapterの責務を区別しやすい。Composer Packageは一つなので、公開・Version管理の複雑さは増えない。

[ANSWER]

A

[/ANSWER]

## Question 2: 内部APIの可視性

PHPにはPackage内限定の可視性がない。Framework利用者向けAPIと内部実装をどう区別するか。

### Options

- A: `Internal` Namespaceを設け、後方互換性の対象外であることを明示する
- B: Namespaceでは分けず、`@internal` PHPDocだけで示す
- C: MVPでは区別せず、安定版公開前に整理する

### Recommendation

Aを推奨する。

`BlackOps\Internal\...` を利用者が直接使用できないよう技術的に禁止はできないが、公開Contractとの境界が明確になる。Static AnalysisやDocumentation生成でも除外しやすい。

[ANSWER]

LaravelでいうとIlluminateみたいな感じ？

[/ANSWER]

### Follow-up 2-1: Laravelとの対応関係

完全には同じではない。

Laravelの `Illuminate` はFramework全体のルート名前空間であり、利用者が直接使う公開APIも含む。BlackOpsでは `BlackOps` がこれに相当する。

```text
Illuminate\Contracts\...  ≒ BlackOps\Core\... 等の公開Contract
Illuminate\Http\...       ≒ BlackOps\Http\...
Illuminate\Console\...    ≒ BlackOps\Console\...
```

提案している `BlackOps\Internal` は、利用者向けではない実装詳細を隔離するための追加的な境界である。

```text
BlackOps\Core\Operation                    公開API・後方互換性の対象
BlackOps\Journal\JournalObserver           公開拡張Contract
BlackOps\Internal\Journal\JsonLineWriter   内部実装・後方互換性の対象外
```

すべての実装Classを `Internal` へ入れるわけではない。利用者が型宣言、DI設定、Adapter実装などで参照するClassは責務別Namespaceへ置き、Framework内部からしか使わないClassだけを `Internal` へ置く。

### Question

この意味で内部実装の扱いをどうするか。

### Options

- A: `BlackOps\Internal` を設け、内部専用Classだけを配置する
- B: `Internal` Namespaceは設けず、`@internal` PHPDocで区別する
- C: MVPでは区別せず、公開API安定化時に整理する

### Recommendation

Aを推奨する。責務別Namespaceを公開APIの中心に保ちながら、互換性を約束しない実装詳細を明確に隔離できる。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

`blackops/framework` の `src/` は責務別のNamespaceとディレクトリへ分割する。

```text
src/
  Core/
  Journal/
  Execution/
  Transport/
  Http/
  Logging/
  Console/
  Internal/
```

`BlackOps\Core`、`BlackOps\Journal` 等には、利用者が型宣言、DI設定、Adapter実装で参照する公開APIと拡張Contractを置く。

`BlackOps\Internal` にはFramework内部からのみ使用する実装詳細を置き、後方互換性の対象外とする。

[/DECISION]

## Consequences

[CONSEQUENCES]

- BlackOps固有の責務がNamespaceへ直接現れる。
- Composer Packageを分割せずに、Core、Journal、Execution、Transport、HTTP等の境界を保てる。
- 公開APIは責務別Namespace、内部実装は `BlackOps\Internal` という判断基準を持つ。
- `Internal` ClassはPHPから参照可能だが、利用者は互換性を期待してはならない。
- 公開APIのSignatureへ `BlackOps\Internal` の型を露出させない。
- Namespace間の許可された依存方向は別途定める。

[/CONSEQUENCES]
