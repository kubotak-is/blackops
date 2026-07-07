# P1-009: Journal Record

Status: Accepted

## Goal

Journal共通EnvelopeのRecord、Operation、Attempt型とInvariantを実装する。

## Acceptance Criteria

- [ ] 3型がPublic final readonly classである
- [ ] Schema Version、Sequence、Attempt番号を1以上に制限する
- [ ] Type IDとStrategy Wire Nameを検証する
- [ ] 時刻をUTCへ正規化する
- [ ] JournalDataだけをDataとして受け付ける
- [ ] 全品質CommandとComment Guardrailが成功する
