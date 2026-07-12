# D032: Journal Ports

Status: Decided

## Context

Observerは安全なProjectionだけを受け取り、Canonical Dataは別のStore Portだけが受け取ることを決定した。

ここでは両者のPHP型、書き込み失敗の表現、Flush、Canonical Journalの読み書きContractを決める。

## Question 1: Projectionの型

### Options

- A: Canonical `JournalRecord` とは別に、`ObservedJournalRecord` を定義する
- B: 同じ `JournalRecord` ClassをSensitive Filter後に再構築して渡す
- C: Observerには `array<string, mixed>` を渡す

### Recommendation

Aを推奨する。

```php
interface JournalObserver
{
    public function observe(ObservedJournalRecord $record): void;
}
```

型を見るだけでRaw Payloadを含まないことが分かり、ObserverがCanonical Recordを要求する実装を防げる。両者は同じ論理Envelopeを共有するが、Dataの安全性Contractが異なる。

[ANSWER]

A

[/ANSWER]

## Question 2: 書き込み失敗

### Options

- A: Portは `void` を返し、失敗時は専用Exceptionを投げる
- B: Success／Failure Result Objectを必ず返す
- C: `bool` を返す

### Recommendation

Aを推奨する。

Observer AggregatorがExceptionを捕捉し、BestEffort／Required／Durable Policyに従って扱う。`false` の見落としを防ぎ、成功時のAPIも単純にできる。

```php
public function observe(ObservedJournalRecord $record): void;
public function append(JournalRecord $record): void;
```

[ANSWER]

A

[/ANSWER]

## Question 3: Flush

すべてのObserverにFlushを要求するか。

### Options

- A: `JournalObserver` は `observe()` だけとし、Buffer型だけが別の `FlushableJournalObserver` を実装する
- B: すべてのObserverへ `flush()` を必須にする
- C: FlushはAdapter固有とし、Frameworkから呼ばない

### Recommendation

Aを推奨する。同期Observerへ空の `flush()` 実装を強制せず、FrameworkはCapabilityとして検出して終了境界で呼び出せる。

[ANSWER]

A

[/ANSWER]

## Question 4: Canonical Storeの読み書き

### Options

- A: WriterとReaderを分離し、必要な実装だけ両方を束ねる
- B: `CanonicalJournalStore` 一つにAppendとReadを必須化する
- C: MVPではAppendだけを定義する

### Recommendation

Aを推奨する。

```php
interface CanonicalJournalWriter
{
    public function append(JournalRecord $record): void;
}

interface CanonicalJournalReader
{
    /** @return iterable<JournalRecord> */
    public function records(OperationId $operationId): iterable;
}

interface CanonicalJournalStore extends
    CanonicalJournalWriter,
    CanonicalJournalReader
{
}
```

Outbox等のWrite-only Adapterと、Replay・監査用Readerの責務を分離できる。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

Canonical `JournalRecord` とは別に、安全なProjection専用の `ObservedJournalRecord` を定義する。`JournalObserver` は `ObservedJournalRecord` だけを受け取る。

Journal Portは成功時に `void` を返し、失敗時は専用Exceptionを投げる。Observer AggregatorがExceptionを捕捉し、Delivery Policyに従って処理する。

`JournalObserver` は `observe()` だけを持つ。Buffer型Observerだけが別の `FlushableJournalObserver` を実装し、FrameworkがOperation終了等の境界でFlushする。

Canonical Storeは `CanonicalJournalWriter` と `CanonicalJournalReader` に分離する。読み書きの両方を提供する実装は、両Interfaceを継承する `CanonicalJournalStore` を実装する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Observerの型SignatureからCanonical Payloadへのアクセス経路を排除できる。
- `bool` の確認漏れを避け、失敗処理を専用Exceptionへ統一できる。
- 同期Observerへ空の `flush()` 実装を強制しない。
- FrameworkはFlush Capabilityを検出し、Operation終了、Worker Loop終了、Process Shutdownで呼び出せる。
- Write-only OutboxとReplay・監査用Readerを別々に実装できる。
- 読み書き両対応Adapterは `CanonicalJournalStore` として一つのServiceへ束ねられる。

[/CONSEQUENCES]
