# Journal Ports

## ObservedJournalRecord

Canonical `JournalRecord` とは別に、安全なProjection専用の `ObservedJournalRecord` を定義する。

両者は同じ論理Envelopeを共有するが、`ObservedJournalRecord` はRaw Payloadを含まないことを型のContractとして保証する。

## JournalObserver

```php
interface JournalObserver
{
    public function observe(ObservedJournalRecord $record): void;
}
```

成功時は `void` を返し、失敗時は専用Exceptionを投げる。Observer AggregatorがExceptionを捕捉し、BestEffort、Required、DurableのDelivery Policyに従って扱う。

## Flush

Buffer型Observerだけが追加のCapabilityを実装する。

```php
interface FlushableJournalObserver extends JournalObserver
{
    public function flush(): void;
}
```

FrameworkはOperation終了、Worker Loop終了、Process Shutdown等の境界でFlush Capabilityを検出して呼び出す。

## Canonical Journal

読み書きContractを分離する。

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

Canonical Writerも成功時は `void` を返し、失敗時は専用Exceptionを投げる。
