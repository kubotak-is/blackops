# P1-008: Journal Contracts

Status: Accepted

## Goal

標準Lifecycle Event、JournalData Marker、EmptyJournalDataを実装する。

## In Scope

- JournalEvent String-backed Enum
- JournalData Marker Interface
- EmptyJournalData
- Public API Testと内部文書

## Out of Scope

- JournalRecord
- Event固有Data
- Factory、Codec、Writer、Sequence

## Acceptance Criteria

- [ ] JournalEventが標準10 Eventを正しいWire Nameで持つ
- [ ] JournalDataがMethodなしMarker Interfaceである
- [ ] EmptyJournalDataがPublic final readonly実装である
- [ ] 全品質CommandとComment Guardrailが成功する
