# D029: Journal Record PHP API

Status: Decided

## Context

Journal Recordの論理Schema、Event Wire Name、Sequence、型付きEvent Dataを決定した。

次に、Framework内部およびJournal Observer Adapterが利用するPHP型を決める。公開Contractを安定させつつ、Event追加とSchema Versioningを可能にする必要がある。

## Question 1: Journal Recordの型

### Options

- A: 共通の `final readonly class JournalRecord` とする
- B: `JournalRecord` Interfaceと標準実装に分ける
- C: Eventごとに別のJournal Record Classを作る

### Recommendation

Aを推奨する。

共通Envelopeは一つの不変Value Objectとし、Event差分は `event` と `data` で表す。ObserverがEventごとのRecord Classを列挙せずに受け取れる。

[ANSWER]

A

[/ANSWER]

## Question 2: Journal Event

### Options

- A: String-backed Enum `JournalEvent` を使う
- B: 任意の文字列を使う
- C: EventごとのClass名をWire Nameとして使う

### Recommendation

Aを推奨する。

```php
enum JournalEvent: string
{
    case OperationReceived = 'operation.received';
    case OperationAccepted = 'operation.accepted';
    case AttemptStarted = 'attempt.started';
    // ...
}
```

標準Lifecycle EventのTypoを防ぎ、Wire NameをPHP Class名から独立させられる。

[ANSWER]

A

[/ANSWER]

## Question 3: Event Data Contract

### Options

- A: `JournalData` Marker InterfaceとEventごとの `final readonly class` を使う
- B: `array<string, mixed>` を使う
- C: EventごとのData Interfaceと実装に分ける

### Recommendation

Aを推奨する。

```php
interface JournalData {}

final readonly class OperationReceivedData implements JournalData {}
final readonly class AttemptFailedData implements JournalData {}
```

Data Classは値だけを保持し、JSON化、Sensitive Filtering、Version変換は共通Codecが担う。

[ANSWER]

A

[/ANSWER]

## Question 4: EventとData型の整合性

`operation.received` に `AttemptFailedData` を渡すような誤りをどう防ぐか。

### Options

- A: Journal Record FactoryがEventとData型の対応表を検証する
- B: `JournalRecord` Constructor内で検証する
- C: 実行時検証はせずStatic Analysisだけに任せる

### Recommendation

Aを推奨する。

Journal RecordのConstructorは非公開とし、Framework内部のFactoryがLifecycle Eventごとの目的別Methodで生成する。

```text
JournalRecordFactory::operationReceived(...)
JournalRecordFactory::attemptFailed(...)
```

利用者とAdapterが不正な組み合わせを生成する必要はない。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

Journal Recordは共通Envelopeを表す `final readonly class JournalRecord` とする。EventごとのRecord Classや利用者独自実装には分けない。

標準Lifecycle EventはString-backed Enum `JournalEvent` で表す。PHPのPascalCase Enum CaseとDot-separated Wire Nameを対応させる。

Event固有Dataは `JournalData` Marker Interfaceを実装するEventごとの `final readonly class` とする。Data Classは値だけを保持し、JSON化、Sensitive Filtering、Version変換は共通Codecが担う。

`JournalRecord` のConstructorは非公開とし、Framework内部の `JournalRecordFactory` がLifecycle Eventごとの目的別Methodで生成する。FactoryはEventとData型の対応を保証する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Observerは一つの不変なJournal Record型を受け取れる。
- 標準Event名のTypoとPHP Class名へのWire Format依存を防げる。
- Event Dataを型検査、Sensitive Filtering、Schema Versioningの対象にできる。
- 任意の `array<string, mixed>` によるSchema逸脱を防止できる。
- 不正なEventとData型の組み合わせをFramework内部Factoryで防止できる。
- Lifecycle Eventを追加するときはEnum、Data型、Factory Method、Codec Schemaを同時に追加する。

[/CONSEQUENCES]
