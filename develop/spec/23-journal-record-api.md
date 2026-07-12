# Journal Record PHP API

## JournalRecord

共通Envelopeは `final readonly class JournalRecord` とする。

EventごとのRecord Classには分けず、Event差分は `JournalEvent` と `JournalData` で表す。利用者による継承と独自実装は拡張点にしない。

## JournalEvent

標準Lifecycle EventはString-backed Enumで表す。

```php
enum JournalEvent: string
{
    case OperationReceived = 'operation.received';
    case OperationAccepted = 'operation.accepted';
    case AttemptStarted = 'attempt.started';
    case AttemptSucceeded = 'attempt.succeeded';
    case AttemptFailed = 'attempt.failed';
    case AttemptRetryScheduled = 'attempt.retry_scheduled';
    case OperationCompleted = 'operation.completed';
    case OperationRejected = 'operation.rejected';
    case OperationFailed = 'operation.failed';
    case OperationDeadLettered = 'operation.dead_lettered';
}
```

## JournalData

Event固有Dataは `JournalData` Marker Interfaceを実装する `final readonly class` とする。

Data Classは値だけを保持する。JSON化、Sensitive Filtering、Schema Version変換は共通Codecが担う。

## 生成

`JournalRecord` のConstructorは非公開とする。

`BlackOps\Internal` の `JournalRecordFactory` がLifecycle Eventごとの目的別Methodで生成し、EventとData型の正しい対応を保証する。

Lifecycle Eventを追加するときは、Enum Case、Data型、Factory Method、Codec Schemaを同時に追加する。
