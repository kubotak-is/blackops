# D056: Journal Record Public API

Status: Decided

## Decision

[DECISION]

JournalRecord、JournalOperation、JournalAttemptは `#[PublicApi] final readonly class` とする。PHPにはpackage-privateまたはfriend classがないため、ConstructorはPublic APIとし、Invariantを各Constructorで検証する。Framework自身の生成は後続のInternal Factoryへ集約する。

JournalRecordはRecord ID、Schema Version、Event、UTC発生時刻、1始まりのSequence、JournalOperation、Optional JournalAttempt、JournalDataを保持する。

JournalOperationはOperation ID、Type ID、Schema Version、Strategy Wire Name、Correlation ID、Optional Causation IDを保持する。

JournalAttemptはAttempt ID、1始まりのAttempt番号、UTC開始時刻を保持する。

ActorとTraceの型、Event固有Data、EventとDataの対応Factory、Codecは後続Taskで追加する。

[/DECISION]
