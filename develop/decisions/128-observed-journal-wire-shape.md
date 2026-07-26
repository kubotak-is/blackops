# D128: Observed Journal Wire Shape

Status: Decided

## Context

Observed JournalはCanonical Journalを`SensitiveProjectionFilter`へ通した後、`JsonlJournalRecordEncoder`でJSONLへ変換する。

現行のProjectionはすべてのObjectをPublic PropertyのArrayへ再帰変換するため、次の型情報を失う。

- `EmptyJournalData`と`EmptyOutcome`の空Object Shape
- `AttemptId`等のFramework Identifier
- `DateTimeImmutable`

その結果、空ObjectはJSON Array `[]`、Identifierと日時は空Array `[]`になる。Guideが説明する`{}`、UUIDv7 String、UTC RFC 3339 Microsecondsと実出力が一致しない。

一方、任意の`Stringable`をProjectionからEncoderへそのまま渡すと、Application Objectの`__toString()`がSensitive Propertyを迂回して秘密値を出力できる。

## Decision

[DECISION]

1. Observed JournalはPHP Object／Arrayの元Shapeを保持し、空ObjectをJSON Object `{}`、空ListをJSON Array `[]`として区別する。
2. Journal EventのTop-level `data`は常にJSON Objectとし、Empty Dataでも省略、`null`、`[]`にしない。
3. Nested Empty OutcomeもJSON Object `{}`として保持する。
4. Framework管理のIdentifierはCanonical lowercase UUIDv7 Stringへ変換する。
5. `DateTimeInterface`はUTC、Microseconds付きRFC 3339 Stringへ変換する。
6. 任意のApplication `Stringable`を安全なScalarとして信頼しない。Application Objectは通常のSensitive Projectionを通し、`__toString()`による迂回を許可しない。
7. Sensitive Omit／Mask／Hash、予約Key Pattern、Actor ID Mask、Rejected ReasonのRaw Value除外を維持する。
8. ProjectorからEncoderまでを通したRegressionで、Empty Data、Retry Scheduled、Dead Letter、Nested Empty Outcome、SensitiveなStringable Objectを検証する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Observed JSONLがJournal Dataの論理Shapeと一致する。
- GuideのParameter Tableと実Runtime Outputを同じContractで検証できる。
- 空Objectと空ListをJSON Consumerが区別できる。
- Identifierと日時は安定したWire Scalarになる。
- Applicationの`__toString()`をSensitive Filterの抜け道にしない。

[/CONSEQUENCES]

## References

- [D031 Sensitive Projection](031-sensitive-projection.md)
- [D127 Journal Documentation](127-journal-documentation.md)
- [Specification 24 Lifecycle Event Data](../spec/24-lifecycle-event-data.md)
- [Specification 25 Sensitive Projection](../spec/25-sensitive-projection.md)
- [Specification 94 Journal Documentation](../spec/94-journal-documentation.md)
