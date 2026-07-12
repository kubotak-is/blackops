# D025: ExecutionContext API

Status: Decided

## Context

Operation Envelope内の識別情報はExecutionContextを正本とすることが決定した。

ExecutionContextはOperation受付時に生成されるが、Attempt IDとAttempt開始時刻はHandler実行直前まで存在しない。Deferred Operationでは受付ProcessとWorker Processも分かれる。この時間差を、型として不自然な状態を作らず表現する必要がある。

## Question 1: ExecutionContextの型

### Options

- A: Framework管理の `final readonly class` とする
- B: Interfaceと標準実装に分ける
- C: 継承可能なClassとする

### Recommendation

Aを推奨する。

Context Extensionという明示的な拡張機構があるため、継承は不要である。Frameworkが伝播・記録可能な不変値だけを保持する。

[ANSWER]

A

[/ANSWER]

## Question 2: Attempt情報

### Option A: FlatなNullable Field

```php
?AttemptId $attemptId
?DateTimeImmutable $attemptStartedAt
```

### Option B: OptionalなAttemptContext

```php
?AttemptContext $attempt
```

`AttemptContext` はAttempt IDと開始時刻を必須で持つ。

### Option C: 受付用Contextと実行用Contextを別Classにする

```text
ReceivedExecutionContext
AttemptExecutionContext
```

### Recommendation

Bを推奨する。

Attempt開始前は `attempt() === null`、開始後はAttempt IDと開始時刻が必ず揃う。Class数を抑えながら、片方だけ存在する不正状態を防げる。

[ANSWER]

B

[/ANSWER]

## Question 3: 状態の更新方法

ExecutionContextはreadonlyなので、Attempt開始や子Operation生成では新しいContextが必要になる。

### Options

- A: `withAttempt()` 等のMethodで新しいContextを返す
- B: Framework内部のFactoryだけが新しいContextを組み立てる
- C: Mutable Classとして同じContextを更新する

### Recommendation

Bを推奨する。

Contextの生成・伝播ルールはFrameworkの重要なInvariantである。公開の汎用 `with...()` によってOperation IDやCorrelation IDを任意変更できるようにせず、受付、Attempt開始、子Operation生成に対応する目的別FactoryをFramework内部に置く。

```text
ExecutionContextFactory::receive(...)
ExecutionContextFactory::startAttempt(...)
ExecutionContextFactory::createChild(...)
```

利用者は生成済みContextを読み取るだけとする。

[ANSWER]

B

[/ANSWER]

## Decision

[DECISION]

`ExecutionContext` はFramework管理の `final readonly class` とする。利用者による継承や独自実装は認めず、アプリケーション固有MetadataはContext Extensionで扱う。

Attempt固有情報はOptionalな `AttemptContext` としてまとめる。`AttemptContext` はAttempt IDとAttempt開始時刻を必須で保持する。

```php
?AttemptContext $attempt
```

Attempt開始前は `attempt() === null`、開始後はAttempt IDと開始時刻が必ず揃う。

Contextの生成と遷移はFramework内部の目的別Factoryだけが行う。

```text
ExecutionContextFactory::receive(...)
ExecutionContextFactory::startAttempt(...)
ExecutionContextFactory::createChild(...)
```

利用者はExecutionContextを読み取るだけとし、識別子等を任意変更する公開 `with...()` Methodは提供しない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- ContextはProcessを越えて安全に伝播できる不変値となる。
- Attempt IDだけ、または開始時刻だけが存在する不正状態を型で防止できる。
- Deferred受付時にはAttempt情報を持たず、WorkerがAttempt開始時に新しいContextを生成する。
- Operation ID、Correlation ID、Causation ID、Deadline等の伝播規則をFactoryへ集約できる。
- 利用者はContextの識別情報を偽装・上書きする公式APIを持たない。
- Factoryは `BlackOps\Internal` に置き、公開APIのSignatureへ露出させない。

[/CONSEQUENCES]
