# D024: Operation Envelope API

Status: Decided

## Context

Handlerは `OperationEnvelope<TValue>` を一つ受け取ることが決定している。EnvelopeにはOperation Definition、OperationValue、ExecutionContext、Execution Strategyを保持する。

MVP実装前に、Envelopeを拡張可能なInterfaceとするかFramework管理のValue Objectとするか、値へどうアクセスするか、識別情報の正本をどこに置くかを決める。

## Question 1: Envelopeの型

### Options

- A: Frameworkが生成する `final readonly class OperationEnvelope` とする
- B: `OperationEnvelope` Interfaceと標準実装に分ける
- C: 利用者が継承できる非final Classとする

### Recommendation

Aを推奨する。

Envelopeは拡張点ではなく、Frameworkが正しい組み合わせで生成する不変の実行値である。アプリケーション固有Metadataは継承ではなく、決定済みのExecutionContext Extensionで扱う。

[ANSWER]

A

[/ANSWER]

## Question 2: 値へのアクセス

### Options

- A: Private readonly PropertyとGetter Methodを使う
- B: Public readonly Propertyを使う
- C: 基本はPublic readonly Propertyとし、一部だけMethodを使う

### Recommendation

Aを推奨する。

```php
$operation->definition();
$operation->value();
$operation->context();
$operation->strategy();
```

公開Property名を固定せず、将来の内部表現変更やInvariant追加に対応しやすい。Handler側の記述量との差は小さい。

[ANSWER]

A

[/ANSWER]

## Question 3: 識別情報の正本

Operation ID、受付時刻、Correlation ID等をEnvelope直下とExecutionContextの両方へ保持すると、不一致が起こり得る。

### Options

- A: ExecutionContextだけを正本とし、Envelopeでは `context()` 経由で参照する
- B: ExecutionContextを正本とし、Envelopeに委譲するConvenience Methodを設ける
- C: Envelope直下とExecutionContextの両方へ保持する

### Recommendation

Bを推奨する。

値はExecutionContextだけに保持しつつ、頻出する識別子には次の委譲Methodを提供できる。

```php
$operation->id();         // context()->operationId()
$operation->receivedAt(); // context()->receivedAt()
```

重複状態を持たず、HandlerとMiddlewareでの可読性も保てる。どのConvenience MethodをMVPへ含めるかは最小限にする。

[ANSWER]

B

[/ANSWER]

## Decision

[DECISION]

`OperationEnvelope<TValue>` はFrameworkが生成する `final readonly class` とする。利用者による継承や独自実装は拡張点にしない。

保持する値はPrivate readonly Propertyとし、次のGetter Methodで公開する。

```php
$operation->definition();
$operation->value();
$operation->context();
$operation->strategy();
```

Operation ID、受付時刻、Correlation ID等の識別情報はExecutionContextだけに保持し、ExecutionContextを正本とする。

MVPでは頻出する次のConvenience MethodをEnvelopeに設け、ExecutionContextへ委譲する。

```php
$operation->id();         // context()->operationId()
$operation->receivedAt(); // context()->receivedAt()
```

[/DECISION]

## Consequences

[CONSEQUENCES]

- EnvelopeはFrameworkがInvariantを保証する不変Value Objectとなる。
- アプリケーション固有MetadataはEnvelopeの継承ではなくExecutionContext Extensionで扱う。
- 公開Propertyへ依存せず、内部表現を変更できる。
- 識別情報を二重保持しないため、EnvelopeとExecutionContextの不一致が起きない。
- Convenience Methodは重複状態を持たず、ExecutionContextへ処理を委譲する。
- Convenience Methodの追加はPHP Public APIの拡張として扱い、MVPでは `id()` と `receivedAt()` に限定する。

[/CONSEQUENCES]
