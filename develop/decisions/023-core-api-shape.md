# D023: Core API Shape

Status: Decided

> Handlerの戻り値に関する決定はD035によって部分的に置き換えられた。Handlerは直接 `Outcome` ではなく `OperationResult<TOutcome>` を返す。Marker Interface、単一 `handle()` Method、`#[PublicApi]` の決定は有効である。

## Context

MVPの実装へ進むため、概念として決定済みのOperation、OperationValue、Outcome、Handlerを、PHPの公開APIとしてどの形にするか決める。

ここで決めるのは最小Contractであり、具体的なDispatcherやLifecycle処理は後続で設計する。

## Question 1: Marker Interface

Operation Definition、OperationValue、Outcomeを引数のないMarker Interfaceとして定義するか。

```php
interface Operation {}
interface OperationValue {}
interface Outcome {}
```

業務Classはこれらを実装し、関連付けは `#[Accepts]`、`#[Returns]` 等のAttributeで宣言する。

### Options

- A: Marker Interfaceを採用する
- B: 共通Methodを持つInterfaceにする
- C: 継承必須のAbstract Classにする

### Recommendation

Aを推奨する。業務ClassへFramework都合の状態や処理を持ち込まず、Manifest Compilerが型の取り違えを検出できる。

[ANSWER]

A

[/ANSWER]

## Question 2: Handler Contract

Handlerの公開Contractを次の形にするか。

```php
/**
 * @template TValue of OperationValue
 * @template TOutcome of Outcome
 */
interface OperationHandler
{
    /**
     * @param OperationEnvelope<TValue> $operation
     * @return TOutcome
     */
    public function handle(OperationEnvelope $operation): Outcome;
}
```

### Options

- A: 単一の `handle()` Contractを採用する
- B: Interfaceは設けず、`#[HandledBy]` が任意のCallableを指す
- C: `__invoke()` Contractを採用する

### Recommendation

Aを推奨する。DI、Static Analysis、Manifest検証、Decorator実装の基準が明確になる。業務ごとの具体的なOutcome型はPHPDoc GenericとManifest Compilerで検証する。

[ANSWER]

A

[/ANSWER]

## Question 3: Public APIの安定性

MVP段階の互換性方針をどうするか。

### Options

- A: `BlackOps\Internal` 以外はMVPからすべて互換性を保証する
- B: `#[Api]` または `@api` を付けた型だけを公開APIとして扱う
- C: `0.x` 中は互換性を保証せず、`1.0` 前に公開APIを確定する

### Recommendation

Bを推奨する。責務別Namespaceには公開ContractとFramework提供実装の両方が入り得るため、`Internal` との二分だけでなく、互換性を約束する型を明示できる。

[ANSWER]

B

[/ANSWER]

### Follow-up 3-1: 公開API Marker

公開APIであることを示す具体的なMarkerを決める。

### Options

- A: BlackOps固有の `#[PublicApi]` Attributeを付ける
- B: PHPDocの `@api` Tagを付ける
- C: `#[PublicApi]` と `@api` を併記する

### Recommendation

Aを推奨する。

`#[PublicApi]` はFramework固有の意味が明確で、ReflectionやManifest Compilerから機械的に収集できる。PHPDoc Parserへ依存せず、CIで「公開APIのSignatureにInternal型がないこと」も検証しやすい。

```php
#[PublicApi]
interface Operation
{
}
```

`#[PublicApi]` がない型は直ちに内部専用という意味ではないが、SemVer上の後方互換性を約束する対象には含めない。

[ANSWER]

Apiってなんだっけ？何を指している？

[/ANSWER]

### Follow-up 3-2: ここでいうPublic API

ここでいうAPIはHTTP Endpointではなく、BlackOpsを使うアプリケーションやAdapterがPHPコードから利用する **公開プログラミングインターフェース** を指す。

たとえば次は公開APIに含まれる。

```text
BlackOps\Core\Operation
BlackOps\Core\OperationValue
BlackOps\Core\Outcome
BlackOps\Core\OperationHandler
BlackOps\Core\Attribute\Accepts
BlackOps\Execution\Dispatcher
```

利用者はこれらを `implements`、型宣言、Attribute、DI設定などで直接参照する。そのため、正式な公開APIに指定した型では、Method名、引数、戻り値等の互換性をSemVerに従って扱う。

一方、次のようなFramework内部の組み立て用Classは公開APIではない。

```text
BlackOps\Internal\Manifest\AttributeReader
BlackOps\Internal\Execution\HandlerInvoker
```

`#[PublicApi]` は「このClassやInterfaceは利用者が直接使うことを公式に想定し、互換性の管理対象とする」という印である。実行時の機能を追加するAttributeではない。

### Question

この意味で、公開API Markerを採用するか。

### Options

- A: `#[PublicApi]` を採用する
- B: HTTP APIとの混同を避け、`#[PublicContract]` という名称にする
- C: Attributeは設けず、公開API一覧を文書だけで管理する

### Recommendation

Aを推奨する。`Public API` はLibrary設計で一般的な表現であり、InterfaceだけでなくAttribute、Value Object、例外、具体Classも対象にできる。BlackOpsの文書では必要に応じて「PHP Public API」と表記し、HTTP APIと区別する。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

Operation Definition、OperationValue、Outcomeは、共通Methodを持たないMarker Interfaceとする。

Handlerは `OperationHandler` を実装し、単一の `handle(OperationEnvelope $operation): Outcome` Methodを持つ。具体的なOperationValue型とOutcome型はPHPDoc Genericで表し、Manifest CompilerとStatic Analysisで整合性を検証する。

PHPコードから利用する公開Contractのうち、SemVer上の後方互換性を管理する型には、BlackOps固有の `#[PublicApi]` Attributeを付ける。

`#[PublicApi]` は実行時の振る舞いを追加せず、Framework利用者による直接利用を公式に想定したPHP Public APIであることを表す。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 業務ClassはFramework都合の共通状態や継承を要求されない。
- Handler呼び出しを一つのContractへ統一できる。
- DI、Decorator、Manifest検証、Static Analysisの基準が明確になる。
- PHPの実行時型だけではGenericの対応関係を完全には表現できないため、Compile時検証を必須とする。
- `#[PublicApi]` が付いた型の破壊的変更はSemVerに従って扱う。
- `#[PublicApi]` がない型は直ちに内部専用とは限らないが、後方互換性の保証対象には含めない。
- 公開APIのSignatureへ `BlackOps\Internal` の型を露出させない。

[/CONSEQUENCES]
