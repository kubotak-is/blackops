# P1-010: Inline Journal Data and Factory

Status: Accepted

## Goal

Inline Lifecycleに必要なEvent DataとJournalRecordFactoryを実装する。

## Acceptance Criteria

- [ ] Received、Completed、Rejectedが型付きDataを持つ
- [ ] Started、SucceededがEmptyJournalDataを持つ
- [ ] FactoryがMetadataとEnvelopeの一致を検証する
- [ ] Record IDと時刻を注入Portから生成する
- [ ] 全品質CommandとComment Guardrailが成功する
